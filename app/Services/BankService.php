<?php

namespace App\Services;

use App\Repositories\AuthRepositoryModel;
use App\Repositories\BankRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\Providers\FlutterwaveServiceProvider;
use App\Services\Providers\InterswitchServiceProvider;
use App\Services\Providers\KoraPayServiceProvider;
use App\Services\Providers\PaystackServiceProvider;
use App\Validators\WalletValidator;
use Exception;
use Illuminate\Support\Facades\Log;

class BankService
{
    protected array $countryMap = [
        'NGN' => 'NG',
        'GHS' => 'GH',
        'ZAR' => 'ZA',
        'KES' => 'KE',
        'UGX' => 'UG',
        'TZS' => 'TZ',
        'EGP' => 'EG',
        'XAF' => 'CM',
        'XOF' => 'CI',
        'USD' => 'US',
        'GBP' => 'GB',
        'EUR' => 'EU',
    ];

    public function __construct(
        protected WalletRepositoryModel $walletModel,
        protected AuthRepositoryModel $authModel,
        protected KoraPayServiceProvider $korapay,
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
        $method = strtolower($request->query('method', 'bank'));

        try {
            if (!isset($this->countryMap[$currency])) {
                return response()->json(['status' => false, 'message' => "Bank details aren't supported for your account currency ({$currency})."], 422);
            }

            $countryCode = $this->countryMap[$currency];

            if ($method === 'mobile_money') {
                if ($countryCode === 'ZA' || $countryCode === 'NG') {
                    return response()->json([
                        'status' => false,
                        'message' => "Mobile money is not supported for {$currency}. Use a bank account instead.",
                    ], 422);
                }

                // Try Korapay MMO first
                $mmoList = $this->korapay->getMobileMoneyOperators($countryCode);

                if (empty($mmoList)) {
                    // Fallback to Flutterwave mobile money networks
                    $networks = $this->flutterwave->getMobileMoneyNetworks($countryCode);
                    $mmoList = array_map(fn($n) => [
                        'id'        => $n['code'],
                        'name'      => $n['name'],
                        'bank_code' => $n['code'],
                        'currency'  => $currency,
                    ], $networks);
                } else {
                    $mmoList = array_map(fn($n) => [
                        'id'        => $n['code'] ?? $n['id'],
                        'name'      => $n['name'],
                        'bank_code' => $n['code'] ?? $n['bank_code'],
                        'currency'  => $currency,
                    ], $mmoList);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Mobile money networks retrieved successfully',
                    'data' => $mmoList,
                ]);
            }

            // Bank list - Korapay is prioritized for all supported currencies
            $bankList = $this->korapay->getBanks($countryCode, $currency);

            // Fallback to Flutterwave if Korapay bank list is empty or failed
            if (empty($bankList)) {
                $bankList = $this->flutterwave->getBanks($countryCode);
            }

            if (!$bankList) {
                return response()->json(['status' => false, 'message' => 'Failed to fetch bank list'], 500);
            }

            $data = array_map(fn($b) => [
                'id'        => $b['code'] ?? $b['id'] ?? '',
                'name'      => $b['name'] ?? '',
                'bank_code' => (string) ($b['code'] ?? $b['bank_code'] ?? $b['id'] ?? ''),
                'currency'  => $currency,
            ], $bankList);

            return response()->json(['status' => true, 'message' => 'Bank list retrieved successfully', 'data' => $data]);
        } catch (Exception $e) {
            Log::error('BankService getBankList error: ' . $e->getMessage());
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
            if (!isset($this->countryMap[$currency])) {
                return response()->json(['status' => false, 'message' => "Bank details aren't supported for your account currency ({$currency})."], 422);
            }

            $countryCode = $this->countryMap[$currency];

            if ($method === 'mobile_money') {
                if ($countryCode === 'ZA' || $countryCode === 'NG') {
                    return response()->json(['status' => false, 'message' => "Mobile money is not supported for {$currency}."], 422);
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

            // Resolve bank account using Korapay first
            $resolved = $this->korapay->resolveAccount($request->account_number, $request->bank_code, $currency, $countryCode);

            // Fallback to Flutterwave if Korapay resolution fails
            if (!$resolved || empty($resolved['account_name'])) {
                $resolved = $this->flutterwave->resolveAccount($request->account_number, $request->bank_code);
            }

            if (!$resolved || empty($resolved['account_name'])) {
                return response()->json(['status' => false, 'message' => 'Account Name not found'], 401);
            }

            return response()->json([
                'status' => true,
                'message' => 'Account Name Found',
                'data' => [
                    'account_number' => $request->account_number,
                    'account_name' => $resolved['account_name'] ?? null,
                    'bank_code' => $request->bank_code,
                    'bank_name' => $resolved['bank_name'] ?? null,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('BankService getAccountDetails error: ' . $e->getMessage());
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

            if (!isset($this->countryMap[$currency])) {
                return response()->json(['status' => false, 'message' => "Bank details aren't supported for your account currency ({$currency})."], 422);
            }

            $countryCode = $this->countryMap[$currency];

            if ($method === 'mobile_money') {
                if ($countryCode === 'ZA' || $countryCode === 'NG') {
                    return response()->json(['status' => false, 'message' => "Mobile money is not supported for {$currency}. Use a bank account instead."], 422);
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
                // Verify account using Korapay first
                $verified = $this->korapay->resolveAccount($request->account_number, $request->bank_code, $currency, $countryCode);

                // Fallback to Flutterwave if Korapay resolution is unavailable
                if (!$verified || empty($verified['account_name'])) {
                    $verified = $this->flutterwave->resolveAccount($request->account_number, $request->bank_code);
                }

                if (!$verified && !$request->filled('account_name')) {
                    return response()->json(['status' => false, 'message' => 'Unable to verify account details. Please try again.'], 401);
                }

                $accountName = $verified['account_name'] ?? $request->account_name;
                $bankName = $verified['bank_name'] ?? $request->bank_name;

                $data = [
                    'user_id' => $user->id,
                    'name' => $accountName,
                    'bank_name' => $bankName,
                    'account_number' => $request->account_number,
                    'bank_code' => $request->bank_code,
                    'recipient_code' => null,
                    'currency' => $currency,
                ];
            }

            $response = $this->bank->saveBankDetails($data, $user);

            return response()->json([
                'status' => true,
                'message' => 'User Account Details Saved Successfully',
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('BankService saveUserAccountDetails error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Error processing request.', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUserBankDetails($request)
    {
        $user = auth()->user();
        $currency = $request->query('currency') ?: $this->getUserCurrency($user);

        // Fetch bank details for the requested/wallet currency
        $bank = $this->bank->getUserBank($user->id, $currency);

        // Fallback to any saved bank details for user if currency-specific is not found
        if ($bank->isEmpty()) {
            $bank = $this->bank->getUserBank($user->id);
        }

        if ($bank->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'Bank details not found'], 404);
        }

        $primaryBank = $bank->first();

        return response()->json([
            'status'   => true,
            'message'  => 'Bank details retrieved successfully',
            'data'     => $primaryBank,
            'accounts' => $bank,
        ]);
    }
}
