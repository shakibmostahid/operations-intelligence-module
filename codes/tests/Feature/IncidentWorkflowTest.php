<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Role;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_move_escalated_incident_back_to_investigating(): void
    {
        $creator = $this->user('support_engineer');
        $incident = $this->incident($creator, status: 'escalated');

        $this->service()->updateIncident(
            $creator,
            $incident,
            $this->updateData($incident, ['status' => 'investigating']),
        );

        $this->assertSame('investigating', $incident->fresh()->status);
        $this->assertDatabaseHas('incident_activities', [
            'incident_id' => $incident->id,
            'type' => 'status_changed',
        ]);
    }

    public function test_unrelated_user_cannot_change_incident_status(): void
    {
        $creator = $this->user('support_engineer');
        $unrelatedUser = $this->user('support_engineer');
        $incident = $this->incident($creator);

        $this->expectException(AuthorizationException::class);

        $this->service()->updateIncident(
            $unrelatedUser,
            $incident,
            $this->updateData($incident, ['status' => 'investigating']),
        );
    }

    public function test_escalation_requires_a_reason(): void
    {
        $creator = $this->user('support_engineer');
        $incident = $this->incident($creator);

        try {
            $this->service()->updateIncident(
                $creator,
                $incident,
                $this->updateData($incident, ['status' => 'escalated']),
            );

            $this->fail('Expected escalation validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('escalation_reason', $exception->errors());
        }
    }

    public function test_resolved_incident_details_are_locked(): void
    {
        $creator = $this->user('support_engineer');
        $incident = $this->incident($creator, status: 'resolved');

        try {
            $this->service()->updateIncident(
                $creator,
                $incident,
                $this->updateData($incident, ['title' => 'Changed title']),
            );

            $this->fail('Expected resolved incident validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('incident', $exception->errors());
        }
    }

    public function test_incident_list_is_sorted_by_latest_by_default(): void
    {
        $creator = $this->user('support_engineer');
        $older = $this->incident($creator);
        $newer = $this->incident($creator);

        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer->forceFill(['created_at' => now()])->save();

        $incidents = $this->service()->filteredIncidents([]);

        $this->assertSame([$newer->id, $older->id], $incidents->pluck('id')->all());
    }

    private function service(): IncidentService
    {
        return app(IncidentService::class);
    }

    private function user(string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(['role' => $role]);

        return User::factory()->create([
            'role_id' => $roleModel->id,
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    private function incident(User $creator, string $status = 'open'): Incident
    {
        return Incident::query()->create([
            'title' => 'Checkout API unavailable',
            'description' => 'Health checks are failing.',
            'severity' => 'critical',
            'status' => $status,
            'assigned_to' => null,
            'created_by' => $creator->id,
            'sla_deadline' => now()->addHour(),
            'resolved_at' => $status === 'resolved' ? now() : null,
            'rca_note' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updateData(Incident $incident, array $overrides = []): array
    {
        return [
            'title' => $incident->title,
            'description' => $incident->description,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'sla_deadline' => $incident->sla_deadline,
            'rca_note' => $incident->rca_note,
            'tag_ids' => [],
            ...$overrides,
        ];
    }
}
