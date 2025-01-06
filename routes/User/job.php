<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobsController;


Route::middleware([
    'auth:api',
    'isUser'
])->prefix('jobs')->group(function () {
    Route::get('/my-job', [JobsController::class, 'myJobs']);
    Route::get('/job-details/{job_id}', [JobsController::class, 'jobDetails']);
    Route::get('/available', [JobsController::class, 'referralStat']);

});
