<?php

namespace App\Services;

use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\LogRepositoryModel;
use App\Repositories\ReferralRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Repositories\WithdrawalRepositoryModel;
use App\Validators\WalletValidator;
use Carbon\Carbon;
use Throwable;
use Illuminate\Support\Facades\DB;

class WalletService
{
    protected  $validator;
    protected  $logModel;
    protected  $campaign;
    protected  $withdrawalModel;
    protected  $currencyModel;
    protected  $walletModel;
    protected  $authModel;
    protected  $referralModel;

    public function __construct(
        AuthRepositoryModel $authModel,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        WalletValidator $validator,
        ReferralRepositoryModel $referralModel,
        LogRepositoryModel $logModel,
        CampaignService $campaign,
        WithdrawalRepositoryModel $withdrawalModel,
    ) {
        $this->logModel = $logModel;
        $this->authModel = $authModel;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->validator = $validator;
        $this->referralModel = $referralModel;
        $this->campaign = $campaign;
        $this->withdrawalModel = $withdrawalModel;
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
            if ($user->transactions) {
                $this->walletModel->createTransaction(
                    $user,
                    $amount,
                    $ref,
                    '1',
                    $currency->code,
                    'wallet_topup',
                    'Wallet Topup',
                    'credit',
                );
                $this->walletModel->creditWallet(
                    $user,
                    $currency->code,
                    $amount
                );
                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => $currency->code . ' Wallet Funded Successfully',
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
                $currency->code,
                'upgrade_payment',
                'Upgrade Payment',
                'credit',
            );
            $this->walletModel->creditWallet(
                $user,
                $currency->code,
                $amount
            );

            // Update user verification and process referral
            $this->authModel->updateUserVerification($user);
            $this->referralInUpgradeUser($user);
            $this->logModel->createLogForReferral($user);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => $currency->code . ' Wallet Verified Successfully',
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


    public function processWithdrawals($request)
    {
        $this->validator->processWithdrawalValidation($request);

        try {
            $user = auth()->user();
            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            $amount = $request->amount;
            if (!$this->walletModel->checkWalletBalance($user, $baseCurrency, $amount)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient wallet balance.',
                ], 401);
            }

            if ($amount < $currency->min_withdrawal_amount) {
                return response()->json([
                    'status' => false,
                    'message' => 'Amount is below the minimum withdrawal limit.',
                ], 401);
            }

            $ref = time();
            $nextFriday = Carbon::now()->isFriday() ? Carbon::now()->endOfDay() : Carbon::now()->next('Friday')->endOfDay();

            DB::beginTransaction();

            // Handle referral commission if applicable
            if ($user->referredBy) {
                // Process user withdrawal and get actual withdrawal amount
                $this->debitUserWallet($user, $currency, $amount, $ref, $nextFriday, $request->paypal_email);
                $this->handleReferralCommission($user, $currency, $amount, $ref);
            } else {
                // Process user withdrawal and get actual withdrawal amount
                $this->debitUserWallet($user, $currency, $amount, $ref, $nextFriday, $request->paypal_email);

                // Handle admin commission
                $this->handleAdminCommission($user, $currency, $amount, $ref);
            }


            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Withdrawal of ' . $baseCurrency . ' ' . $amount . ' processed successfully.',
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

    private function handleReferralCommission($user, $currency, $amount, $ref)
    {
        $referrer = $this->authModel->findUserByReferralCode($user->referredBy->referee_id);

        if (!$referrer) {
            return;
        }

        // Calculate referral commissions
        $freebyzPercent = ($currency->freebyz_withdrawal_percent / 100) * $amount;
        $referrerPercent = ($currency->referral_withdrawal_percent / 100) * $amount;


        if ($user->wallet->base_currency !== $referrer->wallet->base_currency) {
            $currencyRate = $this->currencyModel->convertCurrency(
                $user->wallet->base_currency,
                $referrer->wallet->base_currency
            );

            if (!$currencyRate) {
                return;
            }

            $rate = $currencyRate->rate;
            $referrerPercent *= $rate; // Convert the amount based on the rate
        }

        // Credit referrer
        $this->walletModel->creditWallet($referrer, $referrer->wallet->base_currency, $referrerPercent);
        $this->walletModel->createTransaction(
            $referrer,
            $referrerPercent,
            $ref,
            '1',
            $referrer->wallet->base_currency,
            'withdrawal_commission',
            'Withdrawal commission from ' . $user->name . ' withdrawal',
            'credit'
        );

        // Credit Freebyz platform commission
        $admin = $this->authModel->findUserById('1');
        $this->walletModel->creditWallet($admin, $user->wallet->base_currency, $freebyzPercent);
        $this->walletModel->createAdminTransaction([
            'user_id' => 1,
            'campaign_id' => '1',
            'reference' => $ref,
            'amount' => $freebyzPercent,
            'status' => 'successful',
            'currency' => $user->wallet->base_currency,
            'channel' => 'freebyz',
            'type' => 'withdrawal_commission',
            'description' => 'Withdrawal commission from ' . $user->name,
            'tx_type' => 'Credit',
            'user_type' => 'admin'
        ]);
    }

    private function debitUserWallet($user, $currency, $amount, $ref, $nextFriday, $paypalEmail)
    {
        // Calculate withdrawal fee
        $withdrawalFee = ($currency->withdrawal_percent / 100) * $amount;
        $withdrawalAmount = $amount - $withdrawalFee;

        // Debit user wallet
        if (!$this->walletModel->debitWallet($user, $user->wallet->base_currency, $amount)) {
            return response()->json([
                'status' => false,
                'message' => 'Wallet debit failed. Please try again.',
            ], 500);
        }

        // Create withdrawal entry
        $this->withdrawalModel->createWithdrawal($user, $withdrawalAmount, $nextFriday, $currency, $paypalEmail);

        // Create withdrawal transaction
        $this->walletModel->createTransaction(
            $user,
            $amount,
            $ref,
            '1',
            $user->wallet->base_currency,
            'cash_withdrawal',
            'Cash Withdrawal from ' . $user->name,
            'debit'
        );

        return $withdrawalAmount;
    }

    private function handleAdminCommission($user, $currency, $amount, $ref)
    {
        // Calculate withdrawal fee
        $withdrawalFee = ($currency->withdrawal_percent / 100) * $amount;

        // Credit admin wallet
        $admin = $this->authModel->findUserById('1');
        $this->walletModel->createWallet($admin, $user->wallet->base_currency);

        // Create admin transaction for withdrawal fee
        $this->walletModel->createAdminTransaction([
            'user_id' => 1,
            'campaign_id' => '1',
            'reference' => $ref,
            'amount' => $withdrawalFee,
            'status' => 'successful',
            'currency' => $user->wallet->base_currency,
            'channel' => 'freebyz',
            'type' => 'withdrawal_commission',
            'description' => 'Withdrawal Commission from ' . $user->name,
            'tx_type' => 'Credit',
            'user_type' => 'admin'
        ]);

        // Log the withdrawal
        $this->logModel->createLogForWithdrawal($user, $user->wallet->base_currency, $amount);
        $this->logModel->systemNotification(
            $user,
            'success',
            'Withdrawal Request',
            $user->wallet->base_currency . ' ' . $amount . ' was debited from your wallet'
        );
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

    public function getWithdrawals()
    {
        try {
            $user = auth()->user();
            $withdrawals = $this->withdrawalModel->withdrawalLists($user);

            // Map through each withdrawal record
            $data = $withdrawals->map(function ($withdrawal) use ($user) {
                return [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                    'currency' => $withdrawal->base_currency ?? $user->wallet->base_currency,
                    'payment_date' => $withdrawal->next_payment_date,
                    'withdrawal_created_at' => $withdrawal->created_at,
                    'status' => $withdrawal->status ? 'Paid' : 'Pending',
                ];
            });

            // Pagination details
            $pagination = [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
                'from' => $withdrawals->firstItem(),
                'to' => $withdrawals->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Withdrawals retrieved successfully.',
                'data' => $data,
                'pagination' => $pagination,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }


    public function getTransactions()
    {
        try {
            $user = auth()->user();
            $transactions = $this->walletModel->getUserTransactions($user);

            // Map through each withdrawal record
            $data = $transactions->map(function ($transaction) use ($user) {
                return [
                    'id' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency ?? $user->wallet->base_currency,
                    'status' => $transaction->status,
                    'description' => $transaction->description,
                    'type' => $transaction->tx_type,
                    'created_at' => $transaction->created_at,
                ];
            });

            // Pagination details
            $pagination = [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Transactions retrieved successfully.',
                'data' => $data,
                'pagination' => $pagination,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
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
            'referer_bonus',
            $user->name,
            'credit',
            true

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
