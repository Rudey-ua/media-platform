<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_successfully_login(): void
    {
        $user = User::factory()->create([
            'password' => 'RootRoot123',
        ]);

        $payload = [
            'email' => $user->email,
            'password' => 'RootRoot123',
        ];

        $response = $this->postJson('/api/v1/login', $payload);

        $response
            ->assertOk()
            ->assertJsonPath('type', 'data')
            ->assertJsonStructure([
                'type',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
        ]);
    }

    public function test_login_fails_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'password' => 'RootRoot123',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'missing@example.com',
            'password' => 'RootRoot123',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('type', 'error')
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_login_fails_with_empty_data(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation_error');

        $details = $response->json('details');

        $this->assertIsArray($details);
        $fields = array_column($details, 'field');
        $this->assertContains('email', $fields);
        $this->assertContains('password', $fields);
    }
}
