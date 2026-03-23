<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Models\MassEmailLog;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Models\Wallet;
use App\Models\Webhook;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        protected WalletRepositoryModel   $walletModel,
        protected AuthRepositoryModel     $authModel,
        protected CurrencyRepositoryModel $currencyModel,
        protected WalletService           $walletService,
    ) {}

    // ---------------------------------------------------------------
    // PAYSTACK WEBHOOK
    // ---------------------------------------------------------------
    public function handlePaystack(Request $request)
    {
        // Verify signature
        $signature = $request->header('x-paystack-signature');
        $computed  = hash_hmac('sha512', $request->getContent(), config('services.paystack.secretKey'));

        if ($signature !== $computed) {
            Log::warning('Invalid Paystack webhook signature');
            return response()->json(['status' => 'invalid signature'], 200);
        }

        $event = $request->input('event');
        $data  = $request->input('data');

        if ($event !== 'charge.success') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $amount        = $data['amount'] / 100; // kobo to naira
        $reference     = $data['reference'];
        $currency      = $data['currency'] ?? 'NGN';
        $channel       = $data['channel'] ?? 'paystack';
        $customerCode  = $data['customer']['customer_code'] ?? null;

        DB::beginTransaction();
        try {
            $existingTransaction = PaymentTransaction::where('reference', $reference)->first();

            if ($existingTransaction) {
                // Standard card/bank payment initiated from app
                if ($existingTransaction->status === 'successful') {
                    DB::rollBack();
                    return response()->json(['status' => 'already processed'], 200);
                }

                $user = User::find($existingTransaction->user_id);
                if (!$user) {
                    DB::rollBack();
                    return response()->json(['status' => 'user not found'], 200);
                }

                $this->walletModel->creditWallet($user, $currency, $amount);
                $existingTransaction->update([
                    'status'  => 'successful',
                    'balance' => $this->walletModel->getWalletBalance($user->id),
                ]);

                // Auto-upgrade if unverified and amount qualifies
                $this->attemptAutoUpgrade($user, $currency, $amount);

            } else {
                // Virtual account transfer (no prior transaction record)
                if (!$customerCode) {
                    DB::rollBack();
                    return response()->json(['status' => 'no customer code'], 200);
                }

                $virtualAccount = VirtualAccount::where('customer_id', $customerCode)->first();
                if (!$virtualAccount) {
                    DB::rollBack();
                    Log::warning('Paystack: No virtual account for customer', ['code' => $customerCode]);
                    return response()->json(['status' => 'virtual account not found'], 200);
                }

                $user = User::find($virtualAccount->user_id);
                if (!$user) {
                    DB::rollBack();
                    return response()->json(['status' => 'user not found'], 200);
                }

                $this->walletModel->creditWallet($user, $currency, $amount);

                PaymentTransaction::create([
                    'user_id'     => $user->id,
                    'campaign_id' => 1,
                    'reference'   => $reference,
                    'amount'      => $amount,
                    'balance'     => $this->walletModel->getWalletBalance($user->id),
                    'status'      => 'successful',
                    'currency'    => $currency,
                    'channel'     => 'paystack',
                    'type'        => 'transfer_topup',
                    'description' => 'Virtual Account Transfer from ' . $user->name,
                    'tx_type'     => 'Credit',
                    'user_type'   => 'regular',
                ]);

                // Auto-upgrade if unverified
                $this->attemptAutoUpgrade($user, $currency, $amount);
            }

            DB::commit();

            // Fire in-app + Firebase notification
            event(new NotificationEvent(
                user: $user,
                title: 'Wallet Credited',
                body: "{$currency} {$amount} has been added to your wallet.",
                type: 'wallet',
                data: ['amount' => $amount, 'currency' => $currency, 'reference' => $reference],
            ));

            return response()->json(['status' => 'success'], 200);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Paystack webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    // ---------------------------------------------------------------
    // KORAPAY WEBHOOK
    // ---------------------------------------------------------------
    public function handleKoraPay(Request $request)
    {
        ini_set('serialize_precision', '-1');

        // Verify HMAC signature
        $signature = $request->header('x-korapay-signature');
        $secret    = config('services.korapay.secret_key');
        $data      = $request->input('data');

        $computedSignature = hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_SLASHES), $secret);

        if ($signature !== $computedSignature) {
            Log::warning('Invalid KoraPay webhook signature', [
                'received' => $signature,
                'expected' => $computedSignature,
            ]);
            return response()->json(['status' => 'invalid signature'], 200);
        }

        // Log webhook before processing
        $webhook = Webhook::create([
            'provider' => 'korapay',
            'event'    => $request->input('event'),
            'payload'  => $request->all(),
            'status'   => 'pending',
        ]);

        DB::beginTransaction();
        try {
            $event   = $request->input('event');
            $payload = $data ?? [];

            switch ($event) {
                case 'charge.success':
                    $reference = $payload['reference'] ?? null;

                    if (!$reference) {
                        $webhook->update(['status' => 'failed', 'message' => 'No reference in payload']);
                        DB::rollBack();
                        return response()->json(['status' => 'error', 'message' => 'Missing reference'], 200);
                    }

                    $transaction = PaymentTransaction::where('reference', $reference)->first();
                    $amount      = $payload['amount'] ?? 0;
                    $currency    = $payload['currency'] ?? 'NGN';

                    if (!$transaction) {
                        // Virtual account pay-in — look up user from virtual account details
                        $userId = $this->getUserFromVirtualAccount($payload);

                        if (!$userId) {
                            $webhook->update(['status' => 'failed', 'message' => 'User not found for virtual account']);
                            DB::rollBack();
                            return response()->json(['status' => 'error', 'message' => 'User not found'], 200);
                        }

                        $transaction = PaymentTransaction::create([
                            'user_id'     => $userId,
                            'campaign_id' => '1',
                            'reference'   => $reference,
                            'amount'      => $amount,
                            'balance'     => $this->walletModel->getWalletBalance($userId),
                            'status'      => 'pending',
                            'currency'    => $currency,
                            'channel'     => 'kora',
                            'type'        => 'transfer_topup',
                            'description' => 'Virtual Account Transfer',
                            'tx_type'     => 'Credit',
                            'user_type'   => 'regular',
                        ]);
                    }

                    if ($transaction->status === 'successful') {
                        $webhook->update(['status' => 'ignored', 'message' => 'Already processed']);
                        DB::rollBack();
                        return response()->json(['status' => 'already processed'], 200);
                    }

                    $user = User::find($transaction->user_id);
                    if (!$user) {
                        $webhook->update(['status' => 'failed', 'message' => 'Linked user not found']);
                        DB::rollBack();
                        return response()->json(['status' => 'error', 'message' => 'User not found'], 200);
                    }

                    $this->walletModel->creditWallet($user, $currency, $amount);
                    $transaction->update([
                        'status'  => 'successful',
                        'balance' => $this->walletModel->getWalletBalance($user->id),
                    ]);

                    $this->attemptAutoUpgrade($user, $currency, $amount);

                    $webhook->update(['status' => 'processed', 'message' => 'Pay-in successful']);

                    DB::commit();

                    event(new NotificationEvent(
                        user: $user,
                        title: 'Wallet Credited',
                        body: "{$currency} {$amount} has been added to your wallet.",
                        type: 'wallet',
                        data: ['amount' => $amount, 'currency' => $currency, 'reference' => $reference],
                    ));

                    break;

                case 'transfer.success':
                    $reference   = $payload['reference'] ?? null;
                    $transaction = PaymentTransaction::where('reference', $reference)->first();

                    if ($transaction) {
                        $transaction->update(['status' => 'successful']);
                    }

                    $webhook->update(['status' => 'processed', 'message' => 'Transfer success']);
                    DB::commit();
                    break;

                case 'transfer.failed':
                    $reference   = $payload['reference'] ?? null;
                    $transaction = PaymentTransaction::where('reference', $reference)->first();

                    if ($transaction) {
                        $user = User::find($transaction->user_id);

                        // Refund wallet if debited
                        if ($transaction->tx_type === 'Debit' && $user) {
                            $this->walletModel->creditWallet($user, $transaction->currency, $transaction->amount);
                        }

                        $transaction->update(['status' => 'failed']);

                        if ($user) {
                            event(new NotificationEvent(
                                user: $user,
                                title: 'Transfer Failed',
                                body: "Your transfer of {$transaction->currency} {$transaction->amount} failed. Your wallet has been refunded.",
                                type: 'wallet',
                            ));
                        }
                    }

                    $webhook->update(['status' => 'processed', 'message' => 'Transfer failed — wallet refunded']);
                    DB::commit();
                    break;

                default:
                    $webhook->update(['status' => 'ignored', 'message' => 'Unhandled event: ' . $event]);
                    DB::commit();
                    break;
            }

            return response()->json(['status' => 'success'], 200);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('KoraPay webhook error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $webhook->update(['status' => 'failed', 'message' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    // ---------------------------------------------------------------
    // STRIPE WEBHOOK
    // ---------------------------------------------------------------
    public function handleStripe(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            Log::warning('Invalid Stripe webhook signature: ' . $e->getMessage());
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response()->json(['status' => 'ignored'], 200);
        }

        $session = $event->data->object;

        if ($session->payment_status !== 'paid') {
            return response()->json(['status' => 'not paid'], 200);
        }

        DB::beginTransaction();
        try {
            $reference   = $session->client_reference_id;
            $amountPaid  = $session->amount_total / 100; // cents to dollars

            $transaction = PaymentTransaction::where('reference', $reference)->first();

            if (!$transaction || $transaction->status === 'successful') {
                DB::rollBack();
                return response()->json(['status' => 'already processed or not found'], 200);
            }

            $user = User::find($transaction->user_id);
            if (!$user) {
                DB::rollBack();
                return response()->json(['status' => 'user not found'], 200);
            }

            $this->walletModel->creditWallet($user, $transaction->currency, $amountPaid);
            $transaction->update([
                'status'  => 'successful',
                'balance' => $this->walletModel->getWalletBalance($user->id),
            ]);

            $this->attemptAutoUpgrade($user, $transaction->currency, $amountPaid);

            DB::commit();

            event(new NotificationEvent(
                user: $user,
                title: 'Wallet Credited',
                body: "{$transaction->currency} {$amountPaid} has been added to your wallet.",
                type: 'wallet',
                data: ['amount' => $amountPaid, 'currency' => $transaction->currency, 'reference' => $reference],
            ));

            return response()->json(['status' => 'success'], 200);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    // ---------------------------------------------------------------
    // ZEPTO MAIL WEBHOOK (unchanged from your existing logic)
    // ---------------------------------------------------------------
    public function zeptoWebhook(Request $request)
    {
        $data      = $request->json()->all();
        $eventName = $data['event_name'][0] ?? null;
        $message   = $data['event_message'][0] ?? null;

        if (!$eventName || !$message) {
            return response()->json(['error' => 'Missing event data'], 400);
        }

        $emailReference = $message['email_info']['email_reference'] ?? null;
        if (!$emailReference) {
            return response()->json(['error' => 'Missing email reference'], 400);
        }

        $messageId = explode('@', $emailReference)[0];
        $log       = MassEmailLog::where('message_id', $messageId)->first();

        if (!$log) {
            return response()->json(['error' => 'Log not found'], 404);
        }

        $this->handleEmailEvent($eventName, $log, $message);

        return response()->json(['status' => 'success']);
    }

    // ---------------------------------------------------------------
    // PRIVATE HELPERS
    // ---------------------------------------------------------------

    /**
     * If a user is unverified and the credited amount meets the upgrade fee,
     * trigger the upgrade automatically (mirrors old webapp logic).
     */
    private function attemptAutoUpgrade(User $user, string $currency, float $creditedAmount): void
    {
        if ($user->is_verified) return;

        $mappedCurrency = $this->walletModel->mapCurrency($currency);
        $currencyParams = $this->currencyModel->getCurrencyByCode($mappedCurrency);

        if (!$currencyParams) return;

        $upgradeAmount = $currencyParams->upgrade_fee;
        $walletBalance = $this->walletModel->getWalletBalance($user->id);

        // Qualify if this credit alone is enough, or cumulative balance now qualifies
        $qualifies = $creditedAmount >= $upgradeAmount || $walletBalance >= $upgradeAmount;

        if (!$qualifies) return;

        // Debit upgrade fee from wallet
        $debited = $this->walletModel->debitWallet($user, $mappedCurrency, $upgradeAmount);
        if (!$debited) return;

        // Mark verified + process referral bonus
        $this->authModel->updateUserVerification($user);
        $this->walletService->referralInUpgradeUser($user);

        event(new NotificationEvent(
            user: $user,
            title: 'Account Verified!',
            body: 'Your account has been verified successfully. You can now make withdrawals.',
            type: 'verification',
        ));
    }

    /**
     * Look up a user from KoraPay virtual account details in the webhook payload.
     * Mirrors existing webapp logic exactly.
     */
    private function getUserFromVirtualAccount(array $payload): ?int
    {
        $virtualDetails  = $payload['virtual_bank_account_details']['virtual_bank_account'] ?? [];
        $accountReference = $virtualDetails['account_reference'] ?? null;
        $accountNumber    = $virtualDetails['account_number'] ?? null;
        $accountName      = $virtualDetails['account_name'] ?? null;

        if (!$accountReference && !$accountNumber) {
            Log::warning('KoraPay: virtual account data missing from payload', ['payload' => $payload]);
            return null;
        }

        $query = VirtualAccount::query();

        if ($accountReference) {
            $query->where('customer_id', $accountReference);
        } elseif ($accountNumber) {
            $query->where('account_number', $accountNumber);
        } else {
            $query->where('account_name', $accountName);
        }

        $virtualAccount = $query->first();

        if (!$virtualAccount) {
            Log::warning('KoraPay: No matching virtual account found', [
                'account_reference' => $accountReference,
                'account_number'    => $accountNumber,
            ]);
            return null;
        }

        return $virtualAccount->user_id;
    }

    private function handleEmailEvent(string $eventName, MassEmailLog $log, array $message): void
    {
        $campaign = $log->campaign;
        $details  = $message['event_data'][0]['details'][0] ?? [];

        switch ($eventName) {
            case 'softbounce':
            case 'hardbounce':
                $log->update([
                    'status'        => 'bounced',
                    'bounced_at'    => now(),
                    'error_message' => $details['diagnostic_message'] ?? 'Email bounced',
                ]);
                $campaign->increment('bounced');
                break;

            case 'email_open':
                if ($log->status !== 'opened') {
                    $log->update(['status' => 'opened', 'opened_at' => $details['time'] ?? now()]);
                    $campaign->increment('opened');
                }
                break;

            case 'email_link_click':
                $campaign->increment('clicks');
                break;
        }
    }
}
