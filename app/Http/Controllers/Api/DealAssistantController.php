<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AskDealAssistantRequest;
use App\Services\Ai\DealAssistantService;
use Illuminate\Http\JsonResponse;

class DealAssistantController extends Controller
{
    public function __invoke(AskDealAssistantRequest $request, DealAssistantService $assistant): JsonResponse
    {
        $result = $assistant->ask($request->validated('question'));

        return response()->json([
            'data' => $result + ['generated_at' => now()->toIso8601String()],
        ]);
    }
}