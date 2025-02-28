<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SafeLockController;

Route::middleware([
    'auth:api',
    'isAdmin'
])->prefix('admin/safe-lock')->group(function () {
    Route::get('/', [SafeLockController::class, 'adminGetSafeLocks']);
});
