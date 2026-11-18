<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\CustomerSummaryController;
use App\Http\Controllers\Api\SalesReportController;
use App\Http\Controllers\Api\DealAssistantController;


//5 login attempts per minute
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('deals', DealController::class);

    Route::post('customers/{customer}/summary', CustomerSummaryController::class)
        ->middleware('throttle:10,1');

    Route::get('reports/sales', [SalesReportController::class, 'show']);

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('reports/sales/summary', [SalesReportController::class, 'summary']);
        Route::post('assistant/deals/ask', DealAssistantController::class);
    });
});