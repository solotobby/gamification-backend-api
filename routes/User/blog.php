<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BlogController;



Route::middleware(['auth:api', 'isAdmin'])->prefix('admin/blogs')->group(function () {
    // Route::get('/', [BlogController::class, 'index']);
    // Route::post('/', [BlogController::class, 'store']);
    // Route::get('/analytics', [BlogController::class, 'overallAnalytics']);
    // Route::get('/{id}', [BlogController::class, 'show']);
    // Route::put('/{id}', [BlogController::class, 'update']);
    // Route::delete('/{id}', [BlogController::class, 'destroy']);
    // Route::post('/{id}/publish', [BlogController::class, 'publish']);
    // Route::post('/{id}/unpublish', [BlogController::class, 'unpublish']);
    // Route::get('/{id}/analytics', [BlogController::class, 'analytics']);
});
