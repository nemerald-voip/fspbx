<?php 

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function ok($data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        // Check if $data is a Laravel Paginator
        if ($data instanceof LengthAwarePaginator) {
            
            // Extract the standard pagination metadata
            $pagination = [
                'total'        => $data->total(),
                'per_page'     => $data->perPage(),
                'current_page' => $data->currentPage(),
                'total_pages'  => $data->lastPage(),
            ];

            // Merge with any existing meta passed to the function
            $meta['pagination'] = $pagination;

            // IMPORTANT: Overwrite $data with just the items (removing the wrapper)
            $data = $data->items();
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => (object) $meta,
        ], $status);
    }

    public static function error(string $message, string $code, $details = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => [
                'code'    => $code,
                'details' => $details,
            ],
        ], $status);
    }
}