<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\AuthTokenCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class HttpOnlyCookieAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_login_sets_httponly_cookie_that_authenticates_requests_without_header(): void
    {
        User::factory()->create(['email' => 'sara@example.com', 'password' => 'Secret123']);

        $response = $this->postJson('/api/auth/login', [
            'identifier' => 'sara@example.com',
            'password' => 'Secret123',
        ])->assertOk();

        $token = $response->json('data.token');
        $this->assertNotNull($token);
        $response->assertPlainCookie(AuthTokenCookie::NAME, $token);

        $cookie = $response->getCookie(AuthTokenCookie::NAME, false);
        $this->assertTrue($cookie->isHttpOnly(), 'Auth cookie must be HttpOnly (no JS access).');
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertFalse($cookie->isSecure());

        $this->withCredentials()
            ->withUnencryptedCookies([AuthTokenCookie::NAME => $token])
            ->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('data.email', 'sara@example.com');
    }

    public function test_register_also_sets_the_httponly_cookie(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'سارا',
            'email' => 'sara2@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertCreated();

        $response->assertPlainCookie(AuthTokenCookie::NAME, $response->json('data.token'));
        $this->assertTrue($response->getCookie(AuthTokenCookie::NAME, false)->isHttpOnly());
    }

    public function test_request_without_cookie_or_header_is_unauthenticated(): void
    {
        $this->getJson('/api/user/profile')->assertUnauthorized();
    }

    public function test_invalid_cookie_value_is_rejected(): void
    {
        $this->withCredentials()
            ->withUnencryptedCookies([AuthTokenCookie::NAME => 'invalid-token'])
            ->getJson('/api/user/profile')
            ->assertUnauthorized();
    }

    public function test_explicit_bearer_header_takes_precedence_over_cookie(): void
    {
        User::factory()->create(['email' => 'sara@example.com', 'password' => 'Secret123']);
        $token = $this->postJson('/api/auth/login', [
            'identifier' => 'sara@example.com',
            'password' => 'Secret123',
        ])->json('data.token');

        $this->withCredentials()
            ->withUnencryptedCookies([AuthTokenCookie::NAME => 'stale-or-invalid'])
            ->withToken($token)
            ->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('data.email', 'sara@example.com');
    }

    public function test_logout_expires_cookie_and_revokes_token_access(): void
    {
        User::factory()->create(['email' => 'sara@example.com', 'password' => 'Secret123']);
        $token = $this->postJson('/api/auth/login', [
            'identifier' => 'sara@example.com',
            'password' => 'Secret123',
        ])->json('data.token');

        $this->withCredentials()
            ->withUnencryptedCookies([AuthTokenCookie::NAME => $token])
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertCookieExpired(AuthTokenCookie::NAME);

        $this->assertSame(0, PersonalAccessToken::count(), 'Logout must revoke the access token.');

        // Guards cache the resolved user for the whole test app instance; production
        // PHP-FPM rebuilds them per request, so flush to simulate a fresh request.
        $this->app['auth']->forgetGuards();

        // Token was revoked, so the same cookie value can no longer authenticate.
        $this->withCredentials()
            ->withUnencryptedCookies([AuthTokenCookie::NAME => $token])
            ->getJson('/api/user/profile')
            ->assertUnauthorized();
    }
}
