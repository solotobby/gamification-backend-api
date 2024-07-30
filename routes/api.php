<?php

use App\Http\Controllers\AuthController;
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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['namespace' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    // Route::post('int/register', [AuthController::class, 'intReg']);
    // Route::post('local/register', [AuthController::class, 'localReg']);
    Route::post('login',  [AuthController::class,'login']);
    Route::post('process/otp',  [AuthController::class,'sendEmailOTP']);
    Route::post('validate/otp',  [AuthController::class,'validateOTP']);
    //reset password
    Route::post('send/resset/password/link', [AuthController::class, 'sendRessetPasswordLink']);
    Route::post('resset/password', [AuthController::class, 'ressetPassword']);
    ///public apis
    Route::get('landing', [GeneralController::class, 'ladingpageApi']);
    Route::get('country/list', [GeneralController::class, 'country']);
    /// test apis
    Route::get('test/list', [GeneralController::class, 'apiTest']);

    //get location
    Route::get('device/location', [GeneralController::class, 'deviceLocation']);
    Route::post('email/verification', [AuthController::class, 'emailVerification']);
    Route::post('email/verify/code', [AuthController::class, 'emailVerifyCode']);

    Route::post('phone/verification', [AuthController::class, 'phoneVerification']);
    Route::post('phone/verify/otp', [AuthController::class, 'phoneVerifyOTP']);
});

Route::middleware(['auth:api'])->group(function () {
    Route::get('/user', [UserController::class, 'userResource']);
    // Route::post('/update',  [AuthController::class,'update']);
    // Route::post('/change/password',  [AuthController::class,'changePassword']); 
    Route::get('/logout',  [AuthController::class,'logout']);
    
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [HomeController::class, 'dashboard']);
    });


});

Route::prefix('survey')->middleware(['auth:api'])->group(function () {
    Route::get('/', [SurveyController::class, 'survey']);
    Route::post('/', [SurveyController::class, 'storeSurvey']);
});



