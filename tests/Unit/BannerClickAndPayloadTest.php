<?php

namespace Tests\Unit;

use App\Models\Banner;
use App\Repositories\BannerRepositoryModel;
use App\Services\BannerService;
use Tests\TestCase;

class BannerClickAndPayloadTest extends TestCase
{
    public function test_banner_repository_find_banner_by_banner_id_or_id()
    {
        $banner = new Banner();
        $banner->user_id = 1;
        $banner->banner_id = 'testbnr';
        $banner->banner_url = 'https://example.com/banner.png';
        $banner->external_link = 'https://example.com/dest';
        $banner->ad_placement_point = 0;
        $banner->adplacement_position = 'top';
        $banner->age_bracket = '18';
        $banner->duration = '1';
        $banner->country = 'all';
        $banner->currency = 'NGN';
        $banner->status = true;
        $banner->live_state = 'Started';
        $banner->amount = 1000;
        $banner->clicks = 100;
        $banner->click_count = 0;
        $banner->impression = 0;
        $banner->impression_count = 0;
        $banner->save();

        $repo = app(BannerRepositoryModel::class);

        // Find by string banner_id
        $foundByBannerId = $repo->findBanner('testbnr');
        $this->assertNotNull($foundByBannerId);
        $this->assertEquals('testbnr', $foundByBannerId->banner_id);

        // Find by numeric ID
        $foundById = $repo->findBanner($banner->id);
        $this->assertNotNull($foundById);
        $this->assertEquals($banner->id, $foundById->id);

        // Test BannerService public ad view
        $bannerService = app(BannerService::class);
        $response = $bannerService->adViewPublic('testbnr');
        $json = $response->getData(true);

        $this->assertTrue($json['status']);
        $this->assertEquals('https://example.com/dest', $json['link']);
        $this->assertEquals('https://example.com/dest', $json['data']['link']);
        $this->assertEquals('https://example.com/dest', $json['external_link']);

        // Clean up
        $banner->delete();
    }
}
