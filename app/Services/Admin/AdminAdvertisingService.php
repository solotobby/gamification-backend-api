<?php

namespace App\Services\Admin;

use App\Models\AdAnalyticsEvent;
use App\Models\AdCode;
use App\Models\AdPlacement;
use App\Models\AdvertisingConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminAdvertisingService
{
    public function getConfig(): JsonResponse
    {
        $config = AdvertisingConfig::current();
        return response()->json([
            'status' => true,
            'data' => $config,
        ]);
    }

    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:ACTIVE,INACTIVE',
            'web_enabled' => 'required|boolean',
            'mobile_app_enabled' => 'required|boolean',
            'native_enabled' => 'required|boolean',
            'banner_enabled' => 'required|boolean',
            'social_bar_enabled' => 'nullable|boolean',
            'interstitial_enabled' => 'nullable|boolean',
            'popunder_enabled' => 'nullable|boolean',
            'smartlink_enabled' => 'nullable|boolean',
            'max_ads_per_session' => 'required|integer|min:1|max:50',
        ]);

        $config = AdvertisingConfig::current();
        $config->update($validated);

        // Invalidate public caches
        Cache::flush();

        return response()->json([
            'status' => true,
            'message' => 'Advertising configuration updated successfully.',
            'data' => $config,
        ]);
    }

    public function listPlacements(): JsonResponse
    {
        $placements = AdPlacement::orderBy('page_type')
            ->orderBy('priority')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $placements,
        ]);
    }

    public function updatePlacement(Request $request, int $id): JsonResponse
    {
        $placement = AdPlacement::findOrFail($id);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'display_interval' => 'nullable|integer|min:1|max:50',
            'frequency_cap' => 'nullable|integer|min:1|max:20',
            'priority' => 'nullable|integer|min:0|max:100',
            'code' => 'nullable|string',
        ]);

        $placement->update($validated);
        Cache::flush();

        return response()->json([
            'status' => true,
            'message' => "Placement {$placement->placement_key} updated successfully.",
            'data' => $placement,
        ]);
    }

    public function listCodes(): JsonResponse
    {
        $codes = AdCode::all();
        return response()->json([
            'status' => true,
            'data' => $codes,
        ]);
    }

    public function updateCodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codes' => 'required|array',
            'codes.*.id' => 'required|integer|exists:ad_codes,id',
            'codes.*.code' => 'nullable|string',
            'codes.*.is_active' => 'required|boolean',
        ]);

        foreach ($validated['codes'] as $codeData) {
            AdCode::where('id', $codeData['id'])->update([
                'code' => $codeData['code'] ?? '',
                'is_active' => $codeData['is_active'],
            ]);
        }

        Cache::flush();

        return response()->json([
            'status' => true,
            'message' => 'Ad codes updated successfully.',
            'data' => AdCode::all(),
        ]);
    }

    public function getAnalyticsSummary(Request $request): JsonResponse
    {
        $period = $request->query('period', $request->query('days', '7d'));
        $since = null;
        $periodLabel = 'Last 7 Days';

        switch ((string) $period) {
            case '24h':
            case '1':
            case '1d':
                $since = now()->subHours(24);
                $periodLabel = 'Last 24 Hours';
                $periodKey = '24h';
                break;
            case '30':
            case '30d':
                $since = now()->subDays(30);
                $periodLabel = 'Last 30 Days';
                $periodKey = '30d';
                break;
            case '60':
            case '60d':
                $since = now()->subDays(60);
                $periodLabel = 'Last 60 Days';
                $periodKey = '60d';
                break;
            case '90':
            case '90d':
                $since = now()->subDays(90);
                $periodLabel = 'Last 90 Days';
                $periodKey = '90d';
                break;
            case 'all':
                $since = null;
                $periodLabel = 'All Time';
                $periodKey = 'all';
                break;
            case '7':
            case '7d':
            default:
                $since = now()->subDays(7);
                $periodLabel = 'Last 7 Days';
                $periodKey = '7d';
                break;
        }

        $eventsQuery = AdAnalyticsEvent::query();
        $byPageQuery = AdAnalyticsEvent::where('event_name', 'ad_rendered');
        $byPlacementQuery = AdAnalyticsEvent::query();

        if ($since !== null) {
            $eventsQuery->where('created_at', '>=', $since);
            $byPageQuery->where('created_at', '>=', $since);
            $byPlacementQuery->where('created_at', '>=', $since);
        }

        $eventsSummary = $eventsQuery
            ->select('event_name', DB::raw('count(*) as count'))
            ->groupBy('event_name')
            ->pluck('count', 'event_name');

        $byPageType = $byPageQuery
            ->select('page_type', DB::raw('count(*) as impressions'))
            ->groupBy('page_type')
            ->get();

        $byPlacement = $byPlacementQuery
            ->select('placement_key', 'event_name', DB::raw('count(*) as count'))
            ->groupBy('placement_key', 'event_name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'period' => $periodKey,
                'period_label' => $periodLabel,
                'events' => $eventsSummary,
                'impressions_by_page' => $byPageType,
                'by_placement' => $byPlacement,
            ],
        ]);
    }
}
