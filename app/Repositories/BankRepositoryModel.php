<?php

namespace App\Repositories;

use App\Models\BankInformation;

class BankRepositoryModel
{
    public function getUserBank($userId)
    {
       return BankInformation::where(
            'user_id',
            $userId
        )->first();
    }

    public function saveBankDetails($data, $user)
    {
        $bank = BankInformation::where(
            'user_id',
            $user->id
        )->first();

        $bank->name = $data->account_name;
        $bank->bank_name = $data->bank_name;
        $bank->account_number = $data->account_number;
        $bank->bank_code = $data->bank_code;
        $bank->recipient_code = $data->recipient_code;
        $bank->save();
    }
}
