<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\BankRepositoryModel;
use App\Services\NotificationService;
use App\Services\Providers\FlutterwaveServiceProvider;
use App\Services\Providers\InterswitchServiceProvider;
use App\Services\Providers\PaystackServiceProvider;
use App\Services\VirtualAccountService;
use Mockery;

class VirtualAccountFailureResponseTest extends TestCase
{
    public function test_unsupported_currency_returns_other_means_message()
    {
        $paystack = Mockery::mock(PaystackServiceProvider::class);
        $bankRepo = Mockery::mock(BankRepositoryModel::class);
        $notification = Mockery::mock(NotificationService::class);
        $interswitch = Mockery::mock(InterswitchServiceProvider::class);
        $flutterwave = Mockery::mock(FlutterwaveServiceProvider::class);

        $service = new VirtualAccountService($paystack, $bankRepo, $notification, $interswitch, $flutterwave);

        $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
        $wallet = new Wallet(['base_currency' => 'USD']);
        $user->setRelation('wallet', $wallet);

        $response = $service->generateVirtualAccountNew($user);

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['status']);
        $this->assertStringContainsString('Please use other means of wallet funding on the wallet page', $data['message']);
    }

    public function test_interswitch_creation_failure_returns_other_means_message()
    {
        $paystack = Mockery::mock(PaystackServiceProvider::class);
        $bankRepo = Mockery::mock(BankRepositoryModel::class);
        $notification = Mockery::mock(NotificationService::class);
        $interswitch = Mockery::mock(InterswitchServiceProvider::class);
        $flutterwave = Mockery::mock(FlutterwaveServiceProvider::class);

        $bankRepo->shouldReceive('getVirtualBank')->andReturn(null);
        $interswitch->shouldReceive('createVirtualAccount')->andReturn(['error' => true, 'description' => 'Service unavailable']);

        $service = new VirtualAccountService($paystack, $bankRepo, $notification, $interswitch, $flutterwave);

        $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
        $wallet = new Wallet(['base_currency' => 'NGN']);
        $user->setRelation('wallet', $wallet);

        $response = $service->generateInterswitchVirtualAccount($user);

        $this->assertEquals(500, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['status']);
        $this->assertEquals('Unable to generate virtual account at this time. Please use other means of wallet funding on the wallet page.', $data['message']);
    }

    public function test_flutterwave_creation_failure_returns_other_means_message()
    {
        $paystack = Mockery::mock(PaystackServiceProvider::class);
        $bankRepo = Mockery::mock(BankRepositoryModel::class);
        $notification = Mockery::mock(NotificationService::class);
        $interswitch = Mockery::mock(InterswitchServiceProvider::class);
        $flutterwave = Mockery::mock(FlutterwaveServiceProvider::class);

        $bankRepo->shouldReceive('getVirtualBank')->andReturn(null);
        $flutterwave->shouldReceive('createVirtualAccount')->andReturn(null);

        $service = new VirtualAccountService($paystack, $bankRepo, $notification, $interswitch, $flutterwave);

        $user = new User(['name' => 'Kofi Mensah', 'email' => 'kofi@example.com']);
        $wallet = new Wallet(['base_currency' => 'GHS']);
        $user->setRelation('wallet', $wallet);

        $response = $service->generateFlutterwaveVirtualAccount($user, 'GHS');

        $this->assertEquals(500, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['status']);
        $this->assertEquals('Unable to generate virtual account at this time. Please use other means of wallet funding on the wallet page.', $data['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
