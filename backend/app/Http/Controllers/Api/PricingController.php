<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pricing\PricingService;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService,
    ) {
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->pricingService->getDashboardSummary());
    }
}
