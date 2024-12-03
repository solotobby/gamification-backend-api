<?php

namespace App\Services;


use App\Validators\AuthValidator;
use App\Exceptions\BadRequestException;
use App\Repositories\SurveyRepositoryModel;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SurveyService
{
    private  $survey;

    public function __construct(
        AuthValidator $validator,
        SurveyRepositoryModel $survey,

    ) {
        $this->validator = $validator;
        $this->survey = $survey;
    }

    public function getLists()
    {
        try {
            $user = Auth::user();

            // Check if the user has already selected interests
            if ($user->interests()->exists()) {
                return response()->json([
                    'status' => false,
                    'message' => 'User already has interests selected'
                ], 400);
            }

            // Fetch interests sorted by name
            $interests = $this->survey->listAllInterest();

            return response()->json([
                'status' => true,
                'message' => 'List of interests retrieved successfully',
                'data' => $interests
            ], 200);
        } catch (Throwable) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing request',
            ], 500);
        }
    }
}
