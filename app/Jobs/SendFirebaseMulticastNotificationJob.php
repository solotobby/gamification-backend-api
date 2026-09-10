<?php

namespace App\Jobs;

use App\Services\Providers\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFirebaseMulticastNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 10;

    public function __construct(
        public array $tokens,
        public string $title,
        public string $body,
        public array $data = []
    ) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        if (empty($this->tokens)) {
            return;
        }

        // Firebase multicast supports up to 500 tokens per request
        $chunks = array_chunk($this->tokens, 500);
        foreach ($chunks as $chunk) {
            $firebase->sendToMultiple($chunk, $this->title, $this->body, $this->data);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendFirebaseMulticastNotificationJob failed permanently', [
            'token_count' => count($this->tokens),
            'title'       => $this->title,
            'error'       => $exception->getMessage(),
        ]);
    }
}
