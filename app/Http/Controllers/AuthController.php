<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Mail\EmailVerification;
use App\Mail\GeneralMail;
use App\Services\AuthService;
use App\Models\OTP;
use App\Models\Profile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    protected $authService;
    public function __construct(AuthService $authService)    {
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

    public function intReg(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'numeric', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            if (env('APP_ENV') != 'localenv') {
                $curLocation = currentLocation();
            } else {
                $curLocation = 'Nigeria';
            }
            $res = $this->createUser($request, $curLocation);
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['message' => 'Registration successfully', 'status' => true, 'data' => $data], 201);
    }



    public function sendResetPasswordLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {
            $validateEmail = User::where('email', $request->email)->select(['id', 'name', 'email'])->first();
            if ($validateEmail) {
                $token = Str::random(64);
                \DB::table('password_resets')->insert(['email' => $validateEmail->email, 'token' => $token, 'created_at' => now()]);
                $subject = 'Freebyz Password Reset Link';
                $r_link = url('password/reset/' . $token);
                $content = 'Hi,' . $validateEmail->name . '. Your Password Reset Link is: ' . $r_link;
                Mail::to($validateEmail->email)->send(new GeneralMail($validateEmail, $content, $subject, ''));
            } else {
                return response()->json(['status' => false, 'message' => 'No account associated with the email'], 401);
            }
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Reset Password Link Sent'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $check = \DB::table('password_resets')->where('token', $request->token)->first();
            if ($check) {
                $user = User::where('email', $check->email)->first();
                $user->password = Hash::make($request->password);
                $user->save();

                \DB::table('password_resets')->where('token', $request->token)->delete();
            } else {
                return response()->json(['status' => false, 'message' => 'Something unexpected happen, contact the developer'], 401);
            }
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }

        return response()->json(['status' => true, 'message' => 'Password Reset Successful'], 200);
    }

    public function logout(Request $request)
    {

        $request->user()->token()->revoke();
        return response()->json([
            'status' => 'success',
            'message' => 'User is logged out successfully'
        ], 200);
    }

    public function emailVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {

            $token = rand(10000, 10000000);

            \DB::table('password_resets')->insert(['email' => $request->email, 'token' => $token, 'created_at' => now()]);
            $subject = 'Freebyz Email Verification';
            // $r_link = url('password/reset/'.$token);
            $content = 'Hi, Your email verification code is: ' . $token;
            $user['name'] = '';
            $user['email'] = $request->email;

            Mail::to($request->email)->send(new EmailVerification($request->email, $content, $subject, ''));

            return response()->json(['status' => true, 'message' => 'Verification Email Sent Successfully'], 200);
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
    }

    public function emailVerifyCode(Request $request)
    {
        $request->validate([
            'code' => 'required|numeric',
        ]);

        try {
            $checkValidity = \DB::table('password_resets')->where(['token' => $request->code])->first();
            if ($checkValidity) {

                return response()->json(['status' => true, 'message' => 'Email verified, redirect to registration page'], 200);
            } else {
                return response()->json(['status' => false, 'message' => 'Invalid Code'], 401);
            }
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
    }

    public function phoneVerification(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'numeric', 'digits:11'], //'unique:users'
        ]);

        try {
            $phone_number = '234' . substr($request->phone, 1);
            return $response = sendOTP($phone_number);

            //  if($response['status'] == 200){
            //     OTP::create(['user_id' => auth()->user()->id, 'pinId' => $response['pinId'], 'otp' => '11111', 'phone_number' => $response['to'], 'is_verified' => false]);
            // }

        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
    }

    public function phoneVerifyOTP(Request $request)
    {
        $request->validate([
            'otp' => 'numeric|required|digits:6',
            'pinId' => 'numeric|required'
        ]);
        try {
            return $response = OTPVerify($request->pinId, $request->otp);
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
    }
}
