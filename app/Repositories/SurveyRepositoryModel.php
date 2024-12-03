<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Profile;
use App\Models\Preference;
use Illuminate\Support\Facades\DB;


class SurveyRepositoryModel
{

    public function listAllInterest()
    {
        return Preference::orderBy('name', 'ASC')->get();
    }

    public function updateUserAgeAndGender($data)
    {
        $user = User::where('id', $data->id)->first();

        $user->age_range = $data->age_range;
        $user->gender = $data->gender;
        $user->save();
        return $user;
    }

    public function addUserInterest($user, $data)
    {
        foreach ($data as $int) {
            DB::table('user_interest')->insert([
                'user_id' => $user->id,
                'preference_id' => $int,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    public function markWelcome($userId)
    {
        $profile = Profile::where(
            'user_id',
            $userId
        )->first();

        $profile->is_welcome = 1;
        $profile->save();
        return true;
    }
}
