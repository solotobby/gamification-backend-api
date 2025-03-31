<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;

Route::middleware([
    'auth:api',
    'isUser'
])->group(function () {
    Route::post('/campaign', [CampaignController::class, 'postCampaign']);
    Route::get('/campaign', [CampaignController::class, 'getCampaigns']);
    Route::patch('/campaign/add/worker', [CampaignController::class, 'addWorkerToCampaign']);


    Route::get('/campaign/jobs-by-type', [CampaignController::class, 'allCampaignJob']);
    Route::get('/campaign/activities-stat/{campaignId}', [CampaignController::class, 'campaignActivitiesStat']);
    Route::get('/campaign/activities-job/{campaignId}', [CampaignController::class, 'campaignActivitiesJob']);
    Route::get('/campaign/activities/job-details', [CampaignController::class, 'jobDetails']);
    Route::post('/campaign/activities/approve-or-deny', [CampaignController::class, 'approveOrDeclineJob']);

    Route::get('/campaign/categories', [CampaignController::class, 'getCategories']);
    Route::post('/campaign/pause/{campaignId}', [CampaignController::class, 'pauseCampaign']);
});
