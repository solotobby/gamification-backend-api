<?php

namespace App\Http\Controllers;

use App\Services\BankService;
use App\Services\WalletService;
use Illuminate\Http\Request;


class WalletController extends Controller
{
    protected $walletService;
    protected $bankService;
    public function __construct(
        WalletService $walletService,
        BankService $bankService
    ) {
        $this->middleware('auth');
        $this->walletService = $walletService;
        $this->bankService = $bankService;
    }

    public function fundWallet(Request $request)
    {
        return $this->walletService->fundWallet($request);
    }

    public function getUserTransactions()
    {
        return $this->walletService->getTransactions();
    }

    public function getBankLists()
    {
        return $this->bankService->getBankList();
    }

    public function getUserBankDetails()
    {
        return $this->bankService->getUserBankDetails();
    }

    public function getAccountName(Request $request)
    {
        return $this->bankService->getAccountDetails($request);
    }

    public function createBankDetails(Request $request)
    {
        return $this->bankService->saveUserAccountDetails($request);
    }
}
