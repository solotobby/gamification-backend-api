<?php

use App\Http\Controllers\RemoteJobController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HireWorkerController;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix('remote-jobs')->group(function () {

    Route::get('/', [RemoteJobController::class, 'index']);
    Route::get('/{id}', [RemoteJobController::class, 'show']);
    Route::post('/{id}/apply', [RemoteJobController::class, 'apply']);
});



Route::middleware([
    'auth:api',
    'isUser'
])->prefix('hire-workers')->group(function () {

    Route::get('/filters', [HireWorkerController::class, 'filters']);
    Route::get('/', [HireWorkerController::class, 'index']);
    Route::get('/{id}', [HireWorkerController::class, 'show']);
    Route::post('/{id}/purchase-point', [HireWorkerController::class, 'purchasePoint']);
});
