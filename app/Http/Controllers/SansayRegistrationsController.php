<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Services\SansayApiService;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use App\Services\DeviceActionService;
use Illuminate\Pagination\LengthAwarePaginator;

class SansayRegistrationsController extends Controller
{

    public $sansayApiService;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'SansayRegistrations';
    protected $searchable = ['userDomain', 'states', 'userIp', 'username'];

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
    public function index()
    {

        return Inertia::render(
            $this->viewName,
            [

                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('sansay.registrations.index'),
                    'delete' => route('sansay.registrations.delete'),
                    'select_all' => route('sansay.registrations.select.all'),
                    // 'bulk_delete' => route('sansay.registrations.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    // 'action' => route('registrations.action'),
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData($paginate = 50)
    {
        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Check if showGlobal parameter is present and not empty
        if (!empty(request('filterData.showGlobal'))) {
            $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
        } else {
            $this->filters['showGlobal'] = null;
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'states'); // Default to 'created_at'
        $this->sortOrder = request()->get('sortOrder', 'desc'); // Default to descending

        $data = $this->builder($this->filters);

        // Apply pagination manually
        if ($paginate) {
            $data = $this->paginateCollection($data, $paginate);
        }

        // logger($data);

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        // Return an empty Collection if the request variable is empty
        if (empty(request('filterData.server'))) return collect();

        // get a list of current registrations
        $data = $this->sansayApiService->fetchStats(request('filterData.server'));

        // logger($data);

        // Apply sorting using sortBy or sortByDesc depending on the sort order
        if ($this->sortOrder === 'asc') {
            $data = $data->sortBy($this->sortField);
        } else {
            $data = $data->sortByDesc($this->sortField);
        }


        // Apply additional filters, if any
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    // Pass the collection by reference to modify it directly
                    $data = $this->$method($data, $value);
                }
            }
        }

        // logger($data);

        return $data->values(); // Ensure re-indexing of the collection
    }

    /**
     * Paginate a given collection.
     *
     * @param \Illuminate\Support\Collection $items
     * @param int $perPage
     * @param int|null $page
     * @param array $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateCollection($items, $perPage = 50, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page,
            $options
        );

        // Manually set the path to the current route with proper parameters
        $paginator->setPath(url()->current());

        return $paginator;
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
                if (stripos($item[$field], $value) !== false) {
                    return true;
                }
            }
            return false;
        });

        return $collection;
    }


    public function destroy()
    {
        try {
            // submit API request to delete selected records
            $data = $this->sansayApiService->deleteStats(request('filterData.server'), request('statsData'));

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request to delete was successfully sent']]
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
     * Get all item IDs without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectAll()
    {
        try {
            // Fetch all Sansay registrations without pagination
            $allRegistrations = $this->builder($this->filters);

            // Extract only the IDs from the collection
            $ids = $allRegistrations->pluck('id');

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $ids,  // Returning only the IDs
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
}
