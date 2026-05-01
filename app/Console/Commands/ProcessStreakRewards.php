<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Bonus;
use App\Services\StreakService;
use Illuminate\Console\Command;

class ProcessStreakRewards extends Command
{
    protected $signature   = 'streak:process';
    protected $description = 'Check streak criteria and credit eligible users';

    public function handle(StreakService $streakService): void
    {
        // Only app users with an unclaimed bonus
        $users = User::where('auth_device', 'app')
            ->where('streak_redeemed', false)
            ->whereHas('wallet', fn($q) => $q->where('bonus', '>', 0))
            ->get();

        foreach ($users as $user) {
            $credited = $streakService->creditBonusToWallet($user);
            if ($credited) {
                $this->info("Credited bonus for user #{$user->id}");
            }
        }
    }
}
