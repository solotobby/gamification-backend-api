<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Mail\GeneralMail;
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
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\Providers\InterswitchServiceProvider;

class WebhookController extends Controller
{
    public function __construct(
        protected WalletRepositoryModel   $walletModel,
        protected AuthRepositoryModel     $authModel,
        protected CurrencyRepositoryModel $currencyModel,
        protected WalletService           $walletService,
        protected NotificationService $notification,
        protected InterswitchServiceProvider $interswitch,

    ) {}

    // ---------------------------------------------------------------
    // PAYSTACK WEBHOOK
    // ---------------------------------------------------------------
    public function handlePaystackCallback(Request $request)
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return response()->json(['status' => 'no reference'], 400);
        }

        // Verify transaction from Paystack
        $response = Http::withToken(config('services.paystack.secretKey'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if (!$response->successful() || $response['data']['status'] !== 'success') {
            return response()->json(['status' => 'failed'], 400);
        }

        $data = $response['data'];

        $amount       = $data['amount'] / 100;
        $reference    = $data['reference'];
        $currency     = $data['currency'] ?? 'NGN';
        $channel      = $data['channel'] ?? 'paystack';
        $customerCode = $data['customer']['customer_code'] ?? null;

        DB::beginTransaction();

        try {
            $existingTransaction = PaymentTransaction::where('reference', $reference)->first();

            if ($existingTransaction) {
                // Prevent double processing
                if ($existingTransaction->status === 'successful') {
                    DB::rollBack();
                    return response()->json(['status' => 'already processed'], 200);
                }

                $user = User::find($existingTransaction->user_id);
                if (!$user) {
                    DB::rollBack();
                    // return response()->json(['status' => 'user not found'], 200);
                    return response()->json([
                        'status'  => false,
                        'message' => 'Payment processing failed',
                    ], 402);
                }

                $this->walletModel->creditWallet($user, $currency, $amount);

                $existingTransaction->update([
                    'status'  => 'successful',
                    'balance' => $this->walletModel->getWalletBalance($user->id),
                ]);

                // $this->attemptAutoUpgrade($user, $currency, $amount);
            } else {
                // Virtual account flow
                if (!$customerCode) {
                    DB::rollBack();
                    return response()->json(['status' => 'no customer code'], 200);
                }

                $virtualAccount = VirtualAccount::where('customer_id', $customerCode)->first();

                if (!$virtualAccount) {
                    DB::rollBack();
                    return response()->json([
                        'status'  => false,
                        'message' => 'virtual account not found',
                    ], 402);
                    // return response()->json(['status' => 'virtual account not found'], 200);
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
                    'channel'     => $channel,
                    'type'        => 'transfer_topup',
                    'description' => 'Wallet topup via Paystack',
                    'tx_type'     => 'Credit',
                    'user_type'   => 'regular',
                ]);

                // $this->attemptAutoUpgrade($user, $currency, $amount);
            }

            DB::commit();

            $this->notification->createNotification(
                $user,
                'Wallet Funding Successful!',
                "{$currency} {$amount} has been added to your wallet.",
                'wallet'
            );

            return response()->json([
                'status'  => true,
                'message' => 'Payment successful',
            ], 200);
            // return response()->json(['status' => 'success'], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Paystack callback error: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Payment processing failed',
            ], 402);
            // return response()->json(['status' => 'error'], 500);
        }
    }

    public function handlePaystackWebhook(Request $request)
    {

        // $payload = json_decode($request->getContent(), true);
        // $event = $request->input('event');
        // $data  = $request->input('data');

        // $webhook = Webhook::create([
        //     'provider' => 'paystack',
        //     'event'    => $event,
        //     'payload'  => $payload,
        //     'status'   => 'pending',
        // ]);

        // Verify signature
        $signature = $request->header('x-paystack-signature');
        $computed  = hash_hmac('sha512', $request->getContent(), config('services.paystack.secretKey'));

        if ($signature !== $computed) {
            Log::info('Signature verification', [
                'signature' => $signature,
                'computed'  => $computed
            ]);
            Log::warning('Invalid Paystack webhook signature');
            // return response()->json([
            //     'status' => 'invalid signature'
            // ], 200);
            return response()->json([
                'status'  => false,
                'message' => 'Payment processing failed',
            ], 402);
        }

        $event = $request->input('event');
        $data  = $request->input('data');

        if ($event !== 'charge.success') {
            // return response()->json([
            //     'status' => 'ignored'
            // ], 200);
            return response()->json([
                'status'  => false,
                'message' => 'Payment processing failed',
            ], 402);
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
                    // return response()->json(['status' => 'already processed'], 200);
                    return response()->json([
                        'status'  => false,
                        'message' => 'Payment already processed',
                    ], 402);
                }

                $user = User::find($existingTransaction->user_id);
                if (!$user) {
                    DB::rollBack();
                    // return response()->json(['status' => 'user not found'], 200);
                    return response()->json([
                        'status'  => false,
                        'message' => 'Payment processing failed',
                    ], 402);
                }

                $this->walletModel->creditWallet($user, $currency, $amount);
                $existingTransaction->update([
                    'status'  => 'successful',
                    'balance' => $this->walletModel->getWalletBalance($user->id),
                ]);

                // Auto-upgrade if unverified and amount qualifies
                // $this->attemptAutoUpgrade($user, $currency, $amount);
            } else {
                // Virtual account transfer (no prior transaction record)
                if (!$customerCode) {
                    DB::rollBack();
                    return response()->json([
                        'status'  => false,
                        'message' => 'no customer code',
                    ], 402);
                    // return response()->json(['status' => 'no customer code'], 200);
                }

                $virtualAccount = VirtualAccount::where('customer_id', $customerCode)->first();
                if (!$virtualAccount) {
                    DB::rollBack();
                    Log::warning('Paystack: No virtual account for customer', ['code' => $customerCode]);
                    // return response()->json(['status' => 'virtual account not found'], 200);
                    return response()->json([
                        'status'  => false,
                        'message' => 'virtual account not found',
                    ], 402);
                }

                $user = User::find($virtualAccount->user_id);
                if (!$user) {
                    DB::rollBack();
                    // return response()->json(['status' => 'user not found'], 200);
                    return response()->json([
                        'status'  => false,
                        'message' => 'Payment processing failed',
                    ], 402);
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
                // $this->attemptAutoUpgrade($user, $currency, $amount);
            }

            DB::commit();

            $this->notification->createNotification(
                user: $user,
                title: 'Wallet Credited',
                body: "{$currency} {$amount} has been added to your wallet.",
                type: 'wallet',
                // data: ['amount' => $amount, 'currency' => $currency, 'reference' => $reference],
            );

            $subject = 'Wallet Credited';
            $content = 'Congratulations, your wallet has been credited with ' . $currency . ' ' . $amount;
            Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));

            // $this->notification->createNotification(
            //     $user,
            //     'Wallet Credited',
            //     "{$currency} {$amount} has been added to your wallet.",
            //     'wallet'
            // );
            // return response()->json(['status' => 'success'], 200);
            return response()->json([
                'status'  => true,
                'message' => 'Payment successful',
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Paystack webhook error: ' . $e->getMessage());
            // return response()->json(['status' => 'error'], 500);
            return response()->json([
                'status'  => false,
                'message' => 'Payment processing error',
            ], 500);
        }
    }

    // ---------------------------------------------------------------
    // KORAPAY WEBHOOK
    // ---------------------------------------------------------------
    public function handleKoraPay(Request $request)
    {
        ini_set('serialize_precision', '-1');


        $rawBody   = $request->getContent();
        $signature = $request->header('x-korapay-signature');
        $secret    = config('services.korapay.secret_key');

        $payload = json_decode($rawBody, true);

        // Ensure data exists
        $data = $payload['data'] ?? null;

        if (!$data) {
            Log::warning('KoraPay webhook missing data field', ['payload' => $payload]);
            return response()->json(['status' => 'ignored'], 200);
        }

        // IMPORTANT: match KoraPay's hashing exactly
        $computedSignature = hash_hmac(
            'sha256',
            json_encode($data, JSON_UNESCAPED_SLASHES),
            $secret
        );

        if (!hash_equals($computedSignature, $signature ?? '')) {
            Log::warning('Invalid KoraPay webhook signature', [
                'received' => $signature,
                'expected' => $computedSignature,
            ]);

            return response()->json(['status' => 'invalid signature'], 200);
        }

        $payload = json_decode($rawBody, true);
        $event   = $payload['event'] ?? null;
        $data    = $payload['data'] ?? [];

        $webhook = Webhook::create([
            'provider' => 'korapay',
            'event'    => $event,
            'payload'  => $payload,
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
                        $webhook->update([
                            'status' => 'failed',
                            'message' => 'No reference in payload'
                        ]);
                        DB::rollBack();
                        return response()->json([
                            'status' => false,
                            'message' => 'Missing reference'
                        ], 200);
                    }

                    $transaction = PaymentTransaction::where('reference', $reference)->first();
                    $amount      = $payload['amount'] ?? 0;
                    $currency    = $payload['currency'] ?? 'NGN';

                    if (!$transaction) {
                        // Virtual account pay-in — look up user from virtual account details
                        $userId = $this->getUserFromVirtualAccount($payload);

                        if (!$userId) {
                            $webhook->update([
                                'status' => 'failed',
                                'message' => 'User not found for virtual account'
                            ]);
                            DB::rollBack();
                            return response()->json([
                                'status' => false,
                                'message' => 'User not found'
                            ], 200);
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
                        $webhook->update([
                            'status' => 'ignored',
                            'message' => 'Already processed'
                        ]);
                        DB::rollBack();
                        // return response()->json(['
                        // status' => 'already processed'], 200);
                        return response()->json([
                            'status'  => false,
                            'message' => 'Payment already processed',
                        ], 402);
                    }

                    $user = User::find($transaction->user_id);
                    if (!$user) {
                        $webhook->update([
                            'status' => 'failed',
                            'message' => 'Linked user not found'
                        ]);
                        DB::rollBack();
                        return response()->json([
                            'status' => false,
                            'message' => 'User not found'
                        ], 200);
                    }

                    $this->walletModel->creditWallet($user, $currency, $amount);
                    $transaction->update([
                        'status'  => 'successful',
                        'balance' => $this->walletModel->getWalletBalance($user->id),
                    ]);

                    // $this->attemptAutoUpgrade($user, $currency, $amount);

                    $webhook->update([
                        'status' => 'processed',
                        'message' => 'Pay-in successful'
                    ]);

                    DB::commit();

                    $this->notification->createNotification(
                        user: $user,
                        title: 'Wallet Credited',
                        body: "{$currency} {$amount} has been added to your wallet.",
                        type: 'wallet',
                        // data: ['amount' => $amount, 'currency' => $currency, 'reference' => $reference],
                    );


                    $subject = 'Wallet Credited';
                    $content = 'Congratulations, your wallet has been credited with ' . $currency . ' ' . $amount;
                    Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));

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
                            $this->notification->createNotification(
                                user: $user,
                                title: 'Transfer Failed',
                                body: "Your transfer of {$transaction->currency} {$transaction->amount} failed. Your wallet has been refunded.",
                                type: 'wallet',

                            );
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

            // return response()->json(['status' => 'success'], 200);

            return response()->json([
                'status'  => true,
                'message' => 'Payment successful',
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('KoraPay webhook error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $webhook->update(['status' => 'failed', 'message' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    public function handleKoraPayCallback(Request $request)
    {
        $reference = $request->query('reference');

        if (!$reference) {
            // return response()->json(['status' => 'no reference'], 400);
            return response()->json([
                'status'  => false,
                'message' => 'No Reference',
            ], 402);
        }

        // Verify transaction from KoraPay
        $response = Http::withToken(config('services.korapay.secret_key'))
            ->get("https://api.korapay.com/merchant/api/v1/charges/{$reference}");

        Log::info('KoraPay Callback Verify', [
            'reference' => $reference,
            'response'  => $response->json()
        ]);

        if (!$response->successful() || ($response['data']['status'] ?? null) !== 'success') {
            // return response()->json(['status' => 'failed'], 400);

            return response()->json([
                'status'  => false,
                'message' => 'Payment processing failed',
            ], 402);
        }

        $data = $response['data'];

        $amount   = $data['amount'] ?? 0;
        $currency = $data['currency'] ?? 'NGN';

        DB::beginTransaction();

        try {
            $transaction = PaymentTransaction::where('reference', $reference)->first();

            // 🔒 Prevent double credit
            if ($transaction && $transaction->status === 'successful') {
                DB::rollBack();
                return response()->json([
                    'status'  => false,
                    'message' => 'Payment already processed',
                ], 402);
                // return response()->json(['status' => 'already processed'], 200);
            }

            if ($transaction) {
                $user = User::find($transaction->user_id);

                if (!$user) {
                    DB::rollBack();
                    // return response()->json(['status' => 'user not found'], 200);
                    return response()->json([
                        'status'  => false,
                        'message' => 'Payment processing failed',
                    ], 402);
                }

                $this->walletModel->creditWallet($user, $currency, $amount);

                $transaction->update([
                    'status'  => 'successful',
                    'balance' => $this->walletModel->getWalletBalance($user->id),
                ]);

                // $this->attemptAutoUpgrade($user, $currency, $amount);
            } else {
                // Virtual account / fallback
                $userId = $this->getUserFromVirtualAccount($data);

                if (!$userId) {
                    DB::rollBack();
                    // return response()->json(['status' => 'user not found'], 200);
                    return response()->json([
                        'status'  => false,
                        'message' => 'Payment processing failed',
                    ], 402);
                }

                $user = User::find($userId);

                $this->walletModel->creditWallet($user, $currency, $amount);

                PaymentTransaction::create([
                    'user_id'     => $user->id,
                    'campaign_id' => 1,
                    'reference'   => $reference,
                    'amount'      => $amount,
                    'balance'     => $this->walletModel->getWalletBalance($user->id),
                    'status'      => 'successful',
                    'currency'    => $currency,
                    'channel'     => 'kora',
                    'type'        => 'transfer_topup',
                    'description' => 'Wallet topup via KoraPay',
                    'tx_type'     => 'Credit',
                    'user_type'   => 'regular',
                ]);

                // $this->attemptAutoUpgrade($user, $currency, $amount);
            }

            DB::commit();

            $this->notification->createNotification(
                $user,
                'Wallet Funding Successful!',
                "{$currency} {$amount} has been added to your wallet.",
                'wallet'
            );

            // return response()->json(['status' => 'success'], 200);
            return response()->json([
                'status'  => true,
                'message' => 'Payment successful',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('KoraPay callback error: ' . $e->getMessage());

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

            // $this->attemptAutoUpgrade($user, $transaction->currency, $amountPaid);

            DB::commit();

            $this->notification->createNotification(
                user: $user,
                title: 'Wallet Credited',
                body: "{$transaction->currency} {$amountPaid} has been added to your wallet.",
                type: 'wallet',
                // data: ['amount' => $amountPaid, 'currency' => $transaction->currency, 'reference' => $reference],
            );

            // return response()->json(['status' => 'success'], 200);
            return response()->json([
                'status'  => true,
                'message' => 'Payment successful',
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    }

    // ---------------------------------------------------------------
    // INTERSWITCH WEBHOOK
    // ---------------------------------------------------------------
    public function handleInterswitchWebhook(Request $request)
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('X-Interswitch-Signature');

        // Per docs: HmacSHA512 of raw JSON body, hex-encoded
        // $computed = hash_hmac('sha512', $rawBody, config('services.interswitch.client_secret'));

        // if (!$signature || !hash_equals($computed, $signature)) {
        //     Log::warning('Invalid Interswitch webhook signature', [
        //         'received' => $signature,
        //         'computed' => $computed,
        //     ]);
        //     return response('', 200); // docs say always return 200, no body
        // }

        $payload = json_decode($rawBody, true);
        $event   = $payload['event']     ?? null;
        $uuid    = $payload['uuid']      ?? null; // use uuid for duplicate check per docs
        $data    = $payload['data']      ?? [];

        // Log every webhook
        $webhook = Webhook::create([
            'provider' => 'interswitch',
            'event'    => $event,
            'payload'  => $payload,
            'status'   => 'pending',
        ]);

        // Per docs: respond 200 immediately, then process
        // Only handle TRANSACTION.COMPLETED with responseCode 00
        if ($event !== 'TRANSACTION.COMPLETED' || ($data['responseCode'] ?? null) !== '00') {
            $webhook->update(['status' => 'ignored', 'message' => 'Non-success event: ' . $event]);
            return response('', 200);
        }

        if (!$uuid) {
            $webhook->update(['status' => 'failed', 'message' => 'Missing uuid']);
            return response('', 200);
        }

        // Duplicate check on uuid per docs
        $alreadyProcessed = PaymentTransaction::where('reference', $uuid)
            ->where('status', 'successful')
            ->exists();

        if ($alreadyProcessed) {
            $webhook->update(['status' => 'ignored', 'message' => 'Already processed']);
            return response('', 200);
        }

        $reference     = $data['merchantReference'] ?? $uuid;
        $amount        = isset($data['amount']) ? $data['amount'] / 100 : 0; // kobo → naira
        $accountNumber = $data['retrievalReferenceNumber'] ?? null; // virtual account number paid into
        $currencyCode  = $data['currencyCode'] ?? '566';
        $currency      = $currencyCode === '566' ? 'NGN' : 'NGN'; // extend for multi-currency

        DB::beginTransaction();
        try {
            $transaction = PaymentTransaction::where('reference', $reference)->first();

            if (!$transaction) {
                // Virtual account transfer — identify user from account number
                $virtualAccount = VirtualAccount::where('account_number', $accountNumber)
                    ->where('channel', 'interswitch')
                    ->first();

                if (!$virtualAccount) {
                    $webhook->update(['status' => 'failed', 'message' => 'Virtual account not found: ' . $accountNumber]);
                    DB::rollBack();
                    return response('', 200);
                }

                $user = User::find($virtualAccount->user_id);
                if (!$user) {
                    $webhook->update(['status' => 'failed', 'message' => 'User not found']);
                    DB::rollBack();
                    return response('', 200);
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
                    'channel'     => 'interswitch',
                    'type'        => 'transfer_topup',
                    'description' => 'Virtual Account Transfer from ' . ($data['merchantCustomerName'] ?? 'Unknown'),
                    'tx_type'     => 'Credit',
                    'user_type'   => 'regular',
                ]);

                // $this->attemptAutoUpgrade($user, $currency, $amount);
            } else {
                if ($transaction->status === 'successful') {
                    $webhook->update(['status' => 'ignored', 'message' => 'Already processed']);
                    DB::rollBack();
                    return response('', 200);
                }

                $user = User::find($transaction->user_id);
                if (!$user) {
                    $webhook->update(['status' => 'failed', 'message' => 'User not found']);
                    DB::rollBack();
                    return response('', 200);
                }

                $this->walletModel->creditWallet($user, $currency, $amount);
                $transaction->update([
                    'status'  => 'successful',
                    'balance' => $this->walletModel->getWalletBalance($user->id),
                ]);

                // $this->attemptAutoUpgrade($user, $currency, $amount);
            }

            DB::commit();

            $webhook->update(['status' => 'processed', 'message' => 'Payment successful']);

            $this->notification->createNotification(
                user: $user,
                title: 'Wallet Credited',
                body: "{$currency} {$amount} has been added to your wallet.",
                type: 'wallet',
            );

            Mail::to($user->email)->send(new GeneralMail(
                $user,
                "Congratulations, your wallet has been credited with {$currency} {$amount}",
                'Wallet Credited',
                ''
            ));

            return response('', 200); // no body per docs

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Interswitch webhook error: ' . $e->getMessage());
            $webhook->update(['status' => 'failed', 'message' => $e->getMessage()]);
            return response('', 200); // still 200 so Interswitch doesn't retry endlessly
        }
    }

    // ---------------------------------------------------------------
    // INTERSWITCH CALLBACK
    // ---------------------------------------------------------------
    public function handleInterswitchCallback(Request $request)
    {
        // Interswitch redirects with txnref param
        $reference = $request->query('txnref') ?? $request->query('reference');

        if (!$reference) {
            return response()->json(['status' => false, 'message' => 'No reference'], 400);
        }

        $verified = $this->interswitch->verifyPayment($reference);

        Log::info('Interswitch callback verify', ['reference' => $reference, 'response' => $verified]);

        // Interswitch verify response uses responseCode '00'
        if (!$verified || ($verified['responseCode'] ?? $verified['ResponseCode'] ?? null) !== '00') {
            return response()->json(['status' => false, 'message' => 'Payment verification failed'], 402);
        }

        $amount   = isset($verified['amount']) ? $verified['amount'] / 100 : 0;
        $currency = ($verified['currencyCode'] ?? '566') === '566' ? 'NGN' : 'NGN';

        DB::beginTransaction();
        try {
            $transaction = PaymentTransaction::where('reference', $reference)->first();

            if (!$transaction) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Transaction not found'], 404);
            }

            if ($transaction->status === 'successful') {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Already processed'], 200);
            }

            $user = User::find($transaction->user_id);
            if (!$user) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'User not found'], 404);
            }

            $this->walletModel->creditWallet($user, $currency, $amount);
            $transaction->update([
                'status'  => 'successful',
                'balance' => $this->walletModel->getWalletBalance($user->id),
            ]);

            // $this->attemptAutoUpgrade($user, $currency, $amount);

            DB::commit();

            $this->notification->createNotification(
                user: $user,
                title: 'Wallet Credited',
                body: "{$currency} {$amount} has been added to your wallet.",
                type: 'wallet',
            );

            Mail::to($user->email)->send(new GeneralMail(
                $user,
                "Congratulations, your wallet has been credited with {$currency} {$amount}",
                'Wallet Credited',
                ''
            ));

            return response()->json(['status' => true, 'message' => 'Payment successful'], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Interswitch callback error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Processing error'], 500);
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

        PaymentTransaction::create([
            'user_id'     => $user->id,
            'campaign_id' => 1,
            'reference'   => Str::upper(Str::random(16)),
            'amount'      => $upgradeAmount,
            'balance'     => $this->walletModel->getWalletBalance($user->id),
            'status'      => 'successful',
            'currency'    => $mappedCurrency,
            'channel'     => 'wallet',
            'type'         => 'upgrade_payment',
            'description' => 'Upgrade Payment',
            'tx_type'     => 'Debit',
            'user_type'   => 'regular',
        ]);

        // Mark verified + process referral bonus
        $this->authModel->updateUserVerification($user);
        $this->walletService->referralInUpgradeUser($user);

        $this->notification->createNotification(
            user: $user,
            title: 'Account Verified!',
            body: 'Your account has been verified successfully. You can now make withdrawals.',
            type: 'verification',
        );
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
