<?php

namespace App\Services;

use App\Repositories\AuthRepositoryModel;
use App\Repositories\BankRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\Providers\FlutterwaveServiceProvider;
use App\Services\Providers\InterswitchServiceProvider;
use App\Services\Providers\PaystackServiceProvider;
use App\Validators\WalletValidator;
use Exception;

class BankService
{
    protected array $countryMap = ['GHS' => 'GH', 'ZAR' => 'ZA', 'KES' => 'KE', 'UGX' => 'UG'];

    public function __construct(
        protected WalletRepositoryModel $walletModel,
        protected AuthRepositoryModel $authModel,
        protected PaystackServiceProvider $paystack,
        protected InterswitchServiceProvider $interswitch,
        protected FlutterwaveServiceProvider $flutterwave,
        protected WalletValidator $validator,
        protected BankRepositoryModel $bank,
    ) {}

    /**
     * Every currency-aware method below pulls the currency from the
     * authenticated user's own wallet instead of trusting a client-supplied
     * value — a user only ever sees/saves bank details in their own
     * account's currency, never one they choose arbitrarily.
     */
    private function getUserCurrency($user): string
    {
        return $this->walletModel->mapCurrency($user->wallet->base_currency);
    }

    public function getBankList($request)
    {
        $user = auth()->user();
        $currency = $this->getUserCurrency($user);
        $method = strtolower($request->query('method', 'mobile_money'));

        try {
            if ($currency === 'NGN') {
                // $bankList = $this->paystack->bankList();

                // if (!$bankList) {
                //     return response()->json(['status' => false, 'message' => 'Failed to fetch bank list'], 500);
                // }

                // $data = array_map(fn($bank) => [
                //     'id'        => $bank['id'] ?? null,
                //     'name'      => $bank['name'] ?? null,
                //     'bank_code' => $bank['code'] ?? null,
                //     'currency'  => 'NGN',
                // ], $bankList);
                $bankList = $this->flutterwave->getBanks('NGN');

                if (!$bankList) {
                    return response()->json(['status' => false, 'message' => 'Failed to fetch bank list'], 500);
                }

                $data = array_map(fn($bank) => [
                    'id' => $bank['id'],
                    'name' => $bank['name'],
                    'bank_code' => $bank['code'],
                    'currency' => $currency,
                ], $bankList);

                return response()->json(['status' => true, 'message' => 'Bank list retrieved successfully', 'data' => $data]);
            }

            if (!isset($this->countryMap[$currency])) {
                return response()->json(['status' => false, 'message' => "Bank details aren't supported for your account currency ({$currency})."], 422);
            }

            $countryCode = $this->countryMap[$currency];

            if ($method === 'mobile_money') {
                if ($countryCode === 'ZA') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Mobile money is not supported for South Africa (ZAR). Use a bank account instead.',
                    ], 422);
                }

                $networks = $this->flutterwave->getMobileMoneyNetworks($countryCode);

                return response()->json([
                    'status' => true,
                    'message' => 'Mobile money networks retrieved successfully',
                    'data' => array_map(fn($n) => [
                        'id' => $n['code'],
                        'name' => $n['name'],
                        'bank_code' => $n['code'],
                        'currency' => $currency,
                    ], $networks),
                ]);
            }

            $bankList = $this->flutterwave->getBanks($countryCode);

            if (!$bankList) {
                return response()->json(['status' => false, 'message' => 'Failed to fetch bank list'], 500);
            }

            $data = array_map(fn($bank) => [
                'id' => $bank['id'],
                'name' => $bank['name'],
                'bank_code' => $bank['code'],
                'currency' => $currency,
            ], $bankList);

            return response()->json(['status' => true, 'message' => 'Bank list retrieved successfully', 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage(), 'message' => 'Error processing request'], 500);
        }
    }

    public function getAccountDetails($request)
    {
        $this->validator->getAccountNameValidator($request);

        $user = auth()->user();
        $currency = $this->getUserCurrency($user);
        $method = strtolower($request->method ?? 'bank');

        try {
            if ($currency === 'NGN') {
                // $response = $this->paystack->resolveAccountName($request->account_number, $request->bank_code);

                // if (!$response || !($response['status'] ?? true)) {
                //     return response()->json(['status' => false, 'message' => 'Account Name not found'], 401);
                // }

                // return response()->json([
                //     'status' => true,
                //     'message' => 'Account Name Found',
                //     'data' => [
                //         'account_number' => $request->account_number,
                //         'account_name' => $response['data']['account_name'] ?? $response['name'] ?? null,
                //         'bank_code' => $request->bank_code,
                //     ],
                // ]);
                 $resolved = $this->flutterwave->resolveAccount($request->account_number, $request->bank_code);

            if (!$resolved) {
                return response()->json(['status' => false, 'message' => 'Account Name not found'], 401);
            }

            return response()->json([
                'status' => true,
                'message' => 'Account Name Found',
                'data' => [
                    'account_number' => $request->account_number,
                    'account_name' => $resolved['account_name'] ?? null,
                    'bank_code' => $request->bank_code,
                ],
            ]);
            
            }

            if (!isset($this->countryMap[$currency])) {
                return response()->json(['status' => false, 'message' => "Bank details aren't supported for your account currency ({$currency})."], 422);
            }

            if ($method === 'mobile_money') {
                if ($this->countryMap[$currency] === 'ZA') {
                    return response()->json(['status' => false, 'message' => 'Mobile money is not supported for South Africa (ZAR).'], 422);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Mobile money number accepted (cannot be independently verified).',
                    'data' => [
                        'account_number' => $request->account_number,
                        'account_name' => null,
                        'bank_code' => $request->bank_code,
                        'verified' => false,
                    ],
                ]);
            }

            $resolved = $this->flutterwave->resolveAccount($request->account_number, $request->bank_code);

            if (!$resolved) {
                return response()->json(['status' => false, 'message' => 'Account Name not found'], 401);
            }

            return response()->json([
                'status' => true,
                'message' => 'Account Name Found',
                'data' => [
                    'account_number' => $request->account_number,
                    'account_name' => $resolved['account_name'] ?? null,
                    'bank_code' => $request->bank_code,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage(), 'message' => 'Error processing request'], 500);
        }
    }

    public function saveUserAccountDetails($request, $user = null, $allowUpdate = false)
    {
        $this->validator->createBankDetailsValidator($request);

        try {
            $user = $user ?? auth()->user();
            $currency = $this->getUserCurrency($user);
            $method = strtolower($request->method ?? 'bank');

            if (!$allowUpdate && $this->bank->getUserBankByCurrency($user->id, $currency)) {
                return response()->json([
                    'status' => false,
                    'message' => "You already have {$currency} account details saved. Contact support to update them.",
                ], 401);
            }

            if ($currency === 'NGN') {
                $verified = $this->paystack->resolveAccountName($request->account_number, $request->bank_code);

                if (!$verified || !($verified['status'] ?? false)) {
                    return response()->json(['status' => false, 'message' => 'Unable to verify account details. Please try again.'], 401);
                }

                $accountName = $verified['data']['account_name'] ?? $request->account_name;
                $recipientCode = $this->paystack->recipientCode($accountName, $request->account_number, $request->bank_code);

                $data = [
                    'user_id' => $user->id,
                    'name' => $accountName,
                    'bank_name' => $request->bank_name,
                    'account_number' => $request->account_number,
                    'bank_code' => $request->bank_code,
                    'recipient_code' => $recipientCode['data']['recipient_code'] ?? null,
                    'currency' => 'NGN',
                ];
            } else {
                if (!isset($this->countryMap[$currency])) {
                    return response()->json(['status' => false, 'message' => "Bank details aren't supported for your account currency ({$currency})."], 422);
                }

                if ($method === 'mobile_money') {
                    if ($this->countryMap[$currency] === 'ZA') {
                        return response()->json(['status' => false, 'message' => 'Mobile money is not supported for South Africa (ZAR). Use a bank account instead.'], 422);
                    }

                    if (!$request->filled('account_name')) {
                        return response()->json(['status' => false, 'message' => 'account_name is required for mobile money accounts.'], 422);
                    }

                    $data = [
                        'user_id' => $user->id,
                        'name' => $request->account_name,
                        'bank_name' => $request->bank_name ?? null,
                        'account_number' => $request->account_number,
                        'bank_code' => $request->bank_code,
                        'recipient_code' => null,
                        'currency' => $currency,
                    ];
                } else {
                    $verified = $this->flutterwave->resolveAccount($request->account_number, $request->bank_code);

                    if (!$verified) {
                        return response()->json(['status' => false, 'message' => 'Unable to verify account details. Please try again.'], 401);
                    }

                    $data = [
                        'user_id' => $user->id,
                        'name' => $verified['account_name'] ?? $request->account_name,
                        'bank_name' => $request->bank_name,
                        'account_number' => $request->account_number,
                        'bank_code' => $request->bank_code,
                        'recipient_code' => null,
                        'currency' => $currency,
                    ];
                }
            }

            $response = $this->bank->saveBankDetails($data, $user);

            return response()->json([
                'status' => true,
                'message' => 'User Account Details Saved Successfully',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Error processing request.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUserBankDetails($request)
    {
        $user = auth()->user();
        $currency = $request->query('currency');

        $bank = $this->bank->getUserBank($user->id, $currency);

        if ($bank->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Bank details not found'], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Bank details retrieved successfully',
            'data' => $currency ? $bank->first() : $bank,
        ]);
    }
}
