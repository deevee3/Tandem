<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService
    ) {
    }

    /**
     * Get analytics dashboard data
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = isset($validated['start_date']) 
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->subDays(30)->startOfDay();

        $endDate = isset($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : now()->endOfDay();

        $metrics = $this->analyticsService->getDashboardMetrics($startDate, $endDate);

        return response()->json([
            'data' => $metrics,
            'meta' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }
}
