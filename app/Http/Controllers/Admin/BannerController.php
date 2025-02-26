<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminBannerService;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    protected $admin;
    public function __construct(AdminBannerService $admin)
    {
        $this->middleware('auth');
        $this->admin = $admin;
    }

    public function getBanners()
    {
        return $this->admin->listBanners();
    }

    public function toggleBannerStatus(Request $request)
    {
        return $this->admin->toggleBannerStatus($request);
    }
}
