<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardService;
use App\Services\IncidentService;
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

        return view('app', [
            'page' => 'dashboard',
            'props' => [
                'user' => $this->authenticatedUser($user),
                'analytics' => $this->dashboardService->analytics($from, $to),
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
