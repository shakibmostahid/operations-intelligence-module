<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    public function updateName(User $user, string $name): void
    {
        $user->update(['name' => $name]);
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->update([
            'password' => $password,
            'must_change_password' => false,
        ]);
    }
}
