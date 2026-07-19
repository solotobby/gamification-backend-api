<?php

namespace App\Services;

use App\Repositories\AuthRepositoryModel;
use App\Repositories\ReferralRepositoryModel;
use App\Repositories\WalletRepositoryModel;


class ReferralService
{
    protected $referralModel;
    protected $walletModel;
    protected $authModel;
    public function __construct(
        ReferralRepositoryModel $referralModel,
        WalletRepositoryModel $walletModel,
        AuthRepositoryModel $authModel,
    ) {
        $this->referralModel = $referralModel;
        $this->walletModel = $walletModel;
        $this->authModel = $authModel;
    }

    public function referralStat()
{
    $user = auth()->user();

    $referrals = $this->referralModel->getUserReferrals($user);

    $totalReferral = $referrals->count();
    $verifiedReferral = $referrals->where('is_paid', true)->count();
    $pendingReferral = $referrals->where('is_paid', false)->count();

    // Only sum actual amounts for referrals that were actually paid
    $totalSum = $referrals->where('is_paid', true)->sum(function ($referral) {
        return (float) ($referral->amount ?? 0);
    });

    $data = [
        'total_user_referred' => $totalReferral,
        'verified_user_referred' => $verifiedReferral,
        'pending_user_referred' => $pendingReferral,
        'total_referral_income' => $totalSum,
        'referral_link' => 'https://freebyz.com/register/' . $user->referral_code
    ];

    return response()->json([
        'status' => true,
        'message' => 'Referral Stats Retrieved Successfully',
        'data' => $data
    ]);
}



    public function referralList()
    {
        $user = auth()->user();

        $referrals = $this->referralModel->getUserReferralsPaginated($user);

        $data = [];

        foreach ($referrals as $referral) {
            $referredUser = $this->authModel->findUserById($referral->user_id);

            // Only show an amount if the referrer was actually paid; otherwise 0
            $income = $referral->is_paid ? (float) ($referral->amount ?? 0) : 0;

            $data[] = [
                'id' => $referral->id,
                'name' => $referredUser->name,
                'is_paid' => $referral->is_paid ? true : false,
                'income' => $income,
                'status' => $referredUser->is_verified ? 'Verified' : 'Unverified',
                'created_at' => $referral->created_at,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Referrals Retrieved Successfully',
            'data' => $data,
            'pagination' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'per_page' => $referrals->perPage(),
                'total' => $referrals->total(),
                'from' => $referrals->firstItem(),
                'to' => $referrals->lastItem(),
            ]
        ]);
    }
}
