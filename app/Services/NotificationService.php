<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Jobs\SendFirebaseMulticastNotificationJob;
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\User;
use App\Repositories\NotificationRepositoryModel;
use App\Services\Providers\FirebaseNotificationService;
use Throwable;

class NotificationService
{
    public function __construct(
        protected NotificationRepositoryModel $notifRepo,
        protected FirebaseNotificationService $firebase,
    ) {}


    public function createNotification($user, $title, $body, $type, array $data = [])
    {
        try {
            $userId = is_object($user) ? $user->id : (int) $user;
            $this->notifRepo->createForUser($userId, $title, $body, $type, $data);

            $token = is_object($user) && !empty($user->fcm_token)
                ? $user->fcm_token
                : User::where('id', $userId)->whereNotNull('fcm_token')->value('fcm_token');

            if ($token) {
                SendFirebaseNotificationJob::dispatch($token, $title, $body, array_merge(['type' => (string) $type], $data));
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function createPublicNotification($userId, $title, $body, $type, array $data = [])
    {
        try {
            $token = User::where('id', $userId)->whereNotNull('fcm_token')->value('fcm_token');

            if ($token) {
                SendFirebaseNotificationJob::dispatch($token, $title, $body, array_merge(['type' => (string) $type], $data));
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
    public function getUserNotifications()
    {
        try {
            $user = auth()->user();
            $notifications = $this->notifRepo->getUserNotifications($user->id);

            return response()->json([
                'status'      => true,
                'message'     => 'Notifications retrieved.',
                'unread'      => $this->notifRepo->unreadCount($user->id),
                'data'        => $notifications->items(),
                'pagination' => $this->buildPagination($notifications),

            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching notifications.'
            ], 500);
        }
    }

    public function markRead(int $notificationId)
    {
        $this->notifRepo->markOneRead($notificationId, auth()->id());
        return response()->json([
            'status' => true,
            'message' => 'Marked as read.'
        ]);
    }

    public function markAllRead()
    {
        $this->notifRepo->markAllRead(auth()->id());
        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read.'
        ]);
    }

    // Admin: broadcast to all users via Firebase in queued chunks
    public function broadcastToAll(string $title, string $body, string $type = 'general', array $data = [])
    {
        try {
            $this->notifRepo->createBroadcast($title, $body, $type);

            User::whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->chunk(500, function ($users) use ($title, $body, $type, $data) {
                    $tokens = $users->pluck('fcm_token')->filter()->values()->toArray();
                    if (!empty($tokens)) {
                        SendFirebaseMulticastNotificationJob::dispatch($tokens, $title, $body, array_merge(['type' => (string) $type], $data));
                    }
                });

            return response()->json([
                'status' => true,
                'message' => 'Broadcast queued successfully.'
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Broadcast failed.'
            ], 500);
        }
    }

    public function updateFcmToken(string $token)
    {

        auth()->user()->update(['fcm_token' => $token]);
        return response()->json([
            'status' => true,
            'message' => 'FCM token updated.'
        ]);
    }

    private function buildPagination($paginator)
    {
        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ];
    }
}
