<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix(
    'notifications'
)->group(function () {
  Route::get('/',              [NotificationController::class, 'index']);
        Route::post('/mark-read/{id}', [NotificationController::class, 'markRead']);
        Route::post('/mark-all-read',  [NotificationController::class, 'markAllRead']);
        Route::post('/fcm-token',      [NotificationController::class, 'updateFcmToken']);
});
