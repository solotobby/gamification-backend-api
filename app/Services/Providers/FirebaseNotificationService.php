<?php

namespace App\Services\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
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
        } catch (NotFound $e) {
            Log::warning('FCM token not registered or expired. Removing stale token.', [
                'token' => $fcmToken,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            $this->pruneFcmToken($fcmToken);

            return false;
        } catch (InvalidMessage|InvalidArgument $e) {
            Log::warning('FCM token or message payload is invalid. Removing invalid token.', [
                'token' => $fcmToken,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            $this->pruneFcmToken($fcmToken);

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM send failed', [
                'token'   => $fcmToken,
                'title'   => $title,
                'error'   => $e->getMessage(),
            ]);
            teamsError($e, [
                'service' => 'Firebase Push Notification',
                'title'   => $title,
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

            $report = $this->messaging->sendMulticast($message, $tokens);

            if ($report->hasFailures()) {
                $unknownTokens = $report->unknownTokens();
                $invalidTokens = $report->invalidTokens();
                $staleTokens = array_unique(array_merge($unknownTokens, $invalidTokens));

                if (!empty($staleTokens)) {
                    Log::warning('FCM multicast encountered stale or invalid tokens. Removing from database.', [
                        'count'  => count($staleTokens),
                        'tokens' => $staleTokens,
                    ]);
                    $this->pruneFcmTokens($staleTokens);
                }
            }

            return true;
        } catch (NotFound $e) {
            Log::warning('FCM multicast token not registered. Removing tokens.', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
            $this->pruneFcmTokens($tokens);

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM multicast failed', [
                'tokens'  => $tokens,
                'title'   => $title,
                'error'   => $e->getMessage(),
            ]);
            teamsError($e, [
                'service' => 'Firebase Multicast Push Notification',
                'title'   => $title,
                'recipient_count' => count($tokens),
            ]);

            return false;
        }
    }

    public function sendAsync(string $fcmToken, string $title, string $body, array $data = []): void
    {
        \App\Jobs\SendFirebaseNotificationJob::dispatch($fcmToken, $title, $body, $data);
    }

    public function sendToMultipleAsync(array $tokens, string $title, string $body, array $data = []): void
    {
        \App\Jobs\SendFirebaseMulticastNotificationJob::dispatch($tokens, $title, $body, $data);
    }

    /**
     * Remove a stale or invalid FCM token from the database.
     */
    public function pruneFcmToken(string $fcmToken): void
    {
        if (empty($fcmToken)) {
            return;
        }

        try {
            User::where('fcm_token', $fcmToken)->update(['fcm_token' => null]);
        } catch (\Throwable $e) {
            Log::error('Failed to prune stale FCM token', [
                'token' => $fcmToken,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove multiple stale or invalid FCM tokens from the database.
     */
    public function pruneFcmTokens(array $tokens): void
    {
        if (empty($tokens)) {
            return;
        }

        try {
            User::whereIn('fcm_token', $tokens)->update(['fcm_token' => null]);
        } catch (\Throwable $e) {
            Log::error('Failed to prune stale FCM tokens', [
                'tokens' => $tokens,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}

