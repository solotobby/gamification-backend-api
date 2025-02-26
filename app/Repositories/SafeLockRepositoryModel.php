<?php

namespace App\Repositories;

use App\Models\SafeLock;
use Carbon\Carbon;

class SafeLockRepositoryModel
{

    public function getSafeLocksByUserId($user, $page = null)
    {
        return SafeLock::where(
            'user_id',
            $user->id
        )->orderBy(
            'created_at',
            'DESC'
        )->paginate(
            10,
            ['*'],
            'page',
            $page
        );
    }

    public function getSafeLocksByAdmin( $page = null)
    {
        return SafeLock::orderBy(
            'created_at',
            'DESC'
        )->paginate(
            50,
            ['*'],
            'page',
            $page
        );
    }
    public function createSafeLock($userId, $interestRate, $amountLocked, $currency, $duration, $interestAccrued, $totalPayment, $startDate, $maturityDate)
    {
        $data = [
            'user_id' => $userId,
            'amount_locked' => $amountLocked,
            'currency' => $currency,
            'interest_rate' => $interestRate,
            'duration' => $duration,
            'interest_accrued' => $interestAccrued,
            'total_payment' => $totalPayment,
            'start_date' => $startDate,
            'maturity_date' => $maturityDate,
        ];

        return SafeLock::create($data);
    }

    public function getSafeLockById($id)
    {
        return SafeLock::find($id);
    }

    public function updateSafeLockStatus($id, $status)
    {
        return SafeLock::where('id', $id)->update([
            'status' => $status,
            'is_paid' => true
        ]);
    }
}
