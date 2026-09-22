<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Services\SansayApiService;
use Illuminate\Http\Request;

class SansayActiveCallsController extends Controller
{

    public $sansayApiService;
    protected $viewName = 'SansayActiveCalls';
    protected $searchable = ['orig_ip', 'dnis', 'ani', 'term_ip'];

    public function __construct(SansayApiService $sansayApiService)
    {
        // $this->model = new Messages();
        $this->sansayApiService = $sansayApiService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        return Inertia::render(
            $this->viewName,
            [

                'pagination' => [
                    'per_page' => fspbx_pagination_per_page($request),
                    'per_page_options' => fspbx_pagination_options(),
                ],
                'routes' => [
                    'data_route' => route('sansay.active-calls.data'),
                    'delete' => route('sansay.active-calls.delete'),
                    'select_all' => route('sansay.active-calls.select.all'),
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData(Request $request)
    {
        $data = $this->builder($request->input('filter', []));
        $perPage = fspbx_pagination_per_page($request);
        $lastPage = max(1, (int) ceil($data->count() / $perPage));
        $page = min(max(1, $request->integer('page', 1)), $lastPage);

        return fspbx_paginate_collection($data, $perPage, $page);
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        if (empty($filters['server'])) return collect();

        $data = $this->sansayApiService->fetchActiveCalls($filters['server'])->sortBy('duration');

        // Format duration in human-readable form (HH:MM:SS)
        $data = $data->map(function ($item) {
            // Check if duration exists in the item
            if (isset($item['duration'])) {
                $item['duration_formatted'] = $this->formatDuration($item['duration']);
            }
            return $item;
        });


        if (isset($filters['search']) && $filters['search'] !== '') {
            $data = $this->filterSearch($data, (string) $filters['search']);
        }

        // logger($data);

        return $data->values(); // Ensure re-indexing of the collection
    }

    /**
     * @param $collection
     * @param $value
     * @return void
     */
    protected function filterSearch($collection, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $collection = $collection->filter(function ($item) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (stripos((string) ($item[$field] ?? ''), $value) !== false) {
                    return true;
                }
            }
            return false;
        });

        return $collection;
    }


    public function destroy(Request $request)
    {
        try {
            // submit API request to delete selected records
            $this->sansayApiService->deleteActiveCalls($request->input('filter.server'), $request->input('callsData'));

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => [__('Request to delete was successfully sent')]]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Get all active call IDs without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectAll(Request $request)
    {
        try {
            // Fetch all active calls without pagination
            $allActiveCalls = $this->builder($request->input('filter', []));

            // Extract only the IDs from the collection
            $ids = $allActiveCalls->pluck('callID');

            return response()->json([
                'messages' => ['success' => [__('All items selected')]],
                'items' => $ids,  // Returning only the IDs
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to select all items')]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Format seconds into human-readable time (HH:MM:SS)
     */
    protected function formatDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
