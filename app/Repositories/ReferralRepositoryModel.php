<?php

namespace App\Repositories;

use App\Models\Referral;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;


class ReferralRepositoryModel
{
    public function createReferral($user, $ref_id)
    {
        $referral = Referral::create(['user_id' => $user->id, 'referee_id' => $ref_id]);
        return $referral;
    }

    public function addReferralCode($user)
    {
        $user->referral_code = Str::random(7);
        $user->save();
    }
}
