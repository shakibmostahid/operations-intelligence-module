<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_does_not_reset_an_existing_users_password_or_account_state(): void
    {
        $roles = collect(['super_admin', 'admin', 'support_engineer', 'viewer'])
            ->mapWithKeys(fn (string $role): array => [
                $role => Role::query()->create(['role' => $role]),
            ]);

        $user = User::query()->create([
            'name' => 'Existing Administrator',
            'email' => 'super.admin@iot.com',
            'password' => 'UserChangedPassword1!',
            'must_change_password' => false,
            'email_verified_at' => now(),
            'role_id' => $roles['super_admin']->id,
            'status' => 'inactive',
            'created_by' => null,
        ]);

        $this->seed(UserSeeder::class);

        $user->refresh();

        $this->assertTrue(Hash::check('UserChangedPassword1!', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame('inactive', $user->status);
    }
}
