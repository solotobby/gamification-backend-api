<?php

namespace App\Jobs;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixReferralData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $fixed = 0;
        $skipped = 0;

        Referral::whereRaw("referee_id REGEXP '^[^0-9]+'")
            ->chunkById(500, function ($referrals) use (&$fixed, &$skipped) {

                $codes = $referrals->pluck('referee_id')->unique();
                $users = User::whereIn('referral_code', $codes)
                    ->pluck('id', 'referral_code');

                $userIds = $referrals->pluck('user_id')->unique();
                $referredUsers = User::whereIn('id', $userIds)
                    ->pluck('created_at', 'id');

                $updates = [];

                foreach ($referrals as $referral) {
                    $refereeId = $users[$referral->referee_id] ?? null;

                    if (!$refereeId) {
                        Log::warning("FixReferralData: No user found for referral_code [{$referral->referee_id}] on referral ID {$referral->id}");
                        $skipped++;
                        continue;
                    }

                    $updates[] = [
                        'id'         => $referral->id,
                        'user_id'    => $referral->user_id,
                        'referee_id' => $refereeId,
                        'is_paid'    => $referral->is_paid,
                        'amount'     => $referral->amount,
                        'created_at' => $referral->created_at ?? $referredUsers[$referral->user_id] ?? now(),
                        'updated_at' => $referral->updated_at,
                    ];

                    $fixed++;
                }

                if (!empty($updates)) {
                    DB::table('referral')->upsert($updates, ['id'], ['referee_id', 'created_at']);
                }
            });

        Log::info("FixReferralData: Fixed {$fixed} rows, skipped {$skipped} rows.");
    }
}
