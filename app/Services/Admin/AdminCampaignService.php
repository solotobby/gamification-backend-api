<?php

namespace App\Services\Admin;

use App\Repositories\CampaignRepositoryModel;
use App\Services\CampaignService;
use App\Repositories\WalletRepositoryModel;
use App\Validators\CampaignValidator;
use App\Repositories\Admin\CurrencyRepositoryModel;
use Throwable;

class AdminCampaignService
{
    protected $campaignModel;
    protected $campaignService;
    protected $validator;
    protected $currencyModel;
    protected $walletModel;
    public function __construct(
        CampaignRepositoryModel $campaignModel,
        CampaignValidator $validator,
        CurrencyRepositoryModel $currencyModel,
        WalletRepositoryModel $walletModel,
        CampaignService $campaignService,
    ) {
        $this->campaignModel = $campaignModel;
        $this->validator = $validator;
        $this->currencyModel = $currencyModel;
        $this->walletModel = $walletModel;
        $this->campaignService = $campaignService;
    }

    public function approveOrDeclineCampaign($request)
    {
        $this->validator->AdminDecisionOnCampaign($request);

        try {
            $campaign = $this->campaignModel->getCampaignById($request->campaign_id, $request->user_id);
            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            if ($request->decision === 'approve') {
                $decision = 'Live';
            } else {
                $decision = 'Declined';
            }
            $campaign->status = $decision;
            $campaign->save();

            return response()->json([
                'status' => true,
                'message' => 'Campaign status updated successfully to ' . $campaign->status,
                'data' => $campaign
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }
}
