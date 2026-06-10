<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Services\AlertRoutingService;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    public function run(AlertRoutingService $alertRoutingService): void
    {
        Incident::query()
            ->where('severity', 'critical')
            ->each(fn (Incident $incident) => $alertRoutingService->route('incident_created', $incident));

        Incident::query()
            ->where('status', 'escalated')
            ->each(fn (Incident $incident) => $alertRoutingService->route('incident_escalated', $incident));

        $alertRoutingService->routeCurrentSlaBreaches();
    }
}
