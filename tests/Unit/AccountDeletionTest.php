<?php

namespace Tests\Unit;

use App\Console\Commands\PurgeDeletedUsers;
use App\Mail\AccountDeletionScheduledMail;
use App\Models\User;
use App\Models\VirtualAccount;
use App\Services\AuthService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Set fixed test time
        Carbon::setTestNow('2026-09-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_soft_deleted_user_login_returns_scheduled_deletion_notice_with_days_left(): void
    {
        $email = 'deleted_user_' . time() . '@test.com';
        $user = User::create([
            'name' => 'Deleted User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'regular',
        ]);

        // Soft delete user 10 days ago (50 days remaining)
        $user->delete();
        User::withTrashed()->where('id', $user->id)->update([
            'deleted_at' => Carbon::now()->subDays(10),
        ]);

        $authService = app(AuthService::class);
        $request = new Request([
            'email' => $email,
            'password' => 'password123',
        ]);

        $response = $authService->loginUser($request);
        $data = $response->getData(true);

        $this->assertEquals(403, $response->status());
        $this->assertFalse($data['status']);
        $this->assertTrue($data['is_deleted']);
        $this->assertEquals(50, $data['days_left']);
        $this->assertStringContainsString('Your account has been scheduled for deletion', $data['message']);
        $this->assertStringContainsString('50 days left before final deletion', $data['message']);
        $this->assertStringContainsString('contact support (holla@freebyz.com)', $data['message']);
    }

    public function test_recent_soft_delete_shows_60_days_left(): void
    {
        $email = 'recent_deleted_' . time() . '@test.com';
        $user = User::create([
            'name' => 'Recent Deleted User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'regular',
        ]);

        $user->delete();

        $authService = app(AuthService::class);
        $request = new Request([
            'email' => $email,
            'password' => 'password123',
        ]);

        $response = $authService->loginUser($request);
        $data = $response->getData(true);

        $this->assertEquals(403, $response->status());
        $this->assertTrue($data['is_deleted']);
        $this->assertEquals(60, $data['days_left']);
        $this->assertStringContainsString('60 days left before final deletion', $data['message']);
    }

    public function test_restored_user_can_authenticate_without_deletion_notice(): void
    {
        $email = 'restored_user_' . time() . '@test.com';
        $user = User::create([
            'name' => 'Restored User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'regular',
        ]);

        $user->delete();
        $this->assertTrue($user->trashed());

        // Restore user
        $user->restore();
        $this->assertFalse($user->fresh()->trashed());

        $authService = app(AuthService::class);
        $request = new Request([
            'email' => $email,
            'password' => 'password123',
        ]);

        $response = $authService->loginUser($request);
        $data = $response->getData(true);

        // Should not return 403 deletion notice
        $this->assertNotEquals(
            'Your account has been scheduled for deletion',
            substr($data['message'] ?? '', 0, 44)
        );
    }

    public function test_purge_deleted_users_command_permanently_purges_past_60_days_only(): void
    {
        // User A: deleted 65 days ago -> should be permanently deleted
        $emailA = 'expired_user_' . time() . '@test.com';
        $userA = User::create([
            'name' => 'Expired Deleted User',
            'email' => $emailA,
            'password' => Hash::make('password123'),
            'role' => 'regular',
        ]);
        $userA->delete();
        User::withTrashed()->where('id', $userA->id)->update([
            'deleted_at' => Carbon::now()->subDays(65),
        ]);

        // User B: deleted 20 days ago -> should NOT be purged
        $emailB = 'active_deleted_' . time() . '@test.com';
        $userB = User::create([
            'name' => 'Recent Deleted User',
            'email' => $emailB,
            'password' => Hash::make('password123'),
            'role' => 'regular',
        ]);
        $userB->delete();
        User::withTrashed()->where('id', $userB->id)->update([
            'deleted_at' => Carbon::now()->subDays(20),
        ]);

        $this->artisan('users:purge-deleted')
            ->assertExitCode(0);

        // User A should be permanently gone
        $this->assertNull(User::withTrashed()->find($userA->id));

        // User B should still exist in trashed state
        $this->assertNotNull(User::withTrashed()->find($userB->id));
        $this->assertTrue(User::withTrashed()->find($userB->id)->trashed());
    }

    public function test_virtual_account_soft_deletes_excluded_from_standard_queries(): void
    {
        $email = 'va_test_' . time() . '@test.com';
        $user = User::create([
            'name' => 'VA User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'regular',
        ]);

        $va = VirtualAccount::create([
            'user_id' => $user->id,
            'channel' => 'paystack',
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'status' => true,
        ]);

        $this->assertNotNull(VirtualAccount::find($va->id));

        // Soft delete the Paystack virtual account
        $va->delete();

        // Standard query should return null
        $this->assertNull(VirtualAccount::find($va->id));

        // withTrashed query should still find it
        $this->assertNotNull(VirtualAccount::withTrashed()->find($va->id));
    }

    public function test_account_deletion_dispatches_email_to_user_with_60_days_notice(): void
    {
        Mail::fake();

        $email = 'email_notify_' . time() . '@test.com';
        $user = User::create([
            'name' => 'Email Notify User',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'regular',
        ]);

        // Trigger account deletion
        $user->delete();

        // Assert that the AccountDeletionScheduledMail was sent to the user
        Mail::assertSent(AccountDeletionScheduledMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) &&
                $mail->daysRemaining === 60 &&
                $mail->supportEmail === 'holla@freebyz.com';
        });
    }

    public function test_account_deletion_email_renders_proper_content_and_support_links(): void
    {
        $email = 'template_test_' . time() . '@test.com';
        $user = User::create([
            'name' => 'John Freebyz',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'regular',
        ]);

        $mailable = new AccountDeletionScheduledMail($user, 60);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Scheduled for Deletion', $rendered);
        $this->assertStringContainsString('John Freebyz', $rendered);
        $this->assertStringContainsString($email, $rendered);
        $this->assertStringContainsString('60 Days', $rendered);
        $this->assertStringContainsString('holla@freebyz.com', $rendered);
        $this->assertStringContainsString('Contact Support to Reactivate', $rendered);
    }
}
