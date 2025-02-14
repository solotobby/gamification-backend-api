<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BannerValidator
{
    public function createBannerValidator($request)
    {
        $validationRules = [
            'banner_image' => 'required|image|mimes:png,jpeg,gif,jpg',
            'external_link' => 'required|string',
            'audience' => 'required|array|min:5',
            'budget' => 'required|numeric',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
