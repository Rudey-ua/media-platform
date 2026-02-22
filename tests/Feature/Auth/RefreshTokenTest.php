<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\RefreshTokenCookieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_refresh_access_token(): void
    {
        $user = User::factory()->create();
        $plainRefreshToken = 'valid-refresh-token';

        RefreshToken::factory()
            ->for($user)
            ->forPlainToken($plainRefreshToken)
            ->create();

        $response = $this->postJson('/api/v1/refresh', [
            'refresh_token' => $plainRefreshToken,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('type', 'data')
            ->assertJsonStructure([
                'type',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ])
            ->assertCookie(RefreshTokenCookieService::COOKIE_NAME);
    }

    public function test_refresh_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/refresh', [
            'refresh_token' => 'invalid-refresh-token',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Invalid or expired refresh token');
    }

    public function test_refresh_fails_with_expired_token(): void
    {
        $user = User::factory()->create();
        $plainRefreshToken = 'expired-refresh-token';

        RefreshToken::factory()
            ->for($user)
            ->forPlainToken($plainRefreshToken)
            ->expired()
            ->create();

        $response = $this->postJson('/api/v1/refresh', [
            'refresh_token' => $plainRefreshToken,
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Invalid or expired refresh token');
    }

    public function test_refresh_fails_with_empty_data(): void
    {
        $response = $this->postJson('/api/v1/refresh', []);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation_error');

        $details = $response->json('details');

        $this->assertIsArray($details);
        $fields = array_column($details, 'field');
        $this->assertContains('refresh_token', $fields);
    }
}
