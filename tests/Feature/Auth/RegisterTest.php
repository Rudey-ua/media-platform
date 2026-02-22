<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\RefreshTokenCookieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_successfully_register(): void
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'RootRoot123',
        ];

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('type', 'data')
            ->assertJsonStructure([
                'type',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'access_token',
                    'token_type',
                    'expires_in',
                ],
            ])
            ->assertCookie(RefreshTokenCookieService::COOKIE_NAME);

        $this->assertDatabaseHas('users', [
            'name' => $payload['name'],
            'email' => $payload['email'],
        ]);

        $createdUser = User::query()->where('email', $payload['email'])->firstOrFail();

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $createdUser->id,
        ]);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        $existingUser = User::factory()->create();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Another User',
            'email' => $existingUser->email,
            'password' => 'RootRoot123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation_error')
            ->assertJsonPath('message', 'Email is taken')
            ->assertJsonPath('details.0.field', 'email')
            ->assertJsonPath('details.0.messages.0', 'Email is taken');
    }

    public function test_register_fails_with_empty_data(): void
    {
        $response = $this->postJson('/api/v1/register', []);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('type', 'validation_error');

        $details = $response->json('details');

        $this->assertIsArray($details);
        $fields = array_column($details, 'field');
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('password', $fields);
    }
}
