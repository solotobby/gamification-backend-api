<?php

namespace App\Services;

use App\Helpers\SystemActivities;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\LogRepositoryModel;
use App\Repositories\ReferralRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Validators\WalletValidator;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Support\Facades\DB;

class WalletService
{
    protected  $validator, $logModel, $currencyModel, $walletModel, $authModel, $referralModel;
    public function __construct(
        AuthRepositoryModel $authModel,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        WalletValidator $validator,
        ReferralRepositoryModel $referralModel,
        LogRepositoryModel $logModel,
    ) {
        $this->logModel = $logModel;
        $this->authModel = $authModel;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->validator = $validator;
        $this->referralModel = $referralModel;
    }
    public function fundWallet($request)
    {
        $this->validator->fundWalletValidation($request);

        try {
            $user = auth()->user();
            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            $amount = $request->amount;
            $ref = time();

            DB::beginTransaction();

            // Check if the user has made a transaction before, if it's exist fund wallet
            if ($user->firstTransaction()) {
                $this->walletModel->createTransaction(
                    $user,
                    $amount,
                    $ref,
                    '1',
                    $baseCurrency
                );
                $this->walletModel->creditWallet(
                    $user,
                    $baseCurrency,
                    $amount
                );
                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => $baseCurrency . ' Wallet Funded Successfully',
                    // 'data' => $user
                ], 201);
            }

            // If the amount is less than the required upgrade fee
            if ($amount < $currency->upgrade_fee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Amount less than Verification Amount',
                ], 401);
            }

            // User verification process (if it's not their first transaction)
            $this->walletModel->createTransaction(
                $user,
                $amount,
                $ref,
                '1', // campaign_id or some constant
                $baseCurrency
            );
            $this->walletModel->creditWallet(
                $user,
                $baseCurrency,
                $amount
            );

            // Update user verification and process referral
            $this->authModel->updateUserVerification($user);
            $this->referralInUpgradeUser($user);
            $this->logModel->createLogForReferral($user);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $baseCurrency . ' Wallet Verified Successfully',
            ], 201);

        } catch (Throwable $exception) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function referralInUpgradeUser($user)
    {
        if (!$user->referredBy) {
            $this->creditAdminWallet(1000, $user->name);
            return true;
        }

        $referrer = $this->authModel->findUserByReferralCode($user->referredBy->referee_id);

        $baseCurrency = $referrer->wallet->base_currency;
        $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

        $referralCommission = $this->walletModel->checkReferralCommission($mapCurrency);
        $amount = empty($user->referredBy->amount) ? $referralCommission : $user->referredBy->amount;

        if(empty($user->referredBy->amount)){
            $this->referralModel->updateReferralAmount($user->id, $amount);
        }
        //To be returned too later
        // $isCelebrity = $this->authModel->isCelebrity($referrer->id);
        // $amount = $isCelebrity ? 920 : 1050;

        $this->processReferrerBonus($user, $referrer, $amount, $mapCurrency);

        return true;
    }

    private function processReferrerBonus($user, $referrer, $amount, $currency)
    {
        $this->walletModel->creditWallet($referrer, $currency, $amount);
        $referrer = $this->referralModel->markAsPaid($user->id);

        $ref = time();
        $this->walletModel->createTransaction(
            $referrer,
            $amount,
            $ref,
            1,
            $currency,
            true,
            $user->name
        );
    }
    private function creditAdminWallet($amount, $username)
    {
        $this->walletModel->creditAdminWallet(1, $amount);

        $transactionData = [
            'user_id' => 1,
            'campaign_id' => 1,
            'reference' => time(),
            'amount' => $amount,
            'status' => 'successful',
            'currency' => 'NGN',
            'channel' => 'paystack',
            'type' => 'direct_referer_bonus',
            'description' => 'Direct Referrer Bonus from ' . $username,
            'tx_type' => 'Credit',
            'user_type' => 'admin',
        ];
        $this->walletModel->createAdminTransaction($transactionData);
    }
}
