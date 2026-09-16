<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAdvertisingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisingController extends Controller
{
    protected $adminAdvertisingService;

    public function __construct(AdminAdvertisingService $adminAdvertisingService)
    {
        $this->adminAdvertisingService = $adminAdvertisingService;
    }

    public function getConfig(): JsonResponse
    {
        return $this->adminAdvertisingService->getConfig();
    }

    public function updateConfig(Request $request): JsonResponse
    {
        return $this->adminAdvertisingService->updateConfig($request);
    }

    public function listPlacements(): JsonResponse
    {
        return $this->adminAdvertisingService->listPlacements();
    }

    public function updatePlacement(Request $request, int $id): JsonResponse
    {
        return $this->adminAdvertisingService->updatePlacement($request, $id);
    }

    public function listCodes(): JsonResponse
    {
        return $this->adminAdvertisingService->listCodes();
    }

    public function updateCodes(Request $request): JsonResponse
    {
        return $this->adminAdvertisingService->updateCodes($request);
    }

    public function getAnalyticsSummary(Request $request): JsonResponse
    {
        return $this->adminAdvertisingService->getAnalyticsSummary($request);
    }
}
