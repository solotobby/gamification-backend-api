<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
 * |--------------------------------------------------------------------------
 * | API Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register API routes for your application. These
 * | routes are loaded by the RouteServiceProvider and all of them will
 * | be assigned to the "api" middleware group. Make something great!
 * |
 */

Route::get('/unauthenticated', function () {
    return response()->json([
        'status' => false,
        'message' => 'Unauthenticated access. Please log in.',
    ], 401);
})->name('unauthenticated');

Route::prefix('webhooks')->group(function () {
    // Route::get('/paystack',  [WebhookController::class, 'handlePaystack'])->name('webhook.paystack');
    Route::post('/paystack', [WebhookController::class, 'handlePaystackWebhook'])->name('webhook.paystack');
    Route::get('/paystack/callback', [WebhookController::class, 'handlePaystackCallback'])->name('webhook.paystack.callback');
    // Route::get('/korapay',   [WebhookController::class, 'handleKoraPay'])->name('webhook.korapay');
    Route::post('/korapay', [WebhookController::class, 'handleKoraPay'])->name('webhook.korapay');
    Route::get('/korapay/callback', [WebhookController::class, 'handleKoraPayCallback'])->name('webhook.korapay.callback');
    Route::get('/stripe', [WebhookController::class, 'handleStripe'])->name('webhook.stripe');
    Route::post('/zeptomail', [WebhookController::class, 'zeptoWebhookBounces'])->name('webhook.zeptomail');
    Route::post('/interswitch', [WebhookController::class, 'handleInterswitchWebhook'])->name('webhook.interswitch');
    Route::get('/interswitch/callback', [WebhookController::class, 'handleInterswitchCallback'])->name('webhook.interswitch.callback');
    Route::post('/flutterwave', [WebhookController::class, 'handleFlutterwaveWebhook'])->name('webhook.flutterwave');
    Route::get('/flutterwave/callback', [WebhookController::class, 'handleFlutterwaveCallback'])->name('webhook.flutterwave.callback');
});
