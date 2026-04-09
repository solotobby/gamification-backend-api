<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

//AUTHENTICATION ROUTES
Route::group(['namespace' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',  [AuthController::class, 'login']);

    Route::post('login/google', [AuthController::class, 'googleAuth']);
    // Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);

    //reset password
    Route::post('forget/password', [AuthController::class, 'sendForgetPasswordToken']);
    Route::post('forget/password/verify-code', [AuthController::class, 'verifyToken']);
    Route::post('reset/password', [AuthController::class, 'resetPassword']);
});


Route::middleware(['auth:api', 'isAdmin'])->prefix('admin')->group(function () {
    // Route::post('login',  [AuthController::class, 'login']);
    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::get('/admin-details', [UserController::class, 'userResource']);
});
