<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Services\RefreshTokenCookieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout_and_revoke_refresh_tokens(): void
    {
        $user = User::factory()->create();
        $accessToken = auth('api')->login($user);

        $this->assertIsString($accessToken);

        $refreshTokens = RefreshToken::factory()
            ->for($user)
            ->count(2)
            ->create();

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
            ])
            ->postJson('/api/v1/logout');

        $response
            ->assertOk()
            ->assertJsonPath('type', 'info')
            ->assertJsonPath('message', 'Successfully logged out')
            ->assertJsonMissingPath('data')
            ->assertCookieExpired(RefreshTokenCookieService::COOKIE_NAME);

        foreach ($refreshTokens as $refreshToken) {
            $this->assertDatabaseMissing('refresh_tokens', [
                'id' => $refreshToken->id,
            ]);
        }
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Unauthenticated');
    }
}
