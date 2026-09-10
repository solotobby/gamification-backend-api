<?php

namespace Tests\Unit;

use App\Mail\CreateCampaign;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Wallet;
use Tests\TestCase;

class MailRenderTest extends TestCase
{
    public function test_create_campaign_mailable_renders_without_auth_session()
    {
        // Ensure no user is logged in (simulating a background queue worker)
        auth()->logout();

        $campaign = new Campaign([
            'post_title' => 'Test Promo Campaign',
            'campaign_amount' => 50.00,
            'number_of_staff' => 10,
            'total_amount' => 500.00,
            'job_id' => 'JOB-12345',
            'currency' => 'NGN',
        ]);

        $campaign->setRelation('campaignType', new Category(['name' => 'Social Media']));
        $campaign->setRelation('campaignCategory', new SubCategory(['name' => 'Twitter Follow']));
        
        $user = new User(['name' => 'John Doe']);
        $wallet = new Wallet(['base_currency' => 'NGN']);
        $user->setRelation('wallet', $wallet);
        $campaign->setRelation('user', $user);

        $mailable = new CreateCampaign($campaign);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Campaign Posted', $rendered);
        $this->assertStringContainsString('JOB-12345', $rendered);
        $this->assertStringContainsString('500.00', $rendered);
    }
}
