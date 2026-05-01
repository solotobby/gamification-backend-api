<?php

namespace App\Services;

use App\Models\User;
use App\Models\Bonus;
use App\Models\Referral;
use App\Models\Campaign;
use App\Models\CampaignWorker;
use App\Models\Wallet;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StreakService
{
    // Called on login/register via app
    // public function grantBonusIfEligible(User $user)
    // {
    //     if ($user->auth_device === 'web') {
    //         $user->update(['auth_device' => 'app']);

    //         $baseCurrency = $user->wallet->base_currency;
    //         $mapCurrency = app(WalletRepositoryModel::class)->mapCurrency($baseCurrency);

    //         // Fetch currency details
    //         $currency = app(CurrencyRepositoryModel::class)->getCurrencyByCode($mapCurrency);

    //         // Validate retrieved data
    //         if (!$currency) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Currency not found.'
    //             ], 404);
    //         }

    //         $amount = 50000;
    //         $curr = 'NGN';
    //         if ($currency->code !== $curr) {

    //             $rate = app(CampaignService::class)->currencyConversion($curr, $currency->code);
    //             $amount *= $rate;
    //             $curr = $currency->code;
    //         }
    //         // Grant bonus if not already granted
    //         if (!Bonus::where('user_id', $user->id)->exists()) {
    //             Bonus::create([
    //                 'user_id'  => $user->id,
    //                 'amount'   => $amount,
    //                 'currency' => $curr,
    //             ]);

    //             // Also reflect on wallet bonus column
    //             $user->wallet()->update([
    //                 'bonus' => $amount
    //             ]);
    //         }
    //     }
    // }

    public function grantBonusIfEligible(User $user)
    {
        if ($user->auth_device !== 'web') {
            return;
        }

        try {

            DB::transaction(function () use ($user) {

                // 🔒 Lock user row
                $user = User::where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($user->auth_device !== 'web') {
                    return;
                }

                $user->auth_device = 'app';
                $user->save();

                // 🔒 Prevent duplicate bonus (DB-level safe)
                $alreadyGranted = Bonus::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyGranted) {
                    return;
                }

                $wallet = Wallet::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    // throw new \Exception('Wallet not found');
                    return;
                }

                $baseCurrency = $wallet->base_currency;

                $mapCurrency = app(WalletRepositoryModel::class)
                    ->mapCurrency($baseCurrency);

                $currency = app(CurrencyRepositoryModel::class)
                    ->getCurrencyByCode($mapCurrency);

                if (!$currency) {
                    return;
                    // throw new \Exception('Currency not found');
                }

                $amount = 50000;
                $curr = 'NGN';

                if ($currency->code !== $curr) {

                    $rate = app(CampaignService::class)
                        ->currencyConversion($curr, $currency->code);

                    if (!$rate) {
                        return;
                    }

                    $amount *= $rate;
                    $curr = $currency->code;
                }

                // ✅ Create bonus record
                Bonus::create([
                    'user_id'  => $user->id,
                    'amount'   => $amount,
                    'currency' => $curr,
                ]);

                // ✅ SAFE increment (not overwrite)
                $wallet->bonus = $amount;
                $wallet->save();
            });
        } catch (\Throwable $e) {
            Log::error('Bonus grant failed: ' . $e->getMessage());
        }
    }

    public function getStreakProgress($user)
    {

        $since = Carbon::now()->subDays(30);

        $verifiedReferrals = Referral::where('referee_id', $user->referral_code)
            ->where('is_paid', true)
            ->where('created_at', '>=', $since)
            ->count();

        $campaignValue = Campaign::where('user_id', $user->id)
            ->where('approved', true)
            ->where('created_at', '>=', $since)
            ->sum('total_amount');

        $taskEarnings = CampaignWorker::where('user_id', $user->id)
            ->where('status', 'Approved')
            ->where('created_at', '>=', $since)
            ->sum('amount');

        $hiredWorkers = Campaign::where('user_id', $user->id)
            ->where('created_at', '>=', $since)
            ->withCount(['attempts as approved_count' => fn($q) => $q->where('status', 'Approved')])
            ->get()
            ->sum('approved_count');

        return [
            'criteria' => [
                [
                    'key'         => 'verified_referrals',
                    'label'       => '50 Verified Referrals',
                    'target'      => 50,
                    'progress'    => $verifiedReferrals,
                    'met'         => $verifiedReferrals >= 50,
                    'percentage'  => min(100, round(($verifiedReferrals / 50) * 100)),
                ],
                [
                    'key'         => 'campaign_value',
                    'label'       => 'Post Campaigns worth ₦300,000',
                    'target'      => 300000,
                    'progress'    => $campaignValue,
                    'met'         => $campaignValue >= 300000,
                    'percentage'  => min(100, round(($campaignValue / 300000) * 100)),
                ],
                [
                    'key'         => 'task_earnings',
                    'label'       => 'Complete Tasks worth ₦100,000',
                    'target'      => 100000,
                    'progress'    => $taskEarnings,
                    'met'         => $taskEarnings >= 100000,
                    'percentage'  => min(100, round(($taskEarnings / 100000) * 100)),
                ],
                [
                    'key'         => 'hired_workers',
                    'label'       => 'Hire 150 Workers',
                    'target'      => 150,
                    'progress'    => $hiredWorkers,
                    'met'         => $hiredWorkers >= 150,
                    'percentage'  => min(100, round(($hiredWorkers / 150) * 100)),
                ],
            ],
            'any_met'         => $verifiedReferrals >= 50
                || $campaignValue >= 300000
                || $taskEarnings >= 100000
                || $hiredWorkers >= 150,
            'streak_redeemed' => $user->streak_redeemed,
        ];
    }

    public function creditBonusToWallet(User $user): bool
    {
        if ($user->streak_redeemed) return false;

        $bonus = Bonus::where('user_id', $user->id)
            ->where('is_unlocked', false)
            ->whereNull('credited_at')
            ->first();

        if (!$bonus) return false;

        $progress = $this->getStreakProgress($user);
        if (!$progress['any_met']) return false;

        // Debit bonus wallet, credit main wallet
        $wallet = $user->wallet;
        $wallet->decrement('bonus', $bonus->amount);
        $wallet->increment('balance', $bonus->amount);

        $bonus->update([
            'is_unlocked' => true,
            'unlocked_at' => now(),
            'credited_at' => now(),
        ]);

        $user->update(['streak_redeemed' => true]);

        return true;
    }
}
