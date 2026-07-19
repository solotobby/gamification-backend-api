<?php

namespace App\Console\Commands;

use App\Models\Referral;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\CampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillReferralBonuses extends Command
{
    protected $signature = 'referral:backfill-bonuses {--year=2026} {--dry-run}';
    protected $description = 'Pay referrers who were skipped due to the missing referredBy() relation bug';

    public function handle(
        WalletRepositoryModel $walletModel,
        AuthRepositoryModel $authModel,
        CampaignService $campaign
    ) {
        $year = $this->option('year');
        $dryRun = $this->option('dry-run');

        $unpaid = Referral::where('is_paid', 0)
            ->whereHas('referredUser', fn($q) => $q->where('is_verified', 1)
                ->whereYear('verified_at', $year))
            ->with('referredUser')
            ->get();

        $this->info("Found {$unpaid->count()} unpaid referral bonuses for {$year}");

        foreach ($unpaid as $referral) {
            $referred = $referral->referredUser;
            $referrer = $authModel->findUserById($referral->referee_id);

            if (!$referrer) {
                Log::warning("Referral {$referral->id}: referrer {$referral->referee_id} not found, skipping");
                continue;
            }

            $referrerCurrency = $walletModel->mapCurrency($referrer->wallet->base_currency);
            $userCurrency = $walletModel->mapCurrency($referred->wallet->base_currency);
            $amount = $referral->amount ?? $walletModel->checkReferralCommission($userCurrency);

            if (empty($referral->amount) && $userCurrency !== $referrerCurrency) {
                $rate = $campaign->currencyConversion($userCurrency, $referrerCurrency);
                $amount *= $rate;
            }

            if ($dryRun) {
                $this->line("[DRY RUN] Would pay referrer #{$referrer->id} {$referrerCurrency} {$amount} for referral #{$referral->id} ({$referred->name})");
                continue;
            }

            DB::transaction(function () use ($walletModel, $referrer, $referrerCurrency, $amount, $referral, $referred) {
                $walletModel->creditWallet($referrer, $referrerCurrency, $amount);

                $walletModel->createTransaction(
                    $referrer,
                    $amount,
                    time() . $referral->id,
                    1,
                    $referrerCurrency,
                    'referer_bonus',
                    $referred->name,
                    'Credit',
                    true
                );

                $referral->update([
                    'is_paid' => 1,
                    'amount'  => $amount,
                ]);
            });

            $this->info("Paid referrer #{$referrer->id} {$referrerCurrency} {$amount} for referral #{$referral->id}");
        }
    }
}
