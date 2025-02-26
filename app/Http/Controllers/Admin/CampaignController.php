<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Admin\AdminCampaignService;

class CampaignController extends Controller
{
    protected $campaign;
    public function __construct(AdminCampaignService $campaign)
    {
        $this->middleware("isAdmin");
        $this->campaign = $campaign;
    }

    public function campaignDecision(Request $request)
    {
        return $this->campaign->approveOrDeclineCampaign($request);
    }

    public function getCampaignsByAdmin(Request $request)
    {
        return $this->campaign->getCampaigns($request);
    }

}
