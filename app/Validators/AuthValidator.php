<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthValidator
{
    public static function validateRegistration($request)
    {
        // Normalize the country input to lowercase
        $country = strtolower($request->input('country'));

        // Common validation rules for registration
        $validationRules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'ref_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'numeric', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'unique:users,phone']
        ];

        // Phone validation rules based on the location
        // if ($country === 'nigeria') {
        //     $validationRules['phone'] = ['required', 'numeric', 'digits:11', 'unique:users,phone'];
        // } else {
        //     $validationRules['phone'] = ['required', 'numeric', 'regex:/^([0-9\s\-\+\(\)]*)$/', 'unique:users,phone'];
        // }

        // Perform validation
        $validator = Validator::make($request->all(), $validationRules);

        // Throw a validation exception if validation fails
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }


    public static function validateLogin($request)
    {
        $validationRules = [
            'email' => 'required|email|max:255|exists:users,email',
            'password' => 'required',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            $errors = array();
            $validationErrors = json_decode(json_encode($validator->errors()), true);
            foreach ($validationErrors as $key => $error) {
                $errors[] = $error[0];
            }
            return response()->json([
                'status' => false,
                'message' => implode(',', $errors)
            ], 400);
        }
    }

    public static function validateResendOTP($request)
    {
        $validationRules = [
            'email' => 'required|email|max:255|exists:users,email',
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

    public static function validateResetPasswordLink($request)
    {
        $validationRules = [
            'email' => 'required|email|max:255|exists:users,email',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateResetPassword($request)
    {
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
