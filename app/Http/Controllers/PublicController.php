<?php

namespace App\Http\Controllers;

use App\Services\JobListingService;
use App\Services\JobService;
use Illuminate\Http\Request;

class PublicController extends Controller
{

    protected $jobService;
    protected $jobListingService;
    public function __construct(JobService $jobService,
    JobListingService $jobListingService)
    {
        $this->jobService = $jobService;
        $this->jobListingService = $jobListingService;
    }




    public function publicTasks(Request $request)
    {
        return $this->jobService->publicTasks($request);
    }

    public function taskDetails($jobId)
    {
        return $this->jobService->taskDetails($jobId);
    }

     public function publicJobs(Request $request)
    {
        return $this->jobListingService->publicJobs($request);
    }

    public function jobDetails($jobId)
    {
        return $this->jobListingService->jobDetails($jobId);
    }

      public function getCategories()
    {
        return $this->jobService->getTaskCategories();
    }
}
