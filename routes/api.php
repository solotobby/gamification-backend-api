<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/unauthenticated', function () {
    return response()->json([
        'status' => false,
        'message' => 'Unauthenticated access. Please log in.',
    ], 401);
})->name('unauthenticated');

Route::prefix('webhooks')->group(function () {
    // Route::get('/paystack',  [WebhookController::class, 'handlePaystack'])->name('webhook.paystack');
    Route::post('/paystack',  [WebhookController::class, 'handlePaystackWebhook'])->name('webhook.paystack');
    Route::get('/paystack/callback',  [WebhookController::class, 'handlePaystackCallback'])->name('webhook.paystack.callback');
    // Route::get('/korapay',   [WebhookController::class, 'handleKoraPay'])->name('webhook.korapay');
    Route::post('/korapay',   [WebhookController::class, 'handleKoraPay'])->name('webhook.korapay');
    Route::get('/korapay/callback',   [WebhookController::class, 'handleKoraPayCallback'])->name('webhook.korapay.callback');
    Route::get('/stripe',    [WebhookController::class, 'handleStripe'])->name('webhook.stripe');

});


// Admin routes
// Route::middleware(['auth:api', 'isAdmin'])->prefix('admin')->group(function () {
//     Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast']);
//     Route::post('/manual-verification/{id}/review', [ManualVerificationController::class, 'review']);
// });

// Route::group(['middleware' => 'cors'], function () {
//     Route::middleware(['auth:api'])->group(function () {
//         Route::get('/user', [UserController::class, 'userResource']);
//         // Route::post('/update',  [AuthController::class,'update']);
//         // Route::post('/change/password',  [AuthController::class,'changePassword']);
//         Route::get('/logout',  [AuthController::class,'logout']);

//         Route::prefix('dashboard')->group(function () {
//             Route::get('/', [HomeController::class, 'dashboard']);

//             Route::post('/campaign', [CampaignController::class, 'postCampaign']);
//             Route::post('/campaign/calculate/price', [CampaignController::class, 'calculateCampaignPrice']);
//             Route::post('/submit/campaign', [CampaignController::class, 'submitWork']);

//             Route::get('/campaign/categories', [CampaignController::class, 'getCategories']);
//             Route::get('/campaign/sub/categories/{id}', [CampaignController::class, 'getSubCategories']);
//             // Route::get('/campaign/sub/categories/info/{id}', [CampaignController::class, 'getSubcategoriesInfo']);


//             Route::get('/campaign/list', [CampaignController::class, 'index']);
//             Route::get('/campaign/approved', [CampaignController::class, 'approvedCampaigns']);
//             Route::get('/campaign/denied', [CampaignController::class, 'deniedCampaigns']);

//             Route::get('/campaign/pause/{id}', [CampaignController::class, 'pauseCampaign']);
//             Route::post('/campaign/add/worker', [CampaignController::class, 'addMoreWorkers']);


//             Route::get('/campaign/activities/{id}', [CampaignController::class, 'activities']);
//             Route::get('/campaign/activities/response/{id}', [CampaignController::class, 'viewResponse']);
//             Route::post('/campaign/activities/response/decision', [CampaignController::class, 'campaignDecision']);

//             Route::get('/campaign/{id}', [CampaignController::class, 'viewCampaign']);
//         });


//     });

//     // Route::prefix('survey')->middleware(['auth:api'])->group(function () {
//     //     Route::get('/', [SurveyController::class, 'survey']);
//     //     Route::post('/', [SurveyController::class, 'storeSurvey']);
//     // });

// });
// //


