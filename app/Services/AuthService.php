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

            // return $user;
            if ($user) {
                // Get user role
                $role = $user->getRoleNames();
                if ($role->isEmpty()) {
                    // Assign role if not already assigned
                    $user->assignRole('regular');
                }
                if ($user->referral_code == null) {
                    // Assign referral code if not already assigned
                    $this->refer->addReferralCode($user);
                }

                // Check for password
                $validatePassword  = $this->auth->validatePassword($request->password, $user->password);
                if ($validatePassword) {
                    $data['user'] = $this->auth->findUserWithRole($request->email);
                    $data['token'] = $user->createToken('freebyz')->accessToken;
                    if (env('APP_ENV') != 'localenv') {
                        $data['profile'] = setProfile($user); //set profile page
                        activityLog($user, 'login', $user->name . ' Logged In', 'regular');
                    }
                    return response()->json(['message' => 'Login  successful', 'status' => true, 'data' => $data], 200);
                } else {
                    throw new BadRequestException('Incorrect Login Details');
                }
            } else {
                throw new BadRequestException('Incorrect Login Details');
            }
        } catch (\Exception  $exception) {
            throw new NotFoundException('User Not Found');
        }
    }
    public function createUser($payload)
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
