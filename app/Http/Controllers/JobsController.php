<?php

namespace App\Http\Controllers;

use App\Models\CampaignWorker;
use App\Services\JobService;
use Illuminate\Http\Request;

class JobsController extends Controller
{

    protected $jobService;
    public function __construct(JobService $jobService)
    {
        $this->middleware("isUser");
        $this->jobService = $jobService;
    }


    public function myJobs(Request $request)
    {
        return $this->jobService->myJobs($request);
    }

    public function availableJobs(Request $request)
    {
        return $this->jobService->availableJobs($request);
    }
    public function jobDetails($jobId)
    {

        return $this->jobService->myJobDetails($jobId);
    }

    public function createDispute(Request $request)
    {

        return $this->jobService->createDispute($request);
    }
}
