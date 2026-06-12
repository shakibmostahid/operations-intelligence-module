<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AlertRoutingService;
use App\Services\DailyOperationsSummaryService;
use App\Services\DashboardService;
use App\Services\IncidentService;
use App\Services\SystemHealthService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly IncidentService $incidentService,
        private readonly AlertRoutingService $alertRoutingService,
        private readonly SystemHealthService $systemHealthService,
        private readonly DailyOperationsSummaryService $dailyOperationsSummaryService,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('role');
        $timeframe = $request->string('timeframe', '30')->toString();
        $allowedTimeframes = ['today', '7', '30', '90', 'all', 'custom'];

        if (! in_array($timeframe, $allowedTimeframes, true)) {
            $timeframe = '30';
        }

        [$from, $to] = $this->dateRange($request, $timeframe);
        $trendTo = CarbonImmutable::today()->endOfDay();
        $trendFrom = $trendTo->subDays(29)->startOfDay();
        $this->alertRoutingService->routeCurrentSlaBreaches();

        return view('app', [
            'page' => 'dashboard',
            'props' => [
                'user' => $this->authenticatedUser($user),
                'chartAnalytics' => [
                    ...$this->dashboardService->analytics($from, $to),
                    'trend' => [],
                ],
                'dashboardTrend' => $this->dashboardService->incidentTrend($trendFrom, $trendTo),
                'alerts' => $this->alertRoutingService->alertsFor($user),
                'systemHealth' => $this->systemHealthService->dashboard(),
                'selfAssignedIncidents' => $this->incidentService->selfAssignedIncidents($user),
                'slaBreaches' => $this->incidentService->unresolvedBreaches(),
                'timeframe' => $timeframe,
                'dateFrom' => $from?->toDateString(),
                'dateTo' => $to?->toDateString(),
                'success' => session('success'),
            ],
        ]);
    }

    public function slaBreaches(Request $request): JsonResponse
    {
        $request->validate([
            'sla_page' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->incidentService->unresolvedBreaches());
    }

    public function analytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timeframe' => ['required', 'in:today,7,30,90,all,custom'],
            'from' => ['nullable', 'required_if:timeframe,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:timeframe,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $timeframe = $validated['timeframe'];
        [$from, $to] = $this->dateRange($request, $timeframe);

        return response()->json([
            'analytics' => $this->dashboardService->analytics($from, $to),
            'timeframe' => $timeframe,
            'dateFrom' => $from?->toDateString(),
            'dateTo' => $to?->toDateString(),
        ]);
    }

    public function trend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timeframe' => ['required', 'in:today,7,30,90,all'],
        ]);
        $timeframe = $validated['timeframe'];
        [$from, $to] = $this->dateRange($request, $timeframe);

        return response()->json([
            'trend' => $this->dashboardService->incidentTrend($from, $to),
            'timeframe' => $timeframe,
        ]);
    }

    public function dailySummary(): JsonResponse
    {
        return response()->json($this->dailyOperationsSummaryService->generate());
    }

    public function systemHealth(): JsonResponse
    {
        return response()->json($this->systemHealthService->dashboard());
    }

    /**
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    private function dateRange(Request $request, string &$timeframe): array
    {
        $today = CarbonImmutable::today();

        if ($timeframe === 'all') {
            return [null, null];
        }

        if ($timeframe === 'custom') {
            try {
                $from = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('from')->toString());
                $to = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('to')->toString());

                if ($from !== false && $to !== false && $from->lte($to)) {
                    return [$from->startOfDay(), $to->endOfDay()];
                }
            } catch (Throwable) {
                // Fall through to the default range.
            }

            $timeframe = '30';
        }

        $days = match ($timeframe) {
            'today' => 1,
            '7' => 7,
            '90' => 90,
            default => 30,
        };

        return [
            $today->subDays($days - 1)->startOfDay(),
            $today->endOfDay(),
        ];
    }

    private function authenticatedUser(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->role,
        ];
    }
}
