<?php

namespace App\Http\Controllers;

use App\Services\JobListingService;
use Illuminate\Http\Request;

class RemoteJobController extends Controller
{
    protected $jobService;

    public function __construct(JobListingService $jobService)
    {
        $this->jobService = $jobService;
    }

    public function index(Request $request)
    {
        return $this->jobService->getJobListings($request);
    }

    public function show($id)
    {
        return $this->jobService->getJob($id);
    }

    public function apply(Request $request, $id)
    {
        return $this->jobService->applyForJob($request, $id);
    }

    public function purchasePoint($id)
    {
        return $this->jobService->purchasePoint($id);
    }
}
