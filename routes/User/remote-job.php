<?php

use App\Http\Controllers\CareerProfileController;
use App\Http\Controllers\RemoteJobController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HireWorkerController;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix('remote-jobs')->group(function () {

    Route::get('/', [RemoteJobController::class, 'index']);
    Route::get('/my-listings',  [RemoteJobController::class, 'myJobs']);
    Route::get('/my-listings/{id}',  [RemoteJobController::class, 'myJobDetails']);
    Route::get('/{id}', [RemoteJobController::class, 'show']);
    Route::post('/{id}/apply', [RemoteJobController::class, 'apply']);
    Route::post('/{id}/purchase-point', [RemoteJobController::class, 'purchasePoint']);
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

Route::middleware([
    'auth:api',
    'isUser'
])->group(function () {
    Route::get('career-profile', [CareerProfileController::class, 'show']);
    Route::put('career-profile', [CareerProfileController::class, 'update']);
    Route::get('career-profile/skill-options', [CareerProfileController::class, 'skillOptions']);

    Route::post('career-profile/experience', [CareerProfileController::class, 'storeExperience']);
    Route::put('career-profile/experience/{id}', [CareerProfileController::class, 'updateExperience']);
    Route::delete('career-profile/experience/{id}', [CareerProfileController::class, 'destroyExperience']);

    Route::post('career-profile/education', [CareerProfileController::class, 'storeEducation']);
    Route::put('career-profile/education/{id}', [CareerProfileController::class, 'updateEducation']);
    Route::delete('career-profile/education/{id}', [CareerProfileController::class, 'destroyEducation']);

    Route::post('career-profile/certification', [CareerProfileController::class, 'storeCertification']);
    Route::delete('career-profile/certification/{id}', [CareerProfileController::class, 'destroyCertification']);

    Route::put('career-profile/social-profiles', [CareerProfileController::class, 'updateSocialProfiles']);
    Route::post('career-profile/photo', [CareerProfileController::class, 'uploadPhoto']);
    Route::post('career-profile/cv', [CareerProfileController::class, 'uploadCv']);
    Route::get('career-profile/analytics', [CareerProfileController::class, 'analytics']);
    Route::post('career-profile/certification/{id}/file', [CareerProfileController::class, 'uploadCertificationFile']);

    //
    Route::post('career-profile/complete-onboarding', [CareerProfileController::class, 'completeOnboarding']);
});
