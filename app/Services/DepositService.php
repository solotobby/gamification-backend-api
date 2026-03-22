<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Models\PaymentTransaction;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\Providers\KoraPayServiceProvider;
use App\Services\Providers\PaystackServiceProvider;
use App\Services\Providers\StripeServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DepositService
{
    // Crypto wallet addresses (stable — admin-configured)
    const CRYPTO_WALLETS = [
        'USDT_TRC20' => 'TYOUR_TRON_WALLET_ADDRESS_HERE',
        'USDT_ERC20' => '0xYOUR_ETH_WALLET_ADDRESS_HERE',
        'BTC'        => 'YOUR_BTC_WALLET_ADDRESS_HERE',
    ];

    public function __construct(
        protected WalletRepositoryModel  $walletModel,
        protected CurrencyRepositoryModel $currencyModel,
        protected PaystackServiceProvider $paystack,
        protected KoraPayServiceProvider  $korapay,
        protected StripeServiceProvider   $stripe,
    ) {}

    /**
     * Initiate a deposit. Returns payment link or crypto address.
     *
     * Request params:
     *   amount       - float
     *   method       - korapay | paystack | stripe | crypto | virtual_account
     *   crypto_type  - (if method=crypto) USDT_TRC20 | USDT_ERC20 | BTC
     */
    public function initiateDeposit($request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:korapay,paystack,stripe,crypto,virtual_account',
            'crypto_type' => 'required_if:method,crypto|in:USDT_TRC20,USDT_ERC20,BTC',
        ]);

        try {
            $user         = auth()->user();
            $baseCurrency = $this->walletModel->mapCurrency($user->wallet->base_currency);
            $amount       = $request->amount;
            $method       = $request->method;
            $ref          = Str::upper(Str::random(16));

            // Route based on method
            return match ($method) {
                'korapay'         => $this->handleKoraPay($user, $amount, $ref, $baseCurrency),
                'paystack'        => $this->handlePaystack($user, $amount, $ref, $baseCurrency),
                'stripe'          => $this->handleStripe($user, $amount, $ref, $baseCurrency),
                'crypto'          => $this->handleCrypto($user, $amount, $ref, $baseCurrency, $request->crypto_type),
                'virtual_account' => $this->handleVirtualAccount($user),
                default           => response()->json(['status' => false, 'message' => 'Invalid method.'], 422),
            };
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage(), 'message' => 'Error initiating deposit.'], 500);
        }
    }

    private function handleKoraPay($user, float $amount, string $ref, string $currency)
    {
        if ($currency !== 'NGN') {
            return response()->json(['status' => false, 'message' => 'KoraPay is only available for NGN accounts.'], 422);
        }

        $payload = [
            'amount'           => $amount,
            'currency'         => 'NGN',
            'reference'        => $ref,
            'narration'        => 'Wallet Top Up',
            'redirect_url'     => config('app.url') . '/api/wallet/deposit/korapay/callback',
            'notification_url' => route('webhook.korapay'),
            'channels'         => ['card', 'bank_transfer', 'pay_with_bank'],
            'customer'         => ['name' => $user->name, 'email' => $user->email],
        ];

        $link = $this->korapay->initializeCharge($payload);

        if (!$link) {
            return response()->json(['status' => false, 'message' => 'Failed to initialize KoraPay payment.'], 500);
        }

        $this->createPendingTransaction($user, $amount, $ref, $currency, 'korapay');

        return response()->json([
            'status'  => true,
            'message' => 'Redirect user to payment link.',
            'data'    => ['method' => 'korapay', 'link' => $link, 'reference' => $ref],
        ]);
    }

    private function handlePaystack($user, float $amount, string $ref, string $currency)
    {
        if ($currency !== 'NGN') {
            return response()->json(['status' => false, 'message' => 'Paystack is only available for NGN accounts.'], 422);
        }

        $link = $this->paystack->initializeTransaction(
            $ref,
            $amount,
            config('app.url') . '/api/wallet/deposit/paystack/callback',
            $user->email
        );

        if (!$link) {
            return response()->json(['status' => false, 'message' => 'Failed to initialize Paystack payment.'], 500);
        }

        $this->createPendingTransaction($user, $amount, $ref, $currency, 'paystack');

        return response()->json([
            'status'  => true,
            'message' => 'Redirect user to payment link.',
            'data'    => ['method' => 'paystack', 'link' => $link, 'reference' => $ref],
        ]);
    }

    private function handleStripe($user, float $amount, string $ref, string $currency)
    {
        if ($currency !== 'USF') {
            return response()->json(['status' => false, 'message' => 'Stripe is only available for USD accounts.'], 422);
        }
        $session = $this->stripe->createCheckoutSession([
            'payment_method_types' => ['card'],
            'customer_email'       => $user->email,
            'client_reference_id'  => $ref,
            'success_url'          => config('app.url') . '/api/wallet/deposit/stripe/callback?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => config('app.url') . '/api/wallet/deposit/cancelled',
            'mode'                 => 'payment',
            'expires_at'           => time() + 3600,
            'line_items'           => [[
                'price_data' => [
                    'product_data' => ['name' => 'Wallet Top Up'],
                    'unit_amount'  => (int)($amount * 100), // cents
                    'currency'     => strtolower($currency),
                ],
                'quantity' => 1,
            ]],
        ]);

        if (!$session) {
            return response()->json(['status' => false, 'message' => 'Failed to initialize Stripe payment.'], 500);
        }

        $this->createPendingTransaction($user, $amount, $ref, $currency, 'stripe');

        return response()->json([
            'status'  => true,
            'message' => 'Redirect user to Stripe checkout.',
            'data'    => ['method' => 'stripe', 'link' => $session['url'], 'reference' => $ref],
        ]);
    }

    private function handleCrypto($user, float $amount, string $ref, string $currency, string $cryptoType)
    {
        $wallet = self::CRYPTO_WALLETS[$cryptoType] ?? null;

        if (!$wallet) {
            return response()->json(['status' => false, 'message' => 'Unsupported crypto type.'], 422);
        }

        // Log pending transaction
        $this->createPendingTransaction($user, $amount, $ref, $currency, 'crypto');

        return response()->json([
            'status'  => true,
            'message' => 'Send payment to the wallet address below.',
            'data'    => [
                'method'       => 'crypto',
                'crypto_type'  => $cryptoType,
                'wallet'       => $wallet,
                'amount'       => $amount,
                'reference'    => $ref,
                'note'         => 'After payment, contact admin with your transaction hash and reference number for manual verification.',
            ],
        ]);
    }

    private function handleVirtualAccount($user)
    {
        $virtualAccount = $user->virtualAccount;

        if (!$virtualAccount) {
            return response()->json([
                'status'  => false,
                'message' => 'No virtual account found. Generate one first via /wallet/generate-virtual-account.',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Transfer to this account to fund your wallet.',
            'data'    => [
                'method'         => 'virtual_account',
                'bank_name'      => $virtualAccount->bank_name,
                'account_name'   => $virtualAccount->account_name,
                'account_number' => $virtualAccount->account_number,
                'note'           => 'Funds will be credited automatically once payment is confirmed.',
            ],
        ]);
    }

    // Called by webhooks after payment verified
    public function creditWalletAfterPayment(string $reference, float $amount, string $channel): bool
    {
        $transaction = PaymentTransaction::where('reference', $reference)
            ->where('status', 'unsuccessful')
            ->first();

        if (!$transaction) return false;

        DB::beginTransaction();
        try {
            $user = \App\Models\User::find($transaction->user_id);

            $this->walletModel->creditWallet($user, $transaction->currency, $amount);
            $transaction->update(['status' => 'successful']);

            // Fire notification event
            event(new NotificationEvent(
                user: $user,
                title: 'Wallet Credited',
                body: "{$transaction->currency} {$amount} has been added to your wallet.",
                type: 'wallet',
                data: ['amount' => $amount, 'currency' => $transaction->currency, 'reference' => $reference],
            ));

            DB::commit();
            return true;
        } catch (Throwable $e) {
            DB::rollBack();
            return false;
        }
    }

    private function createPendingTransaction($user, float $amount, string $ref, string $currency, string $channel): void
    {
        PaymentTransaction::create([
            'user_id'     => $user->id,
            'campaign_id' => '1',
            'reference'   => $ref,
            'amount'      => $amount,
            'status'      => 'unsuccessful',
            'currency'    => $currency,
            'channel'     => $channel,
            'type'        => 'wallet_topup',
            'description' => 'Wallet Top Up',
            'tx_type'     => 'Credit',
            'user_type'   => 'regular',
        ]);
    }
}
