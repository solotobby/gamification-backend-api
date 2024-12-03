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

        $request->validate([
            'interest' => 'required|array|min:2',
            'age_range' => 'required|string',
            'gender' => 'required|string'
        ]);


        try {

            $user = User::where('id', auth()->user()->id)->first();

            $user->age_range = $request->age_range;
            $user->gender = $request->gender;
            $user->save();

            foreach ($request->interest as $int) {
                \DB::table('user_interest')->insert(['user_id' => $user->id, 'preference_id' => $int, 'created_at' => now(), 'updated_at' => now()]);
            }
            // $date = \Carbon\Carbon::today()->toDateString();

            ActivityLog::create(['user_id' => $user->id, 'activity_type' => 'survey_points', 'description' =>  getInitials($user->name) . ' earned 100 points for taking freebyz survey', 'user_type' => 'regular']);
            // LoginPoints::create(['user_id' => $user->id, 'date' => $date, 'point' => '100']);

        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }

        return response()->json(['status' => true, 'message' => 'Interest Created Successfully'], 201);


        // return view('user.survey.completed');

    }
}
