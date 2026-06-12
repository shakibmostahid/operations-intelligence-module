<?php

namespace App\Services;

use App\Models\Incident;
use Carbon\CarbonInterface;

class DailyOperationsSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();
        $active = Incident::query()->where('status', '!=', 'resolved');

        $createdToday = Incident::query()->whereBetween('created_at', [$start, $end])->count();
        $resolvedToday = Incident::query()->whereBetween('resolved_at', [$start, $end])->count();
        $activeCount = (clone $active)->count();
        $criticalCount = (clone $active)->where('severity', 'critical')->count();
        $escalatedCount = (clone $active)->where('status', 'escalated')->count();
        $breachedCount = (clone $active)
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->count();
        $unassignedCount = (clone $active)->whereNull('assigned_to')->count();
        $topSource = $this->topSource($start, $end);

        return [
            'date' => now()->toDateString(),
            'label' => now()->format('l, F j'),
            'headline' => $this->headline($breachedCount, $criticalCount, $activeCount),
            'summary' => $this->summary(
                $createdToday,
                $resolvedToday,
                $activeCount,
                $criticalCount,
                $escalatedCount,
                $breachedCount,
                $unassignedCount,
                $topSource,
            ),
            'next_action' => $this->nextAction(
                $breachedCount,
                $criticalCount,
                $escalatedCount,
                $unassignedCount,
            ),
            'metrics' => [
                'created' => $createdToday,
                'resolved' => $resolvedToday,
                'active' => $activeCount,
                'critical' => $criticalCount,
                'escalated' => $escalatedCount,
                'sla_breached' => $breachedCount,
                'unassigned' => $unassignedCount,
            ],
            'top_source' => $topSource,
            'generated_at' => now()->format('M j, Y g:i:s A'),
            'provider' => 'Local operations summary',
        ];
    }

    private function topSource(CarbonInterface $start, CarbonInterface $end): ?array
    {
        $source = Incident::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("COALESCE(source, 'manual') as source_name, COUNT(*) as incident_count")
            ->groupBy('source_name')
            ->orderByDesc('incident_count')
            ->first();

        if ($source === null) {
            return null;
        }

        return [
            'name' => (string) $source->getAttribute('source_name'),
            'count' => (int) $source->getAttribute('incident_count'),
        ];
    }

    private function headline(int $breached, int $critical, int $active): string
    {
        return match (true) {
            $breached > 0 => 'SLA breaches require attention',
            $critical > 0 => 'Critical incidents remain active',
            $active > 0 => 'Operations remain active',
            default => 'Operations are clear',
        };
    }

    private function summary(
        int $created,
        int $resolved,
        int $active,
        int $critical,
        int $escalated,
        int $breached,
        int $unassigned,
        ?array $topSource,
    ): string {
        $summary = "Today, {$created} incidents were created and {$resolved} were resolved. "
            ."{$active} incidents remain active, including {$critical} critical, "
            ."{$escalated} escalated, and {$breached} with breached SLAs.";

        if ($unassigned > 0) {
            $summary .= " {$unassigned} active incidents are unassigned.";
        }

        if ($topSource !== null) {
            $source = ucfirst(str_replace('_', ' ', $topSource['name']));
            $summary .= " {$source} is today's leading source with {$topSource['count']} incidents.";
        }

        return $summary;
    }

    private function nextAction(
        int $breached,
        int $critical,
        int $escalated,
        int $unassigned,
    ): string {
        return match (true) {
            $breached > 0 => 'Review breached incidents first, confirm ownership, and document the immediate recovery step.',
            $critical > 0 => 'Confirm mitigation and ownership for every active critical incident.',
            $escalated > 0 => 'Review escalated incidents and confirm that each has a clear next action.',
            $unassigned > 0 => 'Assign owners to the remaining unassigned incidents.',
            default => 'Continue monitoring incoming incidents and service health.',
        };
    }
}
