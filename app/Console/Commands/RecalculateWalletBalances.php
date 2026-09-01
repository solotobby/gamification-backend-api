<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateWalletBalances extends Command
{
    protected $signature = 'wallet:recalculate-balances
                            {--chunk=5000 : Number of users to process per batch}
                            {--report : Show a mismatch report after recalculating}
                            {--tolerance=0.01 : Difference threshold to flag as a mismatch in the report}';

    protected $description = 'Recompute each user\'s balance from their payment_transactions history into wallets.temp_balance, for verification against the live balance — does NOT touch the live balance/usd_balance/bonus columns.';

    // Mirrors WalletRepositoryModel::mapCurrency() exactly — must stay in
    // sync if that method ever changes, since this has to normalize the
    // same "naira"/"NGN" and "dollar"/"USD" variants it does.
    protected string $currencyMapSql = "
        CASE
            WHEN LOWER(currency_col) IN ('naira', 'ngn') THEN 'NGN'
            WHEN LOWER(currency_col) IN ('dollar', 'usd') THEN 'USD'
            ELSE UPPER(currency_col)
        END
    ";

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $minId = DB::table('wallets')->min('id');
        $maxId = DB::table('wallets')->max('id');

        if (!$minId) {
            $this->info('No wallets found.');
            return self::SUCCESS;
        }

        $this->info("Recalculating balances for wallets #{$minId}–#{$maxId} in batches of {$chunkSize}...");
        $bar = $this->output->createProgressBar((int) ceil(($maxId - $minId + 1) / $chunkSize));
        $bar->start();

        for ($start = $minId; $start <= $maxId; $start += $chunkSize) {
            $end = min($start + $chunkSize - 1, $maxId);
            $this->recalculateBatch($start, $end);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Recalculation complete.');

        if ($this->option('report')) {
            $this->showMismatchReport((float) $this->option('tolerance'));
        }

        return self::SUCCESS;
    }

    /**
     * One UPDATE...JOIN per batch. tx_type is compared case-insensitively
     * (LOWER()) since 'Credit'/'credit' and 'Debit'/'debit' both exist in
     * this table historically — see conversation notes on this. Currency
     * matching normalizes both the wallet's base_currency and the
     * transaction's currency through the same mapping WalletRepositoryModel
     * uses, so 'naira'/'NGN' and 'dollar'/'USD' legacy values reconcile
     * correctly against each other.
     */
    protected function recalculateBatch(int $startId, int $endId): void
    {
        $wCurrency = str_replace('currency_col', 'w.base_currency', $this->currencyMapSql);
        $ptCurrency = str_replace('currency_col', 'pt.currency', $this->currencyMapSql);

        DB::statement("
            UPDATE wallets w
            LEFT JOIN (
                SELECT
                    pt.user_id,
                    SUM(
                        CASE
                            WHEN LOWER(pt.tx_type) = 'credit' THEN pt.amount
                            WHEN LOWER(pt.tx_type) = 'debit'  THEN -pt.amount
                            ELSE 0
                        END
                    ) AS computed_balance
                FROM payment_transactions pt
                INNER JOIN wallets w2 ON w2.user_id = pt.user_id
                WHERE pt.status = 'successful'
                  AND w2.id BETWEEN ? AND ?
                  AND {$ptCurrency} = {$wCurrency}
                GROUP BY pt.user_id
            ) totals ON totals.user_id = w.user_id
            SET
                w.temp_balance = COALESCE(totals.computed_balance, 0),
                w.temp_balance_calculated_at = NOW()
            WHERE w.id BETWEEN ? AND ?
        ", [$startId, $endId, $startId, $endId]);
    }

    /**
     * Compares temp_balance against whichever live column
     * getBalanceFromWallet() actually reads for that wallet's currency
     * today (balance for NGN, usd_balance for USD, bonus for everything
     * else) — matching the live debit/credit code path, not the unused
     * base_currency_balance column.
     */
    protected function showMismatchReport(float $tolerance): void
    {
        $liveBalanceSql = "
            CASE
                WHEN LOWER(base_currency) IN ('naira', 'ngn') THEN balance
                WHEN LOWER(base_currency) IN ('dollar', 'usd') THEN usd_balance
                ELSE bonus
            END
        ";

        $mismatches = DB::table('wallets')
            ->selectRaw("
                user_id,
                base_currency,
                temp_balance,
                {$liveBalanceSql} as live_balance,
                ABS(temp_balance - ({$liveBalanceSql})) as diff
            ")
            ->whereNotNull('temp_balance')
            ->havingRaw('diff > ?', [$tolerance])
            ->orderByDesc('diff')
            ->limit(50)
            ->get();

        $totalMismatchCount = DB::table('wallets')
            ->selectRaw("1")
            ->whereNotNull('temp_balance')
            ->havingRaw("ABS(temp_balance - ({$liveBalanceSql})) > ?", [$tolerance])
            ->get()
            ->count();

        $this->newLine();
        $this->warn("Found {$totalMismatchCount} wallet(s) with a discrepancy > {$tolerance}.");

        if ($mismatches->isNotEmpty()) {
            $this->table(
                ['User ID', 'Currency', 'Live Balance', 'Computed (temp_balance)', 'Diff'],
                $mismatches->map(fn($m) => [
                    $m->user_id,
                    $m->base_currency,
                    number_format($m->live_balance, 2),
                    number_format($m->temp_balance, 2),
                    number_format($m->diff, 2),
                ])
            );

            if ($totalMismatchCount > 50) {
                $this->line('(showing top 50 by size of discrepancy)');
            }
        }
    }
}