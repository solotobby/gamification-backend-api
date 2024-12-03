<?php

namespace App\Services;


use App\Validators\SurveyValidator;
use App\Repositories\SurveyRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use Illuminate\Support\Facades\Auth;
use App\Repositories\LogRepositoryModel;
use Throwable;

class SurveyService
{
    private  $validator, $survey, $auth, $log;

    public function __construct(
        SurveyValidator $validator,
        SurveyRepositoryModel $survey,
        AuthRepositoryModel $auth,
        LogRepositoryModel $log,

    ) {
        $this->validator = $validator;
        $this->survey = $survey;
        $this->auth = $auth;
        $this->log = $log;
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

    public function markWelcomeAsDone()
    {
        $user = $this->auth->findUser(Auth::user()->email);

        // mark welcome done
        $this->survey->markWelcome($user->id);
        return response()->json([
            'status' => true,
            'message' => 'User welcome status successfully done'
        ], 200);
    }

    public function createUserLists($request)
    {

        $this->validator->validateSurveyCreation($request);

        try {
            $user = $this->auth->findUser(Auth::user()->email);

            // check if survey already done
            $check = $this->auth->dashboardStat($user->id);
            
            if (is_array($check) && isset($check['is_survey']) && $check['is_survey'] == 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'User Interest already submitted'
                ], 200);
            }
            $this->survey->updateUserAgeAndGender($user);
            // Save User Interest
            $this->survey->addUserInterest($user, $request->interest);

            //    Log Activities
            $this->log->createLogForSurvey($user);

            return response()->json([
                'status' => true,
                'message' => 'Interest Created Successfully'
            ], 201);
        } catch (Throwable) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing request'
            ], 500);
        }
    }
}
