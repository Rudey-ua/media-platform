<?php

namespace App\Repositories;

use App\DataTransferObjects\Auth\RegisterData;
use App\Models\User;

class UserRepository
{
    public function create(RegisterData $registerData): User
    {
        return User::create([
            'name' => $registerData->name,
            'email' => $registerData->email,
            'password' => $registerData->password,
        ]);
    }

    public function findUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
