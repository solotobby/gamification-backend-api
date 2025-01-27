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

        return true;
        // LoginPoints::create(['user_id' => $user->id, 'date' => $date, 'point' => '100']);
    }

    public function createLogForLogin($user)
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'login',
            'description' =>  $user->name . ' logged in',
            'user_type' => 'regular'
        ]);
        // LoginPoints::create(['user_id' => $user->id, 'date' => $date, 'point' => '100']);
        return true;
    }

    public function activityLogForRegistration($user)
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'account_creation',
            'description' => $user->name . ' Registered ',
            'user_type' => 'regular'
        ]);
        return true;
    }

    public function createLogForJobCreation($user, $currency, $unitPrice)
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'campaign_submission',
            'description' =>  $user->name . ' submitted a campaign of '.$currency->code. number_format($unitPrice),
            'user_type' => 'regular'
        ]);

        return true;
    }

    public function createLogForReferral($user)
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'activity_type' => 'account_verification',
            'description' =>  $user->name . ' account verification',
            'user_type' => 'regular'
        ]);

        return true;
    }

}
