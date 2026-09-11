<?php

namespace App\Services\Logging;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TeamsLoggerService
{
    /**
     * Theme colors for Teams MessageCards by log level.
     */
    protected const THEME_COLORS = [
        'emergency' => '7A0000', // Dark Maroon
        'alert'     => 'A80000', // Crimson
        'critical'  => 'D83B01', // Bright Red
        'error'     => 'E81123', // Red
        'warning'   => 'FF8C00', // Amber / Orange
        'notice'    => 'FFB900', // Yellow
        'info'      => '107C41', // Teams Green
        'success'   => '107C41', // Green
        'debug'     => '0078D4', // Teams Blue
    ];

    /**
     * Log level icons.
     */
    protected const LEVEL_ICONS = [
        'emergency' => '🚨🚨 [EMERGENCY]',
        'alert'     => '🚨 [ALERT]',
        'critical'  => '💥 [CRITICAL]',
        'error'     => '🛑 [ERROR]',
        'warning'   => '⚠️ [WARNING]',
        'notice'    => '🔔 [NOTICE]',
        'info'      => 'ℹ️ [INFO]',
        'success'   => '✅ [SUCCESS]',
        'debug'     => '🔍 [DEBUG]',
    ];

    /**
     * Cache of dispatched signatures in current lifecycle to prevent duplicate sends.
     */
    protected static array $dispatchedSignatures = [];

    /**
     * Reset dispatched signatures cache (useful for tests and long-running workers).
     */
    public static function resetDispatchedSignatures(): void
    {
        self::$dispatchedSignatures = [];
    }

    /**
     * Send an error / exception card to Microsoft Teams.
     *
     * @param Throwable|string $error
     * @param array $context
     * @param Request|null $request
     * @return bool
     */
    public function sendError($error, array $context = [], ?Request $request = null): bool
    {
        // Check ignored exceptions (e.g. 404, validation errors, auth exceptions)
        if (is_object($error)) {
            foreach (config('teams.ignored_exceptions', []) as $ignoredClass) {
                if ($error instanceof $ignoredClass) {
                    return false;
                }
            }
        }

        $exceptionClass = is_object($error) ? get_class($error) : 'Error';
        $message = is_object($error) ? $error->getMessage() : (string) $error;
        $file = is_object($error) ? $error->getFile() : null;
        $line = is_object($error) ? $error->getLine() : null;
        $trace = is_object($error) ? $error->getTraceAsString() : null;

        // Deduplication: prevent sending identical error cards within the same request lifecycle
        $signature = 'error:' . md5($exceptionClass . ':' . $message . ':' . ($file ?? '') . ':' . ($line ?? ''));
        if (isset(self::$dispatchedSignatures[$signature])) {
            return true;
        }
        self::$dispatchedSignatures[$signature] = true;

        $request = $request ?: (app()->runningInConsole() ? null : request());
        $extracted = ContextExtractor::extract($request, $context);

        $env = strtoupper($extracted['environment']);
        $title = sprintf('%s [%s] %s', self::LEVEL_ICONS['error'], $env, $exceptionClass);

        $facts = $this->buildStandardFacts($extracted);

        if ($file && $line) {
            $facts[] = [
                'name' => 'File Location',
                'value' => sprintf('`%s:%d`', $this->cleanFilePath($file), $line),
            ];
        }

        $sections = [
            [
                'activityTitle' => $title,
                'activitySubtitle' => sprintf('**Message:** %s', $message ?: 'No error message provided'),
                'facts' => $facts,
                'markdown' => true,
            ]
        ];

        // Add sanitized Request Payload section if present
        if (!empty($extracted['request']['payload'])) {
            $sections[] = [
                'title' => '📦 Request Payload',
                'text' => sprintf("```json\n%s\n```", json_encode($extracted['request']['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                'markdown' => true,
            ];
        }

        // Add Extra Context section if present
        if (!empty($extracted['extra'])) {
            $sections[] = [
                'title' => '📝 Additional Context',
                'text' => sprintf("```json\n%s\n```", json_encode($extracted['extra'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                'markdown' => true,
            ];
        }

        // Add Stack Trace preview section (truncated safely)
        if ($trace) {
            $traceSnippet = $this->truncateStackTrace($trace, 1500);
            $sections[] = [
                'title' => '🔍 Stack Trace Preview',
                'text' => sprintf("```text\n%s\n```", $traceSnippet),
                'markdown' => true,
            ];
        }

        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => sprintf('Error in %s: %s', $env, $message),
            'themeColor' => self::THEME_COLORS['error'],
            'title' => $title,
            'sections' => $sections,
        ];

        return $this->dispatch('error', $payload);
    }

    /**
     * Send an informational card to Microsoft Teams.
     *
     * @param string $message
     * @param array $context
     * @param Request|null $request
     * @return bool
     */
    public function sendInfo(string $message, array $context = [], ?Request $request = null): bool
    {
        return $this->sendLog('info', $message, $context, $request);
    }

    /**
     * Send a warning card to Microsoft Teams.
     *
     * @param string $message
     * @param array $context
     * @param Request|null $request
     * @return bool
     */
    public function sendWarning(string $message, array $context = [], ?Request $request = null): bool
    {
        return $this->sendLog('warning', $message, $context, $request);
    }

    /**
     * Send a generalized log card by level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @param Request|null $request
     * @return bool
     */
    public function sendLog(string $level, string $message, array $context = [], ?Request $request = null): bool
    {
        $level = strtolower($level);

        // Deduplication: prevent sending identical log cards within the same request lifecycle
        $signature = 'log:' . md5($level . ':' . $message . ':' . json_encode($context));
        if (isset(self::$dispatchedSignatures[$signature])) {
            return true;
        }
        self::$dispatchedSignatures[$signature] = true;

        $request = $request ?: (app()->runningInConsole() ? null : request());
        $extracted = ContextExtractor::extract($request, $context);

        $env = strtoupper($extracted['environment']);
        $icon = self::LEVEL_ICONS[$level] ?? self::LEVEL_ICONS['info'];
        $title = sprintf('%s [%s] %s', $icon, $env, $this->truncateString($message, 120));

        $facts = $this->buildStandardFacts($extracted);

        $sections = [
            [
                'activityTitle' => $title,
                'activitySubtitle' => $message,
                'facts' => $facts,
                'markdown' => true,
            ]
        ];

        if (!empty($extracted['extra'])) {
            $sections[] = [
                'title' => '📝 Log Context',
                'text' => sprintf("```json\n%s\n```", json_encode($extracted['extra'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                'markdown' => true,
            ];
        }

        $themeColor = self::THEME_COLORS[$level] ?? self::THEME_COLORS['info'];

        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => sprintf('%s: %s', strtoupper($level), $message),
            'themeColor' => $themeColor,
            'title' => $title,
            'sections' => $sections,
        ];

        return $this->dispatch($level, $payload);
    }

    /**
     * Build the standard facts list for Teams MessageCards.
     *
     * @param array $extracted
     * @return array
     */
    protected function buildStandardFacts(array $extracted): array
    {
        $account = $extracted['account'];
        $device = $extracted['device'];
        $req = $extracted['request'];

        $facts = [];

        // 1. Account Fact
        if (!empty($account['authenticated'])) {
            $accountValue = sprintf(
                '**%s** (ID: `%s`)<br/>📧 %s | 🏷️ `%s` | %s',
                $account['name'] ?: 'User',
                $account['user_id'],
                $account['email'] ?: 'N/A',
                $account['role'] ?: 'User',
                $account['status']
            );
        } else {
            $accountValue = '👤 `Guest / Unauthenticated`';
        }
        $facts[] = ['name' => '👤 Account', 'value' => $accountValue];

        // 2. Device Fact
        $deviceValue = sprintf(
            '**%s**<br/>💻 `%s` | 🌐 `%s`',
            $device['summary'],
            $device['platform'] ?: 'Unknown Platform',
            $device['browser'] ?: 'Unknown Browser'
        );
        if (!empty($device['device_id']) || !empty($device['app_version'])) {
            $deviceValue .= sprintf('<br/>📱 App: `%s` | ID: `%s`', $device['app_version'] ?: 'N/A', $device['device_id'] ?: 'N/A');
        }
        $facts[] = ['name' => '📱 Device & Client', 'value' => $deviceValue];

        // 3. IP & Geo Location Fact
        $ipLocationValue = sprintf('`%s` (%s)', $device['ip'], $device['location']);
        $facts[] = ['name' => '🌍 IP & Location', 'value' => $ipLocationValue];

        // 4. Request Endpoint & Method Fact
        if ($req['method'] !== 'CLI') {
            $facts[] = [
                'name' => '🔗 Endpoint',
                'value' => sprintf('`%s` %s', $req['method'], $req['url']),
            ];
            if (!empty($req['route_action']) && $req['route_action'] !== 'N/A') {
                $facts[] = [
                    'name' => '🎯 Route Action',
                    'value' => sprintf('`%s`', $req['route_action']),
                ];
            }
        } else {
            $facts[] = ['name' => '⚙️ Execution', 'value' => 'Artisan CLI / Command'];
        }

        // 5. Environment & Timestamp Fact
        $facts[] = [
            'name' => '⏰ Time & Env',
            'value' => sprintf('%s (`%s`)', now()->format('Y-m-d H:i:s T'), $extracted['environment']),
        ];

        return $facts;
    }

    /**
     * Dispatch the payload to the appropriate Teams webhook URL.
     *
     * @param string $level
     * @param array $payload
     * @return bool
     */
    protected function dispatch(string $level, array $payload): bool
    {
        if (!config('teams.enabled', true)) {
            return false;
        }

        $webhookUrl = $this->resolveWebhookUrl($level);

        if (empty($webhookUrl)) {
            return false;
        }

        if (config('teams.queue', false)) {
            \App\Jobs\SendTeamsLogWebhookJob::dispatch($webhookUrl, $payload);
            return true;
        }

        try {
            $timeout = (int) config('teams.timeout', 3);
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($webhookUrl, $payload);

            if (!$response->successful()) {
                Log::channel('single')->warning(sprintf(
                    'Teams logging webhook returned non-200 status: %d - %s',
                    $response->status(),
                    $response->body()
                ));
                return false;
            }

            return true;
        } catch (Throwable $e) {
            // Fail-safe: never crash the application if Teams logging fails
            Log::channel('single')->warning('Failed to dispatch Teams log webhook: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve the webhook URL based on the log level.
     *
     * @param string $level
     * @return string|null
     */
    protected function resolveWebhookUrl(string $level): ?string
    {
        $isErrorLevel = in_array(strtolower($level), ['error', 'critical', 'alert', 'emergency']);

        if ($isErrorLevel) {
            return config('teams.error_webhook_url') ?: config('teams.webhook_url');
        }

        return config('teams.info_webhook_url') ?: config('teams.webhook_url');
    }

    /**
     * Clean relative file paths for readability.
     *
     * @param string $filePath
     * @return string
     */
    protected function cleanFilePath(string $filePath): string
    {
        $base = base_path();
        return str_replace([$base . DIRECTORY_SEPARATOR, $base . '/'], '', $filePath);
    }

    /**
     * Truncate stack trace cleanly to fit inside Teams message limits.
     *
     * @param string $trace
     * @param int $maxLength
     * @return string
     */
    protected function truncateStackTrace(string $trace, int $maxLength = 1500): string
    {
        if (strlen($trace) <= $maxLength) {
            return $trace;
        }

        return substr($trace, 0, $maxLength) . "\n... [Remaining stack trace truncated for Teams]";
    }

    /**
     * Truncate string with ellipsis.
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    protected function truncateString(string $string, int $length = 120): string
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length - 3) . '...';
    }
}
