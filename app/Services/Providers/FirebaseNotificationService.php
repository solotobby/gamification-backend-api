<?php

namespace App\Services\Providers;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseNotificationService
{
    protected Messaging $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    public function send(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::new()
                ->toToken($fcmToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);

            return true;
        } catch (\Throwable $e) {
            Log::error('FCM send failed', [
                'token'   => $fcmToken,
                'title'   => $title,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendToMultiple(array $tokens, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->sendMulticast($message, $tokens);

            return true;
        } catch (\Throwable $e) {
            Log::error('FCM multicast failed', [
                'tokens'  => $tokens,
                'title'   => $title,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }
}
