<?php

namespace App\Repositories;

use App\Models\Banner;
use App\Models\BannerClick;
use App\Models\BannerImpression;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BannerRepositoryModel
{

    public function getBannerById($user, $page = null)
    {
        return Banner::where(
            'user_id',
            $user->id
        )->orderBy(
            'created_at',
            'DESC'
        )->paginate(
            10,
            ['*'],
            'page',
            $page
        );
    }

    public function createBanner($user, $request, $bannerUrl, $currency)
    {
        $banner = new Banner();

        $banner->user_id = $user->id;
        $banner->banner_id = Str::random(7);
        $banner->external_link = $request->external_link;
        $banner->ad_placement_point = 0;
        $banner->adplacement_position = 'top';
        $banner->age_bracket = '18';
        $banner->duration = '1';
        $banner->country = 'all';
        $banner->currency = $currency->code;
        $banner->status = false;
        $banner->amount = $request->budget;
        $banner->banner_url = $bannerUrl;
        $banner->impression = 0;
        $banner->impression_count = 0;
        $banner->clicks = $request->budget / $currency->banner_clicks_amount;
        $banner->click_count = 0;

        $banner->save();
        return $banner;
    }


    public function createBannerInterest($audience, $banner){
        foreach ($audience as $id) {
            DB::table(
                'banner_interests'
            )->insert([
                'banner_id' => $banner->id,
                'interest_id' => $id,
                'unit' => $banner->clicks,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
}
