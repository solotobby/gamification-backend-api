<?php

namespace App\Repositories;

use App\Models\Banner;
use App\Models\BannerClick;
use App\Models\BannerImpression;

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
}
