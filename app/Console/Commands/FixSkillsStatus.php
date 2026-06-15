<?php

namespace App\Console\Commands;

use App\Models\SkillAsset;
use Illuminate\Console\Command;

class FixSkillsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-skills-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing skills status...');

        $skills = SkillAsset::where('status', 'active')
            ->where('created_at', '>=', now()->subDays(60))->get();

        $this->info('Skills to be updated: ' . $skills->count());

        foreach ($skills as $skill) {
            $skill->status = 'pending';
            $skill->save();
            $this->info("Updated skill ID {$skill->id} to pending");
        }

        $this->info('Skills status fix completed.');
    }
}
