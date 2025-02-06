<?php

use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:api',
    'isUser'
])->prefix('wallet')->group(function () {
    Route::post('/fund-wallet', [WalletController::class, 'fundWallet']);
    Route::get('/user-transaction', [WalletController::class, 'getUserTransactions']);
    Route::get('/withdrawal-requests', [WalletController::class, 'getUserWithdrawals']);
    Route::post('/request-withdrawal', [WalletController::class, 'processWithdrawals']);
    Route::get('/bank-list', [WalletController::class, 'getBankLists']);
    Route::post('/fetch/account-name', [WalletController::class, 'getAccountName']);
    Route::post('/create-user/bank-detail', [WalletController::class, 'createBankDetails']);

});
