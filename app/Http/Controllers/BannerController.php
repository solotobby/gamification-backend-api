<?php

namespace App\Http\Controllers;


use App\Services\BannerService;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    protected $bannerService;
    public function __construct(BannerService $bannerService)
    {
        $this->middleware('auth');
        $this->bannerService = $bannerService;
    }

    public function getUserBanner()
    {
        return $this->bannerService->listBanner();
    }

    public function getBannerPreference()
    {
        return $this->bannerService->getPreference();
    }
    public function createBanner(Request $request)
    {
        return $this->bannerService->createBanner($request);
    }
}
