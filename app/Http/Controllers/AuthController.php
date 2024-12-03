<?php

namespace App\Http\Controllers;

use App\Models\User;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\AuthService;


class AuthController extends Controller
{
    protected $authService;
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(Request $request)
    {
        return $this->authService->registerUser($request);
    }

    public function login(Request $request)
    {
        return $this->authService->loginUser($request);
    }

    public function sendEmailOTP(Request $request)
    {
        return $this->authService->resendEmailOTP($request);
    }

    public function validateOTP(Request $request)
    {
        return $this->authService->validateOTP($request);
    }

    public function localReg(Request $request) {}


    public function sendRessetPasswordLink(Request $request)
    {
        return $this->authService->sendResetPasswordLink($request);
    }

    public function ressetPassword(Request $request)
    {
        return $this->authService->resetPassword($request);
    }

    public function logout(Request $request)
    {

    return $this->authService->logout($request);
    }
}
