<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CampaignService;

class CampaignController extends Controller
{
    protected $campaign;
    public function __construct(CampaignService $campaign)
    {
        $this->middleware('isUser');
        $this->campaign = $campaign;
    }


    public function getCategories()
    {
        return $this->campaign->getCategories();
    }


    public function getCampaigns()
    {
        return $this->campaign->getCampaigns();
    }

    public function updateCampaign(Request $request)
    {
        return $this->campaign->updateCampaign($request);
    }

    public function postCampaign(Request $request)
    {
        return $this->campaign->create($request);
    }

    public function viewCampaign($job_id)
    {
        return $this->campaign->viewCampaign($job_id);
    }


    public function activities($id)
    {
        return $this->campaign->activities($id);
    }
}
