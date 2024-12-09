<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

Route::middleware(['auth:api', 'isUser'])->group(function () {

    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::get('/user-details', [UserController::class, 'userResource']);

    Route::post('email/verification', [AuthController::class, 'emailVerification']);
    Route::post('email/verify/code', [AuthController::class, 'emailVerifyCode']);

    Route::post('phone/verification', [AuthController::class, 'phoneVerification']);
    Route::post('phone/verify/otp', [AuthController::class, 'phoneVerifyOTP']);
});
