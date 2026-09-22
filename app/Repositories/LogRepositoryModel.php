<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Services\Logging\ActivityLoggerService;

class LogRepositoryModel
{
    public function createLogForSurvey($user)
    {
        return ActivityLoggerService::log(
            $user,
            'survey_points',
            $user->name . ' earned 100 points for taking freebyz survey',
            'regular',
            ['points' => 100]
        ) !== null;
    }

    public function createLogForLogin($user, ?string $loginMethod = 'email')
    {
        return ActivityLoggerService::log(
            $user,
            'login',
            $user->name . ' logged in' . ($loginMethod ? " ({$loginMethod})" : ''),
            'regular',
            ['login_method' => $loginMethod]
        ) !== null;
    }

    public function activityLogForRegistration($user)
    {
        return ActivityLoggerService::log(
            $user,
            'account_creation',
            $user->name . ' Registered',
            'regular',
            ['email' => $user->email, 'country' => $user->country ?? null]
        ) !== null;
    }

    public function createLogForJobCreation($user, $currency, $unitPrice)
    {
        $currencyCode = is_object($currency) ? ($currency->code ?? 'NGN') : $currency;
        return ActivityLoggerService::log(
            $user,
            'campaign_submission',
            $user->name . ' submitted a campaign of ' . $currencyCode . ' ' . number_format((float) $unitPrice, 2),
            'regular',
            ['currency' => $currencyCode, 'unit_price' => $unitPrice]
        ) !== null;
    }

    public function createLogForReferral($user)
    {
        return ActivityLoggerService::log(
            $user,
            'account_verification',
            $user->name . ' account verification',
            'regular'
        ) !== null;
    }

    public function createLogForWithdrawal($user, $currency, $amount)
    {
        $currencyCode = is_object($currency) ? ($currency->code ?? 'NGN') : $currency;
        return ActivityLoggerService::log(
            $user,
            'withdrawal_request',
            $user->name . ' sent a withdrawal request of ' . $currencyCode . ' ' . number_format((float) $amount, 2),
            'regular',
            ['currency' => $currencyCode, 'amount' => $amount]
        ) !== null;
    }

    public function createLogForWithdrawalPayment($user, $currency, $amount)
    {
        $currencyCode = is_object($currency) ? ($currency->code ?? 'NGN') : $currency;
        return ActivityLoggerService::log(
            $user,
            'withdrawal_sent',
            $currencyCode . ' ' . number_format((float) $amount, 2) . ' cash withdrawal by ' . $user->name,
            'admin',
            ['currency' => $currencyCode, 'amount' => $amount]
        ) !== null;
    }

    public function systemNotification($user, $category, $title, $message)
    {
        return Notification::create([
            'user_id' => $user->id,
            'category' => $category,
            'title' => $title,
            'message' => $message
        ]);
    }
}
