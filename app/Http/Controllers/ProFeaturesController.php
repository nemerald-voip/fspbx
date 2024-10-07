<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use App\Services\FreeswitchEslService;
use Illuminate\Pagination\LengthAwarePaginator;

class ProFeaturesController extends Controller
{

    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'ProFeatures';
    protected $searchable = ['cid_name', 'cid_num', 'dest', 'application_data', 'application', 'read_codec', 'write_codec', 'secure'];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(FreeswitchEslService $eslService)
    {

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () use ($eslService) {
                    return $this->getData($eslService);
                },
                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },

                'routes' => [
                    'current_page' => route('active-calls.index'),
                    'select_all' => route('active-calls.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    'action' => route('active-calls.action'),
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData(FreeswitchEslService $eslService, $paginate = 50)
    {
        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }


        $data = $this->builder($this->filters, $eslService);


        // Apply pagination manually
        if ($paginate) {
            $data = $this->paginateCollection($data, $paginate);
        }

        logger($data);



        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [], FreeswitchEslService $eslService)
    {
        $data = collect(
            [
                ['name' => 'Contact Center',
                'license' => '',
                'status' => ''
                ]
            ]
        );

        // get a list of current registrations
        // $data = $eslService->getAllChannels();

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


    public function handleAction()
    {
        try {
            foreach (request('ids') as $uuid) {
                if (request('action') == 'end_call') {
                    $result = $this->eslService->killChannel($uuid);
                }
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request has been succesfully processed']]
            ], 201);
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
            // Fetch all active calls without pagination
            $allCalls = $this->builder($this->filters);

            // Extract only the UUIDs from the collection
            $uuids = $allCalls->pluck('uuid');

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,  // Returning only the UUIDs
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
