<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Groups;
use Illuminate\Http\Request;

class GroupsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Groups';
    protected $searchable = ['group_name', 'group_description'];

    public function __construct()
    {
        $this->model = new Groups();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("group_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('groups.index'),
                    'item_options' => route('groups.item.options'),
                    'bulk_delete' => route('groups.bulk.delete'),
                    'select_all' => route('groups.select.all'),
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
        $this->sortField = request()->get('sortField', 'group_name');
        $this->sortOrder = request()->get('sortOrder', 'asc');

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
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
        $data =  $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data
            ->where($this->model->getTable() . '.domain_uuid', $domainUuid)
            ->orWhereNull($this->model->getTable() . '.domain_uuid');
        // $data->with(['destinations' => function ($query) {
        //     $query->select('ring_group_destination_uuid', 'ring_group_uuid', 'destination_number');
        // }]);

        $data->select(
            'group_uuid',
            'group_name',
            'group_protected',
            'group_level',
            'group_description'
        );

        $data->withCount(['permissions']);
        $data->withCount(['user_groups']);

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
                if (strpos($field, '.') !== false) {
                    // Nested field (e.g., 'extension.name_formatted')
                    [$relation, $nestedField] = explode('.', $field, 2);

                    $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                        $query->where($nestedField, 'ilike', '%' . $value . '%');
                    });
                } else {
                    // Direct field
                    $query->orWhere($field, 'ilike', '%' . $value . '%');
                }
            }
        });
    }


    public function getItemOptions()
    {
        try {

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request


            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing item by item_uuid
                $item = $this->model::where($this->model->getKeyName(), $item_uuid)
                    ->first();

                // If a model exists, use it; otherwise, create a new one
                if (!$item) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }


                // Define the update route
                $updateRoute = route('groups.update', ['ring_group' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $item = $this->model;

                $storeRoute  = route('groups.store');
            }

            // $permissions = $this->getUserPermissions();

            $routes = [
                'store_route' => $storeRoute ?? null,
                'update_route' => $updateRoute ?? null,
            ];

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $item,
                'routes' => $routes,
                // Define options for other fields as needed
            ];
            // logger($itemOptions);

            return $itemOptions;
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $group = Groups::findOrFail($id);

        if (isset($group)) {
            if ($group->permissions->isNotEmpty()) {
                $deleted = $group->permissions()->delete();
            }
            $deleted = $group->delete();

            if ($deleted) {
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected groups have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected groups'
                    ]
                ]);
            }
        }
    }
}
