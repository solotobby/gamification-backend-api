<?php

namespace App\Console\Commands;

use App\Models\VirtualAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SoftDeletePaystackVirtualAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:soft-delete-paystack-virtual-accounts
                            {--user= : Specific User ID to soft delete Paystack virtual account for}
                            {--chunk=1000 : Number of records to process per batch}
                            {--dry-run : Preview records that will be affected without deleting}
                            {--force : Force execution without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Soft delete Paystack virtual accounts in optimized batches (suitable for 50k+ records) so users can generate new virtual accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId    = $this->option('user');
        $chunkSize = max(100, (int) $this->option('chunk'));
        $dryRun    = (bool) $this->option('dry-run');
        $force     = (bool) $this->option('force');

        $baseQuery = VirtualAccount::where('channel', 'paystack');

        if ($userId) {
            $baseQuery->where('user_id', $userId);
        }

        $count = $baseQuery->count();

        if ($count === 0) {
            $targetDesc = $userId ? "for user ID {$userId}" : "on the system";
            $this->info("No active Paystack virtual accounts found {$targetDesc}.");
            return Command::SUCCESS;
        }

        $formattedCount = number_format($count);
        $this->info("Found {$formattedCount} active Paystack virtual account(s)" . ($userId ? " for user ID {$userId}." : "."));

        // Show a preview sample
        $sampleRecords = (clone $baseQuery)->limit(10)->get(['id', 'user_id', 'bank_name', 'account_name', 'account_number', 'channel', 'created_at']);
        
        $tableRows = $sampleRecords->map(function ($va) {
            return [
                'ID'             => $va->id,
                'User ID'        => $va->user_id,
                'Bank Name'      => $va->bank_name ?? 'N/A',
                'Account Name'   => $va->account_name ?? 'N/A',
                'Account Number' => $va->account_number ?? 'N/A',
                'Channel'        => $va->channel ?? 'N/A',
                'Created At'     => $va->created_at ? $va->created_at->format('Y-m-d H:i:s') : 'N/A',
            ];
        })->toArray();

        $this->table(['ID', 'User ID', 'Bank Name', 'Account Name', 'Account Number', 'Channel', 'Created At'], $tableRows);

        if ($count > 10) {
            $this->comment("... and " . number_format($count - 10) . " more records.");
        }

        if ($dryRun) {
            $this->warn("\n[DRY RUN] Would soft delete {$formattedCount} Paystack virtual account(s) in batches of {$chunkSize}. No changes were made.");
            return Command::SUCCESS;
        }

        if (!$force && !$this->confirm("\nAre you sure you want to soft delete {$formattedCount} Paystack virtual account(s)?", false)) {
            $this->info('Operation cancelled by user.');
            return Command::SUCCESS;
        }

        $this->info("\nStarting batch soft deletion in chunks of {$chunkSize}...");
        $startTime = microtime(true);
        $deletedCount = 0;

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- Elapsed: %elapsed:6s% -- %message%');
        $progressBar->setMessage('Processing...');
        $progressBar->start();

        $now = now()->toDateTimeString();

        // Process in indexed chunkById batches for high performance and zero memory bloat
        VirtualAccount::where('channel', 'paystack')
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->chunkById($chunkSize, function ($accounts) use (&$deletedCount, $progressBar, $now) {
                $ids = $accounts->pluck('id')->toArray();
                
                DB::table('virtual_accounts')
                    ->whereIn('id', $ids)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);

                $batchCount = count($ids);
                $deletedCount += $batchCount;
                $progressBar->advance($batchCount);
            });

        $progressBar->setMessage('Completed');
        $progressBar->finish();
        $this->newLine(2);

        $duration = round(microtime(true) - $startTime, 2);
        $rate = $duration > 0 ? round($deletedCount / $duration, 0) : $deletedCount;

        $this->info("✓ Successfully soft-deleted " . number_format($deletedCount) . " Paystack virtual account(s) in {$duration}s (~{$rate} rows/sec).");
        $this->info("Affected users can now immediately generate new virtual accounts (Interswitch / Flutterwave) via the dashboard or wallet page.");

        Log::channel('single')->info("Batch soft-deleted {$deletedCount} Paystack virtual accounts.", [
            'user_id'  => $userId ?? 'ALL',
            'count'    => $deletedCount,
            'duration' => "{$duration}s",
        ]);

        return Command::SUCCESS;
    }
}
