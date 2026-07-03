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
    Route::post('/{id}/purchase-point', [RemoteJobController::class, 'purchasePoint']);
    Route::get('/my-listings',  [RemoteJobController::class, 'myJobs']);
    Route::post('/',            [RemoteJobController::class, 'store']);
    Route::put('/{id}',         [RemoteJobController::class, 'update']);
});



Route::middleware([
    'auth:api',
    'isUser'
])->prefix('hire-workers')->group(function () {

    // Route::get('/filters', [HireWorkerController::class, 'filters']);
    Route::get('/purchased', [HireWorkerController::class, 'purchased']);
    Route::get('/my-skill', [HireWorkerController::class, 'mySkill']);
    Route::get('/', [HireWorkerController::class, 'index']);
    Route::post('/create-skill', [HireWorkerController::class, 'store']);
    Route::get('/{id}', [HireWorkerController::class, 'show']);
    Route::put('/update-skill/{id}', [HireWorkerController::class, 'update']);
    Route::post('/{id}/purchase-point', [HireWorkerController::class, 'purchasePoint']);
});
