<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GeneralController;
//AUTHENTICAION ROUTES

Route::group(['namespace' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',  [AuthController::class, 'login']);
    Route::post('process/otp',  [AuthController::class, 'sendEmailOTP']);
    Route::post('validate/otp',  [AuthController::class, 'validateOTP']);

    //reset password
    Route::post('send/resset/password/link', [AuthController::class, 'sendRessetPasswordLink']);
    Route::post('resset/password', [AuthController::class, 'ressetPassword']);

    ///public apis
    Route::get('landing', [GeneralController::class, 'ladingpageApi']);
    Route::get('country/list', [GeneralController::class, 'country']);
    
    /// test apis
    Route::get('test/list', [GeneralController::class, 'apiTest']);

    //get location
    Route::get('device/location', [GeneralController::class, 'deviceLocation']);
    Route::post('email/verification', [AuthController::class, 'emailVerification']);
    Route::post('email/verify/code', [AuthController::class, 'emailVerifyCode']);

    Route::post('phone/verification', [AuthController::class, 'phoneVerification']);
    Route::post('phone/verify/otp', [AuthController::class, 'phoneVerifyOTP']);
});
