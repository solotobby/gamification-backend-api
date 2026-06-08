<?php

namespace App\Console\Commands;

use App\Models\Feedback;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ResetMonthlyFeedbackStatus extends Command
{
    protected $signature = 'feedback:reset-monthly-status';

    protected $description = 'Reset feedback status from true to false for feedback created this month';

    public function handle(): int
    {
        $count = Feedback::query()
            ->where('status', true)
            ->whereBetween('created_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ])
            ->update([
                'status' => false,
            ]);

        $this->info("{$count} feedback record(s) updated.");

        return self::SUCCESS;
    }
}
