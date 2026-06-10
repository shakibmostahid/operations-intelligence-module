<?php

namespace App\Services;

use App\Models\SystemHealthCheck;
use Illuminate\Support\Carbon;

class SystemHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $days = 7): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $checks = SystemHealthCheck::query()
            ->where('checked_at', '>=', $from)
            ->orderBy('checked_at')
            ->get();

        $systems = $checks->groupBy('system_name')->map(function ($systemChecks, string $name): array {
            $total = $systemChecks->count();
            $available = $systemChecks->where('status', '!=', 'down')->count();

            return [
                'name' => $name,
                'uptime' => $total === 0 ? 0 : round(($available / $total) * 100, 2),
                'average_response_ms' => (int) round($systemChecks->avg('response_time_ms') ?? 0),
                'latest_status' => $systemChecks->last()?->status ?? 'unknown',
            ];
        })->values()->all();

        $trend = collect(range(0, $days - 1))->map(function (int $offset) use ($checks, $from): array {
            $date = $from->copy()->addDays($offset);
            $daily = $checks->filter(fn (SystemHealthCheck $check) => $check->checked_at->isSameDay($date));
            $total = $daily->count();

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'uptime' => $total === 0
                    ? 0
                    : round(($daily->where('status', '!=', 'down')->count() / $total) * 100, 2),
            ];
        })->all();

        $totalChecks = $checks->count();

        return [
            'overall_uptime' => $totalChecks === 0
                ? 0
                : round(($checks->where('status', '!=', 'down')->count() / $totalChecks) * 100, 2),
            'systems' => $systems,
            'trend' => $trend,
            'period_days' => $days,
        ];
    }
}
