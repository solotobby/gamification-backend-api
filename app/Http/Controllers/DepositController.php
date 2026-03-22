<?php

namespace App\Http\Controllers;

use App\Services\DepositService;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function __construct(protected DepositService $depositService)
    {
        $this->middleware('auth');
    }

    public function initiate(Request $request)
    {
        return $this->depositService->initiateDeposit($request);
    }
}
