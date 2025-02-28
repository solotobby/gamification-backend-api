<?php

namespace App\Services;

use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\BannerRepositoryModel;
use App\Repositories\SurveyRepositoryModel;
use App\Repositories\WalletRepositoryModel;
use App\Services\Providers\AWSServiceProvider;
use App\Validators\BannerValidator;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class BannerService
{
    protected $bannerModel;
    protected $surveyModel;
    protected $currencyModel;
    protected $walletModel;
    protected $bannerValidator;
    protected $awsService;

    public function __construct(
        BannerRepositoryModel $bannerModel,
        SurveyRepositoryModel $surveyModel,
        CurrencyRepositoryModel $currencyModel,
        WalletRepositoryModel $walletModel,
        BannerValidator $bannerValidator,
        AWSServiceProvider $awsService,
    ) {
        $this->bannerModel = $bannerModel;
        $this->surveyModel = $surveyModel;
        $this->currencyModel = $currencyModel;
        $this->walletModel = $walletModel;
        $this->bannerValidator = $bannerValidator;
        $this->awsService = $awsService;
    }

    public function listBanner()
    {

        try {
            $user = auth()->user();
            $banners = $this->bannerModel->getBannerById($user);

            $data = [];
            foreach ($banners as $banner) {

                $data[] = [
                    'id' => $banner->id,
                    'user_id' => $banner->user_id,
                    'banner_id' => $banner->banner_id,
                    'banner_url' => $banner->banner_url,
                    'external_link' => $banner->external_link,
                    'currency' => $banner->currency,
                    'budget' => $banner->amount,
                    'status' => $banner->status ? true : false,
                    'banner_end_date' => $banner->banner_end_date,
                    'clicks' => $banner->click_count,
                    'impressions' => $banner->impression_count,
                    'created_at' => $banner->created_at,
                    'updated_at' => $banner->updated_at,
                ];
            }
            $pagination = [
                'current_page' => $banners->currentPage(),
                'last_page' => $banners->lastPage(),
                'per_page' => $banners->perPage(),
                'total' => $banners->total(),
                'from' => $banners->firstItem(),
                'to' => $banners->lastItem(),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Banners retrieved successfully.',
                'data' => $data,
                'pagination' => $pagination,
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function createBanner($request)
    {
        $this->bannerValidator->createBannerValidator($request);
        try {

            $user = auth()->user();

            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);
            $currency = $this->currencyModel->getCurrencyByCode($mapCurrency);

            DB::beginTransaction();
            // Check wallet balance
            if (!$this->walletModel->checkWalletBalance(
                $user,
                $currency->code,
                $request->budget
            )) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have sufficient funds in your wallet',
                ], 401);
            }

            if (!$this->walletModel->debitWallet(
                $user,
                $currency->code,
                $request->budget
            )) {
                return response()->json([
                    'status' => false,
                    'message' => 'Wallet debit failed. Please try again.',
                ], 401);
            }

           // s3 bucket processing
            $file = $request->file('banner_image');
            $filePath = 'ad/' . time() . '.' . $file->extension();
            $bannerUrl = $this->awsService->uploadImage($file, $filePath);

            //Save Banner
            $banner = $this->bannerModel->createBanner(
                $user,
                $request,
                $bannerUrl,
                $currency
            );

            //transaction log
            $this->walletModel->createTransaction(
                $user,
                $request->budget,
                time(),
                $banner->id,
                $currency->code,
                'ad_banner',
                'Ad Banner Placement by ' . $user->name,
                'debit',
            );

            //Save banner Interest
            $this->bannerModel->createBannerInterest(
                $request->audience,
                $banner
            );

            // $content = 'Your ad banner placement is successfully created. It is currently under review, you will get a notification when it goes live!';
            // $subject = 'Ad Banner Placement - Under Review';
            // Mail::to(auth()->user()->email)->send(new GeneralMail(auth()->user(), $content, $subject, ''));

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Banner Ad Created Successfully and it is currently under review, you will get a notification when it goes live!',
                'data' => $banner
            ], 201);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }

    public function getPreference()
    {
        try {

            $baseCurrency = auth()->user()->wallet->base_currency;
            $mapCurrency = $this->walletModel->mapCurrency($baseCurrency);

            $data['interest'] = $this->surveyModel->listAllInterest();
            $data['currency']  = $this->currencyModel->getCurrencyByCode($mapCurrency);

            return response()->json([
                'status' => true,
                'message' => 'List of banner interests retrieved successfully',
                'data' => $data
            ], 200);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request',
            ], 500);
        }
    }

    public function adView($bannerId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $ban = $this->bannerModel->findBanner($bannerId);
            // Check if banner exists
            if (!$ban) {
                return response()->json([
                    'status' => false,
                    'message' => 'Banner not found.'
                ], 404);
            }

            // Update click count
            $ban->click_count += 1;
            $ban->save();

            // Check if the banner has reached the maximum click count
            if ($ban->click_count >= $ban->clicks) {
                $ban->live_state = 'Ended';
                $ban->status = false;
                $ban->banner_end_date = Carbon::now();
                $ban->save();
            }

            // Log the click for the banner
            $this->bannerModel->logBannerClicks($user, $ban->id);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Banner click recorded successfully',
                'data' => [
                    'link' => $ban->external_link
                ]
            ], 200);
        } catch (Exception $exception) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing banner view.'
            ], 500);
        }
    }
}
