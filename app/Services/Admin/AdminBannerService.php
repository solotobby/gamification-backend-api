<?php

namespace App\Services\Admin;

use App\Mail\GeneralMail;
use App\Repositories\AuthRepositoryModel;
use App\Repositories\BannerRepositoryModel;
use App\Validators\BannerValidator;
use Exception;
use Illuminate\Support\Facades\Mail;

class AdminBannerService
{
    protected $bannerModel;
    protected $authModel;
    protected $bannerValidator;

    public function __construct(
        BannerRepositoryModel $bannerModel,
        AuthRepositoryModel $authModel,
        BannerValidator $bannerValidator,
    ) {
        $this->bannerModel = $bannerModel;
        $this->authModel = $authModel;
        $this->bannerValidator = $bannerValidator;
    }

    public function listBanners()
    {
        try {
            $banners = $this->bannerModel->getBannerByAdmin();

            $data = [];
            foreach ($banners as $banner) {

                $data[] = [
                    'id' => $banner->id,
                    'user_id' => $banner->user_id,
                    'user_name' => $banner->user->name,
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

    public function toggleBannerStatus($request)
    {
        $this->bannerValidator->toggleBannerValidator($request);

        try {
            $ban = $this->bannerModel->findBanner($request->banner_id);

            if (!$ban) {
                return response()->json([
                    'status' => false,
                    'message' => 'Banner not found.'
                ], 404);
            }

            // Handle activation
            if ($request->action === 'activate') {
                if ($ban->status) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Banner is already live.'
                    ], 400);
                }

                $ban->status = true;
                $ban->live_state = 'Started';
                $ban->save();


                $user = $ban->user;
                $content = 'Congratulations, your ad is Live on Freebyz.';
                $subject = 'Ad Banner Placement - Live!';
                Mail::to($user->email)->send(new GeneralMail(
                        $user,
                        $content,
                        $subject,
                        ''
                    ));

                return response()->json([
                    'status' => true,
                    'message' => 'Ad Banner is now live!'
                ], 200);
            }

            // Handle deactivation
            if ($request->action === 'deactivate') {
                if (!$ban->status) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Banner is not live.'
                    ], 400);
                }

                $ban->status = false;
                $ban->live_state = 'Ended';
                $ban->banner_end_date = now();
                $ban->save();

                return response()->json([
                    'status' => true,
                    'message' => 'Ad Banner has been deactivated.'
                ], 200);
            }
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'error' => $exception->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }
    }
}
