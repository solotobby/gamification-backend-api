<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateWalletBalances extends Command
{
    protected $signature = 'wallet:recalculate-balances
                            {--chunk=5000 : Number of wallets to process per batch}
                            {--report : Show a mismatch report after recalculating}
                            {--tolerance=0.01 : Difference threshold to flag as a mismatch in the report}
                            {--user= : Recalculate a single user_id only, ignoring --chunk}';

    protected $description = 'Recompute each user\'s balance from their own payment_transactions history, matched strictly against their wallet\'s own base_currency, into wallets.temp_balance for verification against the live balance — does NOT touch balance/usd_balance/base_currency_balance.';

    // Mirrors WalletRepositoryModel::mapCurrency() exactly — keep in sync
    // if that method ever changes, since this normalizes the same
    // "naira"/"NGN" and "dollar"/"USD" legacy variants it does, for both
    // the wallet's base_currency and the transaction's currency.
    protected string $currencyMapSql = "
        CASE
            WHEN LOWER(currency_col) IN ('naira', 'ngn') THEN 'NGN'
            WHEN LOWER(currency_col) IN ('dollar', 'usd') THEN 'USD'
            ELSE UPPER(currency_col)
        END
    ";

    public function handle(): int
    {
        if ($userId = $this->option('user')) {
            $this->recalculateSingleUser((int) $userId);
            return self::SUCCESS;
        }

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
     * Batch path — one UPDATE...JOIN per chunk. The join to payment_transactions
     * is strictly scoped per wallet: each wallet's total only sums
     * transactions whose currency, once normalized through the same
     * mapping as the wallet's own base_currency, matches THAT wallet's
     * base_currency — never another user's, and never a different
     * currency than the one the wallet is actually denominated in.
     *
     * tx_type is compared case-insensitively (LOWER()) since 'Credit'/
     * 'credit' and 'Debit'/'debit' both exist historically in this table.
     */
    protected function recalculateBatch(int $startId, int $endId): void
    {
        $wCurrency  = str_replace('currency_col', 'w.base_currency', $this->currencyMapSql);
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
     * Single-user path — plain Eloquent/query builder, no raw SQL needed
     * for one row. Useful for spot-checking a specific user (e.g. from a
     * support ticket) without waiting on a full batch run.
     */
    protected function recalculateSingleUser(int $userId): void
    {
        $wallet = DB::table('wallets')->where('user_id', $userId)->first();

        if (!$wallet) {
            $this->error("No wallet found for user #{$userId}.");
            return;
        }

        $mappedWalletCurrency = $this->mapCurrency($wallet->base_currency);

        $computed = DB::table('payment_transactions')
            ->where('user_id', $userId)
            ->where('status', 'successful')
            ->get()
            ->filter(fn($tx) => $this->mapCurrency($tx->currency) === $mappedWalletCurrency)
            ->sum(fn($tx) => strtolower($tx->tx_type) === 'credit' ? (float) $tx->amount
                : (strtolower($tx->tx_type) === 'debit' ? -(float) $tx->amount : 0));

        DB::table('wallets')->where('user_id', $userId)->update([
            'temp_balance' => $computed,
            'temp_balance_calculated_at' => now(),
        ]);

        $liveBalance = match ($mappedWalletCurrency) {
            'NGN'   => (float) $wallet->balance,
            'USD'   => (float) $wallet->usd_balance,
            default => (float) $wallet->base_currency_balance,
        };

        $this->info("User #{$userId} ({$mappedWalletCurrency}):");
        $this->line("  Live balance:      " . number_format($liveBalance, 2));
        $this->line("  Computed (temp):   " . number_format($computed, 2));
        $this->line("  Difference:        " . number_format(abs($liveBalance - $computed), 2));
    }

    /**
     * Compares temp_balance against whichever live column
     * WalletRepositoryModel::getBalanceFromWallet() actually reads for
     * that wallet's currency today: balance for NGN, usd_balance for USD,
     * base_currency_balance for everything else (GHS/ZAR/KES/etc.) — NOT
     * `bonus`, which is now the streak-bonus pool only, per the earlier
     * migration off bonus.
     */
    protected function showMismatchReport(float $tolerance): void
    {
        $liveBalanceSql = "
            CASE
                WHEN LOWER(base_currency) IN ('naira', 'ngn') THEN balance
                WHEN LOWER(base_currency) IN ('dollar', 'usd') THEN usd_balance
                ELSE base_currency_balance
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

    /**
     * PHP-side mirror of the currency-mapping SQL, used only by the
     * single-user path (plain query builder, no raw SQL string needed
     * for one row).
     */
    protected function mapCurrency(string $currency): string
    {
        return match (strtolower($currency)) {
            'naira', 'ngn' => 'NGN',
            'dollar', 'usd' => 'USD',
            default => strtoupper($currency),
        };
    }
}
