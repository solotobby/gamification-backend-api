<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WalletValidator
{
    public static function fundWalletValidation($request)
    {
        $validationRules = [
            'amount' => 'required|numeric|min:0.01',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
