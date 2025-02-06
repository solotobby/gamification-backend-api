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
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function getAccountNameValidator($request)
    {
        $validationRules = [
            'account_number' => 'required|numeric',
            'bank_code' => 'required|string'
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function createBankDetailsValidator($request)
    {
        $validationRules = [
            'account_number' => 'required|numeric',
            'bank_code' => 'required|string',
            'account_name' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
