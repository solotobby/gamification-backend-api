<?php

namespace App\Http\Controllers;

use App\Services\AdvertisingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisingController extends Controller
{
    protected $advertisingService;

    public function __construct(AdvertisingService $advertisingService)
    {
        $this->advertisingService = $advertisingService;
    }

    /**
     * GET /api/public/advertising/config
     */
    public function getConfig(Request $request): JsonResponse
    {
        return $this->advertisingService->getPublicConfig($request);
    }

    /**
     * POST /api/public/advertising/events
     */
    public function logEvent(Request $request): JsonResponse
    {
        return $this->advertisingService->recordEvent($request);
    }
}
