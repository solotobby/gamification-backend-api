<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Http;

class FirebaseNotificationService
{
    protected string $serverKey;
    protected string $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct()
    {
        $this->serverKey = config('services.firebase.server_key');
    }

    public function send(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        $payload = [
            'to'           => $fcmToken,
            'notification' => ['title' => $title, 'body' => $body],
            'data'         => $data,
        ];

        $res = Http::withHeaders([
            'Authorization' => 'key=' . $this->serverKey,
            'Content-Type'  => 'application/json',
        ])->post($this->fcmUrl, $payload);

        return $res->successful();
    }

    public function sendToMultiple(array $tokens, string $title, string $body, array $data = []): bool
    {
        $payload = [
            'registration_ids' => $tokens,
            'notification'     => ['title' => $title, 'body' => $body],
            'data'             => $data,
        ];

        $res = Http::withHeaders([
            'Authorization' => 'key=' . $this->serverKey,
            'Content-Type'  => 'application/json',
        ])->post($this->fcmUrl, $payload);

        return $res->successful();
    }
}
