<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Services\SurveyService;
use Illuminate\Http\Request;


class SurveyController extends Controller
{
    protected $survey;
    public function __construct(SurveyService $survey)
    {
        $this->survey = $survey;
    }

    public function survey()
    {
        return $this->survey->getLists();
    }


    public function storeSurvey(Request $request)
    {
        return $this->survey->createUserLists($request);
    }

    public function markWelcome()
    {
        return $this->survey->markWelcomeAsDone();
    }
}
