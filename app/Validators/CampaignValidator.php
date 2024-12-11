<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CampaignValidator
{
    public static function validateCampaignCreation($request){
        $validationRules = [
            'description' => 'required|string',
            'proof' => 'required|string',
            'post_title' => 'required|string',
            'post_link' => 'required|string',
            'number_of_staff' => 'required',
            'campaign_amount' => 'required',
            'validate' => 'required',
            'campaign_type' => 'required|numeric',
            'campaign_subcategory' => 'required|numeric',
            'priotize' => 'required|boolean',
            'allow_upload' => 'required|boolean'
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
