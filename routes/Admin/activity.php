<?php

use App\Http\Controllers\Admin\ActivityLogController;
use Illuminate\Support\Facades\Route;

// ADMIN Activity Logs & Device Tracking Routes
Route::middleware([
    'auth:api',
    'isAdmin'
])->prefix('admin/activity-logs')->group(function () {
    Route::get('/', [ActivityLogController::class, 'index']);
    Route::get('/stats', [ActivityLogController::class, 'stats']);
    Route::get('/users/{userId}', [ActivityLogController::class, 'userTimeline']);
    Route::get('/{id}', [ActivityLogController::class, 'show']);
});
