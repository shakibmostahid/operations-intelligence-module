<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatedIncidents(array $filters, int $perPage = 15): LengthAwarePaginator
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
            ->when($filters['assigned_to'] ?? null, fn (Builder $query, int $userId) => $query->where('assigned_to', $userId))
            ->when($filters['tag_id'] ?? null, function (Builder $query, int $tagId): void {
                $query->whereHas('tags', fn (Builder $query) => $query->where('tags.id', $tagId));
            })
            ->when($filters['sla'] ?? null, fn (Builder $query, string $sla) => $this->applySlaFilter($query, $sla))
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->orderByRaw("CASE WHEN status = 'resolved' THEN 1 ELSE 0 END")
            ->orderBy('sla_deadline')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Incident $incident): array => $this->summary($incident));
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

            return $incident;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateIncident(User $actor, Incident $incident, array $data): void
    {
        $this->authorizeModification($actor);

        DB::transaction(function () use ($actor, $incident, $data): void {
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
                array_key_exists('status', $changes) => ['status_changed', 'Incident status changed.'],
                array_key_exists('assigned_to', $changes) => ['assigned', 'Incident owner changed.'],
                $changes === [] => ['tags_updated', 'Incident tags updated.'],
                default => ['updated', 'Incident details updated.'],
            };

            $incident->activities()->create([
                'user_id' => $actor->id,
                'type' => $type,
                'content' => $content,
                'metadata' => [
                    'changes' => $changes,
                    'tag_ids' => $currentTagIds,
                ],
            ]);
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
}
