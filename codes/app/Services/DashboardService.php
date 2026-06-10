<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Tag;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    /**
     * @return array{
     *     total: int,
     *     severity: array<int, array{label: string, count: int}>,
     *     status: array<int, array{label: string, count: int}>,
     *     tags: array<int, array{name: string, color: string, count: int}>,
     *     trend: array<int, array{date: string, label: string, created: int, resolved: int}>
     * }
     */
    public function analytics(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $incidents = $this->incidentQuery($from, $to);

        return [
            'total' => (clone $incidents)->count(),
            'severity' => $this->groupCounts($incidents, 'severity'),
            'status' => $this->groupCounts($incidents, 'status'),
            'tags' => $this->tagCounts($from, $to),
            'trend' => $this->incidentTrend($from, $to),
        ];
    }

    /**
     * @return array<int, array{date: string, label: string, created: int, resolved: int}>
     */
    public function incidentTrend(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $firstCreatedAt = Incident::query()->min('created_at');
        $firstDate = $from?->copy()->startOfDay()
            ?? ($firstCreatedAt ? CarbonImmutable::parse($firstCreatedAt)->startOfDay() : now()->startOfDay());
        $lastDate = $to?->copy()->startOfDay() ?? now()->startOfDay();

        $created = Incident::query()
            ->selectRaw('DATE(created_at) as trend_date, COUNT(*) as count')
            ->whereBetween('created_at', [$firstDate->copy()->startOfDay(), $lastDate->copy()->endOfDay()])
            ->groupBy('trend_date')
            ->pluck('count', 'trend_date');

        $resolved = Incident::query()
            ->selectRaw('DATE(resolved_at) as trend_date, COUNT(*) as count')
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$firstDate->copy()->startOfDay(), $lastDate->copy()->endOfDay()])
            ->groupBy('trend_date')
            ->pluck('count', 'trend_date');

        return collect(CarbonPeriod::create($firstDate, $lastDate))
            ->map(fn (CarbonInterface $date): array => [
                'date' => $date->toDateString(),
                'label' => $date->format('M j'),
                'created' => (int) ($created[$date->toDateString()] ?? 0),
                'resolved' => (int) ($resolved[$date->toDateString()] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function groupCounts(Builder $query, string $column): array
    {
        return (clone $query)
            ->selectRaw("{$column} as label, COUNT(*) as count")
            ->groupBy($column)
            ->orderByDesc('count')
            ->get()
            ->map(fn (Incident $incident): array => [
                'label' => $incident->getAttribute('label'),
                'count' => (int) $incident->getAttribute('count'),
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, color: string, count: int}>
     */
    private function tagCounts(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return Tag::query()
            ->withCount([
                'incidents' => fn (Builder $query) => $this->applyTimeframe($query, $from, $to),
            ])
            ->having('incidents_count', '>', 0)
            ->orderByDesc('incidents_count')
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (Tag $tag): array => [
                'name' => $tag->name,
                'color' => $tag->color,
                'count' => $tag->incidents_count,
            ])
            ->all();
    }

    private function incidentQuery(?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return $this->applyTimeframe(Incident::query(), $from, $to);
    }

    private function applyTimeframe(
        Builder $query,
        ?CarbonInterface $from,
        ?CarbonInterface $to,
    ): Builder {
        return $query
            ->when(
                $from !== null,
                fn (Builder $query) => $query->where('incidents.created_at', '>=', $from),
            )
            ->when(
                $to !== null,
                fn (Builder $query) => $query->where('incidents.created_at', '<=', $to),
            );
    }
}
