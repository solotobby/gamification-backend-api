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

class FixReferralCreatedAt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $fixed = 0;

        Referral::whereNull('created_at')
            ->chunkById(500, function ($referrals) use (&$fixed) {

                $userIds = $referrals->pluck('user_id')->unique();
                $referredUsers = User::whereIn('id', $userIds)
                    ->pluck('created_at', 'id');

                $updates = [];

                foreach ($referrals as $referral) {
                    $updates[] = [
                        'id'         => $referral->id,
                        'user_id'    => $referral->user_id,
                        'referee_id' => $referral->referee_id,
                        'is_paid'    => $referral->is_paid,
                        'amount'     => $referral->amount,
                        'created_at' => $referredUsers[$referral->user_id] ?? now(),
                        'updated_at' => $referral->updated_at,
                    ];

                    $fixed++;
                }

                if (!empty($updates)) {
                    DB::table('referral')->upsert($updates, ['id'], ['created_at']);
                }
            });

        Log::info("FixReferralCreatedAt: Fixed {$fixed} rows.");
    }
}
