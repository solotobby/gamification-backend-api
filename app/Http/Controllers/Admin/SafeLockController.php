<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SafeLock;
use App\Services\Admin\AdminSafeLockService;
use Illuminate\Http\Request;

class SafeLockController extends Controller
{
    protected $admin;
    public function __construct(AdminSafeLockService $admin)
    {
        $this->middleware('auth');
        $this->admin = $admin;
    }

    public function adminGetSafeLocks()
    {
        return $this->admin->adminSafeLocks();
    }
}
