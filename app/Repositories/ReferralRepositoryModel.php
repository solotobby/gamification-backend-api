<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Referral;
use Illuminate\Support\Str;


class ReferralRepositoryModel
{
    public function createReferral($user, $ref_id, $amount)
    {
        Referral::create([
            'user_id' => $user->id,
            'referee_id' => $ref_id,
            'amount' => $amount
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

    public function getUserReferralsPaginated($user, $page = null)
    {
        return Referral::where(
            'referee_id',
            $user->referral_code
        )->paginate(
            10,
            ['*'],
            'page',
            $page
        );
    }

    public function getReferrerDetails($ref_id)
    {
        return User::where(
            'referral_code',
            $ref_id
        )->first();
    }

    public function getRefereeId($userId)
    {
        return Referral::where(
            'user_id',
            $userId
        )->value('referee_id');
    }

    public function getReferralByUserId($userId)
    {
        return Referral::where(
            'user_id',
            $userId
        )->first();
    }

    public function markAsPaid($referralId)
    {
        echo $referralId;
        $referral = Referral::where(
            'user_id',
            $referralId
        )->firstOrFail();
        $referral->is_paid = true;
        $referral->save();

        return $referral;
    }

    public function updateReferralAmount($referralId, $amount)
    {
        echo $referralId;
        $referral = Referral::where('user_id', $referralId)->firstOrFail();
        $referral->amount = $amount;
        $referral->save();

        return true;
    }
}
