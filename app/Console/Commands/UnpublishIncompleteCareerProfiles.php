<?php

namespace App\Console\Commands;

use App\Models\CareerProfile;
use Illuminate\Console\Command;

class UnpublishIncompleteCareerProfiles extends Command
{
    protected $signature = 'career-profiles:unpublish-incomplete
                            {--threshold=50 : Minimum completeness percentage required to stay public}
                            {--dry-run : List affected profiles without changing anything}';

    protected $description = 'Set is_public to false for any public career profile below the completeness threshold';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $dryRun    = $this->option('dry-run');

        $query = CareerProfile::query()
            ->where('is_public', true)
            ->where('profile_completeness', '<', $threshold);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No public profiles below {$threshold}% completeness.");
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("{$count} profile(s) would be unpublished (dry run, threshold {$threshold}%):");

            $query->select(['id', 'slug', 'profile_completeness'])
                ->orderBy('id')
                ->chunkById(200, function ($profiles) {
                    foreach ($profiles as $p) {
                        $this->line(" - #{$p->id} ({$p->slug}) — {$p->profile_completeness}%");
                    }
                });

            return self::SUCCESS;
        }

        $updated = $query->update(['is_public' => false]);

        $this->info("Unpublished {$updated} profile(s) below {$threshold}% completeness.");

        return self::SUCCESS;
    }
}
