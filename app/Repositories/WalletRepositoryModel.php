<?php

namespace App\Repositories;

use App\Models\Currency;
use App\Models\PaymentTransaction;
use App\Models\Wallet;


class WalletRepositoryModel
{
    public function createWallet($user, $currency)
    {
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => '0.00', 'base_currency' => $currency]);
        return $wallet;
    }

    public function updateWallet($user, $currency, $amount)
    {
        $wallet = Wallet::where(
            'user_id',
            $user->id
        )->where('', '',    $currency)->where(
            'amount',
            $amount
        )->first();
    }

    public function updateWalletBaseCurrency($user, $currencyId)
    {
        $currency = Currency::where('id', $currencyId)->first();
        $wallet = Wallet::where(
            'user_id',
            $user->id
        )->first();
        $wallet->base_currency = $currency->code;
        $wallet->save();
        return true;
    }


    public function checkWalletBalance($user, $currency, $amount)
    {
        $wallet = Wallet::where(
            'user_id',
            $user->id
        )->first();
        if (!$wallet) {
            return false;
        }

        // Check balance based on the currency
        switch (strtolower($currency)) {
            case 'naira':
                return $wallet->balance >= $amount;

            case 'ngn':
                return $wallet->balance >= $amount;

            case 'dollar':
                return $wallet->usd_balance >= $amount;

            case 'usd':
                return $wallet->usd_balance >= $amount;

            default:
                return $wallet->bonus >= $amount;
        }
    }

    public function checkReferralCommission($mapCurrency)
    {
        $currency = Currency::where('code', $mapCurrency)->first();
        return $currency->referral_commission;
    }
    public function mapCurrency($currency)
    {
        switch (strtolower($currency)) {
            case 'naira':
                return 'NGN';

            case 'ngn':
                return 'NGN';

            case 'usd':
                return 'USD';

            case 'dollar':
                return 'USD';

            default:
                return $currency;
        }
    }

    public function getWalletBalance($user, $currency) {}

    public function debitWallet($user, $currency, $amount)
    {

        $wallet = Wallet::where(
            'user_id',
            $user->id
        )->first();

        if (!$wallet) {
            return false;
        }

        // Process debit based on the currency
        switch (strtoupper($currency)) {
            case 'NAIRA':
            case 'NGN':
                if ($wallet->balance < $amount) {
                    return false;
                }
                $wallet->balance -= $amount;
                break;

            case 'DOLLAR':
            case 'USD':
                if ($wallet->usd_balance < $amount) {
                    return false;
                }
                $wallet->usd_balance -= $amount;
                break;

            default:
                return false;
        }
        // Save the updated wallet
        if($wallet->save()) return true;
    }

    public function creditWallet($user, $currency, $amount)
    {
        $wallet = Wallet::where(
            'user_id',
            $user->id
        )->first();

        if (!$wallet) {
            return false;
        }

        // Process credit based on the currency
        switch (strtoupper($currency)) {
            case 'NAIRA':
            case 'NGN':
                $wallet->balance += $amount;
                break;

            case 'DOLLAR':
            case 'USD':
                $wallet->usd_balance += $amount;
                break;

            default:
                return false;
        }

        // Save the updated wallet
        if ($wallet->save())  return true;
    }

    public function createTransaction($user, $amount, $ref, $campId, $baseCurrency){
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'campaign_id' => $campId,
            'reference' => $ref,
            'amount' => $amount,
            'status' => 'successful',
            'currency' => $baseCurrency,
            'channel' => 'freebyz',
            'type' => 'wallet_topup',
            'description' => 'Wallet Top Up',
            'tx_type' => 'Credit',
            'user_type' => 'regular'
        ]);
        return $transaction;
    }
}
