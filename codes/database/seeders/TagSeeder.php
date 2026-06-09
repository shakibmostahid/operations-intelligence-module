<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'API', 'color' => '#2563EB'],
            ['name' => 'Billing', 'color' => '#7C3AED'],
            ['name' => 'Customer Impact', 'color' => '#DC2626'],
            ['name' => 'Database', 'color' => '#0891B2'],
            ['name' => 'Deployment', 'color' => '#D97706'],
            ['name' => 'Infrastructure', 'color' => '#475569'],
            ['name' => 'Security', 'color' => '#BE123C'],
        ] as $tag) {
            Tag::query()->updateOrCreate(
                ['name' => $tag['name']],
                ['color' => $tag['color']],
            );
        }
    }
}
