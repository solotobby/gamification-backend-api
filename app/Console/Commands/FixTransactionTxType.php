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
        $this->info("Setting standard debit tx_type...");

        DB::table('payment_transactions')
            ->whereIn('type', \App\Models\PaymentTransaction::DEBIT_TYPES)
            ->update(['tx_type' => 'Debit']);

        $this->info("Setting standard credit tx_type...");

        DB::table('payment_transactions')
            ->whereIn('type', \App\Models\PaymentTransaction::CREDIT_TYPES)
            ->update(['tx_type' => 'Credit']);

        $this->info("Setting bi-directional exchange and conversion tx_types based on description...");

        DB::table('payment_transactions')
            ->whereIn('type', ['naira_dollar_exchange', 'currency_conversion'])
            ->where('description', 'LIKE', '%Debit%')
            ->update(['tx_type' => 'Debit']);

        DB::table('payment_transactions')
            ->whereIn('type', ['naira_dollar_exchange', 'currency_conversion'])
            ->where('description', 'LIKE', '%Credit%')
            ->update(['tx_type' => 'Credit']);

        $this->info("Completed successfully.");
        return Command::SUCCESS;
    }
}
