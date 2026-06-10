<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function __construct(private readonly IncidentService $incidentService)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', Rule::in(Incident::SEVERITIES)],
            'status' => ['nullable', Rule::in(Incident::STATUSES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'sla' => ['nullable', Rule::in(['healthy', 'at_risk', 'breached', 'resolved', 'not_set'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ]);

        foreach (['assigned_to', 'tag_id', 'per_page'] as $integerFilter) {
            if (isset($filters[$integerFilter])) {
                $filters[$integerFilter] = (int) $filters[$integerFilter];
            }
        }

        $perPage = (int) ($filters['per_page'] ?? 10);

        return view('app', [
            'page' => 'incident-list',
            'props' => [
                'user' => $this->authenticatedUser($request->user()),
                'incidents' => $this->incidentService->paginatedIncidents($filters, $perPage),
                'filters' => $filters,
                'users' => $this->incidentService->assignableUsers(),
                'tags' => $this->incidentService->tags(),
                'severities' => Incident::SEVERITIES,
                'statuses' => Incident::STATUSES,
                'success' => session('success'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('app', [
            'page' => 'incident-create',
            'props' => [
                'user' => $this->authenticatedUser($request->user()),
                'users' => $this->incidentService->assignableUsers(),
                'tags' => $this->incidentService->tags(),
                'severities' => Incident::SEVERITIES,
                'errors' => session('errors')?->getBag('default')->toArray() ?? [],
                'old' => old(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->incidentRules());
        $incident = $this->incidentService->createIncident($request->user(), $validated);

        return redirect()
            ->to(route('incidents.show', $incident, absolute: false))
            ->with('success', 'Incident created successfully.');
    }

    public function show(Request $request, Incident $incident): View
    {
        $incident->load([
            'assignee:id,name',
            'creator:id,name',
            'tags:id,name,color',
            'activities' => fn ($query) => $query->with('user:id,name')->latest(),
        ]);
        $user = $request->user()->load('role');

        return view('app', [
            'page' => 'incident-detail',
            'props' => [
                'user' => $this->authenticatedUser($user),
                'incident' => [
                    ...$this->incidentService->summary($incident),
                    'description' => $incident->description,
                    'assigned_to' => $incident->assigned_to,
                    'sla_deadline_input' => $incident->sla_deadline?->format('Y-m-d\TH:i'),
                    'rca_note' => $incident->rca_note,
                    'activities' => $incident->activities->map(fn ($activity) => [
                        'id' => $activity->id,
                        'type' => $activity->type,
                        'content' => $activity->content,
                        'metadata' => $activity->metadata,
                        'user' => $activity->user?->name ?? 'System',
                        'created_at' => $activity->created_at?->format('M j, Y g:i A'),
                    ])->values()->all(),
                ],
                'canModify' => $user->role->role !== 'viewer',
                'users' => $this->incidentService->assignableUsers(),
                'tags' => $this->incidentService->tags(),
                'severities' => Incident::SEVERITIES,
                'statuses' => Incident::STATUSES,
                'errors' => session('errors')?->getBag('default')->toArray() ?? [],
                'success' => session('success'),
            ],
        ]);
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $assignableUserIds = collect($this->incidentService->assignableUsers())->pluck('id')->all();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'severity' => ['required', Rule::in(Incident::SEVERITIES)],
            'status' => ['required', Rule::in(Incident::STATUSES)],
            'assigned_to' => ['nullable', 'integer', Rule::in($assignableUserIds)],
            'sla_deadline' => ['nullable', 'date'],
            'rca_note' => ['nullable', 'string', 'max:5000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $this->incidentService->updateIncident($request->user(), $incident, $validated);

        return redirect()
            ->to(route('incidents.show', $incident, absolute: false))
            ->with('success', 'Incident updated successfully.');
    }

    public function comment(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
        ]);

        $this->incidentService->addComment($request->user(), $incident, $validated['content']);

        return redirect()
            ->to(route('incidents.show', $incident, absolute: false))
            ->with('success', 'Comment added.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function incidentRules(): array
    {
        $assignableUserIds = collect($this->incidentService->assignableUsers())->pluck('id')->all();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'severity' => ['required', Rule::in(Incident::SEVERITIES)],
            'assigned_to' => ['nullable', 'integer', Rule::in($assignableUserIds)],
            'sla_deadline' => ['nullable', 'date'],
            'rca_note' => ['nullable', 'string', 'max:5000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ];
    }

    /**
     * @return array{name: string, email: string, role: string}
     */
    private function authenticatedUser(User $user): array
    {
        $user->loadMissing('role');

        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->role,
        ];
    }
}
