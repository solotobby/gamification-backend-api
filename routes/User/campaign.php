<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CampaignController;

Route::middleware(['auth:api', 'isUser'])->group(function () {
    Route::post('/campaign', [CampaignController::class, 'postCampaign']);
    Route::get('/campaign', [CampaignController::class, 'getCampaigns']);
    Route::post('/campaign/calculate/price', [CampaignController::class, 'calculateCampaignPrice']);
    Route::post('/submit/campaign', [CampaignController::class, 'submitWork']);

    Route::get('/campaign/categories', [CampaignController::class, 'getCategories']);
    Route::get('/campaign/sub/categories/{id}', [CampaignController::class, 'getSubCategories']);
    Route::get('/campaign/sub/categories/info/{id}', [CampaignController::class, 'getSubcategoriesInfo']);

});
