<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\ActivityLog;

class LogRepositoryModel
{
    public function createLogForSurvey($user)
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'survey_points',
            'description' =>  getInitials($user->name) . ' earned 100 points for taking freebyz survey',
            'user_type' => 'regular'
        ]);
        // LoginPoints::create(['user_id' => $user->id, 'date' => $date, 'point' => '100']);
    }
}
