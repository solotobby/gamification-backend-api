<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\UserController;
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


