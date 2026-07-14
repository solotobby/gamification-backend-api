<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JobsController;


Route::middleware([
    'auth:api',
    'isUser'
])->prefix('jobs')->group(function () {
    Route::get('/my-job', [JobsController::class, 'myJobs']);
    Route::get('/job-details/{job_id}', [JobsController::class, 'jobDetails']);
    Route::get('/task-detail/{job_id}', [JobsController::class, 'taskDetail']);

    Route::get('/available-jobs', [JobsController::class, 'availableJobs']);
    Route::post('/create-dispute', [JobsController::class, 'createDispute']);
    Route::post('/submit-job', [JobsController::class, 'submitJob']);
    Route::post('/submit-rating', [JobsController::class, 'jobRating']);
});
