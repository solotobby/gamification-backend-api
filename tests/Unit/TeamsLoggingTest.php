<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Logging\ContextExtractor;
use App\Services\Logging\TeamsLoggerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeamsLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'teams.enabled' => true,
            'teams.webhook_url' => 'https://mock.teams.webhook.url/test',
            'teams.error_webhook_url' => 'https://mock.teams.webhook.url/errors',
            'teams.info_webhook_url' => 'https://mock.teams.webhook.url/info',
        ]);
    }

    public function test_context_extractor_extracts_guest_account()
    {
        $request = Request::create('/api/v1/jobs', 'GET');
        $context = ContextExtractor::extract($request);

        $this->assertFalse($context['account']['authenticated']);
        $this->assertEquals('Guest / Unauthenticated', $context['account']['summary']);
        $this->assertEquals('GET', $context['request']['method']);
    }

    public function test_context_extractor_extracts_device_and_headers()
    {
        $request = Request::create('/api/v1/wallet/transfer', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15',
            'HTTP_X_PLATFORM' => 'iOS',
            'HTTP_X_DEVICE_ID' => 'device-uuid-12345',
            'HTTP_X_APP_VERSION' => '2.4.0',
            'HTTP_X_DEVICE_MODEL' => 'iPhone 14 Pro',
        ]);

        $context = ContextExtractor::extract($request);
        $device = $context['device'];

        $this->assertEquals('iOS', $device['platform']);
        $this->assertEquals('device-uuid-12345', $device['device_id']);
        $this->assertEquals('2.4.0', $device['app_version']);
        $this->assertEquals('iPhone 14 Pro', $device['device_model']);
        $this->assertStringContainsString('iPhone', $device['summary']);
    }

    public function test_context_extractor_extracts_forwarded_web_client_headers()
    {
        $request = Request::create('/api/campaign', 'POST', [], [], [], [
            'REMOTE_ADDR' => '138.68.185.34', // Proxy/BFF server IP
            'HTTP_USER_AGENT' => 'GuzzleHttp/7',
            'HTTP_X_CLIENT_IP' => '102.89.43.12',
            'HTTP_X_CLIENT_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'HTTP_X_PLATFORM' => 'Web',
        ]);

        $context = ContextExtractor::extract($request);
        $device = $context['device'];

        $this->assertEquals('102.89.43.12', $device['ip']);
        $this->assertEquals('Web', $device['platform']);
        $this->assertEquals('Windows', $device['os']);
        $this->assertStringContainsString('Chrome', $device['browser']);
        $this->assertStringContainsString('Chrome', $device['summary']);
    }

    public function test_context_extractor_scrubs_sensitive_data()
    {
        $data = [
            'email' => 'user@example.com',
            'password' => 'SuperSecret123!',
            'token' => 'jwt.token.here',
            'authorization' => 'Bearer eyJhbGciOi...',
            'card_number' => '4111111111111111',
            'nested' => [
                'pin' => '1234',
                'amount' => 5000,
            ],
        ];

        $sanitized = ContextExtractor::sanitizeArray($data);

        $this->assertEquals('user@example.com', $sanitized['email']);
        $this->assertEquals('******** [REDACTED]', $sanitized['password']);
        $this->assertEquals('******** [REDACTED]', $sanitized['token']);
        $this->assertEquals('******** [REDACTED]', $sanitized['authorization']);
        $this->assertEquals('******** [REDACTED]', $sanitized['card_number']);
        $this->assertEquals('******** [REDACTED]', $sanitized['nested']['pin']);
        $this->assertEquals(5000, $sanitized['nested']['amount']);
    }

    public function test_teams_logger_service_dispatches_error_card()
    {
        Http::fake([
            'https://mock.teams.webhook.url/errors*' => Http::response(['status' => 1], 200),
        ]);

        $request = Request::create('/api/v1/auth/login', 'POST', [
            'email' => 'test@example.com',
            'password' => 'secret_pass',
        ]);

        $service = new TeamsLoggerService();
        $exception = new Exception('Database connection lost during login');

        $result = $service->sendError($exception, ['attempt' => 1], $request);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);
            return $data['@type'] === 'MessageCard'
                && $data['themeColor'] === 'E81123'
                && str_contains($data['title'], 'Exception');
        });
    }

    public function test_teams_logger_service_dispatches_info_card()
    {
        Http::fake([
            'https://mock.teams.webhook.url/info*' => Http::response(['status' => 1], 200),
        ]);

        $service = new TeamsLoggerService();
        $result = $service->sendInfo('Wallet credited successfully', ['amount' => 10000, 'currency' => 'NGN']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true);
            return $data['@type'] === 'MessageCard'
                && $data['themeColor'] === '107C41'
                && str_contains($data['title'], 'Wallet credited');
        });
    }

    public function test_global_helper_functions_work_without_throwing()
    {
        TeamsLoggerService::resetDispatchedSignatures();
        Http::fake([
            '*' => Http::response(['status' => 1], 200),
        ]);

        $this->assertTrue(teamsLog('Testing teamsLog helper'));
        $this->assertTrue(teamsInfo('Testing teamsInfo helper'));
        $this->assertTrue(teamsWarning('Testing teamsWarning helper'));
        $this->assertTrue(teamsError('Testing teamsError helper'));
    }

    public function test_ignored_exceptions_are_skipped()
    {
        TeamsLoggerService::resetDispatchedSignatures();
        Http::fake([
            '*' => Http::response(['status' => 1], 200),
        ]);

        $service = new TeamsLoggerService();
        $ignoredException = new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Page not found');

        $result = $service->sendError($ignoredException);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_duplicate_errors_in_same_request_are_deduplicated()
    {
        TeamsLoggerService::resetDispatchedSignatures();
        Http::fake([
            'https://mock.teams.webhook.url/errors*' => Http::response(['status' => 1], 200),
        ]);

        $service = new TeamsLoggerService();
        $exception = new Exception('Unique error message for deduplication test');

        $firstResult = $service->sendError($exception);
        $secondResult = $service->sendError($exception);

        $this->assertTrue($firstResult);
        $this->assertTrue($secondResult);

        // Webhook should only have been sent once!
        Http::assertSentCount(1);
    }
}
