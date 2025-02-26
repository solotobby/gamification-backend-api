<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BannerController;

Route::middleware([
    'auth:api',
    'isAdmin'
])->prefix(
    'admin/banner'
)->group(function () {
    Route::post('/toggle-status', [BannerController::class, 'toggleBannerStatus']);
    Route::get('/', [BannerController::class, 'getBanners']);
});
