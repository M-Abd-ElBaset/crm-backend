<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Ai\CustomerSummaryService;
use Illuminate\Http\JsonResponse;

class CustomerSummaryController extends Controller
{
    public function __invoke(Customer $customer, CustomerSummaryService $service): JsonResponse
    {
        return response()->json([
            'data' => [
                'customer_id' => $customer->id,
                'summary' => $service->summarize($customer),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}