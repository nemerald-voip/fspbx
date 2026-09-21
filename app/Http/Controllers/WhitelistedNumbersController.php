<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\WhitelistedNumbers;
use App\Http\Requests\StoreWhitelistNumberRequest;

class WhitelistedNumbersController extends Controller
{
    public $model;
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
                    'data_route' => route('whitelisted-numbers.data'),
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
    public function getData(Request $request)
    {
        return $this->builder(
            ['search' => $request->input('filter.search')],
            (string) $request->input('sort', 'number')
        )->paginate(fspbx_pagination_per_page($request));
    }

    public function builder(array $filters = [], string $sort = 'number')
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
                if ($value === null || $value === '') {
                    continue;
                }
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $sortField = ltrim($sort, '-');
        $sortOrder = str_starts_with($sort, '-') ? 'desc' : 'asc';
        if (! in_array($sortField, ['number', 'description', 'created_at'], true)) {
            $sortField = 'number';
            $sortOrder = 'asc';
        }
        $data->orderBy($sortField, $sortOrder)->orderBy('uuid');

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
        abort_unless($whitelisted_number->domain_uuid === session('domain_uuid'), 404);

        try {

            $whitelisted_number->delete();

            return response()->json(['messages' => ['success' => [__('Item deleted')]]]);
        } catch (\Exception $e) {

            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json(['errors' => ['server' => [__('Server returned an error while deleting this item')]]], 500);
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
                'messages' => ['success' => [__('New item created')]]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to create new item')]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Get all items
     *
     * @return \Illuminate\Http\Response
     */
    public function selectAll(Request $request)
    {
        return response()->json([
            'messages' => ['success' => [__('All items selected')]],
            'items' => $this->builder(['search' => $request->input('filter.search')])->pluck('uuid'),
        ]);
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
                    'errors' => ['server' => [__('No items provided for deletion.')]]
                ], 422); // 422 Unprocessable Entity
            }

            // Perform the bulk deletion
            $deletedCount = $this->model::whereIn('uuid', $items)
                ->where('domain_uuid', session('domain_uuid'))
                ->delete();

            if ($deletedCount === 0) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => [__('No items were deleted.')]]
                ], 404); // 404 Not Found
            }

            return response()->json([
                'success' => true,
                'messages' => ['server' => [__('All selected items have been deleted successfully.')]],
                'deleted_count' => $deletedCount,
            ], 200);
        } catch (\Exception $e) {

            // Log the error message
            logger($e->getMessage());
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Server returned an error while deleting the selected items.')]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
}
