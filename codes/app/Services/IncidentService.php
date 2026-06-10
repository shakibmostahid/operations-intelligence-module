<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IncidentService
{
    public function __construct(private readonly AlertRoutingService $alertRoutingService)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatedIncidents(
        array $filters,
        int $perPage = 15,
        ?int $currentUserId = null,
    ): LengthAwarePaginator
    {
        return $this->filteredQuery($filters, $currentUserId)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Incident $incident): array => $this->summary($incident));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Incident>
     */
    public function filteredIncidents(array $filters, ?int $currentUserId = null): Collection
    {
        return $this->filteredQuery($filters, $currentUserId)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters, ?int $currentUserId): Builder
    {
        return Incident::query()
            ->with(['assignee:id,name', 'creator:id,name', 'tags:id,name,color'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['severity'] ?? null, fn (Builder $query, string $severity) => $query->where('severity', $severity))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['assigned_to'] ?? null, function (Builder $query, int|string $assignedTo) use ($currentUserId): void {
                $query->where('assigned_to', $assignedTo === 'self' ? $currentUserId : $assignedTo);
            })
            ->when($filters['tag_id'] ?? null, function (Builder $query, int $tagId): void {
                $query->whereHas('tags', fn (Builder $query) => $query->where('tags.id', $tagId));
            })
            ->when($filters['sla'] ?? null, fn (Builder $query, string $sla) => $this->applySlaFilter($query, $sla))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when(
                ($filters['sort'] ?? null) === 'id',
                fn (Builder $query) => $query->orderBy('id', $filters['direction'] ?? 'asc'),
                fn (Builder $query) => $query
                    ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
                    ->orderByRaw("CASE WHEN status = 'resolved' THEN 1 ELSE 0 END")
                    ->orderBy('sla_deadline'),
            );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createIncident(User $actor, array $data): Incident
    {
        return DB::transaction(function () use ($actor, $data): Incident {
            $tagIds = $data['tag_ids'] ?? [];
            unset($data['tag_ids']);

            $incident = Incident::query()->create([
                ...$data,
                'status' => 'open',
                'created_by' => $actor->id,
            ]);

            $incident->tags()->sync($tagIds);
            $incident->activities()->create([
                'user_id' => $actor->id,
                'type' => 'created',
                'content' => 'Incident created.',
                'metadata' => [
                    'severity' => $incident->severity,
                    'assigned_to' => $incident->assigned_to,
                ],
            ]);

            $this->alertRoutingService->route('incident_created', $incident);

            return $incident;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Incident, 1: bool}
     */
    public function ingestIncident(User $actor, array $data): array
    {
        $existing = Incident::query()
            ->where('source', $data['source'])
            ->where('external_id', $data['external_id'])
            ->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        return DB::transaction(function () use ($actor, $data): array {
            $tagIds = $data['tag_ids'] ?? [];
            unset($data['tag_ids']);

            $incident = Incident::query()->create([
                ...$data,
                'status' => 'open',
                'created_by' => $actor->id,
            ]);
            $incident->tags()->sync($tagIds);
            $incident->activities()->create([
                'user_id' => $actor->id,
                'type' => 'ingested',
                'content' => "Incident ingested from {$incident->source}.",
                'metadata' => [
                    'source' => $incident->source,
                    'external_id' => $incident->external_id,
                ],
            ]);

            $this->alertRoutingService->route('incident_created', $incident);

            return [$incident, true];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIncident(User $actor, Incident $incident, array $data): void
    {
        $this->authorizeModification($actor);

        if ($incident->status === 'resolved') {
            throw ValidationException::withMessages([
                'incident' => 'Resolved incidents cannot be changed. You may still add comments.',
            ]);
        }

        $nextStatus = $data['status'] ?? $incident->status;
        $statusChanged = $nextStatus !== $incident->status;
        $escalationReason = trim((string) ($data['escalation_reason'] ?? ''));
        unset($data['assigned_to'], $data['escalation_reason']);

        if ($statusChanged && ! $this->canChangeStatus($actor, $incident)) {
            throw new AuthorizationException('Only the incident creator, assigned user, or super admin can change its status.');
        }

        if ($statusChanged && $nextStatus === 'escalated' && $escalationReason === '') {
            throw ValidationException::withMessages([
                'escalation_reason' => 'An escalation reason is required.',
            ]);
        }

        DB::transaction(function () use ($actor, $incident, $data, $escalationReason): void {
            $tagIds = $data['tag_ids'] ?? [];
            unset($data['tag_ids']);
            $previousTagIds = $incident->tags()->pluck('tags.id')->sort()->values()->all();

            if (($data['status'] ?? $incident->status) === 'resolved') {
                $data['resolved_at'] = $incident->resolved_at ?? now();
            } else {
                $data['resolved_at'] = null;
            }

            $before = $incident->only([
                'title',
                'description',
                'severity',
                'status',
                'assigned_to',
                'sla_deadline',
                'rca_note',
            ]);

            $incident->update($data);
            $incident->tags()->sync($tagIds);
            $currentTagIds = collect($tagIds)->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

            $changes = collect($incident->only(array_keys($before)))
                ->filter(fn (mixed $value, string $key) => $this->normalise($value) !== $this->normalise($before[$key]))
                ->mapWithKeys(fn (mixed $value, string $key) => [
                    $key => [
                        'from' => $this->normalise($before[$key]),
                        'to' => $this->normalise($value),
                    ],
                ])
                ->all();

            if ($changes === [] && $previousTagIds === $currentTagIds) {
                return;
            }

            [$type, $content] = match (true) {
                array_key_exists('status', $changes) && $incident->status === 'escalated' => [
                    'escalated',
                    'Incident escalated.',
                ],
                array_key_exists('status', $changes) => [
                    'status_changed',
                    'Incident status changed from '
                        .$this->statusLabel((string) $changes['status']['from'])
                        .' to '
                        .$this->statusLabel((string) $changes['status']['to'])
                        .'.',
                ],
                $changes === [] => ['tags_updated', 'Incident tags updated.'],
                default => ['updated', 'Incident details updated.'],
            };

            if ($type === 'escalated') {
                $content = "Incident escalated: {$escalationReason}";
            }

            $incident->activities()->create([
                'user_id' => $actor->id,
                'type' => $type,
                'content' => $content,
                'metadata' => [
                    'changes' => $changes,
                    'tag_ids' => $currentTagIds,
                    'escalation_reason' => $type === 'escalated' ? $escalationReason : null,
                ],
            ]);

            if (array_key_exists('status', $changes) && $incident->status === 'escalated') {
                $this->alertRoutingService->route('incident_escalated', $incident);
            }
        });
    }

    public function addComment(User $actor, Incident $incident, string $content): void
    {
        $this->authorizeModification($actor);

        $incident->activities()->create([
            'user_id' => $actor->id,
            'type' => 'comment',
            'content' => $content,
        ]);
    }

    public function canEditIncident(User $actor, Incident $incident): bool
    {
        $actor->loadMissing('role');

        return $actor->role->role !== 'viewer' && $incident->status !== 'resolved';
    }

    public function canComment(User $actor): bool
    {
        $actor->loadMissing('role');

        return $actor->role->role !== 'viewer';
    }

    public function canChangeStatus(User $actor, Incident $incident): bool
    {
        $actor->loadMissing('role');

        return $actor->role->role !== 'viewer'
            && $incident->status !== 'resolved'
            && (
                $actor->role->role === 'super_admin'
                || $incident->created_by === $actor->id
                || $incident->assigned_to === $actor->id
            );
    }

    public function unresolvedBreaches(int $perPage = 2): LengthAwarePaginator
    {
        return Incident::query()
            ->with(['assignee:id,name', 'creator:id,name', 'tags:id,name,color'])
            ->where('status', '!=', 'resolved')
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->orderBy('sla_deadline')
            ->paginate($perPage, ['*'], 'sla_page')
            ->withQueryString()
            ->through(fn (Incident $incident): array => [
                ...$this->summary($incident),
                'overdue_for' => $incident->sla_deadline?->diffForHumans(now(), true),
            ]);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function selfAssignedIncidents(User $user, int $limit = 5): array
    {
        $query = Incident::query()
            ->with(['assignee:id,name', 'creator:id,name', 'tags:id,name,color'])
            ->where('assigned_to', $user->id)
            ->where('status', '!=', 'resolved')
            ->whereNull('resolved_at');

        return [
            'total' => (clone $query)->count(),
            'items' => $query
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->orderBy('sla_deadline')
            ->limit($limit)
            ->get()
            ->map(fn (Incident $incident): array => $this->summary($incident))
            ->all(),
        ];
    }

    public function slaState(Incident $incident): string
    {
        if ($incident->status === 'resolved') {
            return 'resolved';
        }

        if ($incident->sla_deadline === null) {
            return 'not_set';
        }

        if ($incident->sla_deadline->isPast()) {
            return 'breached';
        }

        return $incident->sla_deadline->lte(now()->addHour()) ? 'at_risk' : 'healthy';
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Incident $incident): array
    {
        $durationState = match (true) {
            $incident->resolved_at !== null => 'resolved',
            $this->slaState($incident) === 'breached' => 'breached',
            default => 'running',
        };
        $durationEnd = $incident->resolved_at ?? now();
        $duration = $incident->created_at?->diffForHumans($durationEnd, true);

        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'description' => $incident->description,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'assignee' => $incident->assignee?->name,
            'creator' => $incident->creator?->name,
            'sla_deadline' => $incident->sla_deadline?->format('M j, Y g:i A'),
            'sla_state' => $this->slaState($incident),
            'resolved_at' => $incident->resolved_at?->format('M j, Y g:i A'),
            'duration' => $duration,
            'duration_state' => $durationState,
            'tags' => $incident->tags->map->only(['id', 'name', 'color'])->values()->all(),
            'created_at' => $incident->created_at?->format('M j, Y g:i A'),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function assignableUsers(): array
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('role', fn (Builder $query) => $query->where('role', '!=', 'viewer'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * @return array<int, array{id: int, name: string, color: string}>
     */
    public function tags(): array
    {
        return Tag::query()->orderBy('name')->get(['id', 'name', 'color'])->toArray();
    }

    private function authorizeModification(User $actor): void
    {
        $actor->loadMissing('role');

        if ($actor->role->role === 'viewer') {
            throw new AuthorizationException;
        }
    }

    private function applySlaFilter(Builder $query, string $sla): Builder
    {
        return match ($sla) {
            'breached' => $query->where('status', '!=', 'resolved')->where('sla_deadline', '<', now()),
            'at_risk' => $query->where('status', '!=', 'resolved')
                ->whereBetween('sla_deadline', [now(), now()->addHour()]),
            'healthy' => $query->where('status', '!=', 'resolved')->where('sla_deadline', '>', now()->addHour()),
            'resolved' => $query->where('status', 'resolved'),
            'not_set' => $query->whereNull('sla_deadline'),
            default => $query,
        };
    }

    private function normalise(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
    }

    private function statusLabel(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }
}
