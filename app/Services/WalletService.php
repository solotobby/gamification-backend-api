<?php

namespace App\Services;

use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Validators\WalletValidator;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\DB;

class WalletService
{
    protected  $validator, $currencyModel, $walletModel, $authModel;
    public function __construct(
        AuthRepositoryModel $authModel,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        WalletValidator $validator,
    ) {

        $this->authModel = $authModel;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->validator = $validator;
    }
    public function fundWallet($request)
    {

        $this->validator->fundWalletValidation($request);

        try {
            $user = auth()->user();
            $baseCurrency = $user->wallet->base_currency;
            $amount = $request->amount;
            $ref = Str::random(16);

            DB::beginTransaction();
            $this->walletModel->createTransaction($user, $amount, $ref, '1', $baseCurrency);
            $this->walletModel->creditWallet($user, $baseCurrency, $amount);
            $this->authModel->updateUserVerification($user);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $baseCurrency . ' Wallet Funded Successfully',
                'data' => $user
            ], 201);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }
}
