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
    public function googleAuth(Request $request)
    {
        return $this->authService->googleAuth($request);
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


    public function sendForgetPasswordToken(Request $request)
    {
        return $this->authService->sendResetPasswordToken($request);
    }

    public function resetPassword(Request $request)
    {
        return $this->authService->resetPassword($request);
    }


    public function verifyToken(Request $request)
    {
        return $this->authService->verifyToken($request);
    }

    public function logout(Request $request)
    {

        return $this->authService->logout($request);
    }

    public function setUsername(Request $request)
    {
        return $this->authService->setUsername($request);
    }
    
    public function checkUsername(Request $request)
    {
        return $this->authService->checkUsername($request);
    }
}
