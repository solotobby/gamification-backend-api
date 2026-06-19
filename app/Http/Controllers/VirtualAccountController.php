<?php

namespace App\Http\Controllers;

use App\Services\VirtualAccountService;
use Illuminate\Http\Request;

class VirtualAccountController extends Controller
{
    public function __construct(protected VirtualAccountService $service)
    {
        $this->middleware('auth');
    }

    public function generate()
    {
        // return $this->service->generateVirtualAccount();
        return $this->service->generateInterswitchVirtualAccount();
    }
}
