<?php

namespace App\Console\Commands;

use App\Models\CareerProfile;
use App\Services\CareerProfileService;
use Illuminate\Console\Command;

class RecalculateCareerProfileScores extends Command
{
    protected $signature = 'career-profiles:recalculate-scores
                            {--id= : Recalculate a specific career profile ID only}';

    protected $description = 'Recalculate profile completeness and talent scores for career profiles';

    public function handle(CareerProfileService $service): int
    {
        $profileId = $this->option('id');

        if ($profileId) {
            $profile = CareerProfile::find($profileId);
            if (!$profile) {
                $this->error("Career profile with ID {$profileId} not found.");
                return self::FAILURE;
            }

            $service->recalculate($profile);
            $profile->refresh();

            $this->info("Profile #{$profile->id} recalculated: Completeness={$profile->profile_completeness}%, Talent Score={$profile->talent_score}/100");
            return self::SUCCESS;
        }

        $total = CareerProfile::count();
        $this->info("Recalculating {$total} career profiles...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        CareerProfile::chunkById(100, function ($profiles) use ($service, $bar) {
            foreach ($profiles as $profile) {
                $service->recalculate($profile);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("All career profiles recalculated successfully.");

        return self::SUCCESS;
    }
}
