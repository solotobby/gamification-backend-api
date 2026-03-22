<?php

namespace App\Http\Controllers;

use App\Services\ManualVerificationService;
use Illuminate\Http\Request;

class ManualVerificationController extends Controller
{
    public function __construct(protected ManualVerificationService $service)
    {
        $this->middleware('auth');
    }

    public function submit(Request $request)
    {
        return $this->service->submit($request);
    }

    public function review(Request $request, int $id)
    {
        $request->validate(['action' => 'required|in:approve,reject', 'note' => 'nullable|string']);
        return $this->service->review($id, $request->action, $request->note);
    }
}
