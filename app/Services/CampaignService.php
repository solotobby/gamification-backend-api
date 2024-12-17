<?php

namespace App\Services;

use App\Repositories\CampaignRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Repositories\Admin\CurrencyRepositoryModel;
use Throwable;
use Illuminate\Support\Facades\Mail;
use App\Validators\CampaignValidator;
use App\Helpers\SystemActivities;
use App\Mail\ApproveCampaign;
use App\Mail\CreateCampaign;
use App\Mail\GeneralMail;
use App\Models\Campaign;
use App\Models\CampaignWorker;
use App\Models\Category;
use App\Models\DisputedJobs;
use App\Models\PaymentTransaction;
use App\Models\Rating;
use App\Models\User;
use App\Models\Wallet;
use Exception;

class CampaignService
{
    protected $campaignModel, $validator, $currencyModel, $walletModel;
    public function __construct(
        CampaignRepositoryModel $campaignModel,
        CampaignValidator $validator,
        CurrencyRepositoryModel $currencyModel,
        WalletRepositoryModel $walletModel
    ) {
        $this->campaignModel = $campaignModel;
        $this->validator = $validator;
        $this->currencyModel = $currencyModel;
        $this->walletModel = $walletModel;
    }

    public function getCampaigns()
    {
        try {
            $user = auth()->user();

            // Fetch campaigns by user ID
            $campaigns = $this->campaignModel->getCampaignsByPagination($user->id);

            // Fetch user's base currency and map it
            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

            // Fetch currency details
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            // Validate retrieved data
            if (!$currency) {
                return response()->json(['status' => false, 'message' => 'Currency not found.'], 404);
            }

            // Prepare campaign data
            $data = [];
            foreach ($campaigns as $campaign) {
                $unitPrice = $campaign->campaign_amount;
                $totalAmount = $campaign->total_amount;

                //return $campaign->currency;
                // Check if conversion is needed
                if ($currency->code !== $campaign->currency) {

                    $currencyRate = $this->currencyModel->convertCurrency($campaign->currency, $currency->code);

                    // return $currencyRate;
                    if (!$currencyRate) {
                        return response()->json(['status' => false, 'message' => 'Currency conversion rate not found.'], 404);
                    }

                    $rate = $currencyRate->rate;
                    $unitPrice *= $rate;
                    $totalAmount *= $rate;
                }

                $data[] = [
                    'id' => $campaign->id,
                    'user_id' => $campaign->user_id,
                    'job_id' => $campaign->job_id,
                    'title' => $campaign->post_title,
                    'approved' => $campaign->pending_count . '/' . $campaign->completed_count,
                    'unit_price' => round($unitPrice, 5),
                    'total_amount' => round($totalAmount, 5),
                    'currency' => $currency->code,
                    'status' => $campaign->status,
                    'created' => $campaign->created_at,
                ];
            }

            return response()->json(['status' => true, 'message' => 'Campaign List', 'data' => $data], 200);
        } catch (Throwable $exception) {
            return response()->json(['status' => false, 'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
    }

    public function create($request)
    {
        $this->validator->validateCampaignCreation($request);

        try {
            $user = auth()->user();
            $baseCurrency = $user->wallet->base_currency;

            // Map currency to determine prioritization and upload amount
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

            // Get the currency details and status
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            // Determine prioritization amount and status
            $prAmount = $request->priotize ? (int)($currency->priotize) : 0;
            $priotize = $request->priotize ? 'Priotize' : 'Pending';

            // Calculate initial upload amount
            $iniAmount = $request->allow_upload ? $request->number_of_staff * (int)($currency->allow_upload) : 0;
            $allowUpload = (bool)$request->allow_upload;

            // Get the Subcategory amount from db
            $subAmount = $this->campaignModel->getSubCategoryAmount($request->campaign_subcategory, $request->campaign_type);
            // return $subAmount;
            // Calculate estimated amount and total
            $estAmount = $request->number_of_staff * $subAmount->amount;
            $percent = (60 / 100) * $estAmount;
            $total = $estAmount + $percent + $iniAmount + $prAmount;

            // Generate a unique job ID
            $jobId = rand(10000, 10000000);

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

            $type =
                // Process the campaign
                $campaign = $this->processCampaign(
                    $total,
                    $request,
                    $jobId,
                    $percent,
                    $allowUpload,
                    $priotize,
                );

            // Notify user via email
            Mail::to($user->email)->send(new CreateCampaign($campaign));

            return response()->json([
                'status' => true,
                'message' => 'Campaign Posted Successfully. A member of our team will activate your campaign within 24 hours.',
                'data' => $campaign,
            ], 201);
        } catch (Throwable $e) {
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
            $campaign = $this->campaignModel->getCampaignById($request->campaign_id, $user->id);
            // return $campaign;
            $baseCurrency = $user->wallet->base_currency;

            // Get the Subcategory amount from db
            $subAmount = $this->campaignModel->getSubCategoryAmount(
                $campaign->campaign_subcategory,
                $campaign->campaign_type
            );
            $estAmount = $request->new_worker_number * $subAmount->amount;
            $percent = (60 / 100) * $estAmount;
            $total = $estAmount + $percent;

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
                $request->campaign_id,
                $total
            );

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
            $baseCurrency = auth()->user()->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

            // Fetch all active categories
            $categories = $this->campaignModel->listCategories();
            if (!$categories) {
                return response()->json([
                    'status' => false,
                    'message' => 'No categories found',
                    'data' => []
                ], 404);
            }

            $data['category'] = $categories->map(function ($category) use ($mapCurrency) {
                // Fetch subcategories for this category
                $subCategories = $this->campaignModel->listSubCategories($category->id);

                // Transform the subcategories
                $subCategoryData = $subCategories->map(function ($sub) use ($mapCurrency) {
                    $amount = $sub->amount;

                    // If the currency is not NGN, convert the amount
                    if ($mapCurrency !== 'NGN') {
                        $currencyRate = $this->currencyModel->convertCurrency('NGN', $mapCurrency);

                        if (!$currencyRate) {
                            return response()->json(['status' => false, 'message' => 'Currency conversion rate not found.'], 404);
                        }

                        $rate = $currencyRate->rate;
                        $amount *= $rate; // Convert the amount based on the rate
                    }

                    return [
                        'id' => $sub->id,
                        'amount' => round($amount, 5),
                        'category_id' => $sub->category_id,
                        'name' => $sub->name,
                        //'amt_usd' => $sub->usd ?? $sub->amount,
                    ];
                });

                // Add subcategories under the category
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'subcategories' => $subCategoryData
                ];
            });

            // Get the currency details
            $data['currency']  = $this->currencyModel->getCurrencyByCode($mapCurrency);


            return response()->json([
                'status' => true,
                'message' => 'Categories fetched successfully',
                'data' => $data
            ], 200);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage(), 'message' => 'Error processing request'], 500);
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
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Campaign Information', 'data' => $data], 200);
    }
    private function processCampaign($total, $request, $job_id, $percent, $allowUpload, $priotize)
    {
        $user = auth()->user();
        $currency = $user->wallet->base_currency == "Naira" ? 'NGN' : 'USD';
        $channel = $user->wallet->base_currency == "Naira" ? 'paystack' : 'paypal';

        $request->merge([
            'user_id' => $user->id,
            'total_amount' => $total,
            'job_id' => $job_id,
            'currency' => $currency,
            'impressions' => 0,
            'pending_count' => 0,
            'completed_count' => 0,
            'allow_upload' => $allowUpload,
            'approved' => $priotize
        ]);

        // Create the campaign
        $campaign = $this->campaignModel->createCampaign($request);

        // Process payment transaction
        $this->campaignModel->processPaymentTransaction(
            $user,
            $campaign,
            $total,
            $currency,
            $channel,
        );

        // Update admin wallet
        $this->campaignModel->updateAdminWallet($percent, $currency);

        // Log admin transaction
        $this->campaignModel->logAdminTransaction($percent, $currency, $channel, $user);

        return $campaign;
    }

    public function calculateCampaignPrice($request)
    {

        $validated = $request->validate([
            'staff_number' => 'required|numeric',
            'unit_price' => 'required|string',
        ]);

        try {

            $est_amount = $validated['staff_number'] * $validated['unit_price'];
            $percent = (60 / 100) * $est_amount;
            $total = $est_amount + $percent;
        } catch (Throwable $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Campaign Price', 'data' => $total], 200);
    }

    public function viewResponse($id)
    {
        try {

            $res = CampaignWorker::where('id', $id)->where('status', 'Pending')->first();
            if (!$res) {
                return response()->json(['status' => false, 'message' => 'Invalid Response'], 401);
            }
            $camp = Campaign::where('id', $res->campaign_id)->first(['id', 'post_title', 'description', 'proof', 'campaign_amount']);

            $data['campaignInfo'] = $camp;
            $data['response'] = $res;
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Campaign Activities', 'data' => $data], 200);
    }

    public function activities($id)
    {
        try {
            // $cam = Campaign::with(['completed'])->where('job_id', $id)->where('user_id', auth()->user()->id)->select(['id', 'job_id', 'post_title'])->get();
            $cam = Campaign::with(['completed' => function ($query) {
                $query->where('status', 'Pending'); // Filter by status 'Pending'
            }])
                ->where('job_id', $id)
                ->where('user_id', auth()->user()->id)
                ->select(['id', 'job_id', 'post_title'])
                ->get();
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Campaign Activities', 'data' => $cam], 200);
    }
    public function pauseCampaign($id)
    {
        try {

            $campaign = Campaign::where('job_id', $id)->where('user_id', auth()->user()->id)->first();
            if (!$campaign) {
                return response()->json(['status' => false, 'message' => 'Campaign invalid'], 401);
            }
            if ($campaign->status == 'Live') {
                $campaign->status = 'Paused';
                $campaign->save();
            } elseif ($campaign->status == 'Decline') {
            } elseif ($campaign->status == 'Offline') {
            } else {
                $campaign->status = 'Live';
                $campaign->save();
            }
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Campaign ' . $campaign->status . ' successfully', 'data' => $campaign], 200);
    }

    public function campaignDecision($request)
    {
        $request->validate([
            'reason' => 'required|string',
            'action' => 'required|string',
            'campaign_worker_id' => 'required|string',
        ]);

        try {

            $workSubmitted = CampaignWorker::where('id', $request->campaign_worker_id)->first();
            $campaign = Campaign::where('id', $workSubmitted->campaign_id)->first();

            if ($workSubmitted->reason != null) {
                return response()->json(['status' => false, 'message' => 'Campaign has been attended to'], 401);
            }
            if ($campaign->is_completed == true) {
                return response()->json(['status' => false, 'message' => 'Campaign has reached its maximum capacity'], 401);
            }


            if ($request->action == 'approve') {




                //    $completed_campaign = $campaign->completed()->where('status', 'Approved')->count();
                //    if($completed_campaign >= $campaign->number_of_staff){
                //         return back()->with('error', 'Campaign has reached its maximum capacity');
                //    }

                $user = User::where('id', $workSubmitted->user_id)->first();

                $workSubmitted->status = 'Approved';
                $workSubmitted->reason = $request->reason;
                $workSubmitted->save();

                //update completed action
                $campaign->completed_count += 1;
                $campaign->pending_count -= 1;
                $campaign->save();

                setIsComplete($workSubmitted->campaign_id);

                if ($campaign->currency == 'NGN') {
                    $currency = 'NGN';
                    $channel = 'paystack';
                    creditWallet($user, 'Naira', $workSubmitted->amount);
                } elseif ($campaign->currency == 'USD') {
                    $currency = 'USD';
                    $channel = 'paypal';
                    creditWallet($user, 'Dollar', $workSubmitted->amount);
                } elseif ($campaign->currency == null) {
                    $currency = 'NGN';
                    $channel = 'paystack';
                    creditWallet($user, 'Naira', $workSubmitted->amount);
                }


                $ref = time();

                PaymentTransaction::create([
                    'user_id' =>  $workSubmitted->user_id,
                    'campaign_id' =>  $workSubmitted->campaign->id,
                    'reference' => $ref,
                    'amount' =>  $workSubmitted->amount,
                    'status' => 'successful',
                    'currency' => $currency,
                    'channel' => $channel,
                    'type' => 'campaign_payment',
                    'description' => 'Campaign Payment for ' . $workSubmitted->campaign->post_title,
                    'tx_type' => 'Credit',
                    'user_type' => 'regular'
                ]);

                SystemActivities::activityLog($user, 'campaign_payment', $user->name . ' earned a campaign payment of NGN' . number_format($workSubmitted->amount), 'regular');

                $subject = 'Job Approved';
                $status = 'Approved';
                //    Mail::to($workSubmitted->user->email)->send(new ApproveCampaign($workSubmitted, $subject, $status));
                $data['decision_status'] = 'Campaign Approved';
                $data['work'] = $workSubmitted;
                //return back()->with('success', 'Campaign Approve Successfully');


            } else {

                //check if the
                // $chckCount = PaymentTransaction::where('user_id', $workSubmitted->campaign->user_id)->where('type', 'campaign_payment_refund')->whereDate('created_at', Carbon::today())->count();
                // if($chckCount >= 3){
                //     return back()->with('error', 'You cannot deny more than 3 jobs in a day');
                // }
                $workSubmitted->status = 'Denied';
                $workSubmitted->reason = $request->reason;
                $workSubmitted->save();

                $this->removePendingCountAfterDenial($workSubmitted->campaign_id);

                // $campaign = Campaign::where('id', $deny->campaign_id)->first();
                // $campaingOwner = User::where('id', $campaign->user_id)->first();

                if ($campaign->currency == 'NGN') {
                    $currency = 'Naira';
                    $channel = 'paystack';
                } elseif ($campaign->currency == 'USD') {
                    $currency = 'Dollar';
                    $channel = 'paypal';
                } elseif ($campaign->currency == null) {
                    $currency = 'Naira';
                    $channel = 'paystack';
                }

                // creditWallet($campaingOwner, $currency, $workSubmitted->amount);

                // $ref = time();

                // PaymentTransaction::create([
                //     'user_id' => $workSubmitted->campaign->user_id,
                //     'campaign_id' => $workSubmitted->campaign->id,
                //     'reference' => $ref,
                //     'amount' => $workSubmitted->amount,
                //     'status' => 'successful',
                //     'currency' => $currency,
                //     'channel' => $channel,
                //     'type' => 'campaign_payment_refund',
                //     'description' => 'Campaign Payment Refund for '.$workSubmitted->campaign->post_title,
                //     'tx_type' => 'Credit',
                //     'user_type' => 'regular'
                // ]);



                $subject = 'Job Denied';
                $status = 'Denied';
                // Mail::to($workSubmitted->user->email)->send(new ApproveCampaign($workSubmitted, $subject, $status));
                // return back()->with('success', 'Campaign has been denied');
                $data['decision_status'] = 'Campaign Denied';
                $data['work'] = $workSubmitted;
            }
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Campaign Decision submitted', 'data' => $data], 201);
    }


    public function removePendingCountAfterDenial($id)
    {
        $campaign = Campaign::where('id', $id)->first();
        $campaign->pending_count -= 1;
        $campaign->save();
    }

    public function approveCampaign($id)
    {
        $approve = CampaignWorker::where('id', $id)->first();
        if ($approve->reason != null) {
            return back()->with('error', 'Campaign has been attended to');
        }
        $approve->status = 'Approved';
        $approve->reason = 'Approved by User';
        $approve->save();

        $currency = '';
        $channel = '';
        if ($approve->currency == 'NGN') {
            $currency = 'NGN';
            $channel = 'paystack';
            $wallet = Wallet::where('user_id', $approve->user_id)->first();
            $wallet->balance += $approve->amount;
            $wallet->save();
        } else {
            $currency = 'NGN';
            $channel = 'paystack';
            $wallet = Wallet::where('user_id', $approve->user_id)->first();
            $wallet->usd_balance += $approve->amount;
            $wallet->save();
        }

        $ref = time();
        PaymentTransaction::create([
            'user_id' => $approve->user_id,
            'campaign_id' => $approve->campaign->id,
            'reference' => $ref,
            'amount' => $approve->amount,
            'status' => 'successful',
            'currency' => $currency,
            'channel' => $channel,
            'type' => 'campaign_payment',
            'description' => 'Campaign Payment for ' . $approve->campaign->post_title,
            'tx_type' => 'Credit',
            'user_type' => 'regular'
        ]);

        $subject = 'Job Approved';
        $status = 'Approved';
        Mail::to($approve->user->email)->send(new ApproveCampaign($approve, $subject, $status));

        return back()->with('success', 'Campaign Approve Successfully');
    }

    public function denyCampaign($id)
    {
        $deny = CampaignWorker::where('id', $id)->first();
        $deny->status = 'Denied';
        $deny->reason = 'Denied by User';
        $deny->save();
        $subject = 'Job Denied';
        $status = 'Denied';
        Mail::to($deny->user->email)->send(new ApproveCampaign($deny, $subject, $status));
        return back()->with('error', 'Campaign Denied Successfully');
    }

    public function approvedCampaigns()
    {
        try {
            $mycampaigns = Campaign::where('user_id', auth()->user()->id)->pluck('id')->toArray();
            $approved = CampaignWorker::with(['user:id,name', 'campaign:id,post_title'])->whereIn('campaign_id', $mycampaigns)->where('status', 'Approved')->orderby('created_at', 'ASC')->select(['id', 'user_id', 'campaign_id', 'amount', 'status', 'created_at'])->paginate(10);
            if (!$approved) {
                return response()->json(['status' => false, 'message' => 'There are no Approved Campaigns'], 401);
            }
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Approved Campaigns', 'data' =>  $approved], 200);
    }
    public function deniedCampaigns()
    {
        try {
            $mycampaigns = Campaign::where('user_id', auth()->user()->id)->pluck('id')->toArray();
            $denied = CampaignWorker::with(['user:id,name', 'campaign:id,post_title'])->whereIn('campaign_id', $mycampaigns)->where('status', 'Denied')->orderby('created_at', 'ASC')->select(['id', 'user_id', 'campaign_id', 'amount', 'status', 'created_at'])->paginate(10); //CampaignWorker::with(['user:id,name'])->whereIn('campaign_id', $mycampaigns)->where('status', 'Denied')->orderby('created_at', 'ASC')->paginate(10);
            if (!$denied) {
                return response()->json(['status' => false, 'message' => 'There are no Denied Campaigns'], 401);
            }
            // return view('user.campaign.denied', ['lists' => $denied]);
        } catch (Exception $exception) {
            return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
        }
        return response()->json(['status' => true, 'message' => 'Denied Campaigns', 'data' => $denied], 200);
    }

    public function completedJobs()
    {
        $completedJobs = CampaignWorker::where('user_id', auth()->user()->id)->orderBy('created_at', 'ASC')->get();
        return view('user.campaign.completed_jobs', ['lists' => $completedJobs]);
    }

    public function disputedJobs()
    {
        $disputedJobs = CampaignWorker::where('user_id', auth()->user()->id)->where('is_dispute', true)->orderBy('created_at', 'ASC')->get();
        return view('user.campaign.disputed_jobs', ['lists' => $disputedJobs]);
    }

    public function processDisputedJobs($request)
    {
        $workDone = CampaignWorker::where('id', $request->id)->first();
        $workDone->is_dispute = true;
        $workDone->save();

        $disputedJob = DisputedJobs::create([
            'campaign_worker_id' => $workDone->id,
            'campaign_id' => $workDone->campaign_id,
            'user_id' => auth()->user()->id,
            'reason' => $request->reason
        ]);


        if ($disputedJob) {
            $subject = 'New Dispute Raised';
            $content = 'A despute has been raised by ' . auth()->user()->name . ' on a Job. Please follow the link below to attend to it.';
            $url = 'admin/campaign/disputes/' . $workDone->id;
            Mail::to('freebyzcom@gmail.com')->send(new GeneralMail(auth()->user(), $content, $subject, $url));
            return back()->with('success', 'Dispute Submitted Successfully');
        }
    }

    // public function addMoreWorkers($request)
    // {

    //     $validated = $this->validate($request, [

    //         'new_number' => 'required|numeric',
    //         'job_id' => 'required|string',


    //     ]);

    //     try {

    //         $campaign = Campaign::where('job_id', $validated['job_id'])->first();
    //         $est_amount = $validated['new_number'] * $campaign->campain_amount;
    //         $percent = (60 / 100) * $est_amount;
    //         $total = $est_amount + $percent;
    //         //[$est_amount, $percent, $total];
    //         $wallet = Wallet::where('user_id', auth()->user()->id)->first();
    //         if (auth()->user()->wallet->base_currency == 'Naira') {

    //             $uploadFee = '';
    //             if ($campaign->allow_upload == 1) {
    //                 $uploadFee = $validated['new_number'] * 5;
    //             } else {
    //                 $uploadFee = 0;
    //             }
    //             if ($wallet->balance >= $total) {
    //                 $wallet->balance -= $total + $uploadFee;
    //                 $wallet->save();


    //                 $campaign->number_of_staff += $validated['new_number'];
    //                 $campaign->total_amount += $est_amount;
    //                 $campaign->is_completed = false;
    //                 $campaign->save();

    //                 $currency = 'NGN';
    //                 $channel = 'paystack';

    //                 $ref = time();
    //                 PaymentTransaction::create([
    //                     'user_id' => auth()->user()->id,
    //                     'campaign_id' => $campaign->id,
    //                     'reference' => $ref,
    //                     'amount' => $total,
    //                     'status' => 'successful',
    //                     'currency' => $currency,
    //                     'channel' => $channel,
    //                     'type' => 'added_more_worker',
    //                     'description' => 'Added worker for ' . $campaign->post_title . ' campaign',
    //                     'tx_type' => 'Debit',
    //                     'user_type' => 'regular'
    //                 ]);

    //                 //credit admin
    //                 $adminWallet = Wallet::where('user_id', '1')->first();
    //                 $adminWallet->balance += $percent;
    //                 $adminWallet->save();
    //                 PaymentTransaction::create([
    //                     'user_id' => '1',
    //                     'campaign_id' => $campaign->id,
    //                     'reference' => $ref,
    //                     'amount' => $percent,
    //                     'status' => 'successful',
    //                     'currency' => $currency,
    //                     'channel' => $channel,
    //                     'type' => 'campaign_revenue_add',
    //                     'description' => 'Revenue for worker added on ' . $campaign->post_title . ' campaign',
    //                     'tx_type' => 'Credit',
    //                     'user_type' => 'admin'
    //                 ]);

    //                 $content = "You have successfully increased the number of your workers.";
    //                 $subject = "Add More Worker";
    //                 $user = User::where('id', auth()->user()->id)->first();
    //                 Mail::to(auth()->user()->email)->send(new GeneralMail($user, $content, $subject, ''));
    //                 // return back()->with('success', 'Worker Updated Successfully');
    //                 $data = $campaign;
    //             } else {
    //                 return response()->json(['status' => false, 'message' => 'You do not have suficient funds in your wallet'], 401);
    //             }
    //         } else {
    //             if ($wallet->usd_balance >= $total) {
    //                 $campaign = Campaign::where('job_id', $validated['job_id'])->first();
    //                 $uploadFee = '';
    //                 if ($campaign->allow_upload == 1) {
    //                     $uploadFee = $validated['new_number'] * 0.01;
    //                 } else {
    //                     $uploadFee = 0;
    //                 }

    //                 $wallet->usd_balance -= $total + $uploadFee;
    //                 $wallet->save();

    //                 $campaign->number_of_staff += $validated['new_number'];
    //                 $campaign->total_amount += $est_amount;
    //                 $campaign->is_completed = false;
    //                 $campaign->save();


    //                 $currency = 'USD';
    //                 $channel = 'paypal';

    //                 $ref = time();
    //                 PaymentTransaction::create([
    //                     'user_id' => auth()->user()->id,
    //                     'campaign_id' => $campaign->id,
    //                     'reference' => $ref,
    //                     'amount' => $total,
    //                     'status' => 'successful',
    //                     'currency' => $currency,
    //                     'channel' => $channel,
    //                     'type' => 'added_more_worker',
    //                     'description' => 'Added worker for ' . $campaign->post_title . ' campaign',
    //                     'tx_type' => 'Debit',
    //                     'user_type' => 'regular'
    //                 ]);

    //                 //credit admin
    //                 $adminWallet = Wallet::where('user_id', '1')->first();
    //                 $adminWallet->usd_balance += $percent;
    //                 $adminWallet->save();

    //                 PaymentTransaction::create([
    //                     'user_id' => '1',
    //                     'campaign_id' => $campaign->id,
    //                     'reference' => $ref,
    //                     'amount' => $percent,
    //                     'status' => 'successful',
    //                     'currency' => $currency,
    //                     'channel' => $channel,
    //                     'type' => 'campaign_revenue_add',
    //                     'description' => 'Revenue for worker added on ' . $campaign->post_title . ' campaign',
    //                     'tx_type' => 'Credit',
    //                     'user_type' => 'admin'
    //                 ]);


    //                 $content = "You have successfully increased the number of your workers.";
    //                 $subject = "Add More Worker";
    //                 $user = User::where('id', auth()->user()->id)->first();
    //                 Mail::to(auth()->user()->email)->send(new GeneralMail($user, $content, $subject, ''));
    //                 $data = $campaign;
    //                 // return back()->with('success', 'Worker Updated Successfully');
    //             } else {
    //                 return response()->json(['status' => false, 'message' => 'You do not have suficient funds in your wallet'], 401);
    //             }
    //         }
    //     } catch (Exception $exception) {
    //         return response()->json(['status' => false,  'error' => $exception->getMessage(), 'message' => 'Error processing request'], 500);
    //     }
    //     return response()->json(['status' => true, 'message' => 'Worker Updated Successfully', 'data' => $data], 201);
    // }

    public function adminActivities($id)
    {

        $cam = Campaign::where('job_id', $id)->first();

        $approved = $cam->completed()->where('status', 'Approved')->count();

        $remainingNumber = $cam->number_of_staff - $approved;

        $count =  $remainingNumber;

        return view('admin.campaign_mgt.admin_activities', ['lists' => $cam, 'count' => $count]);
    }
}
