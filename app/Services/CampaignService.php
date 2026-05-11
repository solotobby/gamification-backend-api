<?php

namespace App\Services;

use App\Repositories\CampaignRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Repositories\Admin\CurrencyRepositoryModel;
use Throwable;
use Illuminate\Support\Facades\Mail;
use App\Validators\CampaignValidator;
use App\Helpers\SystemActivities;
use App\Mail\AdminCampaignPosted;
use App\Mail\CreateCampaign;
use App\Models\Campaign;
use App\Models\CampaignWorker;
use App\Models\Rating;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\HireWorkerRepository;
use App\Repositories\JobRepositoryModel;
use App\Services\Providers\CloudinaryService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    protected $repo;

    protected $jobModel;
    protected $notification;
    protected $currencyModel;
    protected $walletModel;
    protected $authModel;
    protected $campaignModel;
    protected $validator;
    public function __construct(
        CampaignRepositoryModel $campaignModel,
        CampaignValidator $validator,
        CurrencyRepositoryModel $currencyModel,
        WalletRepositoryModel $walletModel,
        AuthRepositoryModel $authModel,
        JobRepositoryModel $jobModel,
        HireWorkerRepository $repo,
        NotificationService $notification

    ) {
        $this->campaignModel = $campaignModel;
        $this->validator = $validator;
        $this->currencyModel = $currencyModel;
        $this->walletModel = $walletModel;
        $this->authModel = $authModel;
        $this->jobModel = $jobModel;
        $this->repo = $repo;
        $this->notification = $notification;
    }

    public function getCampaigns($request)
    {
        try {
            $user = auth()->user();

            $type = strtolower($request->query('type'));
            $per_page = strtolower($request->query('per_page', 10));
            // Fetch campaigns by user ID
            $campaigns = $this->campaignModel->getCampaignsByPagination($user->id, $type, $per_page);

            // Log::info($campaigns);
            // Fetch user's base currency and map it
            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

            // Fetch currency details
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            // Validate retrieved data
            if (!$currency) {
                return response()->json([
                    'status' => false,
                    'message' => 'Currency not found.'
                ], 404);
            }

            // Prepare campaign data
            $data = [];
            foreach ($campaigns as $campaign) {
                $unitPrice = $campaign->campaign_amount;
                $totalAmount = $campaign->total_amount;

                // Check if conversion is needed
                if ($currency->code !== $campaign->currency) {
                    $rate = $this->currencyConversion($campaign->currency, $currency->code);

                    $unitPrice *= $rate;
                    $totalAmount *= $rate;
                }

                $spentAmount = $this->jobModel->getCampaignSpentAmount($campaign->id);
                $campaignAmount = $campaign->campaign_amount * $campaign->number_of_staff;
                $data[] = [
                    'id' => $campaign->id,
                    'user_id' => $campaign->user_id,
                    'campaign_id' => $campaign->job_id,
                    'title' => $campaign->post_title,
                    'approved' => $campaign->completed_count . '/' . $campaign->number_of_staff,
                    'completed_count' => $campaign->pending_count + $campaign->completed_count,
                    'expected_count' => (int)$campaign->number_of_staff,
                    'campaign_category' => $campaign->campaignType->name,
                    'campaign_category_url' => $campaign->campaignType->url,
                    'unit_price' => round($unitPrice, 5),
                    'total_amount' => round($totalAmount, 5),
                    'currency' => $currency->code,
                    'original_currency' => $campaign->currency,
                    'public_link' => "https://stagging.e-portal.com.ng/tasks/" . $campaign->job_id,
                    // 'status' => $campaign->status,
                    'status' => $this->mapCampaignStatus($campaign),
                    'amount_ratio' => $campaign->currency . '' . $spentAmount . ' / ' . $campaign->currency . '' . $campaignAmount,
                    'stat' => $this->jobModel->getCampaignStats($campaign->id),
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

    public function mapCampaignStatus($campaign)
    {
        if ($campaign->is_completed) {
            return 'completed';
        }

        if ($campaign->status === 'Denied') {
            return 'declined';
        }

        if ($campaign->status === 'Flagged') {
            return 'flagged';
        }

        if ($campaign->status === 'Paused') {
            return 'paused';
        }

        if ($campaign->status === 'Live' && !$campaign->is_completed) {
            return 'live';
        }

        if ($campaign->status === 'Offline') {
            return 'pending';
        }

        return strtolower($campaign->status);
    }

    public function currencyConversion($from, $to)
    {
        $currencyRate = $this->currencyModel->convertCurrency($from, $to);

        // return $currencyRate;
        if (!$currencyRate) {
            return response()->json([
                'status' => false,
                'message' => 'Currency conversion rate not found.'
            ], 404);
        }

        $rate = $currencyRate->rate;
        return $rate;
    }
    public function create($request)
    {
        $this->validator->validateCampaignCreation($request);

        try {

            DB::beginTransaction();
            $user = auth()->user();
            $baseCurrency = $user->wallet->base_currency;

            // Map currency to determine prioritization and upload amount
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

            // Get the currency details and status
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            // Determine prioritization amount and status
            $prAmount = $request->priotize ? (float)($currency->priotize) : 0;
            $priotize = $request->priotize ? 'Priotize' : 'Pending';

            // Calculate initial upload amount
            $iniAmount = $request->allow_upload ? $request->number_of_staff * (float)($currency->allow_upload) : 0;
            $allowUpload = (bool)$request->allow_upload;

            // Get the Subcategory amount from db
            $subAmount = $this->campaignModel->getSubCategoryAmount(
                $request->campaign_subcategory,
                $request->campaign_type
            );
            // return $subAmount;
            // Calculate estimated amount and total
            $estAmount = $request->number_of_staff * $subAmount->amount;
            $userPercent = $user->is_business ? 100 : 60;
            $percent = ($userPercent / 100) * $estAmount;
            $total = $estAmount + $percent + $iniAmount + $prAmount;

            // Generate a unique job ID
            $jobId = rand(1000000, 1000000000);

            // Check wallet balance and debit if valid
            if (!$this->walletModel->checkWalletBalance(
                $user,
                $currency->code,
                $total
            )) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have sufficient funds in your wallet',
                ], 401);
            }

            if (!$this->walletModel->debitWallet(
                $user,
                $currency->code,
                $total
            )) {
                return response()->json([
                    'status' => false,
                    'message' => 'Wallet debit failed. Please try again.',
                ], 401);
            }

            // Process the campaign
            $campaign = $this->processCampaign(
                $total,
                $request,
                $jobId,
                $percent,
                $allowUpload,
                $priotize,
                $currency,
            );

            $this->notification->createNotification(
                $user,
                'Task Created',
                "Task with ID {$jobId} Created Successfully and pending approval from the admin",
                'task'
            );
            DB::commit();
            // Notify user via email
            Mail::to($user->email)->send(new CreateCampaign($campaign));

            return response()->json([
                'status' => true,
                'message' => 'Task Posted Successfully. A member of our team will activate your campaign within 24 hours.',
                'data' => $campaign,
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request',
            ], 500);
        }
    }

    public function updateCampaignWorker($request)
    {

        $this->validator->validateCampaignUpdating($request);
        try {

            $user = auth()->user();
            // Get the campaign details using the UserId and CampaignId
            $campaign = $this->campaignModel->getCampaignByJobId($request->campaign_id, $user->id);

            $baseCurrency = $user->wallet->base_currency;

            // Map currency to determine upload amount
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

            // Get the currency details and status
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            // Calculate initial upload amount
            $iniAmount = $campaign->allow_upload ? $request->new_worker_number * (float)($currency->allow_upload) : 0;

            // Get the Subcategory amount from db
            $subAmount = $this->campaignModel->getSubCategoryAmount(
                $campaign->campaign_subcategory,
                $campaign->campaign_type
            );
            $estAmount = $request->new_worker_number * $subAmount->amount;
            $userPercent = $user->is_business ? 100 : 60;
            $percent = ($userPercent / 100) * $estAmount;
            $total = $estAmount + $percent + $iniAmount;

            // Check wallet balance and debit if valid
            if (!$this->walletModel->checkWalletBalance($user, $baseCurrency, $total)) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have sufficient funds in your wallet',
                ], 401);
            }

            if (!$this->walletModel->debitWallet($user, $baseCurrency, $total)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Wallet debit failed. Please try again.',
                ], 500);
            }

            // Process the campaign
            $saveCampaign = $this->campaignModel->updateCampaignDetails(
                $campaign->id,
                $request->new_worker_number,
                $total
            );
            // Create transaction
            $this->campaignModel->createPaymentTransaction(
                $user->id,
                $campaign->id,
                $total
            );

            $saveCampaign['campaign_id'] = $campaign->job_id;
            // Notify user via email
            Mail::to($user->email)->send(new CreateCampaign($saveCampaign));

            return response()->json([
                'status' => true,
                'message' => 'Campaign Updated Successfully',
                'data' => $saveCampaign,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request',
            ], 500);
        }
    }

    public function getCategories()
    {
        try {
            $user = auth()->user();
            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

            $categories = $this->campaignModel->listCategories();
            if (!$categories) {
                return response()->json(['status' => false, 'message' => 'No categories found', 'data' => []], 404);
            }

            $data['category'] = $this->mapCategories($categories, $mapCurrency);
            $data['currency'] = $this->currencyModel->getCurrencyByCode($mapCurrency) ?? [];
            $data['currency']['campaign_percentage'] = $user->is_business ? 100 : 60;
            $data['currency']['business_account'] = (bool) $user->is_business;
            $data['utility'] = $this->getUtilityData();

            return response()->json(['status' => true, 'message' => 'Categories fetched successfully', 'data' => $data], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage(), 'message' => 'Error processing request'], 500);
        }
    }

    private function mapCategories($categories, string $mapCurrency): array
    {
        return $categories->map(function ($category) use ($mapCurrency) {
            $subCategories = $this->campaignModel->listSubCategories($category['id']);

            $subCategoryData = $subCategories->map(function ($sub) use ($mapCurrency) {
                $amount = $sub->amount;

                if ($mapCurrency !== 'NGN') {
                    $currencyRate = $this->currencyModel->convertCurrency('NGN', $mapCurrency);
                    if (!$currencyRate) return null;
                    $amount *= $currencyRate->rate;
                }

                return [
                    'id' => $sub->id,
                    'amount' => round($amount, 5),
                    'category_id' => $sub->category_id,
                    'name' => $sub->name,
                ];
            })->filter()->values();

            return [
                'id' => $category['id'],
                'name' => $category['name'],
                'url' => $category['url'],
                'subcategories' => $subCategoryData,
            ];
        })->toArray();
    }

    private function getUtilityData(): array
    {

        $currency = auth()->user()->wallet->base_currency;

        $paymentProcessors = [];

        if ($currency === 'NGN') {
            $paymentProcessors = [
                'Paystack' => 'paystack',
                'KoraPay' => 'korapay',
                'Virtual Account' => 'virtual_account',
                // 'Crypto (USDT_TRC20)' => 'crypto',
                'Manual Account' => 'manual',
            ];
        } elseif ($currency === 'USD') {
            $paymentProcessors = [
                'Stripe' => 'stripe',
                'Crypto (USDT_TRC20)' => 'crypto',
            ];
        } else {
            $paymentProcessors = [
                'Paystack' => 'paystack',
                // 'Crypto (USDT_TRC20)' => 'crypto',
            ];
        }
        return [
            'dashboard' => [
                'info' => [
                    'title' => 'Welcome to Freebyz remote jobs, Get Full Time Jobs OR Micro Tasks | Hire Skilled workers for Full Time Job ',
                    'description' => ' Job PROMO: Freebyz is giving out 50k weekly to users with the highest referrals. Copy your referral link below to invite your Friends.\nLearn how to COMPLETE SIMPLE tasks online & earn here (https://youtube.com/shorts/sn64O_osLbs?si=P75AwS0Of9Sc-jA3).'
                ],
                'ads' => [
                    [
                        'title' => 'Raise money for urgent needs',
                        'description' => 'Create a crowd funding link to share with your friends',
                        'image' => '',
                        'link' => 'https://famlic.com/'
                    ],
                    [
                        'title' => 'Monetise your posts on Payhankey',
                        'description' => 'Monetize your posts and content on our social media platform - Payhankey',
                        'image' => 'https://res.cloudinary.com/dwisk11nl/image/upload/v1772094053/uploads/ihruqojz8pjzuzfjjdnu.png',
                        'link' => 'https://payhankey.com/'
                    ]
                ],
                'sorts' => [
                    'Newest First'              => 'newest',
                    'Oldest First'              => 'oldest',
                    'Price: Highest to Lowest'  => 'price_high',
                    'Price: Lowest to Highest'  => 'price_low',
                    'Prioritized First'         => 'priority_first',
                ],

            ],
            'withdraw' => [
                'info' => 'Withdrawals are made every Friday of the week. Only verified users can withdraw',
                'payment_processor' => [
                    'Local Withdrawal' => 'wallet',
                ]

            ],
            'wallet' => [
                'info' => 'Fund your wallet through any means of payment below. Your wallet gets credited in less than 1 min for all except the Manual Account funding',
                'note' => 'If you paid using manual account number, drop your evidence of payment',
                'payment_processor' => $paymentProcessors,
            ],
            'tasks' => [
                'created_tasks' => [
                    'info' => 'Campaigns with Activity Status Completed will not be available on the dashboard.',
                    'warning' => 'To ensure fairness, kindly avoid denying task submissions without genuine reasons. Repeated denials will lead to your campaigns being suspended without refund.',
                    'filter' => ['completed', 'pending', 'live', 'paused', 'declined', 'flagged'],
                ],
                'submitted_tasks' => [
                    'info' => 'Campaigns with Activity Status Completed will not be available on the dashboard.',
                    'warning' => 'To ensure fairness, you only have 12 hours to create a dispute if you think your job is denied abruptly.',
                    'filter' => ['completed', 'pending', 'disputed'],
                ],
                'task_creation' => [
                    'info' => 'Social media Apps like Facebook, TikTok, YouTube, Instagram has algorithms to detect unusual behaviour and attempt to buy followers or subscribers which can lead to a 10-15% drop in the number of followers/subscribers you actually hired. This may make you think our workers actually unsubcribed/unfollowed your page. Therefore avoid using your direct links (as much as possible). You can also choose the Comment before subscribe/Follow Subcategory or other creative means.'
                ],
                'worker_filter' => [
                    'approved',
                    'pending',
                    'denied'
                ]
            ],
            'hire_workers' => [
                'info' => 'Filter skilled worker based on your preferences',
                'skills'           => $this->repo->getSkills(),
                'proficiency_levels'  => $this->repo->getProficiencyLevels(),
                'year_experience'     => ['0-2', '3-5', '6-10', '10+'],
                'availability'        => ['full-time', 'part-time', 'remote', 'contract'],
            ],
            'remote_jobs' => [
                'info' => 'Discover full-time roles, part-time positions, and exciting gigs. Join thousands of professionals finding their perfect match.',
                'ads' => [
                    [
                        'title' => 'Looking for quick gigs',
                        'description' => 'Browse through Freebyz micro-jobs and start earning today!',
                        'image' => '',
                        'color' => 'blue',
                        'button_text' => 'Explore micro jobs',
                        'link' => ''
                    ],
                    [
                        'title' => 'Has your Content ever made you enough money?',
                        'description' => 'Monetize your posts and content on our social media platform - Payhankey',
                        'image' => 'https://res.cloudinary.com/dwisk11nl/image/upload/v1772094053/uploads/ihruqojz8pjzuzfjjdnu.png',
                        'color' => 'purple',
                        'button_text' => 'Get Started',
                        'link' => 'https://payhankey.com/'
                    ]
                ],
                'filter' => [
                    'types' => [
                        'Full-Time' => 'fulltime',
                        'Part-Time' => 'parttime',
                        'Contract' => 'contract',
                        'Internship' => 'internship',
                        'Gig' => 'gig',
                    ],
                    'tiers' => [
                        'Free' => 'free',
                        'Premium' => 'premium'
                    ],
                    'remote' => [
                        'Remote' => 'remote',
                        'Hybrid' => 'hybrid',
                        'On-site' => 'onsite'
                    ],
                    'location' => [],
                    'min_salary' => [],
                    'max_salary' => []
                ]
            ],
            'talk_to_us' => [
                'info' => 'If you are reporting a worker, please include the worker name, email and the job done. This will enable us that proper action',
                'options' => [
                    'Feedback' => 'feedback',
                    'Complaint' => 'complaint',
                    'Transfer Issue' => 'transfer_issue',
                    'Report A Worker' => 'report',
                    'Others' => 'others'
                ]
            ],
            ''
        ];
    }

    public function campaignActivitiesStat($campaignId)
    {
        try {
            $userId = auth()->user()->id;
            $campaign = $this->campaignModel->getCampaignByJobId($campaignId, $userId);

            // Return error if campaign is not found
            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }
            // Prepare Stat Response
            $data['campaign_id'] = $campaign->job_id;
            $data['number_of_workers'] = $campaign->number_of_staff;
            $data['spent_amount'] = $spentAmount = $this->jobModel->getCampaignSpentAmount($campaign->id);
            $data['campaign_total_amount'] = $campaignAmount = $campaign->campaign_amount * $campaign->number_of_staff;
            $data['campaign_unit_amount'] = $campaign->campaign_amount;
            $data['campaign_currency'] = $campaign->currency;
            $data['amount_ratio'] = $campaign->currency . '' . $spentAmount . ' / ' . $campaign->currency . '' . $campaignAmount;
            $data['status'] = $this->jobModel->getCampaignStats($campaign->id);

            return response()->json([
                'status' => true,
                'message' => 'Campaign Activities Stat',
                'data' => $data
            ], 200);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function campaignJobList($request, $campaignId)
    {
        try {
            $userId = auth()->user()->id;
            $type = strtolower($request->query('type'));
            $page = strtolower($request->query('page'));

            $campaign = $this->campaignModel->getCampaignByJobId($campaignId, $userId);

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }
            $jobs = $this->jobModel->getJobsByIdAndType($campaign->id, $type, $page);

            $data['campaign_name'] = $campaign->post_title;
            $data['campaign_id'] = $campaign->job_id;
            $data['jobs'] = $jobs->getCollection()->map(function ($job) use ($campaign) {
                return [
                    'job_id' => $job->id,
                    'worker_name' => $this->authModel->findUserById($job->user_id)->name ?? 'Unknown',
                    'campaign_name' => $campaign->post_title,
                    'external_link' => $campaign->post_link,
                    'campaign_id' => $campaign->job_id,
                    'campaign_description' => $campaign->description,
                    'amount' => $campaign->currency . ' ' . $job->amount,
                    'status' => $job->status,
                    'expected_proof_of_completion' => $campaign->proof,
                    'expected_proof_url' => $campaign->expected_result_image,
                    'worker_proof' => $job->comment,
                    'worker_proof_url' => $job->proof_url === 'no image' ? null : $job->proof_url,
                    'approval_or_denial_reason' => $job->reason,
                    'created_at' => $job->created_at,
                    'has_dispute' => $job->is_dispute,
                    'dispute_resolved' => $job->is_dispute_resolved,

                ];
            });

            $pagination = [
                'total' => $jobs->total(),
                'per_page' => $jobs->perPage(),
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'from' => $jobs->firstItem(),
                'to' => $jobs->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Campaign Activities Jobs',
                'data' => $data,
                'pagination' => $pagination,
            ], 200);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function allCampaignJobList($request)
    {
        try {
            $userId = auth()->user()->id;
            $type = strtolower($request->query('type'));
            $page = $request->query('page', 1);

            $campaigns = $this->campaignModel->getUserCampaigns($userId);
            if ($campaigns->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No task found.'
                ], 404);
            }

            $campaignIds = $campaigns->pluck('id')->toArray();

            // Fetch jobs performed on user's campaigns
            $jobs = $this->jobModel->getJobsByCampaignIdsAndType($campaignIds, $type, $page);

            if ($jobs->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No jobs found for the user campaigns.'
                ], 404);
            }

            // Format job data
            $data = $jobs->getCollection()->map(function ($job) {
                $worker = $this->authModel->findUserById($job->user_id);

                return [
                    'campaign_id' => $job->campaign->job_id,
                    'job_id' => $job->id,
                    'worker_name' => $worker->name ?? 'Unknown',
                    'campaign_name' => $job->campaign->post_title,
                    'amount' => "{$job->campaign->currency} {$job->amount}",
                    'status' => $job->status,
                    'created_at' => $job->created_at,
                    'updated_at' => $job->updated_at,
                ];
            });

            return response()->json([
                'status' => true,
                'message' => ucfirst($type) . ' Campaign Jobs Retrieved Successfully',
                'data' => $data,
                'pagination' => [
                    'total' => $jobs->total(),
                    'per_page' => $jobs->perPage(),
                    'current_page' => $jobs->currentPage(),
                    'last_page' => $jobs->lastPage(),
                    'from' => $jobs->firstItem(),
                    'to' => $jobs->lastItem(),
                ],
            ], 200);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }


    public function pauseCampaign($campaignId)
    {
        try {
            $userId = auth()->user()->id;
            // Retrieve the campaign for the authenticated user
            $campaign = $this->campaignModel->getCampaignByJobId($campaignId, $userId);

            // Return error if campaign is not found
            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Toggle the campaign status
            if ($campaign->status === 'Live') {
                $campaign->status = 'Paused';
            } elseif ($campaign->status === 'Paused') {
                $campaign->status = 'Live';
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign status cannot be paused from its current state'
                ], 400);
            }

            // Save the updated campaign status
            $campaign->save();

            $campaign['campaign_id'] = $campaign->job_id;
            $campaign['status'] = $this->mapCampaignStatus($campaign);
            return response()->json([
                'status' => true,
                'message' => 'Campaign status updated successfully to ' . $campaign->status,
                'data' => $campaign
            ], 200);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function jobDetails($request)
    {

        try {
            $userId = auth()->user()->id;

            $campId = $request->query('campaign_id');
            $jobId = $request->query('job_id');

            if (empty($campId) || empty($jobId)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign Id and Job Id cannot be empty'
                ], 400);
            }

            $campaign = $this->campaignModel->getCampaignByJobId($campId, $userId);

            // Return error if campaign is not found
            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            $job = $this->jobModel->getJobByIdAndCampaignId($jobId, $campaign->id);
            // return $job;
            $data = [
                'job_id' => $job->id,
                'campaign_id' => $campaign->job_id,
                'campaign_name' => $campaign->post_title,
                'campaign_description' => $campaign->description,
                'proof_of_completion' => $campaign->proof,
                'worker_name' => $this->authModel->findUserById($job->user_id)->name,
                'worker_id' => $job->user_id,
                'worker_proof' => $job->comment,
                'worker_proof_url' => $job->proof_url === 'no image' ? null : $job->proof_url,
                'job_status' => $job->status,
                'approval_or_denial_reason' => $job->reason,
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
                'has_dispute' => $job->is_dispute,
                'dispute_resolved' => $job->is_dispute_resolved,
            ];

            return response()->json([
                'status' => true,
                'message' => 'Job details retrieved successfully',
                'data' => $data
            ], 200);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function decreasePendingCountAfterDenial($id)
    {
        $userId = auth()->user()->id;
        $campaign = $this->campaignModel->getCampaignById($id, $userId);
        $campaign->pending_count -= 1;
        $campaign->save();
        return true;
    }

    public function increaseCompletedCountAfterApproval($id)
    {
        $userId = auth()->user()->id;
        $campaign = $this->campaignModel->getCampaignById($id, $userId);
        $campaign->completed_count += 1;
        $campaign->pending_count -= 1;
        $campaign->save();
        return true;
    }

    private function processCampaign($total, $request, $job_id, $percent, $allowUpload, $priotize, $currency)
    {
        $user = auth()->user();
        $channel = $currency->code == "NGN" ? 'paystack' : 'paypal';

        $approvalTime = $user->is_business
            ? $request->approval_time
            : 24;
        // Log::info('Base64 input', ['image' => $request->expected_result_image]);

        if ($request->filled('expected_result_image')) {
            $expectedURL = app(CloudinaryService::class)->uploadBase64Image($request->expected_result_image);
        }
        $request->merge([
            'user_id' => $user->id,
            'total_amount' => $total,
            'job_id' => $job_id,
            'currency' => $currency->code,
            'impressions' => 0,
            'pending_count' => 0,
            'completed_count' => 0,
            'allow_upload' => $allowUpload,
            'approved' => $priotize,
            'expected_result_image' => $expectedURL ?? null,
            'approval_time' => $approvalTime
        ]);

        // Create the campaign
        $campaign = $this->campaignModel->createCampaign($request);

        // Process payment transaction
        $this->campaignModel->processPaymentTransaction(
            $user,
            $campaign,
            $total,
            $currency->code,
            $channel,
        );


        Mail::to('victor@freebyztechnologies.com')
            ->cc('alan@freebyztechnologies.com')
            ->send(new AdminCampaignPosted($campaign));

        // Update admin wallet
        $this->campaignModel->updateAdminWallet($percent, $currency->code);

        // Log admin transaction
        $this->campaignModel->logAdminTransaction($percent, $currency->code, $channel, $user);

        return $campaign;
    }
    public function approveOrDeclineJob($request)
    {
        $this->validator->approveOrDenyReason($request);

        try {
            $user = auth()->user();
            $action = strtolower($request->action);
            $jobId = $request->job_id;
            $campId = $request->campaign_id;
            $reason = $request->reason;

            $campaign = $this->campaignModel->getCampaignByJobId($campId);
            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found.',
                ], 404);
            }
            // Retrieve job details
            $job = $this->jobModel->getJobByIdAndCampaignId($jobId, $campaign->id);
            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found.',
                ], 404);
            }

            // Prevent duplicate actions on already processed jobs
            if (in_array($job->status, ['Approved', 'Denied'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'This job has already been ' . strtolower($job->status) . '. Action cannot be performed.',
                ], 400);
            }

            // Retrieve worker details
            $worker = $this->authModel->findUserById($job->user_id);
            $currency = $worker->wallet->base_currency;

            // Perform action
            if ($action === 'deny') {
                $job = $this->jobModel->updateJobStatus($reason, $jobId, 'Denied');
                // $this->decreasePendingCountAfterDenial($campaign->id);
            } elseif ($action === 'approve') {
                $job = $this->jobModel->updateJobStatus($reason, $jobId, 'Approved');
                $this->increaseCompletedCountAfterApproval($campaign->id);
                $this->walletModel->creditWallet($worker, $currency, $job->amount);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid action. Only "approve" or "deny" are allowed.',
                ], 400);
            }

            // Prepare response data
            $data = [
                'job_id' => $job->id,
                'campaign_id' => $campaign->job_id,
                'campaign_name' => $campaign->post_title,
                'campaign_description' => $campaign->description,
                'proof_of_completion' => $campaign->proof,
                'worker_name' => $worker->name,
                'worker_id' => $job->user_id,
                'worker_proof' => $job->comment,
                'worker_proof_url' => $job->proof_url === 'no image' ? null : $job->proof_url,
                'job_status' => $job->status,
                'approval_or_denial_reason' => $job->reason,
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
                'has_dispute' => (bool) $job->is_dispute,
                'dispute_resolved' => (bool) $job->is_dispute_resolved,
                'public_link' => "https://stagging.e-portal.com.ng/tasks/" . $campaign->job_id,

            ];

            return response()->json([
                'status' => true,
                'message' => ucfirst($action) . ' action completed successfully.',
                'job' => $data,
            ], 200);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request.',
            ], 500);
        }
    }

    public function viewCampaign($job_id)
    {
        try {
            $getCampaign = SystemActivities::viewCampaign($job_id);

            if ($getCampaign->currency == 'USD') {
                if (auth()->user()->USD_verified) {
                    $completed = CampaignWorker::where('user_id', auth()->user()->id)->where('campaign_id', $getCampaign->id)->first();
                    $rating = Rating::where('user_id', auth()->user()->id)->where('campaign_id', $getCampaign->id)->first();
                    $checkRating = isset($rating) ? true : false;

                    $data['campaign'] = $getCampaign;
                    $data['completed'] = $completed;
                    $data['is_rated'] = $checkRating;
                    // return view('user.campaign.view', ['campaign' => $getCampaign, 'completed' => $completed, 'is_rated' => $checkRating]);
                } else {
                    return response()->json(['status' => false, 'message' => 'User not verified for Dollar, redirect to a page to get verified'], 401);
                    // return redirect('conversion');
                }
            } else {

                if (auth()->user()->is_verified) {
                    if ($getCampaign['is_completed'] == true) {
                        return redirect('home');
                    } else {
                        $completed = CampaignWorker::where('user_id', auth()->user()->id)->where('campaign_id', $getCampaign->id)->first();
                        $rating = Rating::where('user_id', auth()->user()->id)->where('campaign_id', $getCampaign->id)->first();
                        $checkRating = isset($rating) ? true : false;
                        $data['campaign'] = $getCampaign;
                        $data['completed'] = $completed;
                        $data['is_rated'] = $checkRating;
                        // return view('user.campaign.view', ['campaign' => $getCampaign, 'completed' => $completed, 'is_rated' => $checkRating]);
                    }
                } elseif (!auth()->user()->is_verified && $getCampaign['campaign_amount'] <= 10) {
                    if ($getCampaign['is_completed'] == true) {
                        return response()->json(['status' => false, 'message' => 'The campaign is completed'], 401);
                        // return redirect('#');

                    } else {
                        $completed = CampaignWorker::where('user_id', auth()->user()->id)->where('campaign_id', $getCampaign->id)->first();
                        $rating = Rating::where('user_id', auth()->user()->id)->where('campaign_id', $getCampaign->id)->first();
                        $checkRating = isset($rating) ? true : false;
                        $data['campaign'] = $getCampaign;
                        $data['completed'] = $completed;
                        $data['is_rated'] = $checkRating;
                        // return view('user.campaign.view', ['campaign' => $getCampaign, 'completed' => $completed, 'is_rated' => $checkRating]);
                    }
                } else {
                    return response()->json(['status' => false, 'message' => 'User not verified for Naira, redirect to a page to get verified'], 401);
                    // return redirect('info'); // show user they are not verified, a button should
                }
            }
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
        return response()->json([
            'status' => true,
            'message' => 'Campaign Information',
            'data' => $data
        ], 200);
    }


    public function adminActivities($id)
    {

        $cam = Campaign::where('job_id', $id)->first();

        $approved = $cam->completed()->where('status', 'Approved')->count();

        $remainingNumber = $cam->number_of_staff - $approved;

        $count =  $remainingNumber;

        return view('admin.campaign_mgt.admin_activities', ['lists' => $cam, 'count' => $count]);
    }
}
