<?php

namespace App\Http\Controllers;

use App\Services\HireWorkerService;
use Illuminate\Http\Request;

class HireWorkerController extends Controller
{
    protected $hireWorkerService;

    public function __construct(HireWorkerService $hireWorkerService)
    {
        $this->hireWorkerService = $hireWorkerService;
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
}
