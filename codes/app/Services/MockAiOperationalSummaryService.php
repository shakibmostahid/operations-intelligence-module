<?php

namespace App\Services;

use App\Models\Incident;
use RuntimeException;

class MockAiOperationalSummaryService
{
    /**
     * @return array{
     *     provider: string,
     *     model: string,
     *     headline: string,
     *     summary: string,
     *     next_action: string,
     *     generated_at: string
     * }
     */
    public function generate(Incident $incident, string $slaState): array
    {
        $mock = $this->mockResponses();
        $profile = $this->profile($mock['combinations'], $incident, $slaState);
        $activityCount = $incident->activities->count();
        $tags = $incident->tags->pluck('name')->implode(', ');

        $context = [
            $profile['assessment'],
            $this->ownershipContext($mock['ownership'], $incident),
            $this->activityContext($mock['activity'], $activityCount),
        ];

        if ($tags !== '') {
            $context[] = "Affected operational areas: {$tags}.";
        }

        return [
            'provider' => $mock['provider'],
            'model' => $mock['model'],
            'headline' => $profile['headline'],
            'summary' => implode(' ', $context),
            'next_action' => $profile['next_action'],
            'generated_at' => now()->format('M j, Y g:i A'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mockResponses(): array
    {
        $path = resource_path('mocks/ai-operational-summary.json');
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('Unable to read the mock AI response file.');
        }

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, array<string, string>>  $combinations
     * @return array<string, string>
     */
    private function profile(array $combinations, Incident $incident, string $slaState): array
    {
        $exactKey = "{$incident->severity}.{$incident->status}.{$slaState}";

        return $combinations[$exactKey]
            ?? ($incident->status === 'resolved' ? $combinations['resolved'] : $combinations['default']);
    }

    /**
     * @param  array<string, string>  $ownership
     */
    private function ownershipContext(array $ownership, Incident $incident): string
    {
        if ($incident->assignee === null) {
            return $ownership['unassigned'];
        }

        return str_replace('{{assignee}}', $incident->assignee->name, $ownership['assigned']);
    }

    /**
     * @param  array<string, string>  $activity
     */
    private function activityContext(array $activity, int $activityCount): string
    {
        return match (true) {
            $activityCount === 0 => $activity['none'],
            $activityCount === 1 => $activity['single'],
            default => str_replace('{{activity_count}}', (string) $activityCount, $activity['multiple']),
        };
    }
}
