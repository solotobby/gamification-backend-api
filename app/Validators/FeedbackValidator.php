<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FeedbackValidator
{
    public static function validateFeedbackCreation($request)
    {
        $validationRules = [
            'category'  => 'required|string|in:transfer_issue,complaint,feedback,others,report',
            'message'   => 'required|string',
            'proof'     => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateMessageSending($request)
    {
        $validationRules = [
            'message'   => 'required_without:image|nullable|string',
            'image'     => 'required_without:message|nullable|string',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
