<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Admin\AdminWalletService;

class WalletController extends Controller
{
    protected $wallet;
    public function __construct(AdminWalletService $wallet,)
    {
        $this->middleware("isAdmin");
        $this->wallet = $wallet;
    }

    public function withdrawalDecision(Request $request)
    {
        return $this->wallet->approveOrDeclineWithdrawal($request);
    }

    public function withdrawalLists(Request $request)
    {
        return $this->wallet->getWithdrawalByStatus($request);
    }
}
