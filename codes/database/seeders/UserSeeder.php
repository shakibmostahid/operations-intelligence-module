<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()
            ->get()
            ->keyBy('role');

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'super.admin@iot.com'],
            [
                'name' => 'Super Administrator',
                'password' => 'incident@admin',
                'must_change_password' => false,
                'email_verified_at' => now(),
                'role_id' => $roles->get('super_admin')->id,
                'status' => 'active',
                'created_by' => null,
            ],
        );

        foreach ([
            [
                'name' => 'Operations Administrator',
                'email' => 'admin@iot.com',
                'role' => 'admin',
            ],
            [
                'name' => 'Support Engineer',
                'email' => 'support@iot.com',
                'role' => 'support_engineer',
            ],
            [
                'name' => 'Operations Viewer',
                'email' => 'viewer@iot.com',
                'role' => 'viewer',
            ],
        ] as $user) {
            User::query()->firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => 'password',
                    'must_change_password' => true,
                    'email_verified_at' => now(),
                    'role_id' => $roles->get($user['role'])->id,
                    'status' => 'active',
                    'created_by' => $superAdmin->id,
                ],
            );
        }
    }
}
