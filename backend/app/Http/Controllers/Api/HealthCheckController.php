<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'market-assistant-api',
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
        ]);
    }
}
