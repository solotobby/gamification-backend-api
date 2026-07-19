<?php

namespace App\Console\Commands;

use App\Models\PaymentTransaction;
use App\Models\Referral;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class UpdatePaidReferralAmounts extends Command
{
    protected $signature = 'referral:update-paid-amounts {--dry-run} {--force} {--chunk=1000}';
    protected $description = 'Backfill referral.amount from the actual referer_bonus transaction paid to the referrer, matched by same calendar day as verification';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        $baseQuery = Referral::where('is_paid', 1)
            ->with('referredUser:id,name,verified_at');

        if (!$force) {
            $baseQuery->where(function ($q) {
                $q->whereNull('amount')->orWhere('amount', 0);
            });
        }

        $total = (clone $baseQuery)->count();
        $this->info("Found {$total} paid referrals to process");

        $updated = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($total);

        $baseQuery->orderBy('id')->chunk($chunkSize, function ($referrals) use (&$updated, &$skipped, $dryRun, $bar) {

            foreach ($referrals as $referral) {
                $referred = $referral->referredUser;

                if (!$referred) {
                    Log::channel('referral_backfill')->warning('Referral skipped: referred user not found', [
                        'referral_id' => $referral->id,
                        'referee_id'  => $referral->referee_id,
                    ]);
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if (!$referred->verified_at) {
                    Log::channel('referral_backfill')->warning('Referral skipped: referred user has no verified_at', [
                        'referral_id'   => $referral->id,
                        'referred_id'   => $referred->id,
                        'referred_name' => $referred->name,
                    ]);
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $candidateTx = PaymentTransaction::where('user_id', $referral->referee_id)
                    ->where('type', 'referer_bonus')
                    ->where('tx_type', 'Credit')
                    ->select(['id', 'amount', 'currency', 'reference', 'created_at'])
                    ->get();

                if ($candidateTx->isEmpty()) {
                    Log::channel('referral_backfill')->warning('Referral skipped: no referer_bonus transaction found for referrer', [
                        'referral_id' => $referral->id,
                        'referee_id'  => $referral->referee_id,
                        'referred_id' => $referred->id,
                    ]);
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $verifiedAt = Carbon::parse($referred->verified_at);
                $verifiedDate = $verifiedAt->toDateString();

                $sameDay = $candidateTx->filter(function ($tx) use ($verifiedDate) {
                    return Carbon::parse($tx->created_at)->toDateString() === $verifiedDate;
                });

                if ($sameDay->isEmpty()) {
                    Log::channel('referral_backfill')->warning('Referral skipped: no transaction on same date as verification', [
                        'referral_id'        => $referral->id,
                        'referee_id'         => $referral->referee_id,
                        'referred_id'        => $referred->id,
                        'verified_date'      => $verifiedDate,
                        'candidate_tx_dates' => $candidateTx->pluck('created_at')
                            ->map(fn($d) => Carbon::parse($d)->toDateString())
                            ->unique()
                            ->values(),
                    ]);
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // If multiple referer_bonus tx fall on the same day (referrer had several referees
                // verify same-day), pick the one closest in time to disambiguate.
                $match = $sameDay->count() === 1
                    ? $sameDay->first()
                    : $sameDay->sortBy(fn($tx) => abs(Carbon::parse($tx->created_at)->diffInSeconds($verifiedAt)))->first();

                if ($sameDay->count() > 1) {
                    Log::channel('referral_backfill')->info('Multiple same-day candidates, picked closest by time', [
                        'referral_id'   => $referral->id,
                        'referee_id'    => $referral->referee_id,
                        'candidate_ids' => $sameDay->pluck('id'),
                        'chosen_tx_id'  => $match->id,
                    ]);
                }

                if ($dryRun) {
                    Log::channel('referral_backfill')->info('DRY RUN: would update referral amount', [
                        'referral_id' => $referral->id,
                        'amount'      => $match->amount,
                        'tx_id'       => $match->id,
                    ]);
                } else {
                    $referral->update(['amount' => $match->amount]);

                    Log::channel('referral_backfill')->info('Referral amount updated', [
                        'referral_id' => $referral->id,
                        'amount'      => $match->amount,
                        'tx_id'       => $match->id,
                    ]);
                }

                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Updated {$updated}, skipped {$skipped}");
        $this->info('See storage/logs/referral-backfill.log for details on skipped rows');
    }
}
