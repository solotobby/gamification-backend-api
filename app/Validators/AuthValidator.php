<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthValidator
{
    public static function validateRegistration($request)
    {
        // Get the current location, default to 'Nigeria' if in localenv
        $curLocation = (env('APP_ENV') != 'localenv') ? currentLocation() : 'Nigeria';

        // Common validation rules for registration
        $commonValidationRules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'ref_id' => ['nullable', 'string', 'max:255'],
        ];

        // Phone validation rules based on the location
        if ($curLocation == 'Nigeria') {
            $phoneValidationRules = ['required', 'numeric', 'digits:11', 'unique:users'];
        } else {
            $phoneValidationRules = ['required', 'numeric', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'unique:users'];
        }

        $validationRules = array_merge($commonValidationRules, [
            'phone' => $phoneValidationRules,
        ]);

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateLogin($request)
    {
        $validationRules = [
            'email' => 'required|email|max:255',
            'password' => 'required',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateResendOTP($request)
    {
        $validationRules = [
            'email' => 'required|email|max:255',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateOTP($request)
    {
        $validationRules = [
            'otp' => 'required|numeric|digits:6',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateResetPasswordLink($request){
        $validationRules = [
            'email' => 'required|email|max:255',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateResetPassword($request){
        $validationRules = [
                'token' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
