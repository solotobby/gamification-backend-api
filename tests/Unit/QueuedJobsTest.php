<?php

namespace Tests\Unit;

use App\Jobs\SendFirebaseMulticastNotificationJob;
use App\Jobs\SendFirebaseNotificationJob;
use App\Jobs\SendTeamsLogWebhookJob;
use App\Services\Providers\FirebaseNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class QueuedJobsTest extends TestCase
{
    public function test_send_firebase_notification_job_handles_correctly()
    {
        $mockFirebase = Mockery::mock(FirebaseNotificationService::class);
        $mockFirebase->shouldReceive('send')
            ->once()
            ->with('sample_fcm_token_123', 'Test Notification', 'Hello world body', ['type' => 'wallet'])
            ->andReturn(true);

        $job = new SendFirebaseNotificationJob('sample_fcm_token_123', 'Test Notification', 'Hello world body', ['type' => 'wallet']);
        $job->handle($mockFirebase);

        $this->assertTrue(true);
    }

    public function test_send_firebase_multicast_notification_job_handles_correctly()
    {
        $mockFirebase = Mockery::mock(FirebaseNotificationService::class);
        $mockFirebase->shouldReceive('sendToMultiple')
            ->once()
            ->with(['token_1', 'token_2'], 'Bulk Notification', 'Broadcast message', ['type' => 'broadcast'])
            ->andReturn(true);

        $job = new SendFirebaseMulticastNotificationJob(['token_1', 'token_2'], 'Bulk Notification', 'Broadcast message', ['type' => 'broadcast']);
        $job->handle($mockFirebase);

        $this->assertTrue(true);
    }

    public function test_send_teams_log_webhook_job_handles_http_post()
    {
        Http::fake([
            'https://webhook.teams.office.com/*' => Http::response(['status' => 1], 200),
        ]);

        $payload = ['title' => 'Test Log Card', 'text' => 'Job execution test'];
        $job = new SendTeamsLogWebhookJob('https://webhook.teams.office.com/webhook-endpoint', $payload);
        $job->handle();

        Http::assertSent(function ($request) use ($payload) {
            return $request->url() === 'https://webhook.teams.office.com/webhook-endpoint'
                && json_decode($request->body(), true) == $payload;
        });
    }
}
