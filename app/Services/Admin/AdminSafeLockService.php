<?php

namespace App\Services\Admin;

use App\Repositories\SafeLockRepositoryModel;
use Throwable;

class AdminSafeLockService
{

    protected $safeLock;

    public function __construct(
        SafeLockRepositoryModel $safeLock,

    ) {

        $this->safeLock = $safeLock;
    }

    public function adminSafeLocks()
    {
        try {
           // $user = auth()->user();

            // Fetch SafeLocks from repository
            $safeLocks = $this->safeLock->getSafeLocksByAdmin();

            // Check if user has SafeLocks
            if ($safeLocks->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No SafeLocks found.',
                    'data' => []
                ], 404);
            }

            // Format response data
            $formattedSafeLocks = $safeLocks->map(function ($safeLock) {
                return [
                    'id' => $safeLock->id,
                    'currency' => $safeLock->currency,
                    'amount_locked' => $safeLock->amount_locked,
                    'interest' => $safeLock->interest_rate,
                    'duration' => $safeLock->duration,
                    'total_payment' => $safeLock->total_payment,
                    'start_date' => $safeLock->start_date->toDateString(),
                    'maturity_date' => $safeLock->maturity_date->toDateString(),
                    'status' => $safeLock->status,
                    'is_matured' => $safeLock->maturity_date <= now(),
                    'is_paid' => $safeLock->is_paid
                ];
            });

            $pagination = [
                'total' => $safeLocks->total(),
                'per_page' => $safeLocks->perPage(),
                'current_page' => $safeLocks->currentPage(),
                'last_page' => $safeLocks->lastPage(),
                'from' => $safeLocks->firstItem(),
                'to' => $safeLocks->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'SafeLocks retrieved successfully.',
                'data' => $formattedSafeLocks,
                'pagination' => $pagination,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
