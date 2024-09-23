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
    protected $searchable = ['agent','userDomain', 'states', 'trunkId', 'userPort', 'protocol', 'userIp', 'id', 'username'];

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
                'data' => [
                    'data' => [], // Empty dataset
                    'prev_page_url' => null,
                    'next_page_url' => null,
                    'from' => 0,
                    'to' => 0,
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                    'links' => [], // Pagination links, can be empty for now
                ],

                'routes' => [
                    'current_page' => route('sansay.registrations.index'),
                    'data' => route('sansay.registrations.data'),
                    // 'select_all' => route('registrations.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
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

        // get a list of current registrations
        $data = $this->sansayApiService->fetchStats(request('server'));

        // logger($data);

        // Apply sorting using sortBy or sortByDesc depending on the sort order
        if ($this->sortOrder === 'asc') {
            $data = $data->sortBy($this->sortField);
        } else {
            $data = $data->sortByDesc($this->sortField);
        }

        // logger($filters);

        // Check if showGlobal is set to true, otherwise filter by sip_auth_realm
        // if (empty($filters['showGlobal']) || $filters['showGlobal'] !== true) {
        //     $domainName = session('domain_name');

        //     $data = $data->filter(function ($item) use ($domainName) {
        //         return $item['sip_auth_realm'] === $domainName;
        //     });
        // }

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


    public function handleAction(DeviceActionService $deviceActionService)
    {
        try {
            foreach (request('regs') as $reg) {
                $deviceActionService->handleDeviceAction($reg, request('action'));
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
}
