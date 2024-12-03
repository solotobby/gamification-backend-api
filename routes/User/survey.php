<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SurveyController;


Route::middleware(['auth:api', 'isUser'])->group(function () {
    Route::get('/survey', [SurveyController::class, 'survey']);
    Route::post('/survey', [SurveyController::class, 'storeSurvey']);
});

