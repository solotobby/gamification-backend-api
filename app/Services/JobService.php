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

    public function availableJobs($request)
    {
        try {
            $user = auth()->user();
            $subCategory = strtolower($request->query('subcategory_id'));
            $page = strtolower($request->query('page'));

            $jobs = $this->jobModel->availableJobs($user->id, $subCategory, $page);
           // return $jobs;
            $data = [];
            foreach ($jobs as $key => $value) {
                $count = $value->pending_count + $value->completed_count;
                $div = $count / $value->number_of_staff;
                $progress = $div * 100;

                $data[] = [
                    'id' => $value->id,
                    'job_id' => $value->job_id,
                    'campaign_amount' => $value->campaign_amount,
                    'post_title' => $value->post_title,
                    'number_of_staff' => $value->number_of_staff,
                    'type' => $value->campaignType->name,
                    'category' => $value->campaignCategory->name,
                    'completed' => $count,
                    'is_completed' => $count >= $value->number_of_staff ? true : false,
                    'progress' => $progress,
                    'currency' => $value->currency,
                    'created_at' => $value->created_at
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

            // if(empty )

        } catch (Throwable $e) {
        }
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

    public function myJobDetails($jobId)
    {
        try {
            $user = auth()->user();

            $job = $this->jobModel->getJobById($jobId);

            return $job;
            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found.',
                ], 404);
            }
            // Retrieve campaign details
            $campaign = $this->campaignModel->getCampaignById($job->campaign_id);
            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Jobs not found.',
                ], 404);
            }
            //return $job;

            // Prepare response data
            $data = [
                'job_id' => $job->id,
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->post_title,
                'campaign_description' => $campaign->description,
                'proof_of_completion' => $campaign->proof,
                'worker_name' => $user->name,
                'worker_id' => $job->user_id,
                'worker_proof' => $job->comment,
                'worker_proof_url' => $job->proof_url,
                'job_status' => $job->status,
                'approval_or_denial_reason' => $job->reason,
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
                'has_dispute' => (bool) $job->is_dispute,
                'dispute_resolved' => (bool) $job->is_dispute_resolved,
            ];
            return response()->json([
                'status' => true,
                'message' => 'Job details retrieved successfully',
                'data' => $data
            ], 200);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function createDispute($request)
    {
        try {
            $user = auth()->user();

            $job = $this->jobModel->getJobById($request->job_id, $user->id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found.',
                ], 404);
            }

            // Prevent duplicate actions on already processed jobs
            if ($job->status !== 'Denied') {
                return response()->json([
                    'status' => false,
                    'message' => 'Dispute action cannot be performed.',
                ], 400);
            }
            if ($job->is_dispute === 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'A dispute has already been lodged for this job, so the action cannot be performed again.',
                ], 400);
            }

            // return $request->reason;
            //create dispute

            $this->jobModel->createDisputeOnWorker($job->id);
            $this->jobModel->createDispute($job, $request->reason, $request->job_proof);

            return response()->json([
                'status' => true,
                'message' => 'Dispute created successfully',

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
