<?php

namespace App\Repositories;

use App\Models\Wallet;


class WalletRepositoryModel
{
    public function createWallet($user, $currency)
    {
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => '0.00', 'base_currency' => $currency]);
        return $wallet;
    }
}
