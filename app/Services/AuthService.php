<?php

namespace App\Services;

use App\Models\User;
use App\Validators\AuthValidator;
use App\Mail\GeneralMail;
use App\Mail\Welcome;
use Illuminate\Support\Facades\Mail;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\ReferralRepositoryModel;
use App\Repositories\WalletRepositoryModel;

class AuthService
{
    private $user, $validator, $auth, $wallet, $refer;

    public function __construct(
        User $user,
        AuthValidator $validator,
        AuthRepositoryModel $auth,
        WalletRepositoryModel $wallet,
        ReferralRepositoryModel $refer
    ) {
        $this->user = $user;
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
        } catch (\Exception $e) {
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
                throw new NotFoundException('User not found');
            }

            // Ensure user has a role
            $this->ensureUserHasRole($user);

            // Ensure user has a referral code
            $this->ensureUserHasReferralCode($user);

            // Validate the password
            if (!$this->auth->validatePassword($request->password, $user->password)) {
                throw new BadRequestException('Incorrect login details');
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
        } catch (NotFoundException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 404);
        } catch (BadRequestException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable) {
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
                throw new NotFoundException('User Not Found');
            }

            $otp = $this->auth->generateOTP($user);

            if (env('APP_ENV') !== 'localenv') {
                $subject = 'Freebyz Email Verification';
                $content = 'Hi, ' . $user->name . ', Your Email Verification Code is ' . $otp;
                Mail::to($user->email)->send(new GeneralMail($user, $content, $subject, ''));
            }

            return response()->json(['status' => true, 'message' => 'Email Verification code sent'], 200);
        } catch (\Throwable) {
            throw new BadRequestException('Error processing request');
        } catch (\Throwable) {
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
        } catch (\Throwable) {
            throw new BadRequestException('Error processing request');
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
