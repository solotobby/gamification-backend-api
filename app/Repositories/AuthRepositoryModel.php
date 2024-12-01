<?php

namespace App\Repositories;

use App\Models\OTP;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthRepositoryModel
{
    public function createUser($request)
    {
        $user = User::create([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'country' => $request->country,
            'phone' => $request->phone,
            'source' => $request->source,
            'password' => Hash::make($request->password),
        ]);

        // Assign the 'regular' role to the user
        $user->assignRole('regular');

        // Generate a referral code
        $user->referral_code = Str::random(7);
        $user->save();

        return $user;
    }

    public function updateUserVerificationStatus($id)
    {
        $user = User::find($id);
        $user->email_verified_at = now();
        $user->save();
        return $user;
    }

    public function findUser($email)
    {
        $user = User::where('email', $email)->first();
        return $user;
    }

    public function findUserWithRole($email)
    {
        $user =  User::with(['roles'])->where('email', $email)->first();
        return $user;
    }
    public function generateOTP($user)
    {
        $startTime = now();
        $convertedTime = $startTime->addMinutes(2);
        $otpCode = random_int(100000, 999999);

        OTP::create([
            'user_id' => $user->id,
            'pinId' => $convertedTime,
            'phone_number' => $user->phone ?? '1234567890',
            'otp' => $otpCode,
            'is_verified' => false,
        ]);
        return $otpCode;
    }

    public function findOtp($otp)
    {
        return OTP::where('otp', $otp)->first();
    }

    public function deleteOtp(OTP $otp)
    {
        $otp->delete();
    }

    public function updateOrCreateProfile($userId, $data)
    {
        return Profile::updateOrCreate(['user_id' => $userId], $data);
    }

    public function findUserWithRoleById($userId)
    {
        return User::with(['roles'])->find($userId);
    }

    public function validatePassword($requestPassword, $userPassword)
    {
        return Hash::check($requestPassword, $userPassword);
    }

    public function createToken($email)
    {
        $token = Str::random(64);
        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => now()
        ]);
        return $token;
    }
}
