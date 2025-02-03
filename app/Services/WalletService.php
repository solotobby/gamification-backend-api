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
    protected  $validator, $logModel, $campaign,
        $currencyModel, $walletModel, $authModel, $referralModel;
    public function __construct(
        AuthRepositoryModel $authModel,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        WalletValidator $validator,
        ReferralRepositoryModel $referralModel,
        LogRepositoryModel $logModel,
        CampaignService $campaign,
    ) {
        $this->logModel = $logModel;
        $this->authModel = $authModel;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->validator = $validator;
        $this->referralModel = $referralModel;
        $this->campaign = $campaign;
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

    public function processWithdrawals($request, $currency, $channel){


       try{

        $user = auth()->user();
        $amount = $request->balance;
        $percent = 5/100 * $amount;
        $formatedAm = $percent;
        $newamount_to_be_withdrawn = $amount - $formatedAm;

        $ref = time();

        if(Carbon::now()->format('l') == 'Friday'){
         $nextFriday = Carbon::now()->endOfDay();
        }else{
         $nextFriday = Carbon::now()->next('Friday')->format('Y-m-d h:i:s');
        }

         $wallet = Wallet::where('user_id', auth()->user()->id)->first();
         if($currency == 'USD'){
            $wallet->usd_balance -= $request->balance;
            $wallet->save();
         }else{
            $wallet->balance -= $request->balance;
            $wallet->save();
         }


        $withdrawal = Withrawal::create([
             'user_id' => auth()->user()->id,
             'amount' => $newamount_to_be_withdrawn,
             'next_payment_date' => $nextFriday,
             'paypal_email' => $currency == 'USD' ? $request->paypal_email : null,
             'is_usd' => $currency == 'USD' ? true : false,
         ]);
        //process dollar withdrawal
        PaymentTransaction::create([
            'user_id' => auth()->user()->id,
            'campaign_id' => '1',
            'reference' => time(),
            'amount' => $newamount_to_be_withdrawn,
            'status' => 'successful',
            'currency' => $currency,
            'channel' => $channel,
            'type' => 'cash_withdrawal',
            'description' => 'Cash Withdrawal from '.auth()->user()->name,
            'tx_type' => 'Credit',
            'user_type' => 'regular'
        ]);

        //admin commission
            $adminWallet = Wallet::where('user_id', '1')->first();
            $adminWallet->usd_balance += $percent;
            $adminWallet->save();
            //Admin Transaction Tablw
            PaymentTransaction::create([
                'user_id' => 1,
                'campaign_id' => '1',
                'reference' => $ref,
                'amount' => $percent,
                'status' => 'successful',
                'currency' => $currency,
                'channel' => $channel,
                'type' => 'withdrawal_commission',
                'description' => 'Withdrwal Commission from '.auth()->user()->name,
                'tx_type' => 'Credit',
                'user_type' => 'admin'
            ]);
            SystemActivities::activityLog(auth()->user(), 'withdrawal_request', auth()->user()->name .'sent a withdrawal request of NGN'.number_format($amount), 'regular');
            // $bankInformation = BankInformation::where('user_id', auth()->user()->id)->first();
            $cur = $currency == 'USD' ? '$' : 'NGN';
            systemNotification(Auth::user(), 'success', 'Withdrawal Request', $cur.$request->balance.' was debited from your wallet');

        // $user = User::where('id', '1')->first();
        // $subject = 'Withdrawal Request Queued!!';
        // $content = 'A withdrwal request has been made and it being queued';
        // Mail::to('freebyzcom@gmail.com')->send(new GeneralMail($user, $content, $subject, ''));

        return $withdrawal;
    }catch(Throwable $exception) {
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

        $referral = $this->setReferralAmountTopPay($user, $referrer);

        //To be returned too later
        // $isCelebrity = $this->authModel->isCelebrity($referrer->id);
        // $amount = $isCelebrity ? 920 : 1050;

        $this->processReferrerBonus($user, $referrer, $referral['amount'], $referral['referralCurrency']);

        return true;
    }

    public function setReferralAmountTopPay($user, $referrer)
    {
        $referrerCurrency = $this->walletModel->mapCurrency($referrer->wallet->base_currency);
        $userCurrency = $this->walletModel->mapCurrency($user->wallet->base_currency);
        $referralCommission = $this->walletModel->checkReferralCommission($userCurrency);

        // Initialize amount and rate
        $amount = $user->referredBy?->amount ?? $referralCommission;
        $rate = 1;

        // Apply currency conversion if currencies differ
        if (empty($user->referredBy?->amount) && $userCurrency !== $referrerCurrency) {
            $rate = $this->campaign->currencyConversion($userCurrency, $referrerCurrency);
            $amount *= $rate;
        }

        // Update referral amount in the database
        $this->referralModel->updateReferralAmount($user->id, $amount);

        $data = [
            'amount' => $amount,
            'referralCurrency' => $referrerCurrency,
        ];
        return $data;
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
