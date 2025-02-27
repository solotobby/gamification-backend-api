<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TicketValidator
{
    public static function validateTicketCreation($request)
    {
        $validationRules = [
            'subject' => 'required|string',
            'message' => 'required|string',
            'proof' => 'nullable|image|mimes:png,jpeg,gif,jpg',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
