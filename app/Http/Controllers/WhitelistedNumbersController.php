<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\JsonResponse;
use App\Models\WhitelistedNumbers;
use App\Http\Requests\StoreWhitelistNumberRequest;

class WhitelistedNumbersController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'WhitelistedNumbers';
    protected $searchable = ['number', 'description'];

    public function __construct()
    {
        $this->model = new WhitelistedNumbers();
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
                    'current_page' => route('whitelisted-numbers.index'),
                    'store' => route('whitelisted-numbers.store'),
                    'select_all' => route('whitelisted-numbers.select.all'),
                    'bulk_delete' => route('whitelisted-numbers.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    // 'retry' => route('messages.retry'),
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

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'number'); // Default to 'number'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to ascending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);

        $data->select(
            'uuid',
            'domain_uuid',
            'number',
            'description',
            'created_at'

        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
        });
    }

    public function destroy(WhitelistedNumbers $whitelisted_number)
    {

        try {

            $whitelisted_number->delete();

            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {

            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWhitelistNumberRequest  $request
     * @return JsonResponse
     */
    public function store(StoreWhitelistNumberRequest $request)
    {
        try {
            $inputs = $request->validated();
            $this->model->fill($inputs);

            // Save the model instance to the database
            $this->model->save();

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Get all items
     *
     * @return \Illuminate\Http\Response
     */
    public function selectAll()
    {
        try {

            $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                ->get($this->model->getKeyName())->pluck($this->model->getKeyName());

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to select all items']]
        ], 500); // 500 Internal Server Error for any other errors
    }


    /**
     * Remove the specified resources from storage.
     *
     * 
     */
    public function BulkDelete()
    {
        try {
            // Ensure 'items' parameter exists in the request
            $items = request('items');

            if (!is_array($items) || empty($items)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['No items provided for deletion.']]
                ], 422); // 422 Unprocessable Entity
            }

            // Perform the bulk deletion
            $deletedCount = $this->model::whereIn('uuid', $items)
                ->where('domain_uuid', session('domain_uuid'))
                ->delete();

            if ($deletedCount === 0) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['No items were deleted.']]
                ], 404); // 404 Not Found
            }

            return response()->json([
                'success' => true,
                'messages' => ['server' => ['All selected items have been deleted successfully.']],
                'deleted_count' => $deletedCount,
            ], 200);
        } catch (\Exception $e) {

            // Log the error message
            logger($e->getMessage());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
}
