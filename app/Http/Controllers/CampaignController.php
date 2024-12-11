<?php

namespace App\Http\Controllers;

use App\Helpers\FacebookHelper;
use App\Helpers\PaystackHelpers;
use App\Helpers\Sendmonny;
use App\Helpers\SystemActivities;
use App\Mail\ApproveCampaign;
use App\Mail\CreateCampaign;
use App\Mail\GeneralMail;
use App\Mail\SubmitJob;
use App\Models\Campaign;
use App\Models\CampaignWorker;
use App\Models\Category;
use App\Models\DisputedJobs;
use App\Models\PaymentTransaction;
use App\Models\Rating;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
