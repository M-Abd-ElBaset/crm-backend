<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\DealStage;
use App\Http\Requests\DealRequest;
use App\Http\Resources\DealResource;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $stage = DealStage::tryFrom((string) $request->query('stage'));

        $deals = Deal::query()
            ->with('customer')
            ->when($stage, fn ($q) => $q->where('stage', $stage->value))
            ->latest()
            ->paginate(10);

        return DealResource::collection($deals);
    }

    public function store(DealRequest $request): JsonResponse
    {
        $deal = Deal::create($request->validated());

        return (new DealResource($deal->load('customer')))->response()->setStatusCode(201);
    }

    public function show(Deal $deal)
    {
        return new DealResource($deal->load('customer'));
    }

    public function update(DealRequest $request, Deal $deal)
    {
        $deal->update($request->validated());

        return new DealResource($deal->load('customer'));
    }

    public function destroy(Deal $deal): JsonResponse
    {
        $deal->delete();

        return response()->json(null, 204);
    }
}
