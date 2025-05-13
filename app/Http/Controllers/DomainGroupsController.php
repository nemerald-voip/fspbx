<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\DomainGroups;
use Illuminate\Http\Request;
use App\Models\DomainGroupRelations;
use Illuminate\Support\Facades\Validator;

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

        logger($data);

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        $data->with(['domain_group_relations' => function ($query) {
            $query->select('uuid', 'domain_group_uuid', 'domain_uuid');
        }]);

        $data->with(['domain_group_relations.domain' => function ($query) {
            $query->select('domain_uuid', 'domain_name', 'domain_description');
        }]);
        
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
    public function store(Request $request, DomainGroups $domain_group)
    {
        $attributes = [
            // 'user_email' => 'email',
        ];

        $validator = Validator::make($request->all(), [
            'group_name' => 'required|string|max:100',
            'domains' => 'nullable',
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();

        $domain_group->fill($attributes);
        $saved = $domain_group->save();

        if (isset($attributes['domains'])) {
            foreach ($attributes['domains'] as $domain) {
                $domain_group_relation = new DomainGroupRelations();
                $domain_group_relation->domain_uuid = $domain;
                $domain_group->domain_group_relations()->save($domain_group_relation);
            }
        }

        if (!$saved) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'There was an error saving some records',
                ],
            ]);
        }

        return response()->json([
            'domain_group' => $domain_group->domain_group_uuid,
            'redirect_url' => route('domaingroups.edit', $domain_group),
            'status' => 200,
            'success' => [
                'message' => 'Domain Group has been saved'
            ]
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\DomainGroups  $domainGroups
     * @return \Illuminate\Http\Response
     */
    public function show(DomainGroups $domainGroups)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\DomainGroups  $domainGroups
     * @return \Illuminate\Http\Response
     */
    public function edit(DomainGroups $domaingroup)
    {

        // Check permissions
        if (!isSuperAdmin()) {
            return redirect('/');
        }

        //get all active domains
        $all_domains = Domain::where('domain_enabled', 'true')
            ->get();

        $data = array();
        $data['all_domains'] = $all_domains;

        $data['assigned_domains'] = collect();
        foreach ($domaingroup->domain_group_relations as $domain_relation) {
            $data['assigned_domains']->push($domain_relation->domain);
        }

        $data['domain_group'] = $domaingroup;

        return view('layouts.domains.groups.createOrUpdate')->with($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DomainGroups  $domainGroups
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, DomainGroups $domaingroup)
    {
        $attributes = [
            // 'user_email' => 'email',
        ];

        $validator = Validator::make($request->all(), [
            'group_name' => 'required|string|max:100',
            'domains' => 'nullable',
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();

        $saved = $domaingroup->update($attributes);

        // Update domain group relation table
        foreach ($domaingroup->domain_group_relations as $relation) {
            $relation->delete();
        }

        if (isset($attributes['domains'])) {
            foreach ($attributes['domains'] as $domain) {
                $domain_group_relation = new DomainGroupRelations();
                $domain_group_relation->domain_uuid = $domain;
                $domaingroup->domain_group_relations()->save($domain_group_relation);
            }
        }

        if (!$saved) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'There was an error saving some records',
                ],
            ]);
        }

        return response()->json([
            'domain_group' => $domaingroup->domain_group_uuid,
            'redirect_url' => route('domaingroups.index', $domaingroup),
            'status' => 200,
            'success' => [
                'message' => 'Domain Group has been saved'
            ]
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DomainGroups  $domainGroups
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $domain_group = DomainGroups::findOrFail($id);

        if (isset($domain_group)) {
            $deleted = $domain_group->delete();

            if ($deleted) {
                return response()->json([
                    'status' => 'success',
                    'id' => $id,
                    'message' => 'Selected domain groups have been deleted'
                ]);
            } else {
                return response()->json([
                    'error' => 401,
                    'message' => 'There was an error deleting this domain group'
                ]);
            }
        }
    }
}
