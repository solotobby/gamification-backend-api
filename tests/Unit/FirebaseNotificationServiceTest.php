<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Providers\FirebaseNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Mockery;
use Tests\TestCase;

class FirebaseNotificationServiceTest extends TestCase
{
    public function test_send_handles_not_found_by_pruning_token_and_not_throwing()
    {
        $mockMessaging = Mockery::mock(Messaging::class);
        $mockMessaging->shouldReceive('send')
            ->once()
            ->andThrow(NotFound::becauseTokenNotFound('expired_fcm_token_123'));

        $service = Mockery::mock(FirebaseNotificationService::class, [$mockMessaging])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('pruneFcmToken')
            ->once()
            ->with('expired_fcm_token_123');

        $result = $service->send('expired_fcm_token_123', 'Test Title', 'Test Body');

        $this->assertFalse($result);
    }
}
