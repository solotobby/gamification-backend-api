<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BannerValidator
{
    public function createBannerValidator($request)
    {
        $validationRules = [
            'banner_url' => 'required|image|mimes:png,jpeg,gif,jpg',
            'count' => 'required|array|min:5',
            'external_link' => 'required|string',
            'budget' => 'required|numeric',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
