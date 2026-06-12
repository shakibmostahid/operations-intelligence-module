<?php

namespace App\Services;

use Carbon\CarbonInterface;

class SystemHealthService
{
    private const SYSTEMS = [
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
        $samples = collect();

        foreach (range(0, $days - 1) as $offset) {
            $date = $from->copy()->addDays($offset);
            $slots = $date->isToday()
                ? max(1, min(48, intdiv(now()->hour * 60 + now()->minute, 30) + 1))
                : 48;

            foreach (self::SYSTEMS as $name => $baseResponseTime) {
                foreach (range(0, $slots - 1) as $slot) {
                    $samples->push($this->historicalProbe($name, $baseResponseTime, $date, $slot));
                }
            }
        }

        $systems = collect(self::SYSTEMS)->map(function (int $baseResponseTime, string $name) use ($samples): array {
            $systemSamples = $samples->where('system_name', $name);
            $total = $systemSamples->count();
            $available = $systemSamples->where('status', '!=', 'down')->count();
            $current = $this->liveProbe($name);

            return [
                'name' => $name,
                'uptime' => $total === 0 ? 0 : round(($available / $total) * 100, 2),
                'average_response_ms' => (int) round($systemSamples->avg('response_time_ms') ?? $baseResponseTime),
                'latest_status' => $current['status'],
                'current_response_ms' => $current['response_time_ms'],
            ];
        })->values()->all();
        $liveUptime = $this->liveUptime($systems);

        $trend = collect(range(0, $days - 1))->map(function (int $offset) use ($samples, $from, $liveUptime): array {
            $date = $from->copy()->addDays($offset);
            $daily = $samples->where('date', $date->toDateString());
            $total = $daily->count();
            $isLive = $date->isToday();

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'uptime' => $isLive
                    ? $liveUptime
                    : ($total === 0
                    ? 0
                    : round(($daily->where('status', '!=', 'down')->count() / $total) * 100, 2)),
                'live' => $isLive,
            ];
        })->all();

        $totalChecks = $samples->count();

        return [
            'overall_uptime' => $totalChecks === 0
                ? 0
                : round(($samples->where('status', '!=', 'down')->count() / $totalChecks) * 100, 2),
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
     * @return array{system_name: string, date: string, status: string, response_time_ms: int}
     */
    private function historicalProbe(
        string $systemName,
        int $baseResponseTime,
        CarbonInterface $date,
        int $slot,
    ): array {
        $key = "{$systemName}:{$date->toDateString()}:{$slot}";
        $signal = abs(crc32($key)) % 1000;
        $status = match (true) {
            $signal < 8 => 'down',
            $signal < 70 => 'degraded',
            default => 'up',
        };
        $variation = abs(crc32("response:{$key}")) % 180;
        $penalty = match ($status) {
            'down' => 900,
            'degraded' => 300,
            default => 0,
        };

        return [
            'system_name' => $systemName,
            'date' => $date->toDateString(),
            'status' => $status,
            'response_time_ms' => $baseResponseTime + $variation + $penalty,
        ];
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
        $base = self::SYSTEMS[$systemName] ?? 200;
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
