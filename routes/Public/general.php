<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GeneralController;

Route::group(['namespace' => 'auth'], function () {
    ///public apis
    Route::get('landing', [GeneralController::class, 'ladingPageApi']);
    Route::get('country/list', [GeneralController::class, 'country']);

    /// test apis
    Route::get('test/list', [GeneralController::class, 'apiTest']);

    //get location
    Route::get('device/location', [GeneralController::class, 'deviceLocation']);
   
});
