<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixTransactionTxType extends Command
{
    protected $signature = 'transactions:fix-tx-type';
    protected $description = 'Fix tx_type for payment_transactions';

    public function handle()
    {
        $debitTypes = [
            'ad_banner',
            'added_more_worker',
            'airtime_purchase',
            'campaign_posted',
            'cash_withdrawal',
            'databundle',
            'edit_campaign_payment',
            'point_purchase',
            'safelock_created',
            'upgrade_payment',
            'upgrade_payment_naira_dollar',
            'wallet_debit'
        ];

        $this->info("Setting debit tx_type...");

        DB::table('payment_transactions')
            ->whereIn('type', $debitTypes)
            ->update(['tx_type' => 'debit']);

        $this->info("Setting credit tx_type...");

        DB::table('payment_transactions')
            ->whereNotIn('type', $debitTypes)
            ->update(['tx_type' => 'credit']);

        $this->info("Completed.");
        return Command::SUCCESS;
    }
}
