<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Models\VirtualAccount;
use App\Repositories\BankRepositoryModel;
use App\Services\Providers\FlutterwaveServiceProvider;
use App\Services\Providers\PaystackServiceProvider;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Services\Providers\InterswitchServiceProvider;

class VirtualAccountService
{
    public function __construct(
        protected PaystackServiceProvider $paystack,
        protected BankRepositoryModel $bankRepo,
        protected NotificationService $notification,
        protected InterswitchServiceProvider $interswitch,
        protected FlutterwaveServiceProvider $flutterwave
    ) {}


    public function generateVirtualAccountNew($user = null)
    {
        $user = $user ?? auth()->user();
        $currency = $user->wallet->base_currency ?? 'NGN';

        if ($currency === 'NGN') {
            return $this->generateInterswitchVirtualAccount($user);
        } elseif ($currency === 'GHS') {
            return $this->generateFlutterwaveVirtualAccount($user, 'GHS');
        } else {
            return response()->json([
                'status'  => false,
                'message' => "Virtual accounts are not available for {$currency} users.",
            ], 422);
        }
    }
    public function generateVirtualAccount()
    {
        try {
            $user = auth()->user();

            if ($user->wallet->base_currency !== 'NGN' && $user->wallet->base_currency !== 'GHS') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Virtual accounts are only available for NGN and GHS users.',
                ], 422);
            }

            // Check if already exists
            $existing = $this->bankRepo->getVirtualBank($user->id);
            if ($existing) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Virtual account already exists.',
                    'data'    => $existing,
                ]);
            }

            // Create Paystack customer
            $nameParts = explode(' ', $user->name);
            $customerRes = $this->paystack->createCustomer([
                'email'      => $user->email,
                'first_name' => $nameParts[0],
                'last_name'  => $nameParts[1] ?? 'User',
                'phone'      => $user->phone,
            ]);

            if (!$customerRes || !$customerRes['status']) {
                return response()->json(['status' => false, 'message' => 'Failed to create Paystack customer.'], 500);
            }

            $customerCode = $customerRes['data']['customer_code'];

            // Create dedicated account
            $accountRes = $this->paystack->createDedicatedAccount([
                'customer'       => $customerCode,
                'preferred_bank' => 'test-bank', // or 'test-bank' for dev
            ]);

            if (!$accountRes || !$accountRes['status']) {
                return response()->json(['status' => false, 'message' => 'Failed to create virtual account.'], 500);
            }

            $accountData = $accountRes['data'];

            $virtual = VirtualAccount::create([
                'user_id'              => $user->id,
                'channel'              => 'paystack',
                'customer_id'          => $customerCode,
                'customer_intgration'  => $accountData['integration'] ?? 147989,
                'bank_name'            => $accountData['bank']['name'],
                'account_name'         => $accountData['account_name'],
                'account_number'       => $accountData['account_number'],
                'status'               => true,
            ]);

            $this->notification->createNotification(
                $user,
                'Virtual Account Created',
                "Your virtual account {$virtual->account_number} ({$virtual->bank_name}) is ready.",
                'wallet'
            );


            return response()->json([
                'status'  => true,
                'message' => 'Virtual account created successfully.',
                'data'    => $virtual

            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage(), 'message' => 'Error creating virtual account.'], 500);
        }
    }

    public function generateInterswitchVirtualAccount($user = null)
    {
        try {
            $user = $user ?? auth()->user();

            if ($user->wallet->base_currency !== 'NGN') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Virtual accounts are only available for NGN users.',
                ], 422);
            }

            // Check if already exists (interswitch channel)
            // $existing = VirtualAccount::where('user_id', $user->id)
            //     ->where('channel', 'interswitch')
            //     ->first();

            $existing = $this->bankRepo->getVirtualBank($user->id);

            if ($existing) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Virtual account already exists.',
                    'data'    => $existing,
                ]);
            }

            // Call Interswitch
            $result = $this->interswitch->createVirtualAccount($user->name);

            if (!$result || isset($result['error'])) {
                Log::error('Interswitch VA creation failed', ['response' => $result]);
                return response()->json([
                    'status'  => false,
                    'message' => $result['description'] ?? 'Could not generate virtual account.',
                ], 500);
            }

            $virtual = VirtualAccount::create([
                'user_id'             => $user->id,
                'channel'             => 'interswitch',
                'customer_id'         => $result['payableCode']   ?? null,
                'customer_intgration' => $result['merchantCode']  ?? $this->merchantCode ?? null,
                'bank_name'           => $result['bankName']      ?? 'Wema Bank',
                'account_name'        => $result['accountName']   ?? $user->name,
                'account_number'      => $result['accountNumber'] ?? null,
                'status'              => true,
                'currency'            => $user->wallet->base_currency ?? 'NGN',

            ]);

            $this->notification->createNotification(
                $user,
                'Virtual Account Created',
                "Your virtual account {$virtual->account_number} ({$virtual->bank_name}) is ready.",
                'wallet'
            );

            return response()->json([
                'status'  => true,
                'message' => 'Virtual account created successfully.',
                'data'    => $virtual,
            ]);
        } catch (Throwable $e) {
            Log::error('Interswitch VA error: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Error creating virtual account.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function generateFlutterwaveVirtualAccount($user, string $currency = 'GHS')
{
    try {
        if (!in_array($currency, FlutterwaveServiceProvider::VIRTUAL_ACCOUNT_CURRENCIES)) {
            return response()->json(['status' => false, 'message' => "Static virtual accounts are not available for {$currency}."], 422);
        }

        $existing = $this->bankRepo->getVirtualBank($user->id, 'flutterwave');
        if ($existing) {
            return response()->json(['status' => true, 'message' => 'Virtual account already exists.', 'data' => $existing]);
        }

        $result = $this->flutterwave->createVirtualAccount([
            'email'     => $user->email,
            'currency'  => $currency,
            'tx_ref'    => 'VA-' . $user->id . '-' . time(),
            'firstname' => explode(' ', $user->name)[0] ?? $user->name,
            'lastname'  => explode(' ', $user->name)[1] ?? 'User',
            'narration' => 'Freebyz Wallet Funding',

        ]);

        if (!$result) {
            return response()->json(['status' => false, 'message' => 'Could not generate virtual account.'], 500);
        }

        $virtual = VirtualAccount::create([
            'user_id'             => $user->id,
            'channel'             => 'flutterwave',
            'customer_id'         => $result['order_ref'] ?? $result['id'] ?? null,
            'customer_intgration' => $result['flw_ref'] ?? null,
            'bank_name'           => $result['bank_name'] ?? 'Flutterwave',
            'account_name'        => $result['account_name'] ?? $user->name,
            'account_number'      => $result['account_number'] ?? null,
            'status'              => true,
            'currency'            => $currency,
        ]);

        $this->notification->createNotification(
            $user,
            'Virtual Account Created',
            "Your {$virtual->currency} virtual account {$virtual->account_number} ({$virtual->bank_name}) is ready.",
            'wallet'
        );

        return response()->json(['status' => true, 'message' => 'Virtual account created successfully.', 'data' => $virtual]);
    } catch (Throwable $e) {
        Log::error('Flutterwave VA error: ' . $e->getMessage());
        return response()->json(['status' => false, 'message' => 'Error creating virtual account.', 'error' => $e->getMessage()], 500);
    }
}
}
