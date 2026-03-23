<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Models\VirtualAccount;
use App\Repositories\BankRepositoryModel;
use App\Services\Providers\PaystackServiceProvider;
use Throwable;

class VirtualAccountService
{
    public function __construct(
        protected PaystackServiceProvider $paystack,
        protected BankRepositoryModel $bankRepo,
    ) {}

    public function generateVirtualAccount()
    {
        try {
            $user = auth()->user();

            if ($user->wallet->base_currency !== 'NGN') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Virtual accounts are only available for NGN users.',
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

            event(new NotificationEvent(
                user: $user,
                title: 'Virtual Account Created',
                body: "Your virtual account {$virtual->account_number} ({$virtual->bank_name}) is ready.",
                type: 'wallet',
            ));

            return response()->json([
                'status'  => true,
                'message' => 'Virtual account created successfully.',
                'data'    => $virtual

            ]);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage(), 'message' => 'Error creating virtual account.'], 500);
        }
    }
}
