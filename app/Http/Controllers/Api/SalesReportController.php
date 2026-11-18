<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\SalesReportNarrativeService;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;

class SalesReportController extends Controller
{
    public function show(SalesReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->stats()]);
    }

    public function summary(SalesReportService $reports, SalesReportNarrativeService $narrative): JsonResponse
    {
        $stats = $reports->stats();

        return response()->json([
            'data' => [
                'stats' => $stats,
                'summary' => $narrative->narrate($stats),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}