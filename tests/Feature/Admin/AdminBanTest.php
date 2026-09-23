<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['auth.admin_emails' => 'admin@hale.test']);
        $this->admin = User::factory()->create(['email' => 'admin@hale.test']);
    }

    public function test_non_admin_cannot_ban_or_unban(): void
    {
        $attacker = User::factory()->create(['email' => 'regular@user.test']);
        $target = User::factory()->create();
        Sanctum::actingAs($attacker);

        $this->postJson("/api/admin/users/{$target->id}/ban", ['reason' => 'spam'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $target->ban(now()->addDay()->toDateTimeString(), 'pre-banned');
        $this->postJson("/api/admin/users/{$target->id}/unban")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_admin_ban_revokes_tokens_and_blocks_login(): void
    {
        $target = User::factory()->create(['email' => 'spammer@test.com', 'password' => 'Secret123']);
        $target->createToken('session-1');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/users/{$target->id}/ban", ['reason' => 'نقض شرایط استفاده'])
            ->assertOk()
            ->assertJsonPath('data.is_banned', true)
            ->assertJsonPath('data.ban_reason', 'نقض شرایط استفاده');

        $this->app['auth']->forgetGuards();

        $fresh = $target->fresh();
        $this->assertTrue($fresh->currentlyBanned());
        $this->assertNotNull($fresh->banned_at);
        $this->assertSame(0, $fresh->tokens()->count(), 'Ban must revoke every access token.');

        $this->postJson('/api/auth/login', [
            'identifier' => 'spammer@test.com',
            'password' => 'Secret123',
        ])->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_BANNED');
    }

    public function test_banned_user_is_blocked_on_api_except_profile_and_logout(): void
    {
        $target = User::factory()->create();
        // Simulate a ban issued from another session where tokens were not
        // (yet) revoked: a leftover valid token must still be rejected.
        $target->forceFill(['banned_at' => now(), 'ban_reason' => 'spam'])->save();
        $token = $target->createToken('leftover')->plainTextToken;

        $this->withToken($token)->getJson('/api/generations')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_BANNED');

        $this->withToken($token)->postJson('/api/products', ['name' => 'کفش'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_BANNED');

        $this->withToken($token)->getJson('/api/user/profile')->assertOk();

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
    }

    public function test_unban_restores_login_access(): void
    {
        $target = User::factory()->create(['email' => 'back@test.com', 'password' => 'Secret123']);
        $target->ban(null, 'temporary mistake');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/users/{$target->id}/unban")
            ->assertOk()
            ->assertJsonPath('data.is_banned', false);

        $this->app['auth']->forgetGuards();
        $this->assertFalse($target->fresh()->currentlyBanned());

        $this->postJson('/api/auth/login', [
            'identifier' => 'back@test.com',
            'password' => 'Secret123',
        ])->assertOk();
    }

    public function test_temporary_ban_expires_automatically(): void
    {
        $target = User::factory()->create(['email' => 'suspended@test.com', 'password' => 'Secret123']);
        $target->ban(now()->subMinute()->toDateTimeString(), 'short suspension');

        $fresh = $target->fresh();
        $this->assertFalse($fresh->currentlyBanned());
        $this->assertFalse((bool) $fresh->is_banned);

        $this->postJson('/api/auth/login', [
            'identifier' => 'suspended@test.com',
            'password' => 'Secret123',
        ])->assertOk();
    }

    public function test_admin_accounts_cannot_be_banned(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/users/{$this->admin->id}/ban", ['reason' => 'self'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ADMIN_ACCOUNT_PROTECTED');

        $secondAdmin = User::factory()->create(['email' => 'admin2@hale.test']);
        config(['auth.admin_emails' => 'admin@hale.test, admin2@hale.test']);

        $this->postJson("/api/admin/users/{$secondAdmin->id}/ban", ['reason' => 'peer'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ADMIN_ACCOUNT_PROTECTED');
    }

    public function test_ban_requires_reason_and_double_ban_is_rejected(): void
    {
        $target = User::factory()->create();
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/users/{$target->id}/ban", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/admin/users/{$target->id}/ban", [
            'reason' => 'spam',
            'expires_at' => now()->subDay()->toDateTimeString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');

        $this->postJson("/api/admin/users/{$target->id}/ban", ['reason' => 'spam'])->assertOk();

        $this->postJson("/api/admin/users/{$target->id}/ban", ['reason' => 'spam again'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_BANNED');

        $this->postJson("/api/admin/users/{$target->id}/unban")
            ->assertOk();

        $this->postJson("/api/admin/users/{$target->id}/unban")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'NOT_BANNED');
    }

    public function test_admin_user_detail_and_list_include_ban_state(): void
    {
        $target = User::factory()->create(['email' => 'flagged@test.com']);
        $target->ban(null, 'under review');

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/admin/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.is_banned', true)
            ->assertJsonPath('data.ban_reason', 'under review');

        $listing = $this->getJson('/api/admin/users?search=flagged')->assertOk();
        $this->assertTrue((bool) $listing->json('data.data.0.is_banned'));
    }
}
