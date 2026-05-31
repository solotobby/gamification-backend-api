<?php

namespace App\Http\Controllers;

use App\Services\JobService;
use Illuminate\Http\Request;

class PublicController extends Controller
{

    protected $jobService;
    public function __construct(JobService $jobService)
    {
        $this->jobService = $jobService;
    }




    public function publicTasks(Request $request)
    {
        return $this->jobService->publicTasks($request);
    }

    public function taskDetails($jobId)
    {
        return $this->jobService->taskDetails($jobId);
    }
}
