<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SafeLockValidator
{
    public static function validateSafeFund($request)
    {
        $validationRules = [
            'amount' => 'required|numeric|min:1000',
            'duration' => 'required|numeric',
            'source' => 'required|string|in:wallet,card'
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
