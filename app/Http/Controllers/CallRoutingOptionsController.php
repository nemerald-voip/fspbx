<?php

namespace App\Http\Controllers;

use App\Services\CallRoutingOptionsService;
use Illuminate\Http\JsonResponse;

class CallRoutingOptionsController extends Controller
{
    public function getRoutingOptions(CallRoutingOptionsService $service): JsonResponse
    {
        return response()->json($service->routingTypes);
    }
}
