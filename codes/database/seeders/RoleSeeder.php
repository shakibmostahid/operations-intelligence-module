<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'super_admin',
            'admin',
            'support_engineer',
            'viewer',
        ] as $role) {
            Role::query()->updateOrCreate(['role' => $role]);
        }
    }
}
