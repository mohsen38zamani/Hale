<?php

namespace Tests\Feature\Admin;

use App\Domains\Admin\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_retrieve_system_settings(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/settings');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_update_usd_rate_via_api(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/settings', [
            'key' => 'usd_to_toman_rate',
            'value' => 125000,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(125000, SystemSetting::get('usd_to_toman_rate'));
    }

    public function test_admin_can_bulk_update_settings(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/settings', [
            'settings' => [
                'usd_to_toman_rate' => 130000,
                'system_announcement' => 'بروزرسانی جدید فعال شد',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(130000, SystemSetting::get('usd_to_toman_rate'));
        $this->assertSame('بروزرسانی جدید فعال شد', SystemSetting::get('system_announcement'));
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $user = User::factory()->create(['email' => 'user@regular.test']);
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/settings', [
            'key' => 'usd_to_toman_rate',
            'value' => 125000,
        ])->assertForbidden();
    }

    public function test_get_does_not_cache_missing_keys_permanently(): void
    {
        $val1 = SystemSetting::get('temporary_missing_key', 'first_default');
        $this->assertSame('first_default', $val1);

        $val2 = SystemSetting::get('temporary_missing_key', 'second_default');
        $this->assertSame('second_default', $val2);

        SystemSetting::query()->create([
            'key' => 'temporary_missing_key',
            'value' => 'db_value',
            'type' => 'string',
        ]);

        $this->assertSame('db_value', SystemSetting::get('temporary_missing_key'));
    }
}
