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
            $campaign = $this->campaignModel->getCampaignByJobId($request->campaign_id, $request->user_id);
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

    public function getCampaigns($request)
    {
        try {

            $type = strtolower($request->query('type'));
            // Fetch campaigns
            $campaigns = $this->campaignModel->getCampaignsByAdmin( $type);

            // Prepare campaign data
            $data = [];
            foreach ($campaigns as $campaign) {
                $unitPrice = $campaign->campaign_amount;
                $totalAmount = $campaign->total_amount;

                $data[] = [
                    'id' => $campaign->id,
                    'user_id' => $campaign->user_id,
                    'user_name' => $campaign->user->name ?? null,
                    'campaign_id' => $campaign->job_id,
                    'title' => $campaign->post_title,
                    'approved' => $campaign->completed_count . '/' . $campaign->number_of_staff,
                    'unit_price' => round($unitPrice, 5),
                    'total_amount' => round($totalAmount, 5),
                    'currency' => $campaign->currency,
                    'status' => $campaign->status,
                    'created' => $campaign->created_at,
                ];
            }
            $pagination = [
                'total' => $campaigns->total(),
                'per_page' => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'from' => $campaigns->firstItem(),
                'to' => $campaigns->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Campaign List',
                'data' => $data,
                'pagination' => $pagination,
            ], 200);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }
}
