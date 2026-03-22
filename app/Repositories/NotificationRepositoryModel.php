<?php

namespace App\Repositories;

use App\Models\AppNotification;
use App\Models\User;

class NotificationRepositoryModel
{
    public function createForUser(int $userId, string $title, string $body, string $type = 'general', array $data = []): AppNotification
    {
        return AppNotification::create([
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'data'    => $data,
        ]);
    }

    public function createBroadcast(string $title, string $body, string $type = 'general'): AppNotification
    {
        return AppNotification::create([
            'user_id'      => null,
            'title'        => $title,
            'body'         => $body,
            'type'         => $type,
            'is_broadcast' => true,
        ]);
    }

    public function getUserNotifications(int $userId, int $perPage = 20)
    {
        return AppNotification::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('is_broadcast', true);
        })->latest()->paginate($perPage);
    }

    public function markAllRead(int $userId): void
    {
        AppNotification::where('user_id', $userId)->update(['is_read' => true]);
    }

    public function markOneRead(int $notificationId, int $userId): bool
    {
        return (bool) AppNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['is_read' => true]);
    }

    public function unreadCount(int $userId): int
    {
        return AppNotification::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('is_broadcast', true);
        })->where('is_read', false)->count();
    }
}
