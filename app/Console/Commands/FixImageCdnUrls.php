<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FixImageCdnUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:fix-cdn-urls
                            {--from=2026-09-17 : Process records created on or after this date (format: YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)}
                            {--all : Process all records regardless of creation date}
                            {--dry-run : Preview changes without writing to database}
                            {--table= : Process a specific table only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attach the DigitalOcean Spaces CDN URL prefix to relative image and file paths in the database';

    /**
     * Table and column mappings to check for image/file paths.
     */
    protected array $tableColumnMap = [
        'campaign_workers'     => ['proof_url'],
        'campaigns'            => ['expected_result_image'],
        'banners'              => ['banner_url', 'banner_url_mobile'],
        'blogs'                => ['cover_image'],
        'career_profiles'      => ['photo_path', 'cv_file_path'],
        'manual_verifications' => ['proof_image'],
        'feedback'             => ['proof_url'],
        'feedback_replies'     => ['image_url'],
        'tickets'              => ['proof_url'],
        'certifications'       => ['file_path'],
        'job_applications'     => ['resume_path'],
        'job_listings'         => ['company_logo'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cdnUrl = rtrim(env('DO_SPACES_CDN_URL', env('DO_SPACES_ENDPOINT', 'https://freebyzbucket.sfo3.cdn.digitaloceanspaces.com')), '/');

        if (empty($cdnUrl)) {
            $this->error('DO_SPACES_CDN_URL or DO_SPACES_ENDPOINT is not configured.');
            return Command::FAILURE;
        }

        $isDryRun   = (bool) $this->option('dry-run');
        $processAll = (bool) $this->option('all');
        $fromDate   = $processAll ? null : $this->normalizeDate($this->option('from') ?: '2026-09-15');
        $onlyTable  = $this->option('table');

        $this->info('====================================================');
        $this->info('  DigitalOcean Spaces CDN URL Image Path Fixer');
        $this->info('====================================================');
        $this->line("  CDN Base URL : <comment>{$cdnUrl}</comment>");
        $this->line("  Date Filter  : " . ($processAll ? '<comment>ALL RECORDS (No Date Filter)</comment>' : "<comment>>= {$fromDate}</comment>"));
        $this->line("  Execution    : " . ($isDryRun ? '<fg=yellow;options=bold>DRY RUN (No database updates)</fg=yellow;options=bold>' : '<fg=green;options=bold>LIVE UPDATE</fg=green;options=bold>'));
        if ($onlyTable) {
            $this->line("  Target Table : <comment>{$onlyTable}</comment>");
        }
        $this->info('====================================================');
        $this->newLine();

        $summary = [];
        $totalUpdated = 0;

        foreach ($this->tableColumnMap as $table => $columns) {
            if ($onlyTable && $onlyTable !== $table) {
                continue;
            }

            if (!Schema::hasTable($table)) {
                $this->warn("Table '{$table}' does not exist, skipping.");
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $this->warn("Column '{$table}.{$column}' does not exist, skipping.");
                    continue;
                }

                $query = DB::table($table);

                if ($fromDate && Schema::hasColumn($table, 'created_at')) {
                    $query->where('created_at', '>=', $fromDate);
                }

                $records = $query->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->get(['id', $column, ...(Schema::hasColumn($table, 'created_at') ? ['created_at'] : [])]);

                $scannedCount = $records->count();
                $updatedCount = 0;
                $skippedCount = 0;

                foreach ($records as $record) {
                    $currentVal = $record->$column;

                    if ($this->shouldAttachCdn($currentVal)) {
                        $newVal = $cdnUrl . '/' . ltrim($currentVal, '/');
                        $updatedCount++;
                        $totalUpdated++;

                        $this->line("  [<comment>{$table} #{$record->id}</comment>] <info>{$column}</info>");
                        $this->line("    <fg=red>-</fg=red> {$currentVal}");
                        $this->line("    <fg=green>+</fg=green> {$newVal}");

                        if (!$isDryRun) {
                            DB::table($table)
                                ->where('id', $record->id)
                                ->update([$column => $newVal]);
                        }
                    } else {
                        $skippedCount++;
                    }
                }

                $summary[] = [
                    'table'   => $table,
                    'column'  => $column,
                    'scanned' => $scannedCount,
                    'updated' => $updatedCount,
                    'skipped' => $skippedCount,
                ];
            }
        }

        $this->newLine();
        $this->table(
            ['Table', 'Column', 'Scanned', $isDryRun ? 'Would Update' : 'Updated', 'Already OK / Skipped'],
            $summary
        );

        $this->newLine();
        if ($isDryRun) {
            $this->info("Dry run complete. {$totalUpdated} record(s) identified for update. Run without --dry-run to apply changes.");
        } else {
            $this->info("Successfully updated {$totalUpdated} image URL record(s).");
        }

        return Command::SUCCESS;
    }

    /**
     * Check if a value requires attaching the CDN URL.
     */
    protected function shouldAttachCdn(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        $val = trim($value);

        // Skip placeholder strings
        if (in_array(strtolower($val), ['no image', 'no_image', 'null', 'undefined', 'none', 'n/a', ''])) {
            return false;
        }

        // Skip if already an absolute URL or base64 data
        if (Str::startsWith($val, ['http://', 'https://', 'data:image'])) {
            return false;
        }

        // Skip local server temp file paths (e.g. C:\... or /tmp/...)
        if (preg_match('/^[a-zA-Z]:\\\\/', $val) || Str::startsWith($val, ['/tmp/', '/var/tmp/'])) {
            return false;
        }

        return true;
    }

    /**
     * Normalize date input to YYYY-MM-DD HH:MM:SS.
     */
    protected function normalizeDate(string $date): string
    {
        $date = trim($date);
        if (strlen($date) === 10) {
            return "{$date} 00:00:00";
        }
        return $date;
    }
}
