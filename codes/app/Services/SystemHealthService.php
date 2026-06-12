<?php

namespace App\Services;

use App\Models\SystemHealthCheck;

class SystemHealthService
{
    private const LIVE_RESPONSE_BASE = [
        'Checkout API' => 145,
        'Customer Portal' => 210,
        'Billing Worker' => 320,
    ];

    /**
     * @return array<string, mixed>
     */
    public function dashboard(int $days = 7): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $checks = SystemHealthCheck::query()
            ->where('checked_at', '>=', $from)
            ->where('checked_at', '<=', now())
            ->orderBy('checked_at')
            ->get();

        $systems = $checks->groupBy('system_name')->map(function ($systemChecks, string $name): array {
            $total = $systemChecks->count();
            $available = $systemChecks->where('status', '!=', 'down')->count();
            $live = $this->liveProbe($name);

            return [
                'name' => $name,
                'uptime' => $total === 0 ? 0 : round(($available / $total) * 100, 2),
                'average_response_ms' => (int) round($systemChecks->avg('response_time_ms') ?? 0),
                'latest_status' => $live['status'],
                'current_response_ms' => $live['response_time_ms'],
            ];
        })->values()->all();
        $liveUptime = $this->liveUptime($systems);

        $trend = collect(range(0, $days - 1))->map(function (int $offset) use ($checks, $from, $liveUptime): array {
            $date = $from->copy()->addDays($offset);
            $daily = $checks->filter(fn (SystemHealthCheck $check) => $check->checked_at->isSameDay($date));
            $total = $daily->count();
            $isToday = $date->isToday();

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'uptime' => $isToday
                    ? $liveUptime
                    : ($total === 0
                    ? 0
                    : round(($daily->where('status', '!=', 'down')->count() / $total) * 100, 2)),
                'live' => $isToday,
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
            'checked_at' => now()->format('M j, Y g:i:s A'),
            'refresh_after_seconds' => 10,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $systems
     */
    private function liveUptime(array $systems): float
    {
        if ($systems === []) {
            return 0;
        }

        $score = collect($systems)->sum(fn (array $system): int => match ($system['latest_status']) {
            'up' => 100,
            'degraded' => 50,
            default => 0,
        });

        return round($score / count($systems), 2);
    }

    /**
     * Generate a stable mock probe for each ten-second interval.
     *
     * @return array{status: string, response_time_ms: int}
     */
    private function liveProbe(string $systemName): array
    {
        $bucket = intdiv(now()->timestamp, 10);
        $signal = abs(crc32("{$systemName}:{$bucket}")) % 100;
        $status = match (true) {
            $signal < 3 => 'down',
            $signal < 15 => 'degraded',
            default => 'up',
        };
        $base = self::LIVE_RESPONSE_BASE[$systemName] ?? 200;
        $variation = abs(crc32("response:{$systemName}:{$bucket}")) % 140;
        $penalty = match ($status) {
            'down' => 900,
            'degraded' => 350,
            default => 0,
        };

        return [
            'status' => $status,
            'response_time_ms' => $base + $variation + $penalty,
        ];
    }
}
