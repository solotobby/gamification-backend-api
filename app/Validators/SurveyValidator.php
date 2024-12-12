<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SurveyValidator
{
    public static function validateSurveyCreation($request)
    {
        $validationRules = [
            'interest' => 'required|array|min:2',
            'age_range' => 'required|string',
            'gender' => 'required|string',
            'currency'=> 'required|numeric',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
