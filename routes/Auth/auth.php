<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
//AUTHENTICATION ROUTES

Route::group(['namespace' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',  [AuthController::class, 'login']);

    //reset password
    Route::post('send/resset/password/link', [AuthController::class, 'sendRessetPasswordLink']);
    Route::post('resset/password', [AuthController::class, 'ressetPassword']);

 });


 Route::middleware(['auth:api', 'isAdmin'])->prefix('admin')->group(function () {
   // Route::post('login',  [AuthController::class, 'login']);
    Route::post('/logout',  [AuthController::class,'logout']);
    Route::get('/admin-details', [UserController::class, 'userResource']);
 });
