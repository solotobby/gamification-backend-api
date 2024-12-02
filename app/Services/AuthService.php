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

class AuthService
{
    private $validator, $auth, $wallet, $refer;

    public function __construct(
        AuthValidator $validator,
        AuthRepositoryModel $auth,
        WalletRepositoryModel $wallet,
        ReferralRepositoryModel $refer
    ) {
        $this->validator = $validator;
        $this->auth = $auth;
        $this->wallet = $wallet;
        $this->refer = $refer;
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

            // Prepare response data
            $data = [
                'user' => $user,
                'wallet' => $wallet,
                'profile' => $profile,
                'token' => $token,
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

            // Perform environment-specific actions
            if (env('APP_ENV') !== 'localenv') {
                $data['profile'] = setProfile($user);
                activityLog($user, 'login', "{$user->name} logged in", 'regular');
            }

            return response()->json([
                'message' => 'Login successful',
                'status' => true,
                'data' => $data,
            ], 200);
        } catch (Throwable) {
            throw new BadRequestException('Error processing request');
        }
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
            $user = $this->auth->findUserWithRoleById($otp->user_id);

            // Delete OTP after successful verification
            $this->auth->deleteOtp($otp);

            return response()->json([
                'status' => true,
                'message' => 'Email verified successfully',
                'data' => $user,
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
}
