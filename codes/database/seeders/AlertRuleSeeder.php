<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AlertRuleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'role');

        $rules = [
            ['event' => 'incident_created', 'severity' => 'critical', 'role_id' => $roles['admin']],
            ['event' => 'incident_created', 'severity' => 'critical', 'role_id' => $roles['support_engineer']],
            ['event' => 'incident_escalated', 'severity' => null, 'role_id' => $roles['super_admin']],
            ['event' => 'incident_escalated', 'severity' => null, 'role_id' => $roles['admin']],
            ['event' => 'sla_breached', 'severity' => null, 'role_id' => $roles['admin']],
        ];

        foreach ($rules as $rule) {
            AlertRule::query()->updateOrCreate(
                [
                    'event' => $rule['event'],
                    'severity' => $rule['severity'],
                    'role_id' => $rule['role_id'],
                ],
                [
                    'channel' => 'in_app',
                    'is_active' => true,
                ],
            );
        }
    }
}
