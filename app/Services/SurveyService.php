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
    protected $validator;
    protected $walletService;
    protected $survey;
    protected $auth;
    protected $log;
    protected $currency;
    protected $wallet;

    public function __construct(
        SurveyValidator $validator,
        SurveyRepositoryModel $survey,
        AuthRepositoryModel $auth,
        LogRepositoryModel $log,
        CurrencyRepositoryModel $currency,
        WalletRepositoryModel $wallet,
        WalletService $walletService,


    ) {
        $this->validator = $validator;
        $this->survey = $survey;
        $this->auth = $auth;
        $this->log = $log;
        $this->currency = $currency;
        $this->wallet = $wallet;
        $this->walletService = $walletService;
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
            $currency = $this->currency->getActiveCurrenciesList();

            $data['currency'] = [];

            foreach ($currency as $cur) {

                $data['currency'][] = [
                    'id' => $cur->id,
                    'code' => $cur->code,
                    'country' => $cur->country,
                    'is_active' => $cur->is_active,
                    'created_at' => $cur->created_at,
                    'updated_at' => $cur->updated_at
                ];
            }
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
            $user = Auth::user();

            // check if survey already done
            //   $check = $this->auth->dashboardStat($user->id);

            $this->survey->updateUserAgeAndGender($user);
            // Save User Interest
            $this->survey->addUserInterest($user, $request->interest);

            // Update user Profile base currency
            $this->wallet->updateWalletBaseCurrency($user, $request->currency);
            // Check if user is referred and update the referral amount of the currency
            if ($user->referredBy) {
                $referrer = $this->auth->findUserByReferralCode($user->referredBy->referee_id);

                $this->walletService->setReferralAmountTopPay($user, $referrer);
            }
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
