<?php

namespace App\Services;

use App\Mail\GeneralMail;
use App\Mail\SubmitJob;
use App\Models\User;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\CampaignRepositoryModel;
use App\Repositories\JobRepositoryModel;
use App\Repositories\LogRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Validators\CampaignValidator;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\StorageAttributes;
use Throwable;
use Illuminate\Support\Facades\DB;

class JobService
{

    protected $jobModel, $currencyModel, $walletModel, $log,
        $authModel, $campaignModel, $campaignService, $validator;
    public function __construct(
        JobRepositoryModel $jobModel,
        AuthRepositoryModel $authModel,
        CampaignRepositoryModel $campaignModel,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        CampaignService $campaignService,
        CampaignValidator $validator,
        LogRepositoryModel $log,
    ) {
        $this->jobModel = $jobModel;
        $this->authModel = $authModel;
        $this->campaignModel = $campaignModel;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->campaignService = $campaignService;
        $this->validator = $validator;
        $this->log = $log;
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

                $baseCurrency = $user->wallet->base_currency;
                $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
                $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);
                $unitPrice = $value->campaign_amount;
                if ($currency->code !== $value->currency) {
                    $rate = $this->campaignService->currencyConversion($value->currency, $currency->code);
                    $unitPrice *= $rate;
                }
                $data[] = [
                    'id' => $value->id,
                    'job_id' => $value->job_id,
                    'campaign_amount' => $unitPrice,
                    'post_title' => $value->post_title,
                    'number_of_staff' => $value->number_of_staff,
                    'type' => $value->campaignType->name,
                    'category' => $value->campaignCategory->name,
                    'completed' => $count,
                    'is_completed' => $count >= $value->number_of_staff ? true : false,
                    'progress' => round($progress, 2),
                    'currency' => $currency->code,
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
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
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

            // return $jobs;
            $data = [];
            foreach ($jobs as $job) {
                $workerDetails = $this->authModel->findUserById($job->user_id);
                $campaignDetails = $this->campaignModel->getCampaignById($job->campaign_id);

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

    public function submitWork($request)
    {
        $this->validator->submitJob($request);
        // return $request;

        try {
            $user = auth()->user();

            $campaign = $this->jobModel->getJobById($request->job_id);
            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 400);
            }
            $checkJob = $this->jobModel->checkIfJobIsDoneByUser($campaign->job_id);
            if ($checkJob) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have already perform this job before'
                ], 400);
            }

            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);
            $unitPrice = $campaign->campaign_amount;
            if ($currency->code !== $campaign->currency) {
                $rate = $this->campaignService->currencyConversion($campaign->currency, $currency->code);
                $unitPrice *= $rate;
            }

            $check = $this->checkVerification($user, $currency, $unitPrice);
            if (!$check) {
                return response()->json([
                    'status' => false,
                    'message' => 'User account yet to be verified',
                ], 403);
            }
            $proofUrl = 'no image';
            if ($request->hasFile('proof') && $campaign->allow_upload) {
                $file = $request->file('proof');
                $filePath = 'proofs/' . time() . '_' . $file->getClientOriginalName();
                Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
                $proofUrl = Storage::disk('s3')->url($filePath);
            }

            //return $proofUrl;
            DB::beginTransaction();

            $campaignWorker =  $this->jobModel->createJobs(
                $user,
                $campaign->id,
                $request,
                $currency,
                $proofUrl,
                $unitPrice
            );
            $campaign->increment('pending_count');
            $this->jobModel->setPendingCount($campaign->id);

            // Activity log
            $this->log->createLogForJobCreation(
                $user,
                $currency,
                $unitPrice
            );

            // Send emails
            Mail::to(
                $user->email
            )->send(new SubmitJob(
                $campaignWorker
            ));
            $subject = 'Job Submission';
            $content = $user->name . ' submitted a response to your campaign - ' . $campaign->post_title . '. Please login to review.';
            Mail::to(
                $campaign->user->email
            )->send(new GeneralMail(
                $campaign->user,
                $content,
                $subject,
                ''
            ));

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Job Submitted Successfully',
                'data' => $campaignWorker
            ], 201);
        } catch (Exception $exception) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function myJobDetails($jobId)
    {
        try {
            $user = auth()->user();


            $job = $this->jobModel->getJobById($jobId);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found.',
                ], 404);
            }

            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);
            $unitPrice = $job->campaign_amount;
            if ($currency->code !== $job->currency) {
                $rate = $this->campaignService->currencyConversion($job->currency, $currency->code);
                $unitPrice *= $rate;
            }


            $check = $this->checkVerification($user, $currency, $unitPrice);
            if (!$check) {
                return response()->json([
                    'status' => false,
                    'message' => 'User account yet to be verified',
                    // 'data' => [$currency, $unitPrice, $user],
                ], 403);
            }
            // Prepare response data
            $data = [
                'id' => $job->id,
                'campaign_name' => $job->post_title,
                'campaign_type' => $job->campaignType->name,
                'campaign_category' => $job->campaignCategory->name,
                'campaign_description' => $job->description,
                'campaign_amount' => $unitPrice,
                'campaign_currency' => $baseCurrency,
                'campaign_number_of_worker' => $job->number_of_staff,
                'campaign_url_link' => $job->post_link,
                'campaign_allow_upload' => $job->allow_upload ? true : false,
                'campaign_instruction' => $job->proof,
                'created_at' => $job->created_at,
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

    public function checkVerification($user, $currency, $unitPrice)
    {
        if ($user->is_verified) {
            return true;
        }
        if ((int) $currency->min_upgrade_amount > $unitPrice) {
            return true;
        }

        return false;
    }


    public function createDispute($request)
    {
        $this->validator->disputeCreation($request);
        try {
            $user = auth()->user();
            $job = $this->jobModel->getMyJobById($request->job_id, $user->id);

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
