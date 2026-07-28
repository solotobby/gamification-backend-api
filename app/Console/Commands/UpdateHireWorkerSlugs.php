<?php

namespace App\Console\Commands;

use App\Models\SkillAsset;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateHireWorkerSlugs extends Command
{
    protected $signature = 'slugs:update-hire-workers
                            {--chunk=500 : Rows to process per chunk}
                            {--force : Regenerate slugs even for rows that already have one}
                            {--dry-run : Preview without writing to the database}';

    protected $description = 'Backfill or regenerate slug (name + title) on skill_assets for large datasets.';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $force     = (bool) $this->option('force');
        $dryRun    = (bool) $this->option('dry-run');

        $query = SkillAsset::query()->with('user:id,name');

        if (!$force) {
            $query->whereNull('slug');
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to update. All rows already have a slug.');
            return self::SUCCESS;
        }

        $this->info(($force ? 'Regenerating' : 'Backfilling') . " slugs for {$total} rows in chunks of {$chunkSize}...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $failed  = 0;

        $query->orderBy('id')->chunkById($chunkSize, function ($skillAssets) use (&$updated, &$failed, $dryRun, $bar) {
            foreach ($skillAssets as $skillAsset) {
                $userName = $skillAsset->user->name ?? 'worker';
                $baseSlug = Str::slug($userName . ' ' . $skillAsset->title) ?: 'worker';
                $slug     = $baseSlug;
                $suffix   = 1;

                // Retry on unique-constraint collision instead of preloading every slug in memory.
                while (true) {
                    try {
                        if (!$dryRun) {
                            DB::table('skill_assets')
                                ->where('id', $skillAsset->id)
                                ->update(['slug' => $slug]);
                        }
                        $updated++;
                        break;
                    } catch (QueryException $e) {
                        if ($e->getCode() === '23000') { // duplicate unique slug
                            $slug = "{$baseSlug}-{$suffix}";
                            $suffix++;

                            if ($suffix > 50) {
                                $failed++;
                                $this->error("\nCould not generate a unique slug for SkillAsset #{$skillAsset->id} after 50 attempts.");
                                break;
                            }
                            continue;
                        }
                        throw $e;
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Updated: {$updated}" . ($failed ? ", Failed: {$failed}" : '') . ($dryRun ? ' (dry run — no changes written)' : ''));

        return self::SUCCESS;
    }
}
