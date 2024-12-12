<?php

namespace App\Services;

use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Validators\SurveyValidator;
use App\Repositories\SurveyRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use Illuminate\Support\Facades\Auth;
use App\Repositories\LogRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use Throwable;

class SurveyService
{
    private  $validator, $survey, $auth, $log, $currency, $wallet;

    public function __construct(
        SurveyValidator $validator,
        SurveyRepositoryModel $survey,
        AuthRepositoryModel $auth,
        LogRepositoryModel $log,
        CurrencyRepositoryModel $currency,
        WalletRepositoryModel $wallet,

    ) {
        $this->validator = $validator;
        $this->survey = $survey;
        $this->auth = $auth;
        $this->log = $log;
        $this->currency = $currency;
        $this->wallet = $wallet;
    }

    public function getLists()
    {
        try {
            $user = Auth::user();

            // Check if the user has already selected interests
            // if ($user->interests()->exists()) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'User already has interests selected'
            //     ], 400);
            // }

            // Fetch interests sorted by name
            $data['interests'] = $this->survey->listAllInterest();
            $data['currency'] = $this->currency->getActiveCurrenciesList();

            return response()->json([
                'status' => true,
                'message' => 'List of interests retrieved successfully',
                'data' => $data
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

            $this->survey->updateUserAgeAndGender($user);
            // Save User Interest
            $this->survey->addUserInterest($user, $request->interest);

            $this->wallet->updateWalletBaseCurrency($user, $request->currency);
            //    Log Activities
            $this->log->createLogForSurvey($user);

            return response()->json([
                'status' => true,
                'message' => 'Interest Created Successfully'
            ], 201);
        } catch (Throwable $e) {
            // return $e;
            return response()->json([
                'status' => false,
                'message' => 'Error processing request'
            ], 500);
        }
    }
}
