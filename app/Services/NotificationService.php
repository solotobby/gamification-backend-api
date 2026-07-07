<?php

namespace App\Services;

use App\Events\NotificationEvent;
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


    public function createNotification($user, $title, $body, $type)
    {
        try {
            $this->notifRepo->createForUser($user->id, $title, $body, $type);

            // $tokens = User::where('id', $user->id)->whereNotNull('fcm_token')->pluck('fcm_token');
            $tokens = User::where('id', $user->id)->whereNotNull('fcm_token')->value('fcm_token');

            if ($tokens) {
                $this->firebase->send($tokens, $title, $body);
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function createPublicNotification($userId, $title, $body, $type)
    {
        try {
            // $this->notifRepo->createForUser($user->id, $title, $body, $type);

            // $tokens = User::where('id', $user->id)->whereNotNull('fcm_token')->pluck('fcm_token');
            $tokens = User::where('id', $userId)->whereNotNull('fcm_token')->value('fcm_token');

            if ($tokens) {
                $this->firebase->send($tokens, $title, $body);
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

    // Admin: broadcast to all users via Firebase
    public function broadcastToAll(string $title, string $body, string $type = 'general')
    {
        try {
            $this->notifRepo->createBroadcast($title, $body, $type);

            $tokens = User::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();
            if ($tokens) {
                $this->firebase->sendToMultiple($tokens, $title, $body);
            }

            return response()->json([
                'status' => true,
                'message' => 'Broadcast sent.'
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
