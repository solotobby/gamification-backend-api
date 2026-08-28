<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCareerProfileViewsCount extends Command
{
    protected $signature = 'career-profiles:sync-views-count
                            {--dry-run : Show what would be updated without writing anything}';

    protected $description = 'Backfill career_profiles.views_count from the existing profile_views event log';

    public function handle(): int
    {
        $this->info('Computing view counts from profile_views…');

        // One pass, driven entirely by the existing (career_profile_id, action)
        // index on profile_views — far cheaper than looping per-profile.
        $counts = DB::table('profile_views')
            ->where('action', 'view')
            ->selectRaw('career_profile_id, COUNT(*) as cnt')
            ->groupBy('career_profile_id')
            ->get();

        if ($counts->isEmpty()) {
            $this->info('No view records found — nothing to backfill.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry run — {$counts->count()} profile(s) would be updated:");
            foreach ($counts->sortByDesc('cnt')->take(10) as $row) {
                $this->line(" - profile #{$row->career_profile_id}: {$row->cnt} views");
            }
            if ($counts->count() > 10) {
                $this->line(' - … and ' . ($counts->count() - 10) . ' more');
            }
            return self::SUCCESS;
        }

        $updated = 0;

        DB::transaction(function () use ($counts, &$updated) {
            foreach ($counts->chunk(500) as $chunk) {
                $cases = $chunk->map(fn($row) => "WHEN {$row->career_profile_id} THEN {$row->cnt}")->implode(' ');
                $ids = $chunk->pluck('career_profile_id')->implode(',');

                DB::statement("
                    UPDATE career_profiles
                    SET views_count = CASE id {$cases} END
                    WHERE id IN ({$ids})
                ");

                $updated += $chunk->count();
            }
        });

        $this->info("Done — updated views_count for {$updated} profile(s).");

        return self::SUCCESS;
    }
}
