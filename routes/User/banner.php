<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BannerController;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix(
    'banner'
)->group(function () {
    Route::post('/create-ad', [BannerController::class, 'createBanner']);
    Route::post('/click-ad/{bannerId}', [BannerController::class, 'clickAdCount']);
    Route::get('/list', [BannerController::class, 'getUserBanner']);
    Route::get('/preference-list', [BannerController::class, 'getBannerPreference']);
});
