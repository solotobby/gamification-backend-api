<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    protected $walletService;
    public function __construct(WalletService $walletService)
    {
        $this->middleware('auth');
        $this->walletService = $walletService;
    }


    public function processWithdrawals(Request $request)
    {
        return $this->walletService->processWithdrawals($request);
    }

    public function getUserWithdrawals()
    {
        return $this->walletService->getWithdrawals();
    }
}
