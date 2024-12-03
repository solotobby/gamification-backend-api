<?php

namespace App\Services;


use App\Validators\AuthValidator;
use App\Mail\GeneralMail;
use App\Mail\Welcome;
use Illuminate\Support\Facades\Mail;
use App\Exceptions\BadRequestException;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\ReferralRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use Throwable;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Repositories\LogRepositoryModel;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    private $validator, $auth, $wallet, $refer, $log;

    public function __construct(
        AuthValidator $validator,
        AuthRepositoryModel $auth,
        WalletRepositoryModel $wallet,
        ReferralRepositoryModel $refer,
        LogRepositoryModel $log,
    ) {
        $this->validator = $validator;
        $this->auth = $auth;
        $this->wallet = $wallet;
        $this->refer = $refer;
        $this->log = $log;
    }

    public function registerUser($request)
    {
        $this->validator->validateRegistration($request);
        try {
            // Create the user and related resources
            $result = $this->createUser($request);

            $user = $result['user'];
            $wallet = $result['wallet'];
            $profile = $result['profile'];
            $token = $user->createToken('freebyz')->accessToken;
            $dashboard = $this->auth->dashboardStat($user->id);
            // Prepare response data
            $data = [
                'user' => $user,
                'wallet' => $wallet,
                'profile' => $profile,
                'token' => $token,
                'dashboard' => $dashboard,
            ];

            return response()->json(['message' => 'Registration successfully', 'status' => true, 'data' => $data], 201);
        } catch (Throwable) {
            // Handle exceptions gracefully
            throw new BadRequestException('Error processing request');
        }
    }

    public function loginUser($request)
    {
        // Validate request data
        $this->validator->validateLogin($request);

        try {
            // Find user by email
            $user = $this->auth->findUser($request->email);

            if (!$user) {
                return response()->json(['status' => false, 'message' => 'User not found'], 404);
            }

            // Ensure user has a role
            $this->ensureUserHasRole($user);

            // Ensure user has a referral code
            $this->ensureUserHasReferralCode($user);

            // Validate the password
            if (!$this->auth->validatePassword($request->password, $user->password)) {
                return response()->json(['status' => false, 'message' => 'Incorrect login details'], 403);
            }

            // Generate user data and token
            $data['user'] = $this->auth->findUserWithRole($request->email);
            $data['token'] = $user->createToken('freebyz')->accessToken;
            $data['dashboard'] = $this->auth->dashboardStat($user->id);

            // Perform environment-specific actions
            if (env('APP_ENV') !== 'localenv') {
                $data['profile'] = setProfile($user);
                //    Log Activities
                $this->log->createLogForSurvey($user);
            }

            return response()->json([
                'message' => 'Login successful',
                'status' => true,
                'data' => $data,
            ], 200);
        } catch (Throwable $e) {
           // return $e;
            throw new BadRequestException('Error processing request');
        }
    }


    public function logout($request)
    {
        $request->user()->token()->revoke();
        return response()->json([
            'status' => true,
            'message' => 'User is logged out successfully'
        ], 200);
    }


    protected function ensureUserHasRole($user)
    {
        if ($user->getRoleNames()->isEmpty()) {
            $user->assignRole('regular');
        }
    }

    protected function ensureUserHasReferralCode($user)
    {
        if (is_null($user->referral_code)) {
            $this->refer->addReferralCode($user);
        }
    }

    public function resendEmailOTP($request)
    {
        // Validate request data
        $this->validator->validateResendOTP($request);

        try {
            $user = $this->auth->findUser($request->email);

            if (!$user) {
                return response()->json(['status' => false, 'message' => 'User not found'], 404);
            }

            $otp = $this->auth->generateOTP($user);

            if (env('APP_ENV') !== 'localenv') {
                $subject = 'Freebyz Email Verification';
                $content = 'Hi, ' . $user->name . ', Your Email Verification Code is ' . $otp;
                Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));
            }

            return response()->json(['status' => true, 'message' => 'Email Verification code sent'], 200);
        } catch (Throwable) {
            throw new BadRequestException('Error processing request');
        }
    }

    public function validateOTP($request)
    {
        // Validate request data
        $this->validator->validateOTP($request);

        try {
            // Find the OTP
            $otp = $this->auth->findOtp($request->otp);

            if (!$otp) {
                return response()->json(['status' => false, 'message' => 'OTP is not correct, please request another one'], 401);
            }

            // Check OTP expiration
            $expirationTime = $otp->created_at->addMinutes(config('auth.otp_expiration', 2));
            if (now()->greaterThan($expirationTime)) {
                $this->auth->deleteOtp($otp);
                return response()->json(['status' => false, 'message' => 'OTP expired, please request another one'], 401);
            }

            // Update or create user profile
            $this->auth->updateOrCreateProfile($otp->user_id, ['email_verified' => true]);

            // Update user verification details
            $this->auth->updateUserVerificationStatus($otp->user_id);
            // Fetch user details
            $data['user'] = $user = $this->auth->findUserWithRoleById($otp->user_id);

            //dashboard data
            $data['dashboard'] =  $this->auth->dashboardStat($user->id);
            // Delete OTP after successful verification
            $this->auth->deleteOtp($otp);

            return response()->json([
                'status' => true,
                'message' => 'Email verified successfully',
                'data' => $data,
            ], 200);
        } catch (Throwable) {
            throw new BadRequestException('Error processing request');
        }
    }

    public function sendResetPasswordLink($request)
    {
        // Validate request
        $this->validator->validateResetPasswordLink($request);

        try {
            // Find user by email
            $validateEmail = $this->auth->findUser($request->email);
            // return $validateEmail;
            if (!$validateEmail) {
                return response()->json(['status' => false, 'message' => 'No account associated with this email'], 404);
            }

            // Create URL token and store it in the password_resets table
            $token = $this->auth->createToken($validateEmail->email);

            // Create reset link
            $link = url('password/reset/' . $token);

            // Send email
            $subject = 'Freebyz Password Reset Link';
            $content = 'Hi, ' . $validateEmail->name . '. Your Password Reset Link is: ' . $link;
            Mail::to($validateEmail->email)->send(new GeneralMail($validateEmail, $content, $subject, ''));

            return response()->json(['status' => true, 'message' => 'Reset Password Link Sent'], 200);
        } catch (Throwable) {
            throw new BadRequestException('Error processing request');
        }
    }

    public function resetPassword($request)
    {

        $this->validator->validateResetPassword($request);
        try {
            // Verify Token
            $checkToken = $this->auth->verifyToken($request->token);
            if (!$checkToken) {
                return response()->json(['status' => false, 'message' => 'Something unexpected happen, contact the admin or try again later'], 401);
            }

            // Update Password
            $this->auth->updateUserPassword($checkToken->email, $request->password);

            // Delete Token
            $this->auth->deleteToken($request->token);

            return response()->json(['status' => true, 'message' => 'Password Reset Successful'], 200);
        } catch (Throwable) {
            return response()->json(['status' => false, 'message' => 'Error processing request'], 500);
        }
    }
    protected function createUser($payload)
    {
        // Create user
        $user = $this->auth->createUser($payload);

        // Set wallet and currency
        $curLocation = env('APP_ENV') !== 'localenv' ? currentLocation() : 'Nigeria';
        $currency = $curLocation === 'Nigeria' ? 'Naira' : 'Dollar';

        // Create wallet
        $wallet = $this->wallet->createWallet($user, $currency);

        // Handle optional profile creation for non-local environments
        $profile = [];
        if (env('APP_ENV') !== 'localenv') {
            $profile = setProfile($user);
        }

        // Process referral if applicable
        $ref_id = $payload['ref_id'] ?? null;
        if (!empty($ref_id)) {
            $referral = $this->refer->createReferral($user, $ref_id);
        }

        // Activity logging for non-local environments
        if (env('APP_ENV') !== 'localenv') {
            activityLog($user, 'account_creation', $user->name . ' Registered ', 'regular');
        }


        // Generate OTP
        $otp = $this->auth->generateOTP($user);

        // Send verification emails if not local
        if (env('APP_ENV') !== 'localenv') {
            $subject = 'Freebyz Email Verification';
            $content = 'Hi, ' . $user->name . ', Your Email Verification Code is ' . $otp;
            Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));

            $welcomeSubject = 'Welcome to Freebyz';
            Mail::to($payload['email'])->send(new Welcome($user, $welcomeSubject, ''));
        }

        return [
            'user' => $user,
            'wallet' => $wallet,
            'profile' => $profile,
        ];
    }

    // Reducdant apis
    public function emailVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {

            $token = rand(10000, 10000000);

            DB::table('password_resets')->insert(['email' => $request->email, 'token' => $token, 'created_at' => now()]);
            $subject = 'Freebyz Email Verification';
            // $r_link = url('password/reset/'.$token);
            $content = 'Hi, Your email verification code is: ' . $token;
            $user['name'] = '';
            $user['email'] = $request->email;

            // Mail::to($request->email)->send(new EmailVerification($request->email, $content, $subject, ''));

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
            $checkValidity = DB::table('password_resets')->where(['token' => $request->code])->first();
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

    public function phoneVerifyOTP($request)
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
            $res = $this->createUser($request);
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        //  return response()->json(['message' => 'Registration successfully', 'status' => true, 'data' => $data], 201);
    }
}
