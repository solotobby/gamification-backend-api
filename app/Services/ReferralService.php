<?php

namespace App\Services;

use App\Http\Controllers\ReferralController;
use App\Models\User;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\ReferralRepositoryModel;
use App\Repositories\WalletRepositoryModel;


class ReferralService
{
    protected $referralModel, $walletModel, $authModel;
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

        // Get all referrals for the authenticated user
        $referrals = $this->referralModel->getUserReferrals($user);

        // Total referrals
        $totalReferral = $referrals->count();

        // Count verified referrals
        $verifiedReferral = $referrals->where('user.is_verified', true)->count();

        // Count pending referrals
        $pendingReferral = $referrals->where('user.is_verified', false)->count();

        // Count how many referrals have null or empty amount and calculate the total empty amount
        $emptyAmount = 0;
        $emptyCount = $referrals->filter(function ($referral) {
            return empty($referral->amount);  // Check if amount is empty or null
        })->count();

        // Calculate the referral commission for empty referrals
        $baseCurrency = $user->wallet->base_currency;
        $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
        $referralCommission = $this->walletModel->checkReferralCommission($mapCurrency);

        if ($emptyCount > 0) {
            $emptyAmount = $emptyCount * $referralCommission;
        }

        // Sum the amount of referrals, including the empty amount as calculated
        $totalSum = $referrals->sum(function ($referral) {
            return (float) ($referral->amount ?? 0);
        });


        $data = [
            'total_user_referred' => $totalReferral,
            'verified_user_referred' => $verifiedReferral,
            'pending_user_referred' => $pendingReferral,
            'total_referral_income' => $totalSum + $emptyAmount,
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
        $baseCurrency = $user->wallet->base_currency;
        $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
        $referralCommission = $this->walletModel->checkReferralCommission($mapCurrency);

        // Get all referrals for the authenticated user with pagination (10 per page)
        $referrals = $this->referralModel->getUserReferralsPaginated($user); // Ensure paginate() is used on query builder

        $data = [];

        foreach ($referrals as $referral) {
            $referredUser = $this->authModel->findUserById($referral->user_id);

            // Check if amount is not empty, otherwise set to referral commission
            $amount = !empty($referral->amount) ? $referral->amount : $referralCommission;

            $data[] = [
                'id' => $referral->id,
                'name' => $referredUser->name,
                'is_paid' => $referral->is_paid ? true : false,
                'income' => $amount,
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
