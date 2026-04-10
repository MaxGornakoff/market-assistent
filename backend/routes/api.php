<?php

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\PricingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class);
Route::get('/dashboard-summary', [PricingController::class, 'summary']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
