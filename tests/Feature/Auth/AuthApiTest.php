<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_token(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'سارا', 'email' => 'sara@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertCreated()->assertJsonPath('success', true)->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_user_can_login_and_access_profile(): void
    {
        User::factory()->create(['email' => 'sara@example.com', 'password' => 'Secret123']);
        $token = $this->postJson('/api/auth/login', ['email' => 'sara@example.com', 'password' => 'Secret123'])->json('data.token');
        $this->withToken($token)->getJson('/api/user/profile')->assertOk()->assertJsonPath('data.email', 'sara@example.com');
    }

    public function test_invalid_registration_uses_standard_error_envelope(): void
    {
        $this->postJson('/api/auth/register', [])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
