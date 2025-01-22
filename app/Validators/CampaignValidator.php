<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CampaignValidator
{
    public static function validateCampaignCreation($request)
    {
        $validationRules = [
            'description' => 'required|string',
            'proof' => 'required|string',
            'post_title' => 'required|string',
            'post_link' => 'required|string',
            'number_of_staff' => 'required|string',
            'campaign_amount' => 'required|string',
            'validate' => 'required|boolean',
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

    public static function validateCampaignUpdating($request)
    {
        $validationRules = [
            'new_worker_number' => 'required|string',
            'campaign_id' => 'required|string|exists:campaigns,id',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function AdminDecisionOnCampaign($request)
    {
        $validationRules = [
            'user_id' => 'required|string|exists:users,id',
            'campaign_id' => 'required|string|exists:campaigns,id',
            'decision' => 'required|string|in:approve,decline'
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function approveOrDenyReason($request)
    {
        $validationRules = [
            'action' => 'required|string|in:approve,deny',
            'reason' => 'required|string',
            'campaign_id' => 'required|string',
            'job_id' => 'required|string'
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
    public static function disputeCreation($request)
    {
        $validationRules = [
            'job_proof' => 'required|url',
            'reason' => 'required|string',
            'job_id' => 'required|string|exists:campaign_workers,id',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function submitJob($request)
    {
        $validationRules = [
            'proof' => 'sometimes|image|mimes:png,jpeg,gif,jpg',
            'comment' => 'required|string',
            'campaign_id' => 'required|string|exists:campaigns,id',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
