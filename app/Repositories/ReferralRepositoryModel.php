<?php

namespace App\Repositories;

use App\Models\Referral;
use Illuminate\Support\Str;


class ReferralRepositoryModel
{
    public function createReferral($user, $ref_id)
    {
        Referral::create([
            'user_id' => $user->id,
            'referee_id' => $ref_id
        ]);
        return true;
    }


    public function addReferralCode($user)
    {
        $user->referral_code = Str::random(7);
        $user->save();
    }

    public function getUserReferrals($user)
    {
        return Referral::where(
            'referee_id',
            $user->referral_code
        )->get();

    }
}
