<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AlertRoutingService
{
    public function route(string $event, Incident $incident): void
    {
        $rules = AlertRule::query()
            ->with('role:id')
            ->where('event', $event)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('severity')
                ->orWhere('severity', $incident->severity))
            ->get();

        foreach ($rules as $rule) {
            $recipients = User::query()
                ->where('status', 'active')
                ->when($rule->role_id, fn (Builder $query) => $query->where('role_id', $rule->role_id))
                ->get(['id']);

            foreach ($recipients as $recipient) {
                $this->createAlert($recipient->id, $event, $incident);
            }
        }

        if ($incident->assigned_to !== null) {
            $this->createAlert($incident->assigned_to, $event, $incident);
        }
    }

    public function routeCurrentSlaBreaches(): void
    {
        Incident::query()
            ->where('status', '!=', 'resolved')
            ->whereNull('resolved_at')
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', now())
            ->each(fn (Incident $incident) => $this->route('sla_breached', $incident));
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, unread: int}
     */
    public function alertsFor(User $user, int $limit = 6): array
    {
        $query = Alert::query()
            ->with('incident:id,title,severity,status')
            ->where('user_id', $user->id);

        return [
            'unread' => (clone $query)->whereNull('read_at')->count(),
            'items' => $query->latest()->limit($limit)->get()->map(fn (Alert $alert): array => [
                'id' => $alert->id,
                'event' => $alert->event,
                'message' => $alert->message,
                'read' => $alert->read_at !== null,
                'created_at' => $alert->created_at?->diffForHumans(),
                'incident' => [
                    'id' => $alert->incident->id,
                    'title' => $alert->incident->title,
                    'severity' => $alert->incident->severity,
                    'status' => $alert->incident->status,
                ],
            ])->all(),
        ];
    }

    private function createAlert(int $userId, string $event, Incident $incident): void
    {
        $message = match ($event) {
            'incident_created' => "New {$incident->severity} incident: {$incident->title}",
            'incident_escalated' => "Incident escalated: {$incident->title}",
            'sla_breached' => "SLA breached: {$incident->title}",
            default => "Incident alert: {$incident->title}",
        };

        Alert::query()->firstOrCreate(
            ['deduplication_key' => "{$event}:{$incident->id}:{$userId}"],
            [
                'incident_id' => $incident->id,
                'user_id' => $userId,
                'event' => $event,
                'message' => $message,
            ],
        );
    }
}
