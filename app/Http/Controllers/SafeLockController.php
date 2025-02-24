<?php

namespace App\Http\Controllers;

use App\Helpers\PaystackHelpers;
use App\Mail\GeneralMail;
use App\Models\BankInformation;
use App\Models\PaymentTransaction;
use App\Models\SafeLock;
use App\Services\SafeLockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SafeLockController extends Controller
{
    protected $safeLockService;
    public function __construct(SafeLockService $safeLockService)
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

    public function redeemSafelock($safelock_id){
        return $this->safeLockService->redeemSafelock($safelock_id);
    }
}
