<?php

namespace App\Console\Commands;

use App\Jobs\FixReferralData;
use Illuminate\Console\Command;

class FixReferralDataCommand extends Command
{
    protected $signature = 'referral:fix';
    protected $description = 'Fix referral table: replace referral_code in referee_id with actual user ID and fix created_at';

    public function handle(): void
    {
        $this->info('Starting referral data fix...');

        FixReferralData::dispatchSync();

        $this->info('Done! Check logs for details.');
    }
}
