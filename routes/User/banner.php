<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BannerController;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix(
    'banner'
)->group(function () {
    Route::post('/create', [BannerController::class, 'createBanner']);
    Route::get('/list', [BannerController::class, 'getUserBanner']);
    Route::patch('/campaign/add/worker', [BannerController::class, 'addWorkerToCampaign']);
});
