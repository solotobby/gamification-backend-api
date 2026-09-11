<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\VirtualAccount;
use Tests\TestCase;

class SoftDeletePaystackVirtualAccountsTest extends TestCase
{
    public function test_command_dry_run_does_not_delete()
    {
        $va = VirtualAccount::create([
            'user_id' => 999901,
            'channel' => 'paystack',
            'bank_name' => 'Paystack Test Bank',
            'account_name' => 'Test User VA',
            'account_number' => '9999990001',
            'status' => true,
        ]);

        $this->artisan('app:soft-delete-paystack-virtual-accounts', [
            '--user' => 999901,
            '--dry-run' => true,
        ])
        ->expectsOutputToContain('[DRY RUN] Would soft delete 1 Paystack virtual account(s)')
        ->assertSuccessful();

        $this->assertNull($va->fresh()->deleted_at);

        // Clean up
        $va->forceDelete();
    }

    public function test_command_soft_deletes_paystack_accounts()
    {
        $va1 = VirtualAccount::create([
            'user_id' => 999902,
            'channel' => 'paystack',
            'bank_name' => 'Paystack Test Bank',
            'account_name' => 'Test User VA 1',
            'account_number' => '9999990002',
            'status' => true,
        ]);

        $vaInterswitch = VirtualAccount::create([
            'user_id' => 999902,
            'channel' => 'interswitch',
            'bank_name' => 'Wema Bank',
            'account_name' => 'Test User VA Interswitch',
            'account_number' => '9999990003',
            'status' => true,
        ]);

        $this->artisan('app:soft-delete-paystack-virtual-accounts', [
            '--user' => 999902,
            '--force' => true,
        ])
        ->expectsOutputToContain('Successfully soft-deleted 1 Paystack virtual account(s)')
        ->assertSuccessful();

        // Paystack account is soft deleted
        $this->assertNotNull($va1->fresh()->deleted_at);
        $this->assertNull(VirtualAccount::find($va1->id)); // Soft-deleted, hidden from default query
        $this->assertNotNull(VirtualAccount::withTrashed()->find($va1->id));

        // Interswitch account is NOT deleted
        $this->assertNull($vaInterswitch->fresh()->deleted_at);
        $this->assertNotNull(VirtualAccount::find($vaInterswitch->id));

        // Clean up
        $va1->forceDelete();
        $vaInterswitch->forceDelete();
    }
}
