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

class SendFirebaseNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 5;

    public function __construct(
        public string $fcmToken,
        public string $title,
        public string $body,
        public array $data = []
    ) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        if (empty($this->fcmToken)) {
            return;
        }

        $firebase->send($this->fcmToken, $this->title, $this->body, $this->data);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendFirebaseNotificationJob failed permanently', [
            'token' => $this->fcmToken,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
