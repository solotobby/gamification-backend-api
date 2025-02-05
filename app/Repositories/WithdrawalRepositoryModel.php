<?php

namespace App\Repositories;

use App\Models\Withdrawal;

class WithdrawalRepositoryModel
{

    public function createWithdrawal($user, $withdrawalAmount, $nextFriday, $currency, $payPal)
    {
        Withdrawal::create([
            'user_id' => $user->id,
            'amount' => $withdrawalAmount,
            'next_payment_date' => $nextFriday,
            'paypal_email' => $currency->code == 'USD' ? $payPal : null,
            'is_usd' => $currency->code == 'USD' ? true : false,
            'base_currency' => $currency->code
        ]);
    }

    public function withdrawalLists($user, $page = null)
    {
        return Withdrawal::where(
            'user_id',
            $user->id
        )->orderBy(
            'created_at',
            'desc'
        )->paginate(
            10,
            ['*'],
            'page',
            $page
        );
    }

    public function adminWithdrawalRequestLists($status = null, $page = null)
    {
        $query = Withdrawal::orderBy(
            'created_at',
            'desc'
        );
        if (!is_null($status)) {
            $query->where(
                'status',
                $status
            );
        }
        return $query->paginate(
            10,
            ['*'],
            'page',
            $page
        );
    }
}
