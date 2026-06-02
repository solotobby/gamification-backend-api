<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\PublicController;

Route::group(['namespace' => 'auth'], function () {
    ///public apis
    Route::get('landing', [GeneralController::class, 'ladingPageApi']);
    Route::get('country/list', [GeneralController::class, 'country']);
    Route::get('/public/tasks', [PublicController::class, 'publicTasks']);
    Route::get('/public/tasks-details/{job_id}', [PublicController::class, 'taskDetails']);
    Route::get('/public/task/categories', [PublicController::class, 'getCategories']);

     Route::get('/public/jobs', [PublicController::class, 'publicJobs']);
    Route::get('/public/job-details/{job_id}', [PublicController::class, 'jobDetails']);


    /// test apis
    Route::get('test/list', [GeneralController::class, 'apiTest']);

    //get location
    Route::get('device/location', [GeneralController::class, 'deviceLocation']);
});
