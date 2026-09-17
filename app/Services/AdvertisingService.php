<?php

namespace App\Services;

use App\Models\AdAnalyticsEvent;
use App\Models\AdCode;
use App\Models\AdPlacement;
use App\Models\AdvertisingConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdvertisingService
{
    /**
     * Get active advertising configuration for Web / Mobile App clients.
     */
    public function getPublicConfig(Request $request): JsonResponse
    {
        $platform = strtoupper($request->query('platform', $request->header('X-Platform', 'WEB')));
        $device = strtoupper($request->query('device', 'ALL'));
        $pageType = $request->query('page_type');

        // Normalize platform
        if (in_array($platform, ['ANDROID', 'ANDROID_APP'])) {
            $platformKey = 'ANDROID_APP';
        } elseif (in_array($platform, ['IOS', 'IOS_APP'])) {
            $platformKey = 'IOS_APP';
        } else {
            $platformKey = 'WEB';
        }

        // Version cache key based on latest DB timestamps so changes from admin (gamification app) reflect instantly
        $version = (string) (AdvertisingConfig::max('updated_at') . '_' . AdPlacement::max('updated_at') . '_' . AdCode::max('updated_at'));
        $cacheKey = "ad_config_{$version}_{$platformKey}_{$device}_" . ($pageType ?: 'all');

        $configData = Cache::remember($cacheKey, 300, function () use ($platformKey, $device, $pageType) {
            $config = AdvertisingConfig::current();

            // Emergency Global Kill Switch
            if ($config->status !== 'ACTIVE') {
                return [
                    'enabled' => false,
                    'provider' => 'adsterra',
                    'platform' => strtolower($platformKey),
                    'placements' => [],
                    'codes' => [],
                    'max_ads_per_session' => 0,
                ];
            }

            // Platform check
            if ($platformKey === 'WEB' && !$config->web_enabled) {
                return [
                    'enabled' => false,
                    'provider' => 'adsterra',
                    'platform' => 'web',
                    'placements' => [],
                    'codes' => [],
                ];
            }

            if (in_array($platformKey, ['ANDROID_APP', 'IOS_APP']) && !$config->mobile_app_enabled) {
                return [
                    'enabled' => false,
                    'provider' => 'adsterra',
                    'platform' => strtolower($platformKey),
                    'placements' => [],
                    'codes' => [],
                ];
            }

            // Fetch active codes
            $codes = AdCode::where('is_active', true)->get()->keyBy(function ($item) {
                return $item->ad_format . '_' . $item->device;
            });

            // Fetch active placements
            $query = AdPlacement::query()
                ->where('enabled', true)
                ->whereIn('platform', ['ALL', $platformKey]);

            if ($device !== 'ALL') {
                $query->whereIn('device', ['ALL', $device]);
            }

            if ($pageType) {
                $query->where('page_type', strtoupper($pageType));
            }

            $placements = $query->orderBy('priority', 'asc')->get();

            $formattedPlacements = [];
            foreach ($placements as $placement) {
                // Check if specific format is globally enabled
                $formatKey = strtolower($placement->ad_format);
                if ($formatKey === 'native_banner' && !$config->native_enabled) continue;
                if ($formatKey === 'standard_banner' && !$config->banner_enabled) continue;
                if ($formatKey === 'social_bar' && !$config->social_bar_enabled) continue;
                if ($formatKey === 'interstitial' && !$config->interstitial_enabled) continue;
                if ($formatKey === 'popunder' && !$config->popunder_enabled) continue;
                if ($formatKey === 'smartlink' && !$config->smartlink_enabled) continue;

                // Resolve snippet code
                $snippet = $placement->code;
                if (empty($snippet)) {
                    // Fallback to format code
                    $formatCodeKey = $placement->ad_format . '_' . $placement->device;
                    $formatCodeAllKey = $placement->ad_format . '_ALL';
                    $snippet = $codes[$formatCodeKey]->code ?? ($codes[$formatCodeAllKey]->code ?? '');
                }

                $formattedPlacements[] = [
                    'key' => $placement->placement_key,
                    'page_type' => $placement->page_type,
                    'format' => strtolower($placement->ad_format),
                    'device' => strtolower($placement->device),
                    'platform' => strtolower($placement->platform),
                    'position' => $placement->position,
                    'display_interval' => $placement->display_interval,
                    'frequency_cap' => $placement->frequency_cap,
                    'priority' => $placement->priority,
                    'enabled' => (bool) $placement->enabled,
                    'code' => $snippet,
                ];
            }

            return [
                'enabled' => true,
                'provider' => 'adsterra',
                'platform' => strtolower($platformKey),
                'max_ads_per_session' => $config->max_ads_per_session,
                'formats' => [
                    'native' => (bool) $config->native_enabled,
                    'banner' => (bool) $config->banner_enabled,
                    'social_bar' => (bool) $config->social_bar_enabled,
                    'interstitial' => (bool) $config->interstitial_enabled,
                    'popunder' => (bool) $config->popunder_enabled,
                    'smartlink' => (bool) $config->smartlink_enabled,
                ],
                'placements' => $formattedPlacements,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Advertising configuration retrieved successfully.',
            'data' => $configData,
        ]);
    }

    /**
     * Record client advertising events (telemetry/analytics).
     */
    public function recordEvent(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'event_name' => 'required|string|in:ad_requested,ad_loaded,ad_failed,ad_rendered,ad_viewed,ad_clicked',
                'placement_key' => 'required|string|max:100',
                'page_type' => 'required|string|max:50',
                'ad_format' => 'nullable|string|max:50',
                'ad_provider' => 'nullable|string|max:50',
                'page_url' => 'nullable|string|max:500',
                'device_type' => 'nullable|string|max:20',
                'platform' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:10',
                'anonymous_session_id' => 'nullable|string|max:100',
            ]);

            AdAnalyticsEvent::create([
                'event_name' => $data['event_name'],
                'user_id' => auth('api')->id() ?? auth()->id() ?? null,
                'anonymous_session_id' => $data['anonymous_session_id'] ?? $request->header('X-Session-ID'),
                'page_type' => strtoupper($data['page_type']),
                'page_url' => $data['page_url'] ?? null,
                'ad_provider' => strtoupper($data['ad_provider'] ?? 'ADSTERRA'),
                'ad_format' => strtoupper($data['ad_format'] ?? 'NATIVE_BANNER'),
                'placement_key' => strtoupper($data['placement_key']),
                'device_type' => strtolower($data['device_type'] ?? 'desktop'),
                'platform' => strtolower($data['platform'] ?? 'web'),
                'country' => $data['country'] ?? null,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Ad event logged successfully.',
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log ad event: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to record event.',
            ], 422);
        }
    }
}
