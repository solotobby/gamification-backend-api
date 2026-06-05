<?php

namespace App\Console\Commands;

use App\Jobs\FixReferralCreatedAt;
use Illuminate\Console\Command;

class FixReferralCreatedAtCommand extends Command
{
   protected $signature = 'referral:fix-created-at';
protected $description = 'Fix null created_at in referral table from referred user created_at';

public function handle(): void
{
    $this->info('Fixing referral created_at...');
    FixReferralCreatedAt::dispatchSync();
    $this->info('Done! Check logs for details.');
}
}
