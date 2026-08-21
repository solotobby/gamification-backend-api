<?php

namespace App\Http\Controllers;

use App\Services\HireWorkerService;
use App\Services\JobListingService;
use Illuminate\Http\Request;

class HireWorkerController extends Controller
{
    protected $hireWorkerService;
    protected $jobListingService;

    public function __construct(
        HireWorkerService $hireWorkerService,
        JobListingService $jobListingService
    ) {
        $this->hireWorkerService = $hireWorkerService;
        $this->jobListingService = $jobListingService;
    }

    // GET /hire-workers/filters
    public function filters()
    {
        return $this->hireWorkerService->getFilters();
    }

    // GET /hire-workers
    public function index(Request $request)
    {
        return $this->hireWorkerService->getWorkers($request);
    }



    // GET /hire-workers/{id}
    public function show($id)
    {
        return $this->hireWorkerService->getWorker($id);
    }

    // POST /hire-workers/{id}/purchase-point
    public function purchasePoint($id)
    {
        return $this->hireWorkerService->purchasePoint($id);
    }

    // GET /hire-workers/my-skill
    public function mySkill()
    {
        return $this->hireWorkerService->getMySkill();
    }

    // PUT /hire-workers/{id}/edit
    public function update(Request $request, $id)
    {
        return $this->hireWorkerService->updateSkillAsset($request, $id);
    }

    // POST /hire-workers
    public function store(Request $request)
    {
        return $this->hireWorkerService->createSkillAsset($request);
    }

    // GET /hire-workers/purchased
    public function purchased()
    {
        return $this->hireWorkerService->getPurchasedWorkers();
    }
}
