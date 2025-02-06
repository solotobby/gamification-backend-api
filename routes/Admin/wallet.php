<?php

use App\Http\Controllers\Admin\WalletController;
use Illuminate\Support\Facades\Route;

//ADMIN Withdrawal ROUTES
Route::middleware([
    'auth:api',
    'isAdmin'
])->prefix(
    'admin/wallet'
)->group(function () {
        Route::get('/withdrawal-requests', [WalletController::class, 'withdrawalLists']);
        Route::post('/approve-withdrawal', [WalletController::class, 'approveWithdrawal']);
    }
);
