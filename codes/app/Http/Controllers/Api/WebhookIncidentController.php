<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WebhookIncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidentService)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $configuredToken = (string) config('services.incident_webhook.token');
        $providedToken = (string) $request->header('X-Webhook-Token');

        if ($configuredToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json(['message' => 'Invalid webhook token.'], 401);
        }

        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'severity' => ['required', Rule::in(Incident::SEVERITIES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'sla_deadline' => ['nullable', 'date'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $actor = User::query()
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('role', 'super_admin'))
            ->firstOrFail();

        [$incident, $created] = $this->incidentService->ingestIncident($actor, $validated);

        return response()->json([
            'message' => $created ? 'Incident ingested.' : 'Incident already ingested.',
            'incident_id' => $incident->id,
            'created' => $created,
        ], $created ? 201 : 200);
    }
}
