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
    // Route::get('/public/hire-workers', [PublicController::class, 'publicWorkers']);
    // Route::get('/public/hire-workers/{id}', [PublicController::class, 'publicWorkerDetails']);
    Route::get('/public/tasks-details/{job_id}', [PublicController::class, 'taskDetails']);
    Route::get('/public/task/categories', [PublicController::class, 'getCategories']);

    Route::get('/public/jobs', [PublicController::class, 'publicJobs']);
    Route::get('/public/job-details/{slug}', [PublicController::class, 'jobDetails']);

    Route::get('public/click-ad/{bannerId}', [PublicController::class, 'clickAdCount']);

    /// test apis
    Route::get('test/list', [GeneralController::class, 'apiTest']);
    Route::post('notifications', [PublicController::class, 'sendNotification']);

    //get location
    Route::get('device/location', [GeneralController::class, 'deviceLocation']);

    // career profile public apis
    Route::get('/public/career-profile/{slug}', [PublicController::class, 'careerProfile']);
    Route::get('/public/talent/{category}', [PublicController::class, 'careerCategory']);
    Route::get('/public/skills/{skill}', [PublicController::class, 'careerSkillPage']);
    Route::get('/public/university/{university}', [PublicController::class, 'careerUniversityPage']);

    Route::get('/public/career', [PublicController::class, 'publicCareerWorkers']);
    Route::get('/public/utility/{key}', [PublicController::class, 'publicUtility']);
});
