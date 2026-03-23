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
        $request->validate([
            'message' => 'nullable|string|max:5000',
            'image'   => 'nullable|string', // base64
        ], [
            'message.max' => 'Message cannot exceed 5000 characters.',
        ]);

        // Custom: at least one must be present
        if (!$request->filled('message') && !$request->filled('image')) {
            abort(response()->json([
                'status'  => false,
                'message' => 'Reply must contain a message, an image, or both.',
            ], 422));
        }
    }
}
