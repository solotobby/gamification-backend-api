<?php

namespace App\Http\Controllers;

use App\Services\SafeLockService;
use Illuminate\Http\Request;

class SafeLockController extends Controller
{
    protected $safeLockService;

    public function __construct(SafeLockService $safeLockService,
    )
    {
        $this->middleware('auth');
        $this->safeLockService = $safeLockService;

    }

    public function getSafeLocks()
    {
        return $this->safeLockService->getSafeLocks();
    }

    public function createSafeLock(Request $request)
    {
        return $this->safeLockService->createSafeLock($request);
    }

    public function redeemSafelock($safelock_id)
    {
        return $this->safeLockService->redeemSafelock($safelock_id);
    }


}
