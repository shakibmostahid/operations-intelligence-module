<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Role;
use App\Models\SystemHealthCheck;
use App\Models\User;
use App\Services\DailyOperationsSummaryService;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_daily_summary_aggregates_current_operational_state(): void
    {
        Carbon::setTestNow('2026-06-13 12:00:00');
        $user = $this->user();

        $this->incident($user, [
            'source' => 'monitoring',
            'severity' => 'critical',
            'status' => 'escalated',
            'sla_deadline' => now()->subHour(),
            'created_at' => now()->subHours(2),
        ]);
        $this->incident($user, [
            'source' => 'monitoring',
            'status' => 'resolved',
            'resolved_at' => now()->subMinutes(20),
            'created_at' => now()->subHours(3),
        ]);
        $this->incident($user, [
            'created_at' => now()->subDay(),
        ]);

        $summary = app(DailyOperationsSummaryService::class)->generate();

        $this->assertSame(2, $summary['metrics']['created']);
        $this->assertSame(1, $summary['metrics']['resolved']);
        $this->assertSame(2, $summary['metrics']['active']);
        $this->assertSame(1, $summary['metrics']['critical']);
        $this->assertSame(1, $summary['metrics']['sla_breached']);
        $this->assertSame(['name' => 'monitoring', 'count' => 2], $summary['top_source']);
    }

    public function test_system_health_excludes_future_checks_and_adds_live_probe_data(): void
    {
        Carbon::setTestNow('2026-06-13 12:00:00');

        SystemHealthCheck::query()->create([
            'system_name' => 'Checkout API',
            'status' => 'up',
            'response_time_ms' => 150,
            'checked_at' => now()->subMinute(),
        ]);
        SystemHealthCheck::query()->create([
            'system_name' => 'Checkout API',
            'status' => 'down',
            'response_time_ms' => 1000,
            'checked_at' => now()->addHour(),
        ]);

        $health = app(SystemHealthService::class)->dashboard();

        $this->assertSame(100.0, $health['overall_uptime']);
        $this->assertSame(10, $health['refresh_after_seconds']);
        $this->assertSame(150, $health['systems'][0]['average_response_ms']);
        $this->assertContains($health['systems'][0]['latest_status'], ['up', 'degraded', 'down']);
        $this->assertIsInt($health['systems'][0]['current_response_ms']);
        $expectedLiveUptime = match ($health['systems'][0]['latest_status']) {
            'up' => 100.0,
            'degraded' => 50.0,
            'down' => 0.0,
        };
        $this->assertSame($expectedLiveUptime, $health['trend'][6]['uptime']);
        $this->assertTrue($health['trend'][6]['live']);
    }

    public function test_dashboard_summary_and_health_endpoints_return_json(): void
    {
        Carbon::setTestNow('2026-06-13 12:00:00');
        $user = $this->user();

        SystemHealthCheck::query()->create([
            'system_name' => 'Checkout API',
            'status' => 'up',
            'response_time_ms' => 150,
            'checked_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/dashboard/daily-summary')
            ->assertOk()
            ->assertJsonStructure([
                'headline',
                'summary',
                'next_action',
                'metrics',
                'generated_at',
            ]);

        $this->actingAs($user)
            ->getJson('/dashboard/system-health')
            ->assertOk()
            ->assertJsonPath('refresh_after_seconds', 10)
            ->assertJsonStructure([
                'overall_uptime',
                'systems',
                'trend',
                'checked_at',
            ]);
    }

    private function user(): User
    {
        $role = Role::query()->create(['role' => 'support_engineer']);

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
            'must_change_password' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function incident(User $user, array $overrides = []): Incident
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $incident = Incident::query()->create([
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'severity' => 'medium',
            'status' => 'open',
            'assigned_to' => null,
            'created_by' => $user->id,
            'sla_deadline' => now()->addHour(),
            'resolved_at' => null,
            'rca_note' => null,
            ...$overrides,
        ]);

        $incident->forceFill(['created_at' => $createdAt])->save();

        return $incident;
    }
}
