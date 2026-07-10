<?php

namespace App\Services;

use App\Mail\GeneralMail;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\BannerRepositoryModel;
use App\Repositories\JobListingRepository;
use App\Repositories\WalletRepositoryModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class JobListingService
{
    protected $jobRepository;
    protected $walletModel;
    protected $currencyModel;
    protected $campaignService;
    protected $bannerModel;


    public function __construct(
        JobListingRepository $jobRepository,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        CampaignService $campaignService,
        BannerRepositoryModel $bannerModel
    ) {
        $this->jobRepository = $jobRepository;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->campaignService = $campaignService;
        $this->bannerModel = $bannerModel;
    }

    public function getJobListings($request)
    {
        try {
            $filters = $request->only([
                'type',
                'tier',
                'location',
                'salary_min',
                'salary_max',
                'remote',
                'search',
            ]);

            $jobs = $this->jobRepository->getJobListings($filters);

            $data = [];
            foreach ($jobs as $job) {
                $hasPurchased = true;

                if ($job->tier === 'premium') {

                    $hasPurchased = $this->jobRepository
                        ->hasUserPurchasedJob($job->id, auth()->user()->id);
                }

                $data[] = $this->formatJobSummary($job, $hasPurchased);
            }
            return response()->json([
                'status'     => true,
                'message'    => 'Job listings retrieved successfully.',
                'data'       => $data,
                'pagination' => $this->buildPagination($jobs),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving job listings',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function createUserJob($request)
    {
        try {
            $user = auth()->user();

            $validated = $request->validate([
                'title'               => 'required|string|max:255',
                'description'         => 'required|string',
                'requirements'        => 'nullable|string',
                'responsibilities'    => 'nullable|string',
                'benefits'            => 'nullable|string',
                'type'                => 'required|in:fulltime,parttime,contract,internship,gig,nysc',
                'tier'                => 'required|in:free,sponsored', // was: free,premium
                'location'            => 'required|string|max:255',
                'remote_allowed'      => 'boolean',
                'salary_min'          => 'nullable|numeric|min:0',
                'salary_max'          => 'nullable|numeric|min:0|gte:salary_min',
                'currency'            => 'nullable|string|max:3',
                'company_name'        => 'required|string|max:255',
                'company_description' => 'nullable|string',
                'company_website'     => 'nullable|url',
                'application_link'    => 'nullable|url',
                'expires_at'          => 'nullable|date|after:today',
            ]);

            $validated['remote_allowed'] = $request->boolean('remote_allowed');

            if ($validated['tier'] === 'sponsored') {
                $baseCurrency = $user->wallet->base_currency;
                $mapCurrency  = $this->walletModel->mapCurrency($baseCurrency);
                $currency     = $this->currencyModel->getCurrencyByCode($mapCurrency);
                $amount       = $currency->job_listing_amount ?? 5000;

                // Log::info('User Job Listing Creation: ', [
                //     'user_id' => $user->id,
                //     'tier' => $validated['tier'],
                //     'currency' => $currency,
                //     'amount' => $amount,
                // ]);
                DB::beginTransaction();

                if (!$this->walletModel->checkWalletBalance($user, $currency->code, $amount)) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Insufficient wallet balance. You need ' . $currency->code . ' ' . number_format($amount, 2) . ' to post a premium job.',
                    ], 401);
                }

                if (!$this->walletModel->debitWallet($user, $currency->code, $amount)) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Wallet debit failed. Please try again.',
                    ], 401);
                }

                // $validated['is_active'] = true;

                $job = $this->jobRepository->createUserJob($user, $validated);

                $this->walletModel->createTransaction(
                    $user,
                    $amount,
                    time(),
                    $job->id,
                    $currency->code,
                    'job_listing',
                    'Sponsored Job Listing: ' . $job->title,
                    'debit',
                );

                DB::commit();
            } else {
                // $validated['is_active'] = true;
                $job = $this->jobRepository->createUserJob($user, $validated);
            }

            $content = 'Your job listing is successfully created. It is currently under review, you will get a notification when it goes live!';
            $subject = 'Job Listing Created Successfully';
            Mail::to(auth()->user()->email)->send(new GeneralMail(auth()->user(), $content, $subject, ''));

            app(NotificationService::class)->createNotification(
                $user,
                'Job Listing Created',
                "Your Job Listing has been Created Successfully and pending approval from the admin",
                'job_listing'
            );
            return response()->json([
                'status'  => true,
                'message' => 'Vacancy posted successfully.',
                'data'    => $this->formatJob($job),
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'error' => 'Error posting job.',
                'message'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateUserJob($request, $jobId)
    {
        try {
            $user = auth()->user();
            $job  = $this->jobRepository->getUserJobById($jobId, $user->id);

            $validated = $request->validate([
                'title'               => 'sometimes|string|max:255',
                'description'         => 'sometimes|string',
                'requirements'        => 'nullable|string',
                'responsibilities'    => 'nullable|string',
                'benefits'            => 'nullable|string',
                'type'                => 'sometimes|in:fulltime,parttime,contract,internship,gig,nysc',
                'location'            => 'sometimes|string|max:255',
                'remote_allowed'      => 'boolean',
                'salary_min'          => 'nullable|numeric|min:0',
                'salary_max'          => 'nullable|numeric|min:0|gte:salary_min',
                'currency'            => 'nullable|string|max:3',
                'company_name'        => 'sometimes|string|max:255',
                'company_description' => 'nullable|string',
                'company_website'     => 'nullable|url',
                'application_link'    => 'nullable|url',
                'expires_at'          => 'nullable|date',
            ]);

            if (isset($validated['remote_allowed'])) {
                $validated['remote_allowed'] = $request->boolean('remote_allowed');
            }

            $job = $this->jobRepository->updateUserJob($job, $validated);

            return response()->json([
                'status'  => true,
                'message' => 'Vacancy updated successfully.',
                'data'    => $this->formatJob($job),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error updating job.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserJobs($request)
    {
        try {
            $user = auth()->user();
            $jobs = $this->jobRepository->getUserJobs($user->id, $request->query('page'));

            // Log::info('User Jobs Data: ', ['data' => $jobs]);
            $data = [];
            foreach ($jobs as $job) {
                $data[] = array_merge($this->formatJobSummary($job), [
                    'applications_count' => $job->applications_count,
                    'is_active'          => $job->is_active,
                    'application_link'   => $job->application_link,
                    'status'             => $this->getJobStatus($job),
                    'decision_reason'    => $job->decision_reason,
                ]);
            }

            return response()->json([
                'status'     => true,
                'message'    => 'Your job listings retrieved.',
                'data'       => $data,
                'pagination' => $this->buildPagination($jobs),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving your jobs.',
            ], 500);
        }
    }

    private function getJobStatus($job): string
    {
        if ($job->trashed()) return 'deleted';
        if ($job->paused_at) return 'paused';
        if (!$job->is_active && $job->decision_reason) return 'declined';
        if (!$job->is_active && is_null($job->decision_reason)) return 'pending';
        if ($job->is_active && $job->expires_at && $job->expires_at < now()) return 'expired';
        if ($job->is_active) return 'active';
        return 'inactive';
    }
    public function getUserJobDetails($request, $id)
    {

        try {
            $user = auth()->user();
            $job  = $this->jobRepository->getUserJobById($id, $user->id);

            return response()->json([
                'status'  => true,
                'message' => 'Job details retrieved successfully.',
                'data'    => $this->formatJob($job),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving job details.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getJob($id)
    {
        try {

            $user = auth()->user();
            $job  = $this->jobRepository->getJobById($id);

            $hasPurchased = true;

            if ($job->tier === 'premium') {

                if (!$user) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Login required.',
                    ], 401);
                }

                $hasPurchased = $this->jobRepository
                    ->hasUserPurchasedJob($job->id, $user->id);

                if (!$hasPurchased) {
                    $baseCurrency = $user->wallet->base_currency;
                    $mapCurrency  = $this->walletModel->mapCurrency($baseCurrency);
                    $currency     = $this->currencyModel->getCurrencyByCode($mapCurrency);
                    $amount       = $currency->job_points_amount ?? 300; // Default to 300 if not set

                    return response()->json([
                        'status' => true,
                        'message' => 'Premium job requires point purchase.',
                        'data' =>
                        array_merge(
                            $this->formatJobSummary($job, false),
                            [
                                'has_purchased' => false,
                                'point_required' => 1,
                                'point_cost_ngn' => $amount,
                            ]
                        )

                    ], 204);
                }
            }
            $this->jobRepository->incrementViews($job);

            $relatedJobs = $this->jobRepository->getRelatedJobs($job);

            $relatedData = [];
            foreach ($relatedJobs as $related) {
                $relatedData[] = $this->formatJobSummary($related);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Job retrieved successfully.',
                'data'    => array_merge(
                    $this->formatJob($job, $hasPurchased),
                    ['related_jobs' => $relatedData]
                ),
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error retrieving job: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Job not found',
            ], 404);
        }
    }

    public function applyForJob($request, $id)
    {
        try {
            $user = auth()->user();
            $job  = $this->jobRepository->getJobById($id);

            // if (!$user->hasVerifiedEmail()) {
            //     return response()->json([
            //         'status'  => false,
            //         'message' => 'Please verify your email to apply.',
            //     ], 403);
            // }

            if ($this->jobRepository->checkHasApplied($job, $user)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You have already applied to this position.',
                ], 409);
            }

            $validated = $request->validate([
                'cover_letter' => 'nullable|string|max:5000',
                'resume'       => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            ]);

            $resumePath = null;
            // if ($request->hasFile('resume')) {
            //     $resumePath = uploadFileToCloudinary(
            //         $request->file('resume'),
            //         'files'
            //     );
            // }

            $application = $this->jobRepository->createApplication($job, [
                'user_id'      => $user->id,
                'cover_letter' => $validated['cover_letter'] ?? null,
                'resume_path'  => $resumePath,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Application submitted successfully.',
                'data'    => $application,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error submitting application',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function jobDetails($slug)
    {
        try {
            $job  = $this->jobRepository->getJobBySlug($slug);

            $this->jobRepository->incrementViews($job);

            $relatedJobs = $this->jobRepository->getRelatedJobs($job);

            $relatedData = [];
            foreach ($relatedJobs as $related) {
                $relatedData[] = $this->formatJobSummary($related);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Job retrieved successfully.',
                'data'    => array_merge(
                    $this->formatJob($job),
                    ['related_jobs' => $relatedData]
                ),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Job not found',
            ], 404);
        }
    }
    public function publicJobs($request)
    {
        try {
            $filters = $request->only([
                'type',
                'tier',
                'location',
                'salary_min',
                'salary_max',
                'remote',
                'search',
            ]);

            $jobs = $this->jobRepository->getJobListings($filters);

            $data = [];
            foreach ($jobs as $job) {
                $data[] = $this->formatJobSummary($job);
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
                    'clicks' => $bannerItem->click_count,
                    'created_at' => $bannerItem->created_at,
                    'updated_at' => $bannerItem->updated_at,
                ];
            }


            return response()->json([
                'status'     => true,
                'message'    => 'Job listings retrieved successfully.',
                'data'       => $data,
                'banners'    => $bannerData,
                'pagination' => $this->buildPagination($jobs),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving job listings',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function purchasePoint($id)
    {
        try {

            $user = auth()->user();
            $job  = $this->jobRepository->getJobById($id);

            if ($job->tier !== 'premium') {
                return response()->json([
                    'status' => false,
                    'message' => 'Point purchase not required.',
                ], 400);
            }

            if ($this->jobRepository->hasUserPurchasedJob($job->id, $user->id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Already purchased.',
                ], 409);
            }

            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
            $currency = $this->currencyModel
                ->getCurrencyByCode($mapCurrency);

            // $amount = 300;
            $amount = $currency->job_points_amount ?? 300; // Default to 300 if not set

            // if ($currency->code !== 'NGN') {
            //     // $rate = $this->campaignService
            //     //     ->currencyConversion('NGN', $currency->code);

            //     // $amount *= $rate;
            //      return response()->json([
            //         'status' => false,
            //         'message' => 'You cannot purchase points with your current wallet currency. Please switch to NGN',
            //     ], 409);
            // }

            // if (!checkWalletBalance($user, $currency, $amount)) {
            //     return response()->json([
            //         'status'  => false,
            //         'message' => 'Insufficient wallet balance. Please fund your wallet.',
            //     ], 402);
            // }


            DB::beginTransaction();

            // app(WalletRepositoryModel::class)->debitWallet(
            //     $user,
            //     $currency->code,
            //     $amount
            // );

            if (!$this->walletModel->checkWalletBalance(
                $user,
                $currency->code,
                $amount
            )) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have sufficient funds in your wallet',
                ], 401);
            }

            if (!$this->walletModel->debitWallet(
                $user,
                $currency->code,
                $amount
            )) {
                return response()->json([
                    'status' => false,
                    'message' => 'Wallet debit failed. Please try again.',
                ], 401);
            }

            $this->jobRepository->purchaseJobPoint(
                $job->id,
                $user->id,
                $amount,
                $currency,
                $job
            );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Premium access purchased successfully.',
            ]);
        } catch (Throwable $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Purchase failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function formatJob($job, $hasPurchased = true)
    {
        return [
            'id'                  => $job->id,
            'title'               => $job->title,
            'slug'                => $job->slug,
            'description'         => $job->description,
            'requirements'        => $job->requirements,
            'responsibilities'    => $job->responsibilities,
            'benefits'            => $job->benefits,
            'type'                => $job->type,
            'tier'                => $job->tier,
            'location'            => $job->location,
            'remote_allowed'      => $job->remote_allowed,
            'salary_min'          => $job->salary_min,
            'salary_max'          => $job->salary_max,
            'salary_range'        => $job->salary_range,
            'currency'            => $job->currency,
            'company_name'        => $job->company_name,
            'company_logo'        => $job->company_logo,
            'company_description' => $job->company_description,
            'company_website'     => $job->company_website,
            // 'application_link'    =>  $job->application_link ? $job->company_website : null,
            'application_link' => $job->application_link ?: $job->company_website,
            'views_count'         => $job->views_count,
            'applications_count'  => $job->applications_count,
            'status'             => $this->getJobStatus($job),
            'decision_reason'    => $job->decision_reason,
            'has_purchased'          => $hasPurchased,
            'posted_by'           => $job->postedBy ? [
                'id'   => $job->postedBy->id,
                'name' => $job->postedBy->name,
            ] : null,
            'expires_at'          => $job->expires_at,
            'created_at'          => $job->created_at,
            'updated_at'          => $job->updated_at,
        ];
    }

    private function formatJobSummary($job, $hasPurchased = true)
    {

        return [
            'id'                      => $job->id,
            'title'                   => $job->title,
            'slug'                    => $job->slug,
            'type'                    => $job->type,
            'tier'                    => $job->tier,
            'location'                => $hasPurchased ? $job->location : '🔒 Location Hidden',
            'remote_allowed'          => $job->remote_allowed,
            'salary_range'            => $job->salary_range,
            'currency'                => $job->currency,
            'company_name'            => $hasPurchased ? $job->company_name : '🔒 Company Hidden',
            'company_logo'            => $job->company_logo,
            'has_purchased'           => $hasPurchased,
            // 'can_perform_task'        => $check === true,
            // 'can_perform_task_reason' => $check === true ? '' : $check,
            'created_at'              => $job->created_at,

        ];
    }

    private function buildPagination($paginator)
    {
        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ];
    }
}
