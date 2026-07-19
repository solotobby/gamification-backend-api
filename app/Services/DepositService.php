<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\Providers\KoraPayServiceProvider;
use App\Services\Providers\PaystackServiceProvider;
use App\Services\Providers\StripeServiceProvider;
use App\Services\Providers\InterswitchServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DepositService
{
    // Crypto wallet addresses (stable — admin-configured)
    const CRYPTO_WALLETS = [
        'USDT_TRC20' => 'TDq4Lg25Vbr9BxZpsWc1WcuW2UmuqnnSZZ',
        'USDT_ERC20' => '0xYOUR_ETH_WALLET_ADDRESS_HERE',
        'BTC'        => 'YOUR_BTC_WALLET_ADDRESS_HERE',
    ];

    public function __construct(
        protected WalletRepositoryModel  $walletModel,
        protected CurrencyRepositoryModel $currencyModel,
        protected PaystackServiceProvider $paystack,
        protected KoraPayServiceProvider  $korapay,
        protected StripeServiceProvider   $stripe,
        protected VirtualAccountService $virtual,
        protected InterswitchServiceProvider $interswitch,

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
            'method' => 'required|in:korapay,paystack,stripe,crypto,virtual_account,manual,interswitch',
            // 'crypto_type' => 'required_if:method,crypto|in:USDT_TRC20,USDT_ERC20,BTC',
            'device' => 'nullable|in:web'
        ]);

        // set_time_limit(120);
        try {
            $user         = auth()->user();
            $baseCurrency = $this->walletModel->mapCurrency($user->wallet->base_currency);
            $amount       = $request->amount;
            $method       = $request->method;
            $ref          = Str::upper(Str::random(16));
            $device       = $request->device ?? 'app';

            // Route based on method
            return match ($method) {
                'korapay'         => $this->handleKoraPay($user, $amount, $ref, $baseCurrency, $device),
                'paystack'        => $this->handlePaystack($user, $amount, $ref, $baseCurrency, $device),
                'stripe'          => $this->handleStripe($user, $amount, $ref, $baseCurrency),
                'crypto'          => $this->handleCrypto($user, $amount, $ref, $baseCurrency, 'USDT_TRC20'),
                // 'virtual_account' => $this->handleVirtualAccount($user),
                'virtual_account' => $this->handleInterswitchVirtualAccount($user),
                // 'interswitch'     => $this->handleInterswitch($user, $amount, $ref, $baseCurrency, $device),

                'manual' => $this->handleManualAccount($user, $baseCurrency),
                default           => response()->json([
                    'status' => false,
                    'message' => 'Invalid method.'
                ], 422),
            };
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error initiating deposit.'
            ], 500);
        }
    }

    private function handleKoraPay($user, float $amount, string $ref, string $currency, string $device)
    {
        if ($currency !== 'NGN') {
            return response()->json(['status' => false, 'message' => 'KoraPay is only available for NGN accounts.'], 422);
        }

        $redirectUrl = $device === 'web'
            ? 'https://dashboard.freebyz.com/wallet'
            : route('webhook.korapay.callback');

        $payload = [
            'amount'           => $amount,
            'currency'         => 'NGN',
            'reference'        => $ref,
            'narration'        => 'Wallet Top Up',
            'redirect_url'     => $redirectUrl,
            'notification_url' => route('webhook.korapay'),
            'channels'         => ['card', 'bank_transfer', 'pay_with_bank'],
            'customer'         => [
                'name'  => $user->name,
                'email' => $user->email
            ],
        ];

        $link = $this->korapay->initializeCharge($payload);

        if (!$link) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to initialize KoraPay payment.'
            ], 500);
        }

        $this->createPendingTransaction($user, $amount, $ref, $currency, 'korapay');

        return response()->json([
            'status'  => true,
            'message' => 'Redirect user to payment link.',
            'data'    => [
                'method' => 'korapay',
                'link' => $link,
                'reference' => $ref,
                'manual_verification' => false

            ],
        ]);
    }

    private function handlePaystack($user, float $amount, string $ref, string $currency, string $device)
    {
        if ($currency !== 'NGN') {
            return response()->json([
                'status' => false,
                'message' => 'Paystack is only available for NGN accounts.'
            ], 422);
        }

        $redirectUrl = $device === 'web'
            ? 'https://dashboard.freebyz.com/wallet'
            : route('webhook.paystack.callback');

        $link = $this->paystack->initializeTransaction(
            $ref,
            $amount,
            $redirectUrl, // route('webhook.paystack.callback'),
            $user->email
        );

        if (!$link) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to initialize Paystack payment.'
            ], 500);
        }

        $this->createPendingTransaction($user, $amount, $ref, $currency, 'paystack');

        return response()->json([
            'status'  => true,
            'message' => 'Redirect user to payment link.',
            'data'    => [
                'method' =>
                'paystack',
                'link' => $link,
                'reference' => $ref,
                'manual_verification' => false

            ],
        ]);
    }

    private function handleInterswitch($user, float $amount, string $ref, string $currency, string $device)
    {
        $supportedCurrencies = ['NGN', 'KES', 'GHS', 'ZAR', 'USD'];

        if (!in_array($currency, $supportedCurrencies)) {
            return response()->json([
                'status'  => false,
                'message' => 'Interswitch does not support ' . $currency . ' accounts.',
            ], 422);
        }

        $redirectUrl = $device === 'web'
            ? 'https://dashboard.freebyz.com/wallet'
            : route('webhook.interswitch.callback');

        $result = $this->interswitch->initializePayment([
            'reference'    => $ref,
            'amount'       => $amount,
            'currency'     => $currency,
            'email'        => $user->email,
            'callback_url' => $redirectUrl,
        ]);

        if (!$result) {
            return response()->json([
                'status'  => false,
                'message' => 'Failed to initialize Interswitch payment.',
            ], 500);
        }

        $this->createPendingTransaction($user, $amount, $ref, $currency, 'interswitch');

        return response()->json([
            'status'  => true,
            'message' => 'Redirect user to payment link.',
            'data'    => [
                'method'               => 'interswitch',
                'link'                 => $result['redirectUrl'] ?? $result['checkoutUrl'] ?? null,
                'reference'            => $ref,
                'manual_verification'  => false,
            ],
        ]);
    }

    // public function generateInterswitchVirtualAccount(User $user)
    // {
    //     $existing = $user->virtualAccount;
    //     // if ($existing) {
    //     //     return response()->json([
    //     //         'status' => true,
    //     //         'data' => $existing
    //     //     ]);
    //     // }

    //     $result = $this->interswitch->createVirtualAccount($user->name, null);

    //     if (!$result) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Could not generate virtual account.'
    //         ], 500);
    //     }

    //     // Persist to DB same as existing virtual account flow
    //     $user->virtualAccount()->create([
    //         'bank_name'      => $result['bankName']      ?? 'Interswitch',
    //         'account_name'   => $result['accountName']   ?? $user->name,
    //         'account_number' => $result['accountNumber'] ?? null,
    //         'currency'       => 'NGN',
    //         'status'         => true,
    //         'channel'       => 'interswitch',
    //         'customer_id'    => $result['payableCode'] ?? $user->id . now(),
    //         'customer_intgration'    => $result['merchantCode'] ?? 'Freebyz',
    //     ]);
    //     $virtualAccount = $user->virtualAccount;

    //     // Normalize (handle both array and object)
    //     $bankName = is_array($virtualAccount)
    //         ? ($virtualAccount['bank_name'] ?? null)
    //         : $virtualAccount->bank_name;

    //     $accountName = is_array($virtualAccount)
    //         ? ($virtualAccount['account_name'] ?? null)
    //         : $virtualAccount->account_name;

    //     $accountNumber = is_array($virtualAccount)
    //         ? ($virtualAccount['account_number'] ?? null)
    //         : $virtualAccount->account_number;

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Transfer to this account to fund your wallet.',
    //         'data'    => [
    //             'method'         => 'virtual_account',
    //             'bank_name'      => $bankName,
    //             'account_name'   => $accountName,
    //             'account_number' => $accountNumber,
    //             'note'           => 'Funds will be credited automatically once payment is confirmed.',
    //             'manual_verification' => false
    //         ],
    //     ]);

    //     // return response()->json(['status' => true, 'data' => $result]);
    // }


    private function handleInterswitchVirtualAccount($user)
    {
        // $virtualAccount = VirtualAccount::where('user_id', $user->id)
        //     ->where('channel', 'interswitch')
        //     ->first();

        $virtualAccount = $user->virtualAccount;

        if (!$virtualAccount) {
            $response = $this->virtual->generateInterswitchVirtualAccount($user);

            $virtual = $response->getData(true);

            if (!($virtual['status'] ?? false)) {
                return response()->json([
                    'status'  => false,
                    'message' => $virtual['message'] ?? 'Unable to generate virtual account',
                ], $response->status());
            }

            $virtualAccount = $virtual['data'] ?? null;
        }

        // Normalize (handle both array and object)
        $bankName = is_array($virtualAccount)
            ? ($virtualAccount['bank_name'] ?? null)
            : $virtualAccount->bank_name;

        $accountName = is_array($virtualAccount)
            ? ($virtualAccount['account_name'] ?? null)
            : $virtualAccount->account_name;

        $accountNumber = is_array($virtualAccount)
            ? ($virtualAccount['account_number'] ?? null)
            : $virtualAccount->account_number;

        return response()->json([
            'status'  => true,
            'message' => 'Transfer to this account to fund your wallet.',
            'data'    => [
                'method'              => 'virtual_account',
                'bank_name'           => $bankName,
                'account_name'        => $accountName,
                'account_number'      => $accountNumber,
                'note'                => 'Funds will be credited automatically once payment is confirmed.',
                'manual_verification' => false,
            ],
        ]);
    }
    // private function handleInterswitchVirtualAccount(User $user)
    // {
    //     return $this->generateInterswitchVirtualAccount($user);
    // }

    private function handleStripe(User $user, float $amount, string $ref, string $currency)
    {
        if ($currency !== 'USD') {
            return response()->json(['status' => false, 'message' => 'Stripe is only available for USD accounts.'], 422);
        }
        $session = $this->stripe->createCheckoutSession([
            'payment_method_types' => ['card'],
            'customer_email'       => $user->email,
            'client_reference_id'  => $ref,
            'success_url'          => route('webhook.stripe'),
            'cancel_url'           => route('webhook.stripe'),
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
            'data'    => [
                'method' => 'stripe',
                'link' => $session['url'],
                'reference' => $ref,
                'manual_verification' => false

            ],
        ]);
    }

    private function handleCrypto($user, float $amount, string $ref, string $currency, string $cryptoType)
    {
        $wallet = self::CRYPTO_WALLETS[$cryptoType] ?? null;

        if (!$wallet) {
            return response()->json([
                'status' => false,
                'message' => 'Unsupported crypto type.'
            ], 422);
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
                'note'         => 'After payment, contact admin with your transaction hash, transaction screenshot and reference number for manual verification.',
                'manual_verification' => true

            ],
        ]);
    }

    private function handleVirtualAccount($user)
    {
        $virtualAccount = $user->virtualAccount;

        if (!$virtualAccount) {
            $response = $this->virtual->generateVirtualAccount();

            $virtual = $response->getData(true);

            if (!($virtual['status'] ?? false)) {
                return response()->json([
                    'status'  => false,
                    'message' => $virtual['message'] ?? 'Unable to generate virtual account',
                ], $response->status());
            }

            $virtualAccount = $virtual['data'] ?? null;
        }

        // Normalize (handle both array and object)
        $bankName = is_array($virtualAccount)
            ? ($virtualAccount['bank_name'] ?? null)
            : $virtualAccount->bank_name;

        $accountName = is_array($virtualAccount)
            ? ($virtualAccount['account_name'] ?? null)
            : $virtualAccount->account_name;

        $accountNumber = is_array($virtualAccount)
            ? ($virtualAccount['account_number'] ?? null)
            : $virtualAccount->account_number;

        return response()->json([
            'status'  => true,
            'message' => 'Transfer to this account to fund your wallet.',
            'data'    => [
                'method'         => 'virtual_account',
                'bank_name'      => $bankName,
                'account_name'   => $accountName,
                'account_number' => $accountNumber,
                'note'           => 'Funds will be credited automatically once payment is confirmed.',
                'manual_verification' => false
            ],
        ]);
    }

    private function handleManualAccount($user, $currency)
    {
        // $virtualAccount = $user->virtualAccount;

        // if (!$virtualAccount) {
        //     return response()->json([
        //         'status'  => false,
        //         'message' => 'No virtual account found. Generate one first via /wallet/generate-virtual-account.',
        //     ], 404);
        // }

        if ($currency !== 'NGN') {
            return response()->json(['status' => false, 'message' => 'Paystack is only available for NGN accounts.'], 422);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Transfer to this account to fund your wallet.',
            'data'    => [
                'method'         => 'manual',
                'bank_name'      => 'Moniepoint BANK',
                'account_name'   => 'Freebyz Technologies LTD',
                'account_number' => '6667335193',
                'note'           => 'After payment, contact admin with your transaction screenshot and reference number for manual verification.',
                'manual_verification' => true

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
