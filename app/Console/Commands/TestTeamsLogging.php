<?php

namespace App\Console\Commands;

use App\Services\Logging\TeamsLoggerService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestTeamsLogging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:test 
                            {--type=info : Type of test to run: info, error, warning, exception, monolog}
                            {--webhook= : Optional webhook URL to test directly}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test notification card to Microsoft Teams to verify webhook integration and formatting';

    /**
     * Execute the console command.
     */
    public function handle(TeamsLoggerService $teamsService): int
    {
        $type = strtolower($this->option('type') ?: 'info');
        $customWebhook = $this->option('webhook');

        if ($customWebhook) {
            config(['teams.webhook_url' => $customWebhook]);
            config(['teams.error_webhook_url' => $customWebhook]);
            config(['teams.info_webhook_url' => $customWebhook]);
        }

        $defaultWebhook = config('teams.webhook_url');
        $errorWebhook = config('teams.error_webhook_url');
        $infoWebhook = config('teams.info_webhook_url');

        $this->info('=============================================');
        $this->info('   Microsoft Teams Logging Verification      ');
        $this->info('=============================================');
        $this->line(sprintf('Enabled:          <comment>%s</comment>', config('teams.enabled') ? 'true' : 'false'));
        $this->line(sprintf('Default Webhook:  <comment>%s</comment>', $defaultWebhook ? $this->maskWebhook($defaultWebhook) : '[NOT CONFIGURED]'));
        $this->line(sprintf('Error Webhook:    <comment>%s</comment>', $errorWebhook ? $this->maskWebhook($errorWebhook) : '[Using Default]'));
        $this->line(sprintf('Info Webhook:     <comment>%s</comment>', $infoWebhook ? $this->maskWebhook($infoWebhook) : '[Using Default]'));
        $this->line(sprintf('Running Test:     <fg=cyan>%s</>', strtoupper($type)));
        $this->newLine();

        if (empty($defaultWebhook) && empty($errorWebhook) && empty($infoWebhook)) {
            $this->warn('⚠️  No TEAMS_WEBHOOK_URL is configured in your .env file.');
            $this->line('To test live delivery to your Teams channel, set TEAMS_WEBHOOK_URL in .env, or use --webhook option:');
            $this->line('  php artisan teams:test --webhook="https://outlook.office.com/webhook/..."');
            $this->newLine();
        }

        $this->comment('Dispatching test card to Microsoft Teams...');

        $context = [
            'test_mode' => true,
            'triggered_by' => 'Artisan CLI Command',
            'sample_metadata' => [
                'service' => 'Gamification API',
                'version' => '1.0.0',
                'action' => 'Verification Ping',
            ],
        ];

        $success = false;

        switch ($type) {
            case 'error':
                $success = $teamsService->sendError('Sample API error triggered via teams:test command', $context);
                break;

            case 'warning':
                $success = $teamsService->sendWarning('Sample warning: high memory usage or degraded provider response detected.', $context);
                break;

            case 'exception':
                try {
                    throw new Exception('Simulated test exception: Database query timed out on replica connection.');
                } catch (Exception $e) {
                    $success = $teamsService->sendError($e, $context);
                }
                break;

            case 'monolog':
                Log::channel('teams')->info('Test message dispatched via Log::channel("teams")', $context);
                $success = true;
                break;

            case 'info':
            default:
                $success = $teamsService->sendInfo('Test info notification: Microsoft Teams logger successfully integrated with API.', $context);
                break;
        }

        if ($success) {
            $this->info('✅ Test card successfully dispatched to Microsoft Teams!');
            return Command::SUCCESS;
        } else {
            if (empty($defaultWebhook) && empty($errorWebhook)) {
                $this->line('ℹ️ Dispatch skipped or webhook not reachable (no active webhook URL configured).');
            } else {
                $this->error('❌ Failed to dispatch card to Teams. Check storage/logs/laravel.log for HTTP details.');
            }
            return Command::SUCCESS;
        }
    }

    /**
     * Mask webhook URL for safe console display.
     *
     * @param string $url
     * @return string
     */
    protected function maskWebhook(string $url): string
    {
        if (strlen($url) <= 30) {
            return $url;
        }

        return substr($url, 0, 30) . '...' . substr($url, -8);
    }
}
