<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SafeLockController;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix('safe-lock')->group(function () {
    Route::get('/', [SafeLockController::class, 'getSafeLocks']);
    Route::post('/create', [SafeLockController::class, 'createSafeLock']);
    Route::post('/redeem/{safelock_id}', [SafeLockController::class, 'redeemSafelock']);
});
