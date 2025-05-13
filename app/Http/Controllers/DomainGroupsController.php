<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\DomainGroups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DomainGroupRelations;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\CreateDomainGroupRequest;
use App\Http\Requests\UpdateDomainGroupRequest;

class DomainGroupsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'DomainGroups';
    protected $searchable = ['group_name'];

    public function __construct()
    {
        $this->model = new DomainGroups();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("domain_groups_list_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('domain-groups.index'),
                    'item_options' => route('domain-groups.item.options'),
                    'bulk_delete' => route('domain-groups.bulk.delete'),
                    'select_all' => route('domain-groups.select.all'),
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
        // $data->with(['domain_group_relations' => function ($query) {
        //     $query->select('uuid', 'domain_group_uuid', 'domain_uuid');
        // }]);

        // $data->with(['domain_group_relations.domain' => function ($query) {
        //     $query->select('domain_uuid', 'domain_name', 'domain_description');
        // }]);

        $data->select(
            'domain_group_uuid',
            'group_name',
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
                    ->with(['domain_group_relations' => function ($query) {
                        $query->select('uuid', 'domain_group_uuid', 'domain_uuid');
                    }])
                    ->first();



                // If a model exists, use it; otherwise, create a new one
                if (!$item) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }


                // Define the update route
                $updateRoute = route('domain-groups.update', ['domain_group' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $item = $this->model;

                $storeRoute  = route('domain-groups.store');
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
                ->toArray();



            $routes = [
                'store_route' => $storeRoute ?? null,
                'update_route' => $updateRoute ?? null,
            ];

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $item,
                'routes' => $routes,
                'domains' => $domains,
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
     * Store a newly created Domain Group in storage.
     *
     * @param  \App\Http\Requests\StoreDomainGroupRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateDomainGroupRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            // 1) create the domain group
            $domainGroup = DomainGroups::create([
                'group_name' => $data['group_name'],
            ]);

            // 2) attach initial members
            $members = $data['members'] ?? [];
            foreach ($members as $domainUuid) {
                DomainGroupRelations::create([
                    'domain_group_uuid' => $domainGroup->domain_group_uuid,
                    'domain_uuid'       => $domainUuid,
                ]);
            }

            DB::commit();

            return response()->json([
                'messages'           => ['success' => ['Domain group created successfully.']],
                'domain_group_uuid'  => $domainGroup->domain_group_uuid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'DomainGroups store error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile()
                    . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while creating the domain group.']]
            ], 500);
        }
    }



    /**
     * Update the specified Domain Group in storage.
     *
     * @param  \App\Http\Requests\UpdateDomainGroupRequest  $request
     * @param  \App\Models\DomainGroups                     $domain_group
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateDomainGroupRequest $request, DomainGroups $domain_group)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            // 1) update the group name
            $domain_group->update([
                'group_name' => $data['group_name'],
            ]);

            // 2) sync the members
            $members = $data['members'] ?? [];

            // delete any existing relations
            $domain_group->domain_group_relations()->delete();

            // re-create relations for each selected domain
            foreach ($members as $domainUuid) {
                DomainGroupRelations::create([
                    'domain_group_uuid' => $domain_group->domain_group_uuid,
                    'domain_uuid'       => $domainUuid,
                ]);
            }

            DB::commit();

            return response()->json([
                'messages'           => ['success' => ['Domain group updated successfully.']],
                'domain_group_uuid'  => $domain_group->domain_group_uuid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'DomainGroups update error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile()
                    . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating the domain group.']]
            ], 500);
        }
    }


/**
 * Remove the specified Domain Groups from storage.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\JsonResponse
 */
public function bulkDelete(Request $request)
{
    // if (! userCheckPermission('domain_group_delete')) {
    //     return response()->json([
    //         'messages' => ['error' => ['Access denied.']]
    //     ], 403);
    // }


    $uuids = $request->input('items');

    try {
        DB::beginTransaction();

        // 1) remove any related relations
        DomainGroupRelations::whereIn('domain_group_uuid', $uuids)->delete();

        // 2) delete the domain groups themselves
        DomainGroups::whereIn('domain_group_uuid', $uuids)->delete();

        DB::commit();

        return response()->json([
            'messages' => ['success' => ['Selected domain group(s) were deleted successfully.']]
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        logger('DomainGroups bulkDelete error: '
            . $e->getMessage()
            . ' at ' . $e->getFile() . ':' . $e->getLine()
        );

        return response()->json([
            'messages' => ['error' => ['An error occurred while deleting the selected domain group(s).']]
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
