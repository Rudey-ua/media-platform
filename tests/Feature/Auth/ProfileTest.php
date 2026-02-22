<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();
        $accessToken = auth('api')->login($user);

        $this->assertIsString($accessToken);

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
            ])
            ->getJson('/api/v1/profile');

        $response
            ->assertOk()
            ->assertJsonPath('type', 'data')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Unauthenticated');
    }
}
