<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentService;
use App\Services\MockAiOperationalSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function __construct(
        private readonly IncidentService $incidentService,
        private readonly MockAiOperationalSummaryService $operationalSummaryService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $this->validatedListFilters($request);

        $perPage = (int) ($filters['per_page'] ?? 10);

        return view('app', [
            'page' => 'incident-list',
            'props' => [
                'user' => $this->authenticatedUser($request->user()),
                'incidents' => $this->incidentService->paginatedIncidents(
                    $filters,
                    $perPage,
                    $request->user()->id,
                ),
                'filters' => (object) $filters,
                'users' => $this->incidentService->assignableUsers(),
                'tags' => $this->incidentService->tags(),
                'severities' => Incident::SEVERITIES,
                'statuses' => Incident::STATUSES,
                'success' => session('success'),
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedListFilters($request);
        unset($filters['per_page']);

        $incidents = $this->incidentService->filteredIncidents($filters, $request->user()->id);

        return response()->streamDownload(function () use ($incidents): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'ID', 'Title', 'Description', 'Severity', 'Status', 'Assigned User',
                'Created By', 'SLA Deadline', 'SLA State', 'Duration', 'Created At',
                'Resolved At', 'RCA Note', 'Tags',
            ], ',', '"', '');

            foreach ($incidents as $incident) {
                $summary = $this->incidentService->summary($incident);
                fputcsv($output, [
                    $incident->id,
                    $incident->title,
                    $incident->description,
                    $incident->severity,
                    $incident->status,
                    $incident->assignee?->name,
                    $incident->creator?->name,
                    $summary['sla_deadline'],
                    $summary['sla_state'],
                    $summary['duration'],
                    $summary['created_at'],
                    $summary['resolved_at'],
                    $incident->rca_note,
                    $incident->tags->pluck('name')->implode(', '),
                ], ',', '"', '');
            }

            fclose($output);
        }, 'incidents-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Incident $incident)
    {
        $incident->load([
            'assignee:id,name,email',
            'creator:id,name,email',
            'tags:id,name,color',
            'activities' => fn ($query) => $query->with('user:id,name,email')->oldest(),
        ]);

        return Pdf::loadView('exports.incident', [
            'incident' => $incident,
            'summary' => $this->incidentService->summary($incident),
        ])->setPaper('a4')->download("incident-{$incident->id}.pdf");
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
        $canEditIncident = $this->incidentService->canEditIncident($user, $incident);

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
                'canEditIncident' => $canEditIncident,
                'canChangeStatus' => $this->incidentService->canChangeStatus($user, $incident),
                'canComment' => $this->incidentService->canComment($user),
                'tags' => $this->incidentService->tags(),
                'severities' => Incident::SEVERITIES,
                'statuses' => Incident::STATUSES,
                'errors' => session('errors')?->getBag('default')->toArray() ?? [],
                'success' => session('success'),
            ],
        ]);
    }

    public function operationalSummary(Incident $incident): JsonResponse
    {
        $incident->load([
            'assignee:id,name',
            'tags:id,name,color',
            'activities:id,incident_id',
        ]);

        return response()
            ->json($this->operationalSummaryService->generate(
                $incident,
                $this->incidentService->slaState($incident),
            ))
            ->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'severity' => ['required', Rule::in(Incident::SEVERITIES)],
            'status' => ['required', Rule::in(Incident::STATUSES)],
            'escalation_reason' => ['nullable', 'string', 'max:2000'],
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
     * @return array<string, mixed>
     */
    private function validatedListFilters(Request $request): array
    {
        $assignableUserIds = collect($this->incidentService->assignableUsers())
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->all();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', Rule::in(Incident::SEVERITIES)],
            'status' => ['nullable', Rule::in(Incident::STATUSES)],
            'assigned_to' => ['nullable', Rule::in(['self', ...$assignableUserIds])],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'sla' => ['nullable', Rule::in(['healthy', 'at_risk', 'breached', 'resolved', 'not_set'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', Rule::in(['id'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ]);

        foreach (['tag_id', 'per_page'] as $integerFilter) {
            if (isset($filters[$integerFilter])) {
                $filters[$integerFilter] = (int) $filters[$integerFilter];
            }
        }

        if (isset($filters['assigned_to']) && $filters['assigned_to'] !== 'self') {
            $filters['assigned_to'] = (int) $filters['assigned_to'];
        }

        return $filters;
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
