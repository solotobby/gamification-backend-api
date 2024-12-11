<?php

namespace App\Validators\Admin;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CurrencyValidator
{
    public static function validateUpdateCurrency($request)
    {
        $validationRules = [
            'id' => 'required|exists:currencies,id',
            'base_rate' => 'nullable|numeric',
            'referral_commission' => 'nullable|numeric',
            'upgrade_fee' => 'nullable|numeric',
            'priotize' => 'nullable|numeric',
            'allow_upload' => 'nullable|numeric',
            'min_upgrade_amount' => 'nullable|numeric|min:0',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
