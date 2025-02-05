<?php

namespace App\Services\Admin;

use App\Repositories\CampaignRepositoryModel;
use App\Services\CampaignService;
use App\Repositories\WalletRepositoryModel;
use App\Validators\WalletValidator;
use App\Repositories\WithdrawalRepositoryModel;
use Throwable;

class AdminWalletService
{
    protected $withdrawalModel, $validator, $walletModel;
    public function __construct(
        WalletRepositoryModel $walletModel,
        WithdrawalRepositoryModel $withdrawalModel,
        WalletValidator $validator,
    ) {
        $this->validator = $validator;
        $this->walletModel = $walletModel;
        $this->withdrawalModel = $withdrawalModel;
    }


    public function getWithdrawalByStatus($request)
    {
        try {
            $statusQuery = strtolower($request->query('status'));

            if ($statusQuery === 'paid') {
                $status = true;
            } elseif ($statusQuery === 'pending') {
                $status = false;
            } else {
                $withdrawals = $this->withdrawalModel->adminWithdrawalRequestLists(); // Fetch all withdrawals
            }

            if (isset($status)) {
                $withdrawals = $this->withdrawalModel->adminWithdrawalRequestLists($status);
            }

            $data = $withdrawals->map(function ($withdrawal) {
                return [
                    'id' => $withdrawal->id,
                    'user_id' => $withdrawal->user_id,
                    'user_name' => $withdrawal->user->name,
                    'amount' => $withdrawal->amount,
                    'currency' => $withdrawal->base_currency ?? $withdrawal->user->wallet->base_currency,
                    'payment_date' => $withdrawal->next_payment_date,
                    'withdrawal_created_at' => $withdrawal->created_at,
                    'status' => $withdrawal->status ? 'Paid' : 'Pending',
                ];
            });

            $pagination = [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
                'from' => $withdrawals->firstItem(),
                'to' => $withdrawals->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Withdrawals retrieved successfully.',
                'data' => $data,
                'pagination' => $pagination,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }


    public function approveOrDeclineWithdrawal($request)
    {
        $this->validator->AdminDecisionOnWithdrawal($request);

        try {
            $campaign = $this->campaignModel->getCampaignById($request->campaign_id, $request->user_id);
            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            if($request->decision === 'approve'){
                $decision = 'Live';
            }
            else{
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
