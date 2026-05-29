<?php

namespace App\Services;

use App\Mail\GeneralMail;
use App\Mail\SubmitJob;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\BannerRepositoryModel;
use App\Repositories\CampaignRepositoryModel;
use App\Repositories\JobRepositoryModel;
use App\Repositories\LogRepositoryModel;
use App\Repositories\RatingRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\Providers\CloudinaryService;
use App\Validators\CampaignValidator;
use Exception;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Illuminate\Support\Facades\DB;

class JobService
{

    protected $jobModel;

    protected $currencyModel;
    protected $walletModel;
    protected $log;
    protected $authModel;
    protected $ratingModel;
    protected $campaignModel;
    protected $campaignService;
    protected $validator;
    protected $cloudinary;
    protected $bannerModel;

    public function __construct(
        JobRepositoryModel $jobModel,
        RatingRepositoryModel $ratingModel,
        AuthRepositoryModel $authModel,
        CampaignRepositoryModel $campaignModel,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        CampaignService $campaignService,
        CampaignValidator $validator,
        LogRepositoryModel $log,
        CloudinaryService $cloudinary,
        BannerRepositoryModel $bannerModel,
    ) {
        $this->jobModel = $jobModel;
        $this->authModel = $authModel;
        $this->ratingModel = $ratingModel;
        $this->campaignModel = $campaignModel;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->campaignService = $campaignService;
        $this->validator = $validator;
        $this->log = $log;
        $this->cloudinary = $cloudinary;
        $this->bannerModel = $bannerModel;
    }

    public function availableJobs($request)
    {
        try {
            $user       = auth()->user();
            $category   = strtolower($request->query('category_id'));
            $page       = $request->query('page');
            $sort       = $request->query('sort');

            $jobs = $this->jobModel->availableJobs($user->id, $category, $page, $sort)
                ->appends(['page' => $page, 'sort' => $sort, 'category_id' => $category]);

            $data = [];

            // return $jobs;
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

                $check =  $this->checkCanPerform($user, $currency, $unitPrice, $value);
                $data[] = [
                    'id' => $value->id,
                    'campaign_id' => $value->job_id,
                    'campaign_amount' => $unitPrice,
                    'post_title' => $value->post_title,
                    'post_link' => $value->post_link,
                    'number_of_staff' => $value->number_of_staff,
                    'type' => $value->campaignType->name,
                    'category' => $value->campaignCategory->name,
                    'url' => $value->campaignType->url,
                    'completed' => $count,
                    'completed_count' => $count,
                    'expected_count' => (int)$value->number_of_staff,
                    'campaign_review' => round(mt_rand(40, 50) / 10, 1), // Review calculation to be done later
                    'is_completed' => $count >= $value->number_of_staff ? true : false,
                    'progress' => round($progress, 2),
                    'currency' => $currency->code,
                    'original_currency' => $value->currency,
                    'campaign_allow_upload' => $value->allow_upload ? true : false,
                    'campaign_instruction' => $value->proof,
                    'campaign_approval_time' => $value->approval_time,
                    'campaign_description' => $value->description,
                    'expected_image_url' => $value->expected_result_image,
                    'public_link' => "https://dashbaord.freebyz.com/tasks/" . $value->job_id,
                    'can_perform_task' => $check === true,
                    'can_perform_task_reason' => $check === true ? '' : $check,
                    'created_at' => $value->created_at,
                ];
            }

            // Fetching 2 random active banners
            $banners = $this->bannerModel->getRandomActiveBanners();

            $bannerData = [];

            foreach ($banners as $bannerItem) {

                //Increase impression upon display
                $bannerItem->impression_count += 1;
                $bannerItem->save();

                $bannerData[] = [
                    'banner_id' => $bannerItem->banner_id,
                    'banner_url' => $bannerItem->banner_url,
                    //  'external_link' => $bannerItem->external_link,
                    // 'status' => $bannerItem->status ? true : false,
                    'clicks' => $bannerItem->click_count,
                    'created_at' => $bannerItem->created_at,
                    'updated_at' => $bannerItem->updated_at,
                ];
            }

            // Pagination data for jobs
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
                'message' => 'Tasks retrieved successfully.',
                'data' => $data,
                'banners' => $bannerData,
                'pagination' => $pagination,
            ]);
        } catch (Throwable $exception) {
            // return $exception;
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
            $type = strtolower($request->query('type', ''));

            $validTypes = ['completed', 'disputed', 'pending'];

            if ($type && !in_array($type, $validTypes)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid task type provided.',
                ], 400);
            }

            $type = $type === 'completed' ? 'approved' : $type;

            $jobs = match (true) {
                !$type => $this->jobModel->getAllJobs($user),
                $type === 'disputed' => $this->jobModel->getDisputedJobs($user),
                default => $this->jobModel->getJobByType($user, $type),
            };

            // return $jobs;
            $data = [];
            foreach ($jobs as $job) {
                $workerDetails = $this->authModel->findUserById($job->user_id);
                $campaignDetails = $this->campaignModel->getCampaignById($job->campaign_id);

                $data[] = [
                    'id' => $job->id,
                    'worker_id' => $job->user_id,
                    'worker_name' => $workerDetails->name,
                    'campaign_id' => $campaignDetails->job_id,
                    'campaign_name' => $campaignDetails->post_title,
                    'campaign_owner_id' => $campaignDetails->user_id,
                    'campaign_category' => $campaignDetails->campaignType->name,
                    'campaign_category_url' => $campaignDetails->campaignType->url,
                    'comment' => $job->comment,
                    'currency' => $user->wallet->base_currency,
                    'amount' => $job->amount,
                    'proof_url' => $job->proof_url,
                    'expected_image_url' => $campaignDetails->proof_url,
                    'status' => $job->status,
                    'reason' => $job->reason,
                    'created_at' => $job->created_at,
                    'can_dispute' =>  $job->canCreateDispute(),
                    'has_dispute' => $job->is_dispute ? true : false,
                    'is_dispute_resolved' => $job->is_dispute_resolved ? true : false,
                    'public_link' => "https://dashboard.freebyz.com/tasks/" . $campaignDetails->job_id,
                ];
            }

            // Fetching 2 random active banners
            $banners = $this->bannerModel->getRandomActiveBanners();

            $bannerData = [];

            foreach ($banners as $bannerItem) {

                //Increase impression upon display
                $bannerItem->impression_count += 1;
                $bannerItem->save();

                $bannerData[] = [
                    'banner_id' => $bannerItem->banner_id,
                    'banner_url' => $bannerItem->banner_url,
                    // 'external_link' => $bannerItem->external_link,
                    //'status' => $bannerItem->status ? true : false,
                    'clicks' => $bannerItem->click_count,
                    'created_at' => $bannerItem->created_at,
                    'updated_at' => $bannerItem->updated_at,
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
                'message' => 'Tasks retrieved successfully.',
                'data' => $data,
                'banners' => $bannerData,
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
                    'message' => 'Task not found'
                ], 400);
            }

            $owner = $this->jobModel->checkIfJobIsYours($campaign->job_id);
            if ($owner) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot perform your own task'
                ], 400);
            }

            $checkJob = $this->jobModel->checkIfJobIsDoneByUser($campaign->id);
            if ($checkJob) {
                return response()->json([
                    'status' => false,
                    'message' => 'You have already perform this task before'
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

            // $check = $this->checkVerification($user, $currency, $unitPrice);
            // if (!$check) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'User account yet to be verified',
            //     ], 403);
            // }
            $proofUrl = 'no image';
            if ($request->hasFile('proof') && $campaign->allow_upload) {
                $file = $request->file('proof');
                $proofUrl = $this->cloudinary->uploadImage($file, 'uploads', true);
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
            // $campaign->increment('pending_count');
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
            $subject = 'Task Submission';
            $content = $user->name . ' submitted a response to your task - ' . $campaign->post_title . '. Please login to review.';
            Mail::to(
                $campaign->user->email
            )->send(new GeneralMail(
                $campaign->user,
                $content,
                $subject,
                ''
            ));

            DB::commit();

            unset($campaignWorker['job_id']);
            $campaignWorker['campaign_id'] = $campaign->job_id;
            return response()->json([
                'status' => true,
                'message' => 'Task Submitted Successfully',
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
                    'message' => 'Task not found.',
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


            // $check = $this->checkVerification($user, $currency, $unitPrice);
            // if (!$check) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'User account yet to be verified',
            //         // 'data' => [$currency, $unitPrice, $user],
            //     ], 403);
            // }

            // Prepare response data
            $data = [
                'id' => $job->id,
                'campaign_id' => $job->job_id,
                'campaign_name' => $job->post_title,
                'campaign_type' => $job->campaignType->name,
                'campaign_category' => $job->campaignCategory->name,
                'campaign_description' => $job->description,
                'campaign_amount' => $unitPrice,
                'campaign_currency' => $baseCurrency,
                'campaign_number_of_worker' => $job->number_of_staff,
                'campaign_url_link' => $job->post_link,
                'campaign_expect' => $job->expected_result_image ?? null,
                'campaign_allow_upload' => $job->allow_upload ? true : false,
                'campaign_instruction' => $job->proof,
                'created_at' => $job->created_at,
                'public_link' => "https://dashboard.freebyz.com/tasks/" . $job->job_id,

            ];
            return response()->json([
                'status' => true,
                'message' => 'Task details retrieved successfully',
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

    public function checkCanPerform($user, $currency, $unitPrice, $campaign)
    {
        $isOwner = $campaign->user_id === $user->id;
        $isVerified = $user->is_verified;
        // $isVerified = true;
        $isBelowThreshold = (int) $currency->min_upgrade_amount > $unitPrice;

        if ($isOwner) {
            return "You cannot perform your own task.";
        }

        if ($isVerified) {
            return true;
        }

        if ($isBelowThreshold) {
            return true;
        }

        return "Your account needs verification to perform this task.";
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
                    'message' => 'Task not found.',
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
                    'message' => 'A dispute has already been lodged for this task, so the action cannot be performed again.',
                ], 400);
            }

            $this->jobModel->createDisputeOnWorker($job->id);
            $this->jobModel->createDispute($job, $request->reason, $request->job_proof);

            $subject = 'New Dispute Raised';
            $content = 'A dispute has been raised by ' . auth()->user()->name . ' on a task. Please attend to it.';


          $url = 'https://dashboard.freebyz.com/admin/campaign/disputes/' . $job->id;
            Mail::to('freebyzcom@gmail.com')
                ->cc('favour@freebyztechnologies.com')
                ->send(new GeneralMail(auth()->user(), $content, $subject, $url));

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

    public function jobRating($request)
    {

        $this->validator->validateJobRating($request);

        try {

            $userId = auth()->user()->id;
            $campaign = $this->campaignModel->getCampaignByJobId($request->campaign_id);

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Task not found'
                ], 404);
            }
            $workDone = $this->jobModel->getJobByIdAndCampaignId(
                $request->job_id,
                $campaign->id,
                $userId
            );

            $this->ratingModel->create([
                'user_id' => $userId,
                'campaign_id' => $campaign->id,
                'campaign_worker_id' => $workDone->id,
                'rating' => $request->rating,
                'comment' => $request->comment ?? null,
                'type' => 'job',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Thank you for taking the time to rate this task, we really appreciate it',
                'data' => null
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request',
            ], 500);
        }
    }
}
