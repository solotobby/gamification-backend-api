<?php

namespace App\Repositories;

use App\Models\BankInformation;
use App\Models\VirtualAccount;

class BankRepositoryModel
{
    public function saveBankDetails(array $data, $user): BankInformation
    {
        return BankInformation::updateOrCreate(
            ['user_id' => $user->id, 'currency' => $data['currency']],
            $data
        );
    }

    public function getUserBank($userId, ?string $currency = null)
    {
        return BankInformation::where('user_id', $userId)
            ->when($currency, fn($q) => $q->where('currency', $currency))
            ->when(!$currency, fn($q) => $q) // no filter → all currencies
            ->get();
    }

    public function getUserBankByCurrency($userId, string $currency): ?BankInformation
    {
        return BankInformation::where('user_id', $userId)->where('currency', $currency)->first();
    }

    public function getVirtualBank($userId, ?string $channel = null)
    {
        return VirtualAccount::where('user_id', $userId)
            ->when($channel, fn($q) => $q->where('channel', $channel))
            ->first();
    }
}
