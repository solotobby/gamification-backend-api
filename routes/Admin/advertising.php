<?php

use App\Http\Controllers\Admin\AdvertisingController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:api',
    'isAdmin'
])->prefix(
    'admin/advertising'
)->group(function () {
    Route::get('/config', [AdvertisingController::class, 'getConfig']);
    Route::put('/config', [AdvertisingController::class, 'updateConfig']);
    Route::get('/placements', [AdvertisingController::class, 'listPlacements']);
    Route::put('/placements/{id}', [AdvertisingController::class, 'updatePlacement']);
    Route::get('/codes', [AdvertisingController::class, 'listCodes']);
    Route::put('/codes', [AdvertisingController::class, 'updateCodes']);
    Route::get('/analytics', [AdvertisingController::class, 'getAnalyticsSummary']);
});
