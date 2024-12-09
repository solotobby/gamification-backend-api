<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

//ADMIN CURRENCY ROUTES
Route::middleware(['auth:api', 'isAdmin'])->prefix('admin')->group(function () {
    Route::get('currency', [\App\Http\Controllers\Admin\CurrencyController::class, 'getCurrenciesList']);
    Route::get('currency/{id}', [\App\Http\Controllers\Admin\CurrencyController::class, 'getCurrency']);
    Route::patch('update/currency', [\App\Http\Controllers\Admin\CurrencyController::class, 'updateCurrency']);
    Route::get('base/rates', [\App\Http\Controllers\Admin\CurrencyController::class, 'baseRates']);
    Route::post('currency/status-toggle/{id}', [\App\Http\Controllers\Admin\CurrencyController::class, 'toggleCurrencyStatus']);
});

