<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Repositories\AuthRepositoryModel;
use Throwable;

class  UserService
{
    protected $user, $auth;
    public function __construct(AuthRepositoryModel $auth,)
    {
        $this->auth = $auth;
    }
    public function userDetails()
    {
        try {
            $user = $this->auth->findUser(Auth::user()->email);

            $data['user'] = $this->auth->findUserWithRole(Auth::user()->email);
            $data['wallet'] = $user->wallet;
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
