<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BannerValidator
{
    public function createBannerValidator($request)
    {
        $validationRules = [
            'banner_image' => 'required|file|mimes:jpg,jpeg,png,gif,heic|max:5120',
            'external_link' => 'required|string|url',
            // 'audience' => 'required|array|min:5',
            'budget' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public function toggleBannerValidator($request)
    {
        $validationRules = [
            'action' => 'required|string|in:activate,deactivate',
            'banner_id' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
