<?php

namespace App\Repositories;

use App\Models\Referral;


class ReferralRepositoryModel
{
    public function createReferral($user,$ref_id){
        $referral= Referral::create(['user_id' => $user->id, 'referee_id' => $ref_id]);
        return $referral;
    }


}
