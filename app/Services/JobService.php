<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\CampaignRepositoryModel;
use App\Repositories\JobRepositoryModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;

class JobService
{

    protected $jobModel, $authModel, $campaignModel;
    public function __construct(
        JobRepositoryModel $jobModel,
        AuthRepositoryModel $authModel,
        CampaignRepositoryModel $campaignModel
    ) {
        $this->jobModel = $jobModel;
        $this->authModel = $authModel;
        $this->campaignModel = $campaignModel;
    }

    public function myJobs($request)
    {
        try {
            $user = auth()->user();
            $type = strtolower($request->query('type'));

            // Validate the type
            $validTypes = ['completed', 'disputed', 'pending'];
            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid job type provided.',
                ], 400);
            }

            if ($type === 'completed') {
                $type = 'approved';
            }

            $jobs = [];
            if ($type === 'disputed') {
                $jobs = $this->jobModel->getDisputedJobs($user);
            } else {
                $jobs = $this->jobModel->getJobByType($user, $type);
            }

            $data = [];
            foreach ($jobs as $job) {
                $workerDetails = $this->authModel->findUserById($job->user_id);
                $campaignDetails = $this->campaignModel->getCampaignById($job->campaign_id, $user->id);

                $data[] = [
                    'id' => $job->id,
                    'worker_id' => $job->user_id,
                    'worker_name' => $workerDetails->name,
                    'campaign_id' => $job->campaign_id,
                    'campaign_name' => $campaignDetails->post_title,
                    'campaign_owner_id' => $campaignDetails->user_id,
                    'comment' => $job->comment,
                    'amount' => $job->amount,
                    'currency' => $user->wallet->base_currency,
                    'status' => $job->status,
                    'reason' => $job->reason,
                    'created_at' => $job->created_at,
                    'has_dispute' => $job->is_dispute ? true : false,
                    'is_dispute_resolved' => $job->is_dispute_resolved ? true : false,
                ];
            }

            $pagination = [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
                'from' => $jobs->firstItem(),
                'to' => $jobs->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Jobs retrieved successfully.',
                'data' => $data,
                'pagination' => $pagination,
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request',
            ], 500);
        }
    }
}
