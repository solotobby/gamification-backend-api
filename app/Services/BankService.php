<?php

namespace App\Services;

use App\Repositories\AuthRepositoryModel;
use App\Repositories\BankRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\Providers\PaystackServiceProvider;
use App\Validators\WalletValidator;
use Exception;

class BankService
{
    protected $walletModel, $bank, $authModel, $paystack, $validator;
    public function __construct(
        WalletRepositoryModel $walletModel,
        AuthRepositoryModel $authModel,
        PaystackServiceProvider $paystack,
        WalletValidator $validator,
        BankRepositoryModel $bank,
    ) {
        $this->walletModel = $walletModel;
        $this->authModel = $authModel;
        $this->validator = $validator;
        $this->bank = $bank;
        $this->paystack = $paystack;
    }

    public function getBankList()
    {
        $bankList = $this->paystack->bankList();

        if (!$bankList) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch bank list'
            ], 500);
        }

        $data = array_map(function ($bank) {
            return [
                'id' => $bank['id'],
                'name' => $bank['name'],
                'bank_code' => $bank['code'],
                'currency' => $bank['currency'],
            ];
        }, $bankList);

        return response()->json([
            'status' => true,
            'message' => 'Bank list retrieved successfully',
            'data' => $data
        ], 200);
    }

    public function getAccountDetails($request)
    {

        $this->validator->getAccountNameValidator($request);
        try {

            $response = $this->paystack->resolveAccountName(
                $request->account_number,
                $request->bank_code
            );

            if (!$response['status']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Account Name not found',
                ], 401);
            }

            return response()->json([
                'status' => true,
                'message' => 'Account Name Found',
                'data' => [
                    'account_number' => $response['data']['account_number'],
                    'account_name' => $response['data']['account_name'],
                    'bank_id' => $response['data']['bank_id'],
                ],
            ], 200);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function saveUserAccountDetails($request)
    {
        // Validate request data
        $this->validator->createBankDetailsValidator($request);

        try {
            $user = auth()->user();

            // Prevent duplicate account details
            if ($user->bankDetails) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to create new account details. Contact support to update existing details.',
                ], 401);
            }

            // Request recipient code from Paystack
            $recipientResponse = $this->paystack->recipientCode(
                $request->account_name,
                $request->account_number,
                $request->bank_code
            );

            if (!$recipientResponse['status']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to save account details. Please try again.',
                ], 401);
            }

            $recipientData = $recipientResponse['data']['details'];
            $data = [
                'name' => $recipientData['account_name'],
                'bank_name' => $recipientData['bank_name'],
                'account_number' => $recipientData['account_number'],
                'bank_code' => $recipientData['bank_code'],
                'recipient_code' => $recipientResponse['data']['recipient_code'],
                'currency' => 'NGN',
            ];

            // Save bank details
            $response = $this->bank->saveBankDetails($data, $user);

            return response()->json([
                'status' => true,
                'message' => 'User Account Details Saved Successfully',
                'data' => [
                    'user_id' => $response['user_id'],
                    'account_number' => $response['account_number'],
                    'account_name' => $response['name'],
                    'bank_code' => $response['bank_code'],
                    'bank_name' => $response['bank_name'],
                    'recipient_code' => $response['recipient_code'],
                    'currency' => $response['currency'],
                ],
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing request.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

}
