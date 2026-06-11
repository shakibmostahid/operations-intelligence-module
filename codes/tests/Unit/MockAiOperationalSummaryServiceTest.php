<?php

namespace Tests\Unit;

use App\Models\Incident;
use App\Models\IncidentActivity;
use App\Models\Tag;
use App\Models\User;
use App\Services\MockAiOperationalSummaryService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class MockAiOperationalSummaryServiceTest extends TestCase
{
    public function test_it_selects_the_matching_mock_profile_and_builds_context(): void
    {
        $incident = new Incident([
            'severity' => 'critical',
            'status' => 'escalated',
        ]);
        $incident->setRelation('assignee', new User(['name' => 'Support Engineer']));
        $incident->setRelation('tags', new Collection([
            new Tag(['name' => 'API', 'color' => '#2563eb']),
            new Tag(['name' => 'Customer Impact', 'color' => '#dc2626']),
        ]));
        $incident->setRelation('activities', new Collection([
            new IncidentActivity(),
            new IncidentActivity(),
        ]));

        $summary = app(MockAiOperationalSummaryService::class)
            ->generate($incident, 'breached');

        $this->assertSame('Immediate intervention required', $summary['headline']);
        $this->assertStringContainsString('Support Engineer', $summary['summary']);
        $this->assertStringContainsString('2 activities', $summary['summary']);
        $this->assertStringContainsString('API, Customer Impact', $summary['summary']);
        $this->assertNotEmpty($summary['next_action']);
    }

    public function test_it_uses_the_resolved_profile_for_resolved_incidents(): void
    {
        $incident = new Incident([
            'severity' => 'low',
            'status' => 'resolved',
        ]);
        $incident->setRelation('assignee', null);
        $incident->setRelation('tags', new Collection());
        $incident->setRelation('activities', new Collection());

        $summary = app(MockAiOperationalSummaryService::class)
            ->generate($incident, 'resolved');

        $this->assertSame('Incident response completed', $summary['headline']);
        $this->assertStringContainsString('No responder is currently assigned', $summary['summary']);
    }
}
