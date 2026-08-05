<?php

namespace App\Http\Controllers;

use App\Services\JobListingService;
use App\Services\JobService;
use App\Services\BannerService;
use App\Services\HireWorkerService;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Services\CareerProfileService;

class PublicController extends Controller
{

    protected $jobService;
    protected $jobListingService;
    protected $notificationService;
    protected $bannerService;
    protected $hireWorkerService;
    protected $careerProfileService;


    public function __construct(
        JobService $jobService,
        JobListingService $jobListingService,
        NotificationService $notificationService,
        BannerService $bannerService,
        HireWorkerService $hireWorkerService,
        CareerProfileService $careerProfileService
    ) {
        $this->jobService = $jobService;
        $this->jobListingService = $jobListingService;
        $this->notificationService = $notificationService;
        $this->bannerService = $bannerService;
        $this->hireWorkerService = $hireWorkerService;
        $this->careerProfileService = $careerProfileService;
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

    public function jobDetails($slug)
    {
        return $this->jobListingService->jobDetails($slug);
    }

    public function getCategories()
    {
        return $this->jobService->getTaskCategories();
    }

    public function clickAdCount($bannerId)
    {
        return $this->bannerService->adViewPublic($bannerId);
    }

    public function publicWorkers(Request $request)
    {
        return $this->hireWorkerService->getWorkersPublic($request);
    }

    public function publicWorkerDetails($id)
    {
        return $this->hireWorkerService->getWorkerPublic($id);
    }

    public function sendNotification(Request $request)
    {
        $validationRules = [
            'user_id' => 'required|integer',
            'title' => 'required|string',
            'body' => 'required|string',
            'type' => 'required|string',
            'api_token' => 'required|string',
        ];
        $validator = Validator::make($request->all(), $validationRules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        if ($request->api_token !== config('services.public_api.token')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        $send = $this->notificationService->createPublicNotification(
            $request->user_id,
            $request->title,
            $request->body,
            $request->type
        );
        if ($send) {
            return response()->json([
                'status' => true,
                'message' => 'Notification sent successfully.'
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send notification.'
            ], 500);
        }
    }

    public function careerProfile(string $slug)
    {
        return $this->careerProfileService->getPublic($slug);
    }

    public function careerCategory(Request $request, string $category)
    {
        return $this->careerProfileService->categoryIndex($category, $request->query('page', 1));
    }

    public function careerSkillPage(Request $request, string $skill)
    {
        return $this->careerProfileService->skillPage($skill, $request->query('page', 1));
    }

    public function careerUniversityPage(Request $request, string $university)
    {
        return $this->careerProfileService->universityPage($university, $request->query('page', 1));
    }
}
