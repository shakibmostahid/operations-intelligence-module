<?php

namespace Database\Seeders;

use App\Models\SystemHealthCheck;
use Illuminate\Database\Seeder;

class SystemHealthCheckSeeder extends Seeder
{
    public function run(): void
    {
        SystemHealthCheck::query()->delete();

        $systems = [
            'Checkout API' => 145,
            'Customer Portal' => 210,
            'Billing Worker' => 320,
        ];
        $start = now()->subDays(6)->startOfDay();
        $rows = [];

        foreach (array_keys($systems) as $systemIndex => $name) {
            $baseResponseTime = $systems[$name];

            for ($slot = 0; $slot < 336; $slot++) {
                $checkedAt = $start->copy()->addMinutes($slot * 30);
                $downSlots = [
                    [117],
                    [191, 192],
                    [249, 250, 251],
                ];
                $isDown = in_array($slot, $downSlots[$systemIndex], true);
                $isDegraded = ! $isDown && ($slot + $systemIndex * 11) % 61 === 0;

                $rows[] = [
                    'system_name' => $name,
                    'status' => $isDown ? 'down' : ($isDegraded ? 'degraded' : 'up'),
                    'response_time_ms' => $baseResponseTime + (($slot * 17 + $systemIndex * 29) % 180),
                    'checked_at' => $checkedAt,
                    'created_at' => $checkedAt,
                    'updated_at' => $checkedAt,
                ];
            }
        }

        SystemHealthCheck::query()->insert($rows);
    }
}
