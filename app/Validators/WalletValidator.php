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

    public static function processWithdrawalValidation($request)
    {
        $validationRules = [
            'amount' => 'required|numeric',
            'option' => 'nullable',
            'paypal_email' => 'nullable|email'
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function AdminDecisionOnWithdrawal($request)
    {
        $validationRules = [
            'user_id' => 'required|string|exists:users,id',
            'withdrawal_id' => 'required|string|exists:campaigns,id',
            'decision' => 'required|string|in:approve,decline'
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
