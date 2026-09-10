<?php

namespace App\Services\Logging;

use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;
use Throwable;

class ContextExtractor
{
    /**
     * Extract complete request context including Account, Device, and Request details.
     *
     * @param Request|null $request
     * @param array $extraContext
     * @return array
     */
    public static function extract(?Request $request = null, array $extraContext = []): array
    {
        $request = $request ?: request();

        $account = self::extractAccount($request);
        $device = self::extractDevice($request);
        $reqDetails = self::extractRequestDetails($request);

        return [
            'account' => $account,
            'device' => $device,
            'request' => $reqDetails,
            'environment' => config('app.env', 'production'),
            'timestamp' => now()->toIso8601String(),
            'extra' => self::sanitizeArray($extraContext),
        ];
    }

    /**
     * Extract authenticated user / account information.
     *
     * @param Request|null $request
     * @return array
     */
    public static function extractAccount(?Request $request = null): array
    {
        try {
            $user = null;

            if ($request && method_exists($request, 'user') && $request->user()) {
                $user = $request->user();
            } elseif (auth('api')->check()) {
                $user = auth('api')->user();
            } elseif (auth()->check()) {
                $user = auth()->user();
            }

            if (!$user) {
                return [
                    'authenticated' => false,
                    'summary' => 'Guest / Unauthenticated',
                    'user_id' => null,
                    'name' => null,
                    'email' => null,
                    'role' => null,
                    'status' => 'Guest',
                ];
            }

            $role = $user->role ?? null;
            if (empty($role) && method_exists($user, 'getRoleNames')) {
                $role = $user->getRoleNames()->implode(', ');
            }

            $statusBadges = [];
            if (isset($user->is_verified)) {
                $statusBadges[] = $user->is_verified ? 'Verified' : 'Unverified';
            }
            if (!empty($user->is_blacklisted)) {
                $statusBadges[] = '⚠️ Blacklisted';
            }

            $summary = sprintf(
                '%s (ID: %s, %s)',
                $user->name ?? 'User',
                $user->id,
                $user->email ?? 'No email'
            );

            return [
                'authenticated' => true,
                'summary' => $summary,
                'user_id' => $user->id,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
                'role' => $role ?: 'User',
                'country' => $user->country ?? null,
                'currency' => $user->base_currency ?? null,
                'status' => !empty($statusBadges) ? implode(' | ', $statusBadges) : 'Active',
                'auth_device' => $user->auth_device ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'authenticated' => false,
                'summary' => 'Error resolving account: ' . $e->getMessage(),
                'user_id' => null,
            ];
        }
    }

    /**
     * Extract client device, OS, browser, IP, location, and mobile headers.
     *
     * @param Request|null $request
     * @return array
     */
    public static function extractDevice(?Request $request = null): array
    {
        if (!$request) {
            return [
                'summary' => 'CLI / Console Execution',
                'platform' => 'CLI',
                'os' => PHP_OS,
                'client' => 'Artisan / Cron',
                'ip' => '127.0.0.1',
                'location' => 'Localhost',
            ];
        }

        $userAgent = $request->header('X-Client-User-Agent')
            ?: $request->header('X-User-Agent')
            ?: $request->header('X-Original-User-Agent')
            ?: ($request->userAgent() ?: 'Unknown User-Agent');
        $ip = self::extractClientIp($request);
        $parsed = self::parseUserAgent($userAgent);

        // Extract custom client headers passed by mobile apps / web frontends
        $customPlatform = $request->header('X-Platform') ?: $request->header('Platform');
        $customDeviceId = $request->header('X-Device-Id') ?: $request->header('Device-Id');
        $customAppVersion = $request->header('X-App-Version') ?: $request->header('App-Version');
        $customDeviceModel = $request->header('X-Device-Model') ?: $request->header('Device-Model');
        $customDeviceOS = $request->header('X-Device-OS') ?: $request->header('Device-OS');
        $customDeviceName = $request->header('X-Device-Name') ?: $request->header('Device-Name');

        $platform = $customPlatform ?: $parsed['os'];
        $osVersion = $customDeviceOS ?: $parsed['os_version'];
        $client = $customAppVersion ? "App v{$customAppVersion}" : $parsed['browser'];
        $model = $customDeviceModel ?: $parsed['device_type'];

        // Build a concise device summary string
        $summaryParts = [];
        if ($customDeviceName) {
            $summaryParts[] = $customDeviceName;
        } elseif ($customDeviceModel) {
            $summaryParts[] = $customDeviceModel;
        } else {
            $summaryParts[] = $platform . ($osVersion ? " {$osVersion}" : '');
        }

        if ($client) {
            $summaryParts[] = "({$client})";
        }

        $summary = implode(' ', array_filter($summaryParts)) ?: $userAgent;

        $location = self::extractLocation($ip);

        return [
            'summary' => $summary,
            'platform' => $platform,
            'os' => $parsed['os'],
            'os_version' => $osVersion,
            'browser' => $parsed['browser'],
            'device_type' => $parsed['device_type'],
            'device_id' => $customDeviceId,
            'device_model' => $customDeviceModel,
            'device_name' => $customDeviceName,
            'app_version' => $customAppVersion,
            'ip' => $ip,
            'location' => $location,
            'user_agent' => $userAgent,
        ];
    }

    /**
     * Extract request details with sanitized payload.
     *
     * @param Request|null $request
     * @return array
     */
    public static function extractRequestDetails(?Request $request = null): array
    {
        if (!$request) {
            return [
                'method' => 'CLI',
                'url' => 'console',
                'route' => 'artisan',
                'ip' => '127.0.0.1',
            ];
        }

        $routeAction = 'N/A';
        $routeName = 'N/A';

        try {
            if ($route = $request->route()) {
                $routeAction = $route->getActionName() ?: 'Closure';
                $routeName = $route->getName() ?: 'unnamed';
            }
        } catch (Throwable $e) {
            // Ignore route resolution error during early middleware/boot
        }

        return [
            'method' => strtoupper($request->method()),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'route_action' => $routeAction,
            'route_name' => $routeName,
            'origin' => $request->header('origin') ?: $request->header('referer') ?: 'N/A',
            'query' => self::sanitizeArray($request->query()),
            'payload' => self::sanitizeArray($request->except(config('teams.scrub_fields', []))),
        ];
    }

    /**
     * Extract client IP with proxy and Cloudflare support.
     *
     * @param Request $request
     * @return string
     */
    public static function extractClientIp(Request $request): string
    {
        // 1. Check custom forwarded client header (from BFF web frontend)
        if ($clientIp = $request->header('X-Client-IP')) {
            $ip = trim(explode(',', $clientIp)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // 2. Check Cloudflare connecting IP
        if ($cfIp = $request->header('CF-Connecting-IP')) {
            $ip = trim(explode(',', $cfIp)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // 3. Check True-Client-IP (Cloudflare Enterprise / Akamai)
        if ($trueIp = $request->header('True-Client-IP')) {
            $ip = trim(explode(',', $trueIp)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // 4. Check X-Real-IP
        if ($realIp = $request->header('X-Real-IP')) {
            $ip = trim(explode(',', $realIp)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // 5. Check X-Forwarded-For (first IP in chain is original client)
        if ($forwarded = $request->header('X-Forwarded-For')) {
            $ip = trim(explode(',', $forwarded)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $request->ip() ?: 'Unknown IP';
    }

    /**
     * Extract geographical location for an IP address.
     *
     * @param string $ip
     * @return string
     */
    public static function extractLocation(string $ip): string
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost', 'Unknown IP']) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false) {
            return 'Localhost / Private Network';
        }

        try {
            if (class_exists(Location::class)) {
                $position = Location::get($ip);
                if ($position) {
                    $parts = array_filter([
                        $position->cityName ?? null,
                        $position->regionName ?? null,
                        $position->countryName ?? null,
                    ]);
                    if (!empty($parts)) {
                        return implode(', ', $parts);
                    }
                }
            }
        } catch (Throwable $e) {
            // Silently fall through
        }

        return 'Unknown Location';
    }

    /**
     * Parse User-Agent string to extract OS, Browser, and Device Type.
     *
     * @param string $userAgent
     * @return array
     */
    public static function parseUserAgent(string $userAgent): array
    {
        $os = 'Unknown OS';
        $osVersion = '';
        $browser = 'Unknown Client';
        $deviceType = 'Desktop';

        // Check for common API clients first
        if (stripos($userAgent, 'PostmanRuntime') !== false) {
            return ['os' => 'Postman Client', 'os_version' => '', 'browser' => 'Postman', 'device_type' => 'API Client'];
        }
        if (stripos($userAgent, 'Dart') !== false || stripos($userAgent, 'Flutter') !== false) {
            return ['os' => 'Mobile App', 'os_version' => '', 'browser' => 'Flutter/Dart', 'device_type' => 'Mobile'];
        }
        if (stripos($userAgent, 'axios') !== false || stripos($userAgent, 'GuzzleHttp') !== false || stripos($userAgent, 'curl') !== false) {
            return ['os' => 'HTTP Client', 'os_version' => '', 'browser' => 'HTTP Client', 'device_type' => 'API Client'];
        }

        // Detect Operating System
        if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            $os = 'iOS';
            $deviceType = stripos($userAgent, 'iPad') !== false ? 'Tablet' : 'Mobile';
            if (preg_match('/OS ([\d_]+)/i', $userAgent, $matches)) {
                $osVersion = str_replace('_', '.', $matches[1]);
            }
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
            $deviceType = stripos($userAgent, 'Mobile') !== false ? 'Mobile' : 'Tablet';
            if (preg_match('/Android ([\d.]+)/i', $userAgent, $matches)) {
                $osVersion = $matches[1];
            }
        } elseif (preg_match('/Windows NT ([\d.]+)/i', $userAgent, $matches)) {
            $os = 'Windows';
            $ntMap = [
                '10.0' => '10/11',
                '6.3' => '8.1',
                '6.2' => '8',
                '6.1' => '7',
            ];
            $osVersion = $ntMap[$matches[1]] ?? $matches[1];
            $deviceType = 'Desktop';
        } elseif (preg_match('/Macintosh|Mac OS X ([\d_]+)/i', $userAgent, $matches)) {
            $os = 'macOS';
            $osVersion = isset($matches[1]) ? str_replace('_', '.', $matches[1]) : '';
            $deviceType = 'Desktop';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
            $deviceType = 'Desktop';
        }

        // Detect Browser
        if (preg_match('/Edg(?:e)?\/([\d.]+)/i', $userAgent, $matches)) {
            $browser = 'Microsoft Edge ' . explode('.', $matches[1])[0];
        } elseif (preg_match('/Chrome\/([\d.]+)/i', $userAgent, $matches) && stripos($userAgent, 'Chromium') === false) {
            $browser = 'Chrome ' . explode('.', $matches[1])[0];
        } elseif (preg_match('/Firefox\/([\d.]+)/i', $userAgent, $matches)) {
            $browser = 'Firefox ' . explode('.', $matches[1])[0];
        } elseif (preg_match('/Version\/([\d.]+).*Safari/i', $userAgent, $matches)) {
            $browser = 'Safari ' . explode('.', $matches[1])[0];
        } elseif (preg_match('/Opera|OPR\/([\d.]+)/i', $userAgent, $matches)) {
            $browser = 'Opera ' . (isset($matches[1]) ? explode('.', $matches[1])[0] : '');
        }

        return [
            'os' => $os,
            'os_version' => $osVersion,
            'browser' => $browser,
            'device_type' => $deviceType,
        ];
    }

    /**
     * Recursively sanitize sensitive keys from an array.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function sanitizeArray($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $scrubList = config('teams.scrub_fields', [
            'password', 'password_confirmation', 'current_password', 'token',
            'access_token', 'refresh_token', 'secret', 'authorization', 'pin',
            'cvv', 'card_number', 'bvn', 'nin', 'private_key', 'client_secret'
        ]);

        $scrubMap = array_fill_keys(array_map('strtolower', $scrubList), true);

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = is_string($key) ? strtolower($key) : $key;

            if (isset($scrubMap[$lowerKey])) {
                $sanitized[$key] = '******** [REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = method_exists($value, 'toArray') ? self::sanitizeArray($value->toArray()) : (string) $value;
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
