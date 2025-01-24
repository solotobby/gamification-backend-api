<?php

namespace App\Services;

use App\Models\PaymentTransaction;
use App\Models\Profile;
use App\Models\Referral;
use App\Models\User;
use App\Models\Wallet;
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
            //$this->referralInUpgradeUser($user);
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
    public function referralInUpgradeUser($user)
    {

        if (!$user->referees) {
            return true;
        }
        $referee_id = Referral::where('user_id', $user->id)->first()->referee_id;
        @$referee_id = Referral::where('user_id', $user->id)->first()->referee_id;
        @$profile_celebrity = Profile::where('user_id', $referee_id)->first()->is_celebrity;
        $amount = 0;
        if ($profile_celebrity) {
            $amount = 920;
        } else {
            $amount = 1050;
        }

        $ref = time();
        $userInfo = User::where('id', $user->id)->first();
        $userInfo->is_verified = true;
        $userInfo->save();

        $transaction =  PaymentTransaction::create([
            'user_id' => $user->id,
            'campaign_id' => 1,
            'reference' => $ref,
            'amount' => $amount,
            'status' => 'successful',
            'currency' => 'NGN',
            'channel' => 'paystack',
            'type' => 'upgrade_payment',
            'description' => 'Upgrade Payment',
            'tx_type' => 'Debit',
            'user_type' => 'regular'
        ]);


        $referee = \DB::table('referral')->where('user_id',  $user->id)->first();

        if ($referee) {
            $refereeInfo = Profile::where('user_id', $referee->referee_id)->first()->is_celebrity;

            if (!$refereeInfo) {
                $wallet = Wallet::where('user_id', $referee->referee_id)->first();
                $wallet->balance += 500;
                $wallet->save();

                $refereeUpdate = Referral::where('user_id',  $user->id)->first(); //\DB::table('referral')->where('user_id',  auth()->user()->id)->update(['is_paid', '1']);
                $refereeUpdate->is_paid = true;
                $refereeUpdate->save();

                ///Transactions
                $description = 'Referer Bonus from ' . $user->name;
                // PaystackHelpers::paymentTrasanction($referee->referee_id, '1', time(), 500, 'successful', 'referer_bonus', $description, 'Credit', 'regular');

                PaymentTransaction::create([
                    'user_id' => $referee->referee_id,
                    'campaign_id' => 1,
                    'reference' => $ref,
                    'amount' => 500,
                    'status' => 'successful',
                    'currency' => 'NGN',
                    'channel' => 'paystack',
                    'type' => 'referer_bonus',
                    'description' => $description,
                    'tx_type' => 'Credit',
                    'user_type' => 'regular'
                ]);

                $adminWallet = Wallet::where('user_id', '1')->first();
                $adminWallet->balance += 500;
                $adminWallet->save();

                //Admin Transaction Table
                $description = 'Referer Bonus from ' . $user->name;
                // PaystackHelpers::paymentTrasanction(1, 1, time(), 500, 'successful', 'referer_bonus', $description, 'Credit', 'admin');

                PaymentTransaction::create([
                    'user_id' => 1,
                    'campaign_id' => 1,
                    'reference' => $ref,
                    'amount' => 500,
                    'status' => 'successful',
                    'currency' => 'NGN',
                    'channel' => 'paystack',
                    'type' => 'referer_bonus',
                    'description' => $description,
                    'tx_type' => 'Credit',
                    'user_type' => 'admin'
                ]);
            } else {
                $refereeUpdate = Referral::where('user_id', $user->id)->first(); //\DB::table('referral')->where('user_id',  auth()->user()->id)->update(['is_paid', '1']);
                $refereeUpdate->is_paid = true;
                $refereeUpdate->save();
            }
        } else {
            $adminWallet = Wallet::where('user_id', '1')->first();
            $adminWallet->balance += 1000;
            $adminWallet->save();
            //Admin Transaction Tablw
            PaymentTransaction::create([
                'user_id' => 1,
                'campaign_id' => '1',
                'reference' => $ref,
                'amount' => 1000,
                'status' => 'successful',
                'currency' => 'NGN',
                'channel' => 'paystack',
                'type' => 'direct_referer_bonus',
                'description' => 'Direct Referer Bonus from ' . $user->name,
                'tx_type' => 'Credit',
                'user_type' => 'admin'
            ]);
        }

        $name = SystemActivities::getInitials($user->name);
        SystemActivities::activityLog($user, 'account_verification', $name . ' account verification', 'regular');


        return $transaction;
    }
}
