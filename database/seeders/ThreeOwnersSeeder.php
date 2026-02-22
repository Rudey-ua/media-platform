<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ThreeOwnersSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 3) as $index) {
            $owner = User::query()->firstOrCreate(
                ['email' => "owner{$index}@example.com"],
                [
                    'name' => "Owner {$index}",
                    'password' => 'OwnerRoot123',
                    'email_verified_at' => now(),
                ],
            );

            if (! $owner->hasRole('owner')) {
                $owner->assignRole('owner');
            }
        }
    }
}
