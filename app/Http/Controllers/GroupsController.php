<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Groups;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\CreatePermissionGroupRequest;
use App\Http\Requests\UpdatePermissionGroupRequest;

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
                $updateRoute = route('groups.update', ['group' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $item = $this->model;

                $storeRoute  = route('groups.store');
            }

            // $permissions = $this->getUserPermissions();

            $domains = Domain::where('domain_enabled', 'true')
                ->select('domain_uuid', 'domain_name', 'domain_description')
                ->orderBy('domain_description')
                ->get()
                ->map(function ($domain) {
                    return [
                        'value' => $domain->domain_uuid,
                        'label' => $domain->domain_description,
                    ];
                })
                ->prepend([
                    'value' => '',
                    'label' => 'Global',
                ])
                ->toArray();

            $group_levels = [];
            for ($i = 10; $i <= 70; $i += 10) {
                $group_levels[] = [
                    'value' => (string)$i,
                    'label' => (string)$i,
                ];
            }


            $routes = [
                'store_route' => $storeRoute ?? null,
                'update_route' => $updateRoute ?? null,
            ];

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $item,
                'routes' => $routes,
                'domains' => $domains,
                'group_levels' => $group_levels,
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
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreatePermissionGroupRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $groupManager = Groups::create(array_merge($validated, [
                'domain_uuid' => session('domain_uuid'),
                'group_uuid'  => Str::uuid(),
            ]));

            DB::commit();

            return response()->json([
                'messages'   => ['success' => ['Group created']],
                'group_uuid' => $groupManager->group_uuid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'GroupManager store error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while creating the group.']]
            ], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdatePermissionGroupRequest  $request
     * @param  \App\Models\Groups                     $group
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePermissionGroupRequest $request, Groups $group)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $group->update($data);

            DB::commit();

            return response()->json([
                'messages'      => ['success' => ['Group updated']],
                'group_uuid'    => $group->group_uuid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'GroupManager update error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile()
                    . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating the group.']]
            ], 500);
        }
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


    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        if (!userCheckPermission('group_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            $uuids = $request->input('items');

            // delete all matching groups in one query
            Groups::whereIn('group_uuid', $uuids)->delete();

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected group(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('RingGroups bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected group(s).']]
            ], 500);
        }
    }

    public function selectAll()
    {
        try {
            $domainUuid = session('domain_uuid');
            $uuids = $this->model::where($this->model->getTable() . '.domain_uuid', $domainUuid)
                ->orWhereNull($this->model->getTable() . '.domain_uuid')
                ->get($this->model->getKeyName())->pluck($this->model->getKeyName());



            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
}
