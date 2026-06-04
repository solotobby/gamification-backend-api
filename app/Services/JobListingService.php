<?php

namespace App\Services;

use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\JobListingRepository;
use App\Repositories\WalletRepositoryModel;
use Illuminate\Support\Facades\DB;
use Throwable;

class JobListingService
{
    protected $jobRepository;
    protected $walletModel;
    protected $currencyModel;
    protected $campaignService;

    public function __construct(
        JobListingRepository $jobRepository,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        CampaignService $campaignService
    ) {
        $this->jobRepository = $jobRepository;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->campaignService = $campaignService;
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
                $data[] = $this->formatJobSummary($job);
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
                    return response()->json([
                        'status' => true,
                        'message' => 'Premium job requires point purchase.',
                        'data' =>
                        array_merge(
                            $this->formatJobSummary($job),
                            [
                                'has_purchased' => false,
                                'point_required' => 1,
                                'point_cost_ngn' => 300,
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


    public function jobDetails($id)
    {
        try {
            $job  = $this->jobRepository->getJobBySlug($id);


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
            ], 204);
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

            $amount = 300;

            if ($currency->code !== 'NGN') {
                $rate = $this->campaignService
                    ->currencyConversion('NGN', $currency->code);

                $amount *= $rate;
            }

            if (!checkWalletBalance($user, $currency, $amount)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient wallet balance.',
                ], 402);
            }

            DB::beginTransaction();

            app(WalletRepositoryModel::class)->debitWallet(
                $user,
                $currency->code,
                $amount
            );

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
            'views_count'         => $job->views_count,
            'applications_count'  => $job->applications_count,
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

    private function formatJobSummary($job)
    {

        return [
            'id'                      => $job->id,
            'title'                   => $job->title,
            'slug'                    => $job->slug,
            'type'                    => $job->type,
            'tier'                    => $job->tier,
            'location'                => $job->location,
            'remote_allowed'          => $job->remote_allowed,
            'salary_range'            => $job->salary_range,
            'currency'                => $job->currency,
            'company_name'            => $job->company_name,
            'company_logo'            => $job->company_logo,
            // 'has_purchased'           => $hasPurchased,
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
