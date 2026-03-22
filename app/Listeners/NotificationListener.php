<?php

namespace App\Listeners;

use App\Events\NotificationEvent;
use App\Models\AppNotification;
use App\Services\Providers\FirebaseNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotificationListener implements ShouldQueue
{
    public function __construct(protected FirebaseNotificationService $firebase) {}

    public function handle(NotificationEvent $event): void
    {
        // 1. Save in-app notification
        AppNotification::create([
            'user_id' => $event->user->id,
            'title'   => $event->title,
            'body'    => $event->body,
            'type'    => $event->type,
            'data'    => $event->data,
        ]);

        // 2. Firebase push notification
        if ($event->sendFirebase && $event->user->fcm_token) {
            $this->firebase->send(
                $event->user->fcm_token,
                $event->title,
                $event->body,
                $event->data
            );
        }

        // 3. Email (if requested — expand per template later)
        if ($event->sendEmail && $event->emailTemplate) {
            // will hook in email service here
        }
    }
}
