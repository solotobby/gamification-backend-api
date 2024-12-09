<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
//AUTHENTICATION ROUTES

Route::group(['namespace' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',  [AuthController::class, 'login']);
    Route::post('process/otp',  [AuthController::class, 'sendEmailOTP']);
    Route::post('validate/otp',  [AuthController::class, 'validateOTP']);

    //reset password
    Route::post('send/resset/password/link', [AuthController::class, 'sendRessetPasswordLink']);
    Route::post('resset/password', [AuthController::class, 'ressetPassword']);

 });


 Route::middleware(['auth:api', 'isUser'])->group(function () {

    Route::post('/logout',  [AuthController::class,'logout']);
    Route::get('/user-details', [UserController::class, 'userResource']);
 });

 Route::middleware(['auth:api', 'isAdmin'])->prefix('admin')->group(function () {
    Route::post('login',  [AuthController::class, 'login']);
    Route::post('/logout',  [AuthController::class,'logout']);
    Route::get('/admin-details', [UserController::class, 'userResource']);
 });
