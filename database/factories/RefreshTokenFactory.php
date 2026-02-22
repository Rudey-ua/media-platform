<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RefreshToken>
 */
class RefreshTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function forPlainToken(string $plainToken): static
    {
        return $this->state(fn (array $attributes): array => [
            'token' => hash('sha256', $plainToken),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
