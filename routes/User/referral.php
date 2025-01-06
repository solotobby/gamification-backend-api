<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReferralController;


Route::middleware([
    'auth:api',
    'isUser'
])->group(function () {
    Route::get('/referral-stat', [ReferralController::class, 'referralStat']);
    Route::get('/referral', [ReferralController::class, 'referralList']);
});
