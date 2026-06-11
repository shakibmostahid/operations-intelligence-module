<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get()->keyBy('email');
        $tags = Tag::query()->get()->keyBy('name');
        $now = now()->startOfMinute();

        $admin = $users->get('admin@iot.com');
        $support = $users->get('support@iot.com');
        $superAdmin = $users->get('super.admin@iot.com');

        $incidents = [
            [
                'title' => 'Checkout API returning elevated 5xx responses',
                'description' => 'Payment authorization requests are intermittently failing for customers in the primary region.',
                'severity' => 'critical',
                'status' => 'escalated',
                'assigned_to' => $support->id,
                'created_by' => $admin->id,
                'created_at' => $now->copy()->subHours(5),
                'sla_deadline' => $now->copy()->subMinutes(35),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['API', 'Billing', 'Customer Impact'],
                'activities' => [
                    ['user' => $admin, 'type' => 'created', 'content' => 'Incident created after payment failure alerts crossed the critical threshold.'],
                    ['user' => $admin, 'type' => 'assigned', 'content' => 'Assigned to Support Engineer.', 'metadata' => ['assigned_to' => $support->id]],
                    ['user' => $support, 'type' => 'escalated', 'content' => 'Escalated after confirming sustained customer impact.', 'metadata' => ['from' => 'investigating', 'to' => 'escalated']],
                ],
            ],
            [
                'title' => 'Primary database replica lag increasing',
                'description' => 'Read replica lag is above the normal threshold and affecting reporting freshness.',
                'severity' => 'high',
                'status' => 'investigating',
                'assigned_to' => $support->id,
                'created_by' => $superAdmin->id,
                'created_at' => $now->copy()->subHours(2),
                'sla_deadline' => $now->copy()->addMinutes(40),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['Database', 'Infrastructure'],
                'activities' => [
                    ['user' => $superAdmin, 'type' => 'created', 'content' => 'Incident created from database monitoring alert.'],
                    ['user' => $support, 'type' => 'status_changed', 'content' => 'Investigation started.', 'metadata' => ['from' => 'open', 'to' => 'investigating']],
                ],
            ],
            [
                'title' => 'Invoice generation queue processing slowly',
                'description' => 'Scheduled invoices are processing behind the expected completion window.',
                'severity' => 'medium',
                'status' => 'open',
                'assigned_to' => null,
                'created_by' => $admin->id,
                'created_at' => $now->copy()->subMinutes(35),
                'sla_deadline' => $now->copy()->addHours(4),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['Billing'],
                'activities' => [
                    ['user' => $admin, 'type' => 'created', 'content' => 'Incident created after the billing completion check failed.'],
                ],
            ],
            [
                'title' => 'Deployment health checks timing out',
                'description' => 'The latest application deployment is healthy, but post-deployment probes are timing out intermittently.',
                'severity' => 'high',
                'status' => 'investigating',
                'assigned_to' => $support->id,
                'created_by' => $admin->id,
                'created_at' => $now->copy()->subHours(3),
                'sla_deadline' => $now->copy()->addMinutes(15),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['Deployment', 'Infrastructure'],
                'activities' => [
                    ['user' => $admin, 'type' => 'created', 'content' => 'Incident created from deployment health alerts.'],
                    ['user' => $admin, 'type' => 'assigned', 'content' => 'Assigned to Support Engineer.', 'metadata' => ['assigned_to' => $support->id]],
                ],
            ],
            [
                'title' => 'Customer portal login latency',
                'description' => 'Authentication requests are succeeding but p95 response time is above the service target.',
                'severity' => 'medium',
                'status' => 'open',
                'assigned_to' => $support->id,
                'created_by' => $admin->id,
                'created_at' => $now->copy()->subHours(1),
                'sla_deadline' => $now->copy()->addHours(2),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['API', 'Customer Impact'],
                'activities' => [
                    ['user' => $admin, 'type' => 'created', 'content' => 'Incident created from the authentication latency monitor.'],
                    ['user' => $admin, 'type' => 'assigned', 'content' => 'Assigned to Support Engineer.', 'metadata' => ['assigned_to' => $support->id]],
                ],
            ],
            [
                'title' => 'Expired TLS certificate warning on staging',
                'description' => 'A staging integration endpoint is presenting an expired certificate. Production is unaffected.',
                'severity' => 'low',
                'status' => 'open',
                'assigned_to' => null,
                'created_by' => $support->id,
                'created_at' => $now->copy()->subDays(2),
                'sla_deadline' => $now->copy()->addDay(),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['Infrastructure', 'Security'],
                'activities' => [
                    ['user' => $support, 'type' => 'created', 'content' => 'Incident created during routine certificate review.'],
                ],
            ],
            [
                'title' => 'Duplicate webhook deliveries',
                'description' => 'A subset of downstream customers received duplicate event notifications.',
                'severity' => 'high',
                'status' => 'resolved',
                'assigned_to' => $support->id,
                'created_by' => $admin->id,
                'created_at' => $now->copy()->subDays(1)->subHours(2),
                'sla_deadline' => $now->copy()->subDay()->addHours(2),
                'resolved_at' => $now->copy()->subDay()->addMinutes(45),
                'rca_note' => 'A retry worker did not persist the delivery acknowledgement before retrying. The acknowledgement is now written atomically.',
                'tags' => ['API', 'Customer Impact'],
                'activities' => [
                    ['user' => $admin, 'type' => 'created', 'content' => 'Incident created from customer reports.'],
                    ['user' => $support, 'type' => 'resolved', 'content' => 'Retry handling was corrected and duplicate delivery stopped.', 'metadata' => ['from' => 'investigating', 'to' => 'resolved']],
                ],
            ],
            [
                'title' => 'Analytics dashboard data delayed',
                'description' => 'Operational dashboard data was delayed due to a failed overnight aggregation task.',
                'severity' => 'medium',
                'status' => 'resolved',
                'assigned_to' => $support->id,
                'created_by' => $superAdmin->id,
                'created_at' => $now->copy()->subDays(3),
                'sla_deadline' => $now->copy()->subDays(2)->subHours(20),
                'resolved_at' => $now->copy()->subDays(2)->subHours(18),
                'rca_note' => 'The aggregation task exhausted memory while processing an unusually large partition. Processing is now partitioned into smaller batches.',
                'tags' => ['Database', 'Infrastructure'],
                'activities' => [
                    ['user' => $superAdmin, 'type' => 'created', 'content' => 'Incident created after delayed data was confirmed.'],
                    ['user' => $support, 'type' => 'resolved', 'content' => 'Aggregation completed and dashboard freshness returned to normal.'],
                ],
            ],
            [
                'title' => 'Admin export producing incomplete CSV files',
                'description' => 'Large exports stop after the first page and produce incomplete customer reports.',
                'severity' => 'medium',
                'status' => 'investigating',
                'assigned_to' => $support->id,
                'created_by' => $admin->id,
                'created_at' => $now->copy()->subHours(7),
                'sla_deadline' => $now->copy()->subMinutes(10),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['Customer Impact'],
                'activities' => [
                    ['user' => $admin, 'type' => 'created', 'content' => 'Incident created from an operations team report.'],
                    ['user' => $support, 'type' => 'status_changed', 'content' => 'Reproduction confirmed with a large export.', 'metadata' => ['from' => 'open', 'to' => 'investigating']],
                ],
            ],
            [
                'title' => 'Container image vulnerability detected',
                'description' => 'The security scanner detected a high-severity package vulnerability in a non-public worker image.',
                'severity' => 'high',
                'status' => 'escalated',
                'assigned_to' => $admin->id,
                'created_by' => $support->id,
                'created_at' => $now->copy()->subHours(6),
                'sla_deadline' => $now->copy()->addHours(1),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['Deployment', 'Security'],
                'activities' => [
                    ['user' => $support, 'type' => 'created', 'content' => 'Incident created from container security scan results.'],
                    ['user' => $support, 'type' => 'escalated', 'content' => 'Escalated for remediation planning.', 'metadata' => ['from' => 'open', 'to' => 'escalated']],
                ],
            ],
            [
                'title' => 'Internal knowledge base typo reported',
                'description' => 'A documentation typo was reported and does not require an SLA commitment.',
                'severity' => 'low',
                'status' => 'open',
                'assigned_to' => null,
                'created_by' => $support->id,
                'created_at' => $now->copy()->subDays(4),
                'sla_deadline' => null,
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['Customer Impact'],
                'activities' => [
                    ['user' => $support, 'type' => 'created', 'content' => 'Incident created from an internal documentation report.'],
                ],
            ],
            [
                'title' => 'Search indexing worker stopped overnight',
                'description' => 'Product updates are not appearing in search because an indexing worker stopped processing.',
                'severity' => 'critical',
                'status' => 'open',
                'assigned_to' => $support->id,
                'created_by' => $superAdmin->id,
                'created_at' => $now->copy()->subDays(1)->subHours(8),
                'sla_deadline' => $now->copy()->subDays(1)->subHours(2),
                'resolved_at' => null,
                'rca_note' => null,
                'tags' => ['Infrastructure', 'Customer Impact'],
                'activities' => [
                    ['user' => $superAdmin, 'type' => 'created', 'content' => 'Incident created after the indexing freshness monitor failed.'],
                    ['user' => $superAdmin, 'type' => 'assigned', 'content' => 'Assigned to Support Engineer.', 'metadata' => ['assigned_to' => $support->id]],
                ],
            ],
            [
                'title' => 'Scheduled report email delivery recovered',
                'description' => 'Scheduled report emails were delayed briefly and recovered before the SLA deadline.',
                'severity' => 'low',
                'status' => 'resolved',
                'assigned_to' => $support->id,
                'created_by' => $admin->id,
                'created_at' => $now->copy()->subDays(6)->subHours(2),
                'sla_deadline' => $now->copy()->subDays(6)->addHours(2),
                'resolved_at' => $now->copy()->subDays(6)->subMinutes(30),
                'rca_note' => 'A temporary mail provider throttle delayed delivery. Backoff settings were adjusted and the queued messages were delivered.',
                'tags' => ['Customer Impact', 'Infrastructure'],
                'activities' => [
                    ['user' => $admin, 'type' => 'created', 'content' => 'Incident created after scheduled report delivery slowed.'],
                    ['user' => $support, 'type' => 'resolved', 'content' => 'Delivery recovered and the delayed queue was cleared.', 'metadata' => ['from' => 'investigating', 'to' => 'resolved']],
                ],
            ],
            [
                'title' => 'Billing reconciliation completed after SLA',
                'description' => 'The daily billing reconciliation completed successfully, but only after the committed SLA window.',
                'severity' => 'medium',
                'status' => 'resolved',
                'assigned_to' => $admin->id,
                'created_by' => $superAdmin->id,
                'created_at' => $now->copy()->subDays(9),
                'sla_deadline' => $now->copy()->subDays(8)->subHours(20),
                'resolved_at' => $now->copy()->subDays(8)->subHours(16),
                'rca_note' => 'A large settlement batch exceeded the expected processing window. The reconciliation job now processes settlements in bounded chunks.',
                'tags' => ['Billing', 'Database'],
                'activities' => [
                    ['user' => $superAdmin, 'type' => 'created', 'content' => 'Incident created when reconciliation exceeded its expected duration.'],
                    ['user' => $admin, 'type' => 'resolved', 'content' => 'Reconciliation completed and processing was optimized.', 'metadata' => ['from' => 'investigating', 'to' => 'resolved']],
                ],
            ],
        ];

        foreach ($incidents as $data) {
            $tagNames = $data['tags'];
            $activities = $data['activities'];
            $createdAt = $data['created_at'];
            unset($data['tags'], $data['activities'], $data['created_at']);

            $incident = Incident::query()->updateOrCreate(
                ['title' => $data['title']],
                $data,
            );

            $incident->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $data['resolved_at'] ?? $createdAt,
            ])->save();

            $incident->tags()->sync(
                collect($tagNames)->map(fn (string $name) => $tags->get($name)->id),
            );

            $incident->activities()->delete();

            foreach ($activities as $activityIndex => $activity) {
                $activityCreatedAt = $this->activityTime(
                    $createdAt,
                    $data['resolved_at'],
                    $activityIndex,
                    count($activities),
                );

                $activityModel = $incident->activities()->make([
                    'user_id' => $activity['user']->id,
                    'type' => $activity['type'],
                    'content' => $activity['content'],
                    'metadata' => $activity['metadata'] ?? null,
                ]);

                $activityModel->forceFill([
                    'created_at' => $activityCreatedAt,
                    'updated_at' => $activityCreatedAt,
                ])->save();
            }
        }
    }

    private function activityTime(
        Carbon $incidentCreatedAt,
        ?Carbon $resolvedAt,
        int $activityIndex,
        int $activityCount,
    ): Carbon {
        if ($resolvedAt !== null && $activityIndex === $activityCount - 1) {
            return $resolvedAt->copy();
        }

        return $incidentCreatedAt->copy()->addMinutes($activityIndex * 30);
    }
}
