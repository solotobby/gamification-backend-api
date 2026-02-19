<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\BankRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use Throwable;

class  UserService
{
    protected $user;
    protected $auth;
    protected $bank;
    protected $wallet;
    public function __construct(
        AuthRepositoryModel $auth,
        WalletRepositoryModel $wallet,
        BankRepositoryModel $bank
    ) {
        $this->auth = $auth;
        $this->bank = $bank;
        $this->wallet = $wallet;
    }
    public function userDetails()
    {
        try {
            $user = $this->auth->findUser(Auth::user()->email);

            $data['user'] = $this->auth->findUserWithRole(Auth::user()->email);
            $data['wallet'] = $this->wallet->walletDetails($user);
            $data['dashboard'] = $this->auth->dashboardStat($user->id);
            $data['profile'] = setProfile($user);
            $data['virtual_account'] = $this->bank->getVirtualBank($user->id);
            return response()->json([
                'status' => true,
                'message' => 'User Details Successfully Retrieved',
                'data' => $data
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }
}
