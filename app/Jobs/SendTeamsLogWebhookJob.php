<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTeamsLogWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 10;
    public int $backoff = 5;

    public function __construct(
        public string $webhookUrl,
        public array $payload
    ) {}

    public function handle(): void
    {
        if (empty($this->webhookUrl) || empty($this->payload)) {
            return;
        }

        try {
            $timeout = (int) config('teams.timeout', 5);
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->webhookUrl, $this->payload);

            if (!$response->successful()) {
                Log::channel('single')->warning(sprintf(
                    'Queued Teams webhook returned non-200 status: %d - %s',
                    $response->status(),
                    $response->body()
                ));
            }
        } catch (Throwable $e) {
            Log::channel('single')->warning('Failed to dispatch Queued Teams webhook: ' . $e->getMessage());
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('single')->warning('SendTeamsLogWebhookJob exhausted retries: ' . $exception->getMessage());
    }
}
