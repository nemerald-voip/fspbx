<?php

namespace App\Http\Controllers;

use App\Services\CallRoutingOptionsService;
use Illuminate\Http\JsonResponse;

class CallRoutingOptionsController extends Controller
{
    public function getRoutingOptions(CallRoutingOptionsService $service): JsonResponse
    {

        try {
            $options = $service->getOptions();
            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'options' => $options,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch routing options']]
            ], 500); // 500 Internal Server Error for any other errors
        }

    }
}
