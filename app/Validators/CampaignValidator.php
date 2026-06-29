<?php

namespace App\Validators;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CampaignValidator
{
    // public static function validateCampaignCreation($request)
    // {
    //     $validationRules = [
    //         'description' => 'required|string',
    //         'proof' => 'required|string',
    //         'post_title' => 'required|string',
    //         'post_link' => 'required|string',
    //         'number_of_staff' => 'required|string',
    //         'campaign_amount' => 'required|string',
    //         'validate' => 'required|boolean',
    //         'campaign_type' => 'required|numeric',
    //         'campaign_subcategory' => 'required|numeric',
    //         'priotize' => 'required|boolean',
    //         'allow_upload' => 'required|boolean',
    //         'expected_result_image' => 'nullable|string',
    //         'approval_time' => 'required|numeric|in:24,36,48,56,72'
    //     ];
    //     $validator = Validator::make($request->all(), $validationRules);

    //     if ($validator->fails()) {
    //         throw new ValidationException($validator);
    //     }
    // }

    public static function validateCampaignCreation($request)
    {
        $validationRules = [
            'description' => 'required|string',
            'proof' => 'required|string',
            'post_title' => 'required|string',
            'post_link' => 'required|string',
            'number_of_staff' => 'required|numeric|min:10',
            'campaign_amount' => 'required|string',
            'validate' => 'required|boolean',
            'campaign_type' => 'required',
            'campaign_subcategory' => 'required',
            'priotize' => 'required|boolean',
            'allow_upload' => 'required|boolean',
            'expected_result_image' => 'nullable|string',
            'expected_result_file' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp',
            'approval_time' => 'required|numeric|in:24,36,48,56,72'
        ];

        $validator = Validator::make(
            $request->all(),
            $validationRules,
            [
                'number_of_staff.min' => 'Number of workers must not be less than 10.'
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateCampaignUpdating($request)
    {
        $validationRules = [
            'new_worker_number' => 'required|string',
            'campaign_id' => 'required|string|exists:campaigns,job_id',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function validateJobRating($request)
    {
        $validationRules = [
            'campaign_id' => 'required|string|exists:campaigns,job_id',
            'job_id' => 'required|string|exists:campaign_workers,id',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string',
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
            'campaign_id' => 'required|string|exists:campaigns,job_id',
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
            'campaign_id' => 'required|string|exists:campaigns,job_id',
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
            // 'job_proof' => 'required|url',
            'reason' => 'required|string',
            'job_id' => 'required|string|exists:campaign_workers,id',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function submitJobOld($request)
    {
        $validationRules = [
            'proof' => 'sometimes|image|mimes:png,jpeg,gif,jpg|max:2048',
            'comment' => 'required|string',
            'job_id' => 'required|string|exists:campaigns,job_id',
        ];
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public static function submitJob($request, $campaign)
    {
        $validationRules = [
            'comment' => ['required', 'string'],
            'job_id' => ['required', 'string', 'exists:campaigns,job_id'],
        ];

        if ($campaign->allow_upload) {
            $validationRules['proof'] = [
                'required',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:5120', // 5MB
            ];
        } else {
            $validationRules['proof'] = [
                'sometimes',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:5120',
            ];
        }

        $validator = Validator::make(
            $request->all(),
            $validationRules,
            [
                'proof.required' => 'This task requires a proof image.',
                'proof.image' => 'Proof must be an image.',
                'proof.mimes' => 'Proof must be jpg, jpeg, png, gif or webp.',
                'proof.max' => 'Proof image cannot exceed 5MB.',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
