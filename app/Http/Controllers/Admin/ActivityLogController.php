<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    /**
     * List paginated activity logs with flexible filters, search, and device analytics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->get('per_page', 25)), 200);

        $query = ActivityLog::with('user:id,name,email,phone,role,created_at')
            ->search($request->get('search'))
            ->forUser($request->get('user_id'))
            ->userType($request->get('user_type'))
            ->activityType($request->get('activity_type'))
            ->clientType($request->get('client_type'))
            ->device($request->get('device'))
            ->dateRange($request->get('start_date') ?: $request->get('start'), $request->get('end_date') ?: $request->get('end'))
            ->orderBy('created_at', 'DESC');

        $logs = $query->paginate($perPage);

        // Compute summary metrics (scoped or overall today)
        $summary = $this->calculateSummary($request);

        return response()->json([
            'status' => true,
            'message' => 'Activity logs retrieved successfully',
            'data' => [
                'logs' => $logs,
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Get details for a single activity log.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $log = ActivityLog::with('user:id,name,email,phone,role,is_verified,created_at')->find($id);

        if (!$log) {
            return response()->json([
                'status' => false,
                'message' => 'Activity log not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Activity log details retrieved successfully',
            'data' => [
                'log' => $log,
                'is_app' => $log->is_app,
                'is_web' => $log->is_web,
            ]
        ]);
    }

    /**
     * Get a specific user's activity timeline.
     *
     * @param int $userId
     * @param Request $request
     * @return JsonResponse
     */
    public function userTimeline($userId, Request $request): JsonResponse
    {
        $user = User::select('id', 'name', 'email', 'phone', 'role', 'created_at')->find($userId);

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        $perPage = min((int) ($request->get('per_page', 20)), 100);

        $activities = ActivityLog::where('user_id', $userId)
            ->activityType($request->get('activity_type'))
            ->clientType($request->get('client_type'))
            ->dateRange($request->get('start_date') ?: $request->get('start'), $request->get('end_date') ?: $request->get('end'))
            ->orderBy('created_at', 'DESC')
            ->paginate($perPage);

        // Distinct devices & platforms used by this user
        $devicesUsed = ActivityLog::where('user_id', $userId)
            ->select('client_type', 'device', 'platform', 'browser', 'device_model', 'ip_address', DB::raw('COUNT(*) as count'), DB::raw('MAX(created_at) as last_used'))
            ->groupBy('client_type', 'device', 'platform', 'browser', 'device_model', 'ip_address')
            ->orderBy('last_used', 'DESC')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'User activity timeline retrieved successfully',
            'data' => [
                'user' => $user,
                'activities' => $activities,
                'devices_used' => $devicesUsed,
            ]
        ]);
    }

    /**
     * Get aggregated statistics on user & admin activity and device distribution.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $baseQuery = ActivityLog::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // Platform breakdown (Web vs App vs API)
        $clientBreakdown = (clone $baseQuery)
            ->select('client_type', DB::raw('COUNT(*) as total'))
            ->groupBy('client_type')
            ->get()
            ->pluck('total', 'client_type');

        // Device breakdown (Mobile vs Desktop vs Tablet)
        $deviceBreakdown = (clone $baseQuery)
            ->select('device', DB::raw('COUNT(*) as total'))
            ->groupBy('device')
            ->get()
            ->pluck('total', 'device');

        // Top activity types
        $topActivities = (clone $baseQuery)
            ->select('activity_type', DB::raw('COUNT(*) as total'))
            ->groupBy('activity_type')
            ->orderBy('total', 'DESC')
            ->limit(10)
            ->get();

        // User type breakdown (Regular vs Admin)
        $userTypeBreakdown = (clone $baseQuery)
            ->select('user_type', DB::raw('COUNT(*) as total'))
            ->groupBy('user_type')
            ->get()
            ->pluck('total', 'user_type');

        // Daily activity trend
        $dailyTrend = (clone $baseQuery)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Activity stats retrieved successfully',
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'clients' => [
                    'web' => $clientBreakdown['web'] ?? 0,
                    'app' => ($clientBreakdown['app'] ?? 0) + ($clientBreakdown['mobile_app'] ?? 0) + ($clientBreakdown['ios'] ?? 0) + ($clientBreakdown['android'] ?? 0),
                    'api' => $clientBreakdown['api'] ?? 0,
                ],
                'devices' => [
                    'mobile' => $deviceBreakdown['mobile'] ?? 0,
                    'desktop' => $deviceBreakdown['desktop'] ?? 0,
                    'tablet' => $deviceBreakdown['tablet'] ?? 0,
                ],
                'user_types' => [
                    'regular' => $userTypeBreakdown['regular'] ?? 0,
                    'admin' => $userTypeBreakdown['admin'] ?? 0,
                ],
                'top_activities' => $topActivities,
                'daily_trend' => $dailyTrend,
            ]
        ]);
    }

    /**
     * Calculate summary overview counts for the index list.
     */
    protected function calculateSummary(Request $request): array
    {
        $today = now()->toDateString();

        $total = ActivityLog::count();
        $todayCount = ActivityLog::whereDate('created_at', $today)->count();

        $webCount = ActivityLog::where('client_type', 'web')->count();
        $appCount = ActivityLog::whereIn('client_type', ['app', 'mobile_app', 'ios', 'android'])->count();
        $apiCount = ActivityLog::where('client_type', 'api')->count();

        $mobileCount = ActivityLog::where('device', 'mobile')->count();
        $desktopCount = ActivityLog::where('device', 'desktop')->count();

        $adminCount = ActivityLog::where('user_type', 'admin')->count();
        $regularCount = ActivityLog::where('user_type', 'regular')->count();

        return [
            'total_activities' => $total,
            'today_activities' => $todayCount,
            'web_count' => $webCount,
            'app_count' => $appCount,
            'api_count' => $apiCount,
            'mobile_count' => $mobileCount,
            'desktop_count' => $desktopCount,
            'admin_count' => $adminCount,
            'regular_count' => $regularCount,
        ];
    }
}
