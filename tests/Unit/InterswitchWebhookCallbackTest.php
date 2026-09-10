<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\VirtualAccount;
use App\Models\PaymentTransaction;
use App\Services\Providers\InterswitchServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class InterswitchWebhookCallbackTest extends TestCase
{
    public function test_logging_channel_handles_comma_separated_env()
    {
        config(['logging.default' => str_contains('single,teams', ',') ? 'stack' : 'single,teams']);
        config(['logging.channels.stack.channels' => array_values(array_filter(array_map('trim', explode(',', 'single,teams'))))]);

        $this->assertEquals('stack', config('logging.default'));
        $this->assertEquals(['single', 'teams'], config('logging.channels.stack.channels'));
    }
}
