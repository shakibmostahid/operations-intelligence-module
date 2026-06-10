<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Incident #{{ $incident->id }}</title>
    <style>
        @page { margin: 36px; }
        body { color: #172027; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { border-bottom: 1px solid #dce1e4; font-size: 13px; margin: 22px 0 10px; padding-bottom: 5px; }
        .muted { color: #667079; }
        .header { border-bottom: 3px solid #297069; padding-bottom: 14px; }
        .brand { color: #297069; font-size: 10px; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .grid td { border: 1px solid #dce1e4; padding: 7px; vertical-align: top; width: 25%; }
        .label { color: #667079; display: block; font-size: 9px; text-transform: uppercase; }
        .section { white-space: pre-wrap; }
        .tag { border: 1px solid #cbd1d5; display: inline-block; margin: 0 4px 4px 0; padding: 3px 6px; }
        .activity { border-left: 3px solid #297069; margin-bottom: 10px; padding: 4px 0 4px 10px; }
        .activity-title { font-weight: bold; }
        .footer { color: #899298; font-size: 9px; margin-top: 24px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">FGL Incident &amp; Operations Tracking</div>
        <h1>Incident #{{ $incident->id }}: {{ $incident->title }}</h1>
        <div class="muted">Exported {{ now()->format('M j, Y g:i A') }}</div>
    </div>

    <table class="grid">
        <tr>
            <td><span class="label">Severity</span>{{ ucfirst($incident->severity) }}</td>
            <td><span class="label">Status</span>{{ str($incident->status)->replace('_', ' ')->title() }}</td>
            <td><span class="label">Assigned user</span>{{ $incident->assignee?->name ?? 'Unassigned' }}</td>
            <td><span class="label">Created by</span>{{ $incident->creator?->name ?? 'Unknown' }}</td>
        </tr>
        <tr>
            <td><span class="label">Created</span>{{ $summary['created_at'] }}</td>
            <td><span class="label">Resolved</span>{{ $summary['resolved_at'] ?? 'Not resolved' }}</td>
            <td><span class="label">Duration</span>{{ $summary['duration_state'] === 'resolved' ? 'Resolved in' : 'Running' }} {{ $summary['duration'] }}</td>
            <td><span class="label">SLA</span>{{ $summary['sla_deadline'] ?? 'Not set' }} ({{ str($summary['sla_state'])->replace('_', ' ')->title() }})</td>
        </tr>
    </table>

    <h2>Description</h2>
    <div class="section">{{ $incident->description }}</div>

    <h2>Tags</h2>
    @forelse ($incident->tags as $tag)
        <span class="tag">{{ $tag->name }}</span>
    @empty
        <span class="muted">No tags</span>
    @endforelse

    <h2>Root Cause Analysis</h2>
    <div class="section">{{ $incident->rca_note ?: 'No RCA note recorded.' }}</div>

    <h2>Activity Timeline</h2>
    @forelse ($incident->activities as $activity)
        <div class="activity">
            <div class="activity-title">{{ str($activity->type)->replace('_', ' ')->title() }}</div>
            <div>{{ $activity->content }}</div>
            <div class="muted">{{ $activity->user?->name ?? 'System' }} &middot; {{ $activity->created_at?->format('M j, Y g:i A') }}</div>
        </div>
    @empty
        <div class="muted">No activity recorded.</div>
    @endforelse

    <div class="footer">Incident #{{ $incident->id }}</div>
</body>
</html>
