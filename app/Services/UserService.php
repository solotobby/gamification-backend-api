<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use Throwable;

class  UserService
{
    protected $user;
    protected $auth;
    protected $wallet;
    public function __construct(
        AuthRepositoryModel $auth,
        WalletRepositoryModel $wallet
    ) {
        $this->auth = $auth;
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
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
        return response()->json([
            'status' => true,
            'message' => 'User Details Successfully Retrieved',
            'data' => $data
        ], 200);
    }
}
