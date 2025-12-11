<?php

namespace App\Http\Controllers;

use Throwable;
use Inertia\Inertia;
use App\Models\Faxes;
use Inertia\Response;
use App\Models\Dialplans;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use App\Models\Destinations;
use Illuminate\Http\Request;
use App\Models\DialplanDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use App\Imports\PhoneNumbersImport;
use App\Exports\PhoneNumberTemplate;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\HeadingRowImport;
use Illuminate\Database\Eloquent\Builder;
use App\Services\CallRoutingOptionsService;
use Maatwebsite\Excel\Excel as ExcelWriter;
use App\Http\Requests\StorePhoneNumberRequest;
use App\Http\Requests\UpdatePhoneNumberRequest;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\BulkUpdatePhoneNumberRequest;
use App\Services\DialplanBuilderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Traits\ChecksLimits;
use App\Exports\PhoneNumbersExport;

class PhoneNumbersController extends Controller
{
    use ChecksLimits;

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'PhoneNumbers';
    protected $searchable = ['destination_number', 'destination_data', 'destination_description'];

    public function export()
    {
        if (! userCheckPermission('destination_export')) {
            abort(403);
        }
        return Excel::download(new PhoneNumbersExport, 'phone_numbers.csv', ExcelWriter::CSV);
    }

    public function __construct()
    {
        $this->model = new Destinations();
    }

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return Redirector|Response|RedirectResponse|Application
     */
    public function index(Request $request)
    {
        if (!userCheckPermission("destination_view")) {
            return redirect('/');
        }

        $perPage = 50;
        $currentDomain = session('domain_uuid');

        // If the filter is not present, assign default value before QueryBuilder
        if (!$request->has('filter.showGlobal')) {
            $request->merge([
                'filter' => array_merge(
                    $request->input('filter', []),
                    ['showGlobal' => false]
                ),
            ]);
        }

        $phone_numbers = QueryBuilder::for(Destinations::class)
            ->select([
                'destination_uuid',
                'destination_number',
                'destination_prefix',
                'destination_actions',
                'destination_enabled',
                'destination_description',
                'domain_uuid',
            ])
            // allow ?filter[username]=foo or ?filter[user_email]=bar
            ->allowedFilters([
                // Only email and name_formatted
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('destination_number', 'ilike', "%{$value}%")
                            ->orWhere('destination_description', 'ilike', "%{$value}%")
                            ->orWhere('destination_actions', 'ilike', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) use ($currentDomain) {
                    // If showGlobal is falsey (0, '0', false, null), restrict to the current domain
                    if (!$value || $value === '0' || $value === 0 || $value === false) {
                        $query->where('domain_uuid', $currentDomain);
                    }
                    // else, do nothing and show all domains
                }),
            ])

            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])

            ->allowedSorts(['destination_number'])
            ->defaultSort('destination_number')
            ->paginate($perPage);


        return Inertia::render(
            $this->viewName,
            [
                'data' => $phone_numbers,

                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },

                'routes' => [
                    'current_page' => route('phone-numbers.index'),
                    'store' => route('phone-numbers.store'),
                    'select_all' => route('phone-numbers.select.all'),
                    'bulk_update' => route('phone-numbers.bulk.update'),
                    'bulk_delete' => route('phone-numbers.bulk.delete'),
                    'item_options' => route('phone-numbers.item.options'),
                    'download_template' => route('phone-numbers.template.download'),
                    'import' => route('phone-numbers.import'),
                    'export' => route('phone-numbers.export'),
                ],
                'permissions' => [
                    'view_global' => userCheckPermission('destination_all'),
                    'create' => userCheckPermission('destination_add'),
                    'update' => userCheckPermission('destination_edit'),
                    'destroy' => userCheckPermission('destination_delete'),
                    'upload' => userCheckPermission('destination_upload'),

                ],

            ]
        );
    }


    public function getItemOptions()
    {
        try {

            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request


            // Check for limits
            if (!$item_uuid) {
                if ($resp = $this->enforceLimit('destinations', \App\Models\Destinations::class)) {
                    return $resp;
                }
            }

            $routingOptionsService = new CallRoutingOptionsService;
            $routingTypes = $routingOptionsService->routingTypes;

            $faxes = [];
            $faxesCollection = Faxes::query();
            $faxesCollection->where('domain_uuid', Session::get('domain_uuid'));
            $faxesCollection = $faxesCollection->orderBy('fax_name')->get([
                'fax_extension',
                'fax_name',
                'fax_uuid'
            ]);
            foreach ($faxesCollection as $fax) {
                $faxes[] = [
                    'label' => $fax->fax_extension . ' ' . $fax->fax_name,
                    'value' => $fax->fax_uuid
                ];
            }

            $domains = [];
            $domainsCollection = Session::get("domains");
            if ($domainsCollection) {
                foreach ($domainsCollection as $domain) {
                    $domains[] = [
                        'value' => $domain->domain_uuid,
                        'label' => $domain->domain_description
                    ];
                }
            }

            // Check if item_uuid exists to find an existing voicemail
            if ($item_uuid) {
                // Find existing item by item_uuid
                // $phoneNumber = $this->model::where($this->model->getKeyName(), $item_uuid)->first();

                $phoneNumber = QueryBuilder::for($this->model)
                    ->select([
                        'domain_uuid',
                        'destination_uuid',
                        'fax_uuid',
                        'destination_number',
                        'destination_prefix',
                        'destination_record',
                        'destination_distinctive_ring',
                        'destination_cid_name_prefix',
                        'destination_accountcode',
                        'destination_actions',
                        'destination_enabled',
                        'destination_description',
                        'destination_type_fax',
                        'destination_hold_music',
                    ])
                    ->whereKey($item_uuid)
                    ->firstOrFail();

                // logger($phoneNumber);

                // If a voicemail exists, use it; otherwise, create a new one
                if (!$phoneNumber) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Define the update route
                $updateRoute = route('phone-numbers.update', ['phone_number' => $item_uuid]);
            } else {
                // Create a new voicemail if item_uuid is not provided
                $phoneNumber = $this->model;
            }

            $permissions = $this->getUserPermissions();

            $music_on_hold_options = getMusicOnHoldCollection(session('domain_uuid'));

            $routes = [
                'store_route' => route('phone-numbers.store'),
                'update_route' => $updateRoute ?? null,
                'get_routing_options' => route('routing.options'),
                'bulk_update_route' => route('phone-numbers.bulk.update'),
            ];


            // Construct the itemOptions object
            $itemOptions = [
                'item' => $phoneNumber,
                'permissions' => $permissions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'faxes' => $faxes,
                'domains' => $domains,
                'music_on_hold_options' => $music_on_hold_options ?? null,
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
     * @return LengthAwarePaginator
     */
    public function getData($paginate = 50): LengthAwarePaginator
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
        $this->sortField = request()->get('sortField', 'destination_number'); // Default to 'destination'
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
    public function builder(array $filters = []): Builder
    {
        $data =  $this->model::query();

        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter devices by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter devices by the session's domain_uuid
            $domainUuid = Session::get('domain_uuid');
            $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        }

        $data->select(
            'destination_uuid',
            'destination_number',
            'destination_prefix',
            'destination_actions',
            'destination_enabled',
            'destination_description',
            'domain_uuid',
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

    /**
     * Import the specified resource
     *
     * @return \Illuminate\Http\Response
     */
    public function import()
    {
        if (! userCheckPermission('destination_import')) {
            abort(403);

            try {

                $file = request()->file('file');
                // $domain_uuid = session('domain_uuid');

                // 1. Count how many rows will be imported
                // $rows = Excel::toCollection(new PhoneNumbersImport, $file)->first(); // Get first sheet
                // $importCount = $rows->count();

                // 2. Check current count and limit
                // $currentCount = \App\Models\Extensions::where('domain_uuid', $domain_uuid)->count();
                // $maxLimit = get_limit_setting('extensions', $domain_uuid);

                // if ($maxLimit !== null && ($currentCount + $importCount) > $maxLimit) {
                //     return response()->json([
                //         'success' => false,
                //         'errors' => [
                //             'extension' => [
                //                 "Importing this file would exceed your extension limit of $maxLimit. " .
                //                     "You currently have $currentCount extensions and are trying to import $importCount."
                //             ]
                //         ]
                //     ], 422);
                // }

                $headings = (new HeadingRowImport)->toArray(request()->file('file'));

                $import = new PhoneNumbersImport;
                $import->import($file);

                if ($import->failures()->isNotEmpty()) {

                    // Transform each failure into a readable error message
                    $errors = [];
                    foreach ($import->failures() as $failure) {
                        $row = $failure->row(); // Row number
                        $attr = $failure->attribute(); // Column/field name
                        $errList = $failure->errors(); // Array of error messages

                        foreach ($errList as $errMsg) {
                            $errors[] = "Row {$row}, '{$attr}': {$errMsg}";
                        }
                    }

                    return response()->json([
                        'success' => false,
                        'errors' => ['server' => $errors]
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'messages' => ['success' => ['Phone numbers have been successfully imported.']]
                ], 200);
            } catch (Throwable $e) {
                logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                // Send response in format that Dropzone understands
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => [$e->getMessage()]]
                ], 500);
            }
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StorePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function store(StorePhoneNumberRequest $request): JsonResponse
    {

        // Enforce limit BEFORE creating new phone number
        if ($resp = $this->enforceLimit('destinations', \App\Models\Destinations::class)) {
            return $resp;
        }

        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());

            // Process routing_options to form destination_actions
            $destination_actions = [];
            if (!empty($inputs['routing_options'])) {
                foreach ($inputs['routing_options'] as $option) {
                    $destination_actions[] = buildDestinationAction($option);
                }
            }

            // Assign the formatted actions to the destination_actions field
            $inputs['destination_actions'] = json_encode($destination_actions);

            $instance = $this->model;
            $instance->fill([
                'destination_uuid' => Str::uuid(),
                'domain_uuid' => $inputs['domain_uuid'],
                'dialplan_uuid' => Str::uuid(),
                'fax_uuid' => $inputs['fax_uuid'] ?? null,
                'destination_type' => 'inbound',
                'destination_prefix' => $inputs['destination_prefix'],
                'destination_number' => $inputs['destination_number'],
                'destination_actions' => $inputs['destination_actions'],
                // 'destination_conditions' => $inputs['destination_conditions'],
                'destination_hold_music' => $inputs['destination_hold_music'] ?? null,
                'destination_description' => $inputs['destination_description'] ?? null,
                'destination_enabled' => $inputs['destination_enabled'] ?? true,
                'destination_record' => $inputs['destination_record'] ?? false,
                'destination_type_fax' => $inputs['destination_type_fax'] ?? false,
                'destination_cid_name_prefix' => $inputs['destination_cid_name_prefix'] ?? null,
                'destination_accountcode' => $inputs['destination_accountcode'] ?? null,
                'destination_distinctive_ring' => $inputs['destination_distinctive_ring'] ?? null,
                'destination_context' => $inputs['destination_context'] ?? 'public',
            ]);
            $instance->save();

            // $this->generateDialPlanXML($instance);
            // Generate dialplan
            dispatch(new \App\Jobs\BuildDialplanForPhoneNumber($instance->destination_uuid, session('domain_name')));

            return response()->json([
                'messages' => ['success' => ['New phone number succesfully created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item'], 'ss' => $e->getMessage()]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Bulk update requested items
     *
     * @param  BulkUpdatePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function bulkUpdate(BulkUpdatePhoneNumberRequest $request)
    {
        $data = $request->validated();

        // logger($data);

        $ids = $data['items'] ?? [];
        unset($data['items']);

        if (empty($ids) || empty($data)) {
            return response()->json([
                'success' => false,
                'errors' => ['input' => ['No phone numbers or fields provided for update.']]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update each phone number in chunks
            Destinations::whereIn('destination_uuid', $ids)
                ->chunk(10, function ($phoneNumbers) use ($data) {
                    foreach ($phoneNumbers as $phoneNumber) {
                        $updateData = $data;

                        // Handle routing_options if present (per number)
                        if (isset($updateData['routing_options'])) {
                            $destination_actions = [];
                            foreach ($updateData['routing_options'] as $option) {
                                $destination_actions[] = buildDestinationAction($option);
                            }
                            $updateData['destination_actions'] = json_encode($destination_actions);
                            unset($updateData['routing_options']);
                        }

                        $phoneNumber->fill($updateData);
                        if ($phoneNumber->isDirty()) {
                            $phoneNumber->save();

                            //regenerate XML
                            // $this->generateDialPlanXML($phoneNumber);
                            dispatch(new \App\Jobs\BuildDialplanForPhoneNumber($phoneNumber->destination_uuid, session('domain_name')));
                        }
                    }
                });

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected phone numbers updated']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger('PhoneNumbersController@bulkUpdate error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update selected items']]
            ], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  UpdatePhoneNumberRequest  $request
     * @param  Destinations  $phone_number
     * @return JsonResponse
     */
    public function update(UpdatePhoneNumberRequest $request, Destinations $phone_number)
    {
        if (!$phone_number) {
            // If the model is not found, return an error response
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Model not found']]
            ], 404); // 404 Not Found if the model does not exist
        }

        try {
            $data = $request->validated();

            // Process routing_options to form destination_actions
            $destination_actions = [];
            if (!empty($data['routing_options'])) {
                foreach ($data['routing_options'] as $option) {
                    $destination_actions[] = buildDestinationAction($option);
                }
            }

            // Assign the formatted actions to the destination_actions field
            $data['destination_actions'] = json_encode($destination_actions);

            $phone_number->update($data);

            // $this->generateDialPlanXML($phone_number);
            dispatch(new \App\Jobs\BuildDialplanForPhoneNumber($phone_number->destination_uuid, session('domain_name')));

            return response()->json([
                'messages' => ['success' => ['Phone number updated successfully']],
                'phone_number' => $phone_number,
            ], 200);
        } catch (\Exception $e) {
            logger('PhoneNumbersController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this item']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Remove the specified resource from storage.
     * @return JsonResponse
     */
    public function bulkDelete(): JsonResponse
    {
        try {
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items at once
            $items = $this->model::whereIn('destination_uuid', request('items'))
                ->get(['destination_uuid', 'dialplan_uuid']);

            $dialplanBuilder = new DialplanBuilderService();

            foreach ($items as $item) {
                //Get dialplan details
                $dialPlan = Dialplans::where('dialplan_uuid', $item->dialplan_uuid)->first();
                // Delete dialplan
                if ($dialPlan) {
                    $dialPlan->delete();
                }

                // Delete the item itself
                $item->delete();

                //clear fusionpbx cache
                // $this->clearCache($item);
                $dialplanBuilder->clearCacheForPhoneNumber($item);
            }

            // Commit Transaction
            DB::commit();

            return response()->json([
                'messages' => ['server' => ['All selected items have been deleted successfully.']],
            ], 200);
        } catch (\Exception $e) {
            // Rollback Transaction if any error occurs
            DB::rollBack();

            // Log the error message
            logger('PhoneNumbersController@bulkDelete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    // public function generateDialPlanXML(Destinations $phoneNumber): void
    // {

    //     // logger($phoneNumber);
    //     // Data to pass to the Blade template
    //     $data = [
    //         'phone_number' => $phoneNumber,
    //         'domain_name' => Session::get('domain_name'),
    //         'fax_data' => $phoneNumber->fax()->first() ?? null,
    //         'dialplan_continue' => 'false',
    //         'destination_condition_field' => get_domain_setting('destination'),
    //     ];

    //     // Render the Blade template and get the XML content as a string
    //     $xml = trim(view('layouts.xml.phone-number-dial-plan-template', $data)->render());

    //     $dom = new \DOMDocument();
    //     $dom->preserveWhiteSpace = false;  // Removes extra spaces
    //     $dom->loadXML($xml);
    //     $dom->formatOutput = true;         // Formats XML properly
    //     $xml = $dom->saveXML($dom->documentElement);


    //     $dialPlan = Dialplans::where('dialplan_uuid', $phoneNumber->dialplan_uuid)->first();

    //     if (!$dialPlan) {
    //         $dialPlan = new Dialplans();
    //         $dialPlan->dialplan_uuid = $phoneNumber->dialplan_uuid;
    //         $dialPlan->app_uuid = 'c03b422e-13a8-bd1b-e42b-b6b9b4d27ce4';
    //         $dialPlan->domain_uuid = Session::get('domain_uuid');
    //         $dialPlan->dialplan_name = $phoneNumber->destination_number;
    //         $dialPlan->dialplan_number = $phoneNumber->destination_number;
    //         if (isset($phoneNumber->destination_context)) {
    //             $dialPlan->dialplan_context = $phoneNumber->destination_context;
    //         }
    //         $dialPlan->dialplan_continue = $data['dialplan_continue'];
    //         $dialPlan->dialplan_xml = $xml;
    //         $dialPlan->dialplan_order = 100;
    //         $dialPlan->dialplan_enabled = $phoneNumber->destination_enabled;
    //         $dialPlan->dialplan_description = $phoneNumber->destination_description;
    //         $dialPlan->insert_date = date('Y-m-d H:i:s');
    //         $dialPlan->insert_user = Session::get('user_uuid');
    //     } else {
    //         $dialPlan->dialplan_xml = $xml;
    //         $dialPlan->dialplan_name = $phoneNumber->destination_number;
    //         $dialPlan->dialplan_number = $phoneNumber->destination_number;
    //         $dialPlan->dialplan_enabled = $phoneNumber->destination_enabled;
    //         $dialPlan->dialplan_description = $phoneNumber->destination_description;
    //         $dialPlan->update_date = date('Y-m-d H:i:s');
    //         $dialPlan->update_user = Session::get('user_uuid');
    //     }

    //     $dialPlan->save();

    //     $this->generateDialplanDetails($phoneNumber, $dialPlan);

    //     //clear fusionpbx cache
    //     $this->clearCache($phoneNumber);
    // }

    /**
     * Probably need to move this to somewhere else like helper
     * @param  array  $inputs
     * @return array
     */
    private function processActionConditionInputs(array $inputs): array
    {
        if (!empty($inputs['destination_actions'])) {
            $actions = [];
            foreach ($inputs['destination_actions'] as $action) {
                if (!empty($action['targetValue'])) {
                    $actions[] = $this->findActionConditionApp($action['targetValue']);
                }
            }
            $inputs['destination_actions'] = json_encode($actions);
        } else {
            $inputs['destination_actions'] = null;
        }
        if (!empty($inputs['destination_conditions'])) {
            $conditions = [];
            foreach ($inputs['destination_conditions'] as $condition) {
                if (!empty($condition['condition_target']['targetValue'])) {
                    $data = $this->findActionConditionApp($condition['condition_target']['targetValue'], 'condition');
                    $data['condition_field'] = $condition['condition_field'];
                    $data['condition_expression'] = $condition['condition_expression'];
                    $conditions[] = $data;
                }
            }
            $inputs['destination_conditions'] = json_encode($conditions);
        } else {
            $inputs['destination_conditions'] = null;
        }

        return $inputs;
    }

    private function findActionConditionApp($action, $prefix = 'destination')
    {
        $pattern = '/^(transfer|lua|playback):(.*)$/';
        if (preg_match($pattern, $action, $matches)) {
            return [$prefix . '_app' => $matches[1], $prefix . '_data' => trim($matches[2])];
        } else {
            throw new \Exception('Unknown action: ' . $action);
        }
    }

    // private function clearCache($phoneNumber): void
    // {
    //     // Handling for multiple dialplan mode
    //     FusionCache::clear("dialplan:public");

    //     // Handling for single dialplan mode
    //     if (isset($phoneNumber->destination_prefix) && is_numeric($phoneNumber->destination_prefix) && isset($phoneNumber->destination_number) && is_numeric($phoneNumber->destination_number)) {
    //         //  logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_prefix.$phoneNumber->destination_number);
    //         FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);
    //         //logger("dialplan:". $phoneNumber->destination_context.":+".$phoneNumber->destination_prefix.$phoneNumber->destination_number);
    //         FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":+" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);
    //     }
    //     if (isset($phoneNumber->destination_number) && str_starts_with($phoneNumber->destination_number, '+') && is_numeric(str_replace('+', '', $phoneNumber->destination_number))) {
    //         //logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_number);
    //         FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_number);
    //     }
    //     if (isset($phoneNumber->destination_number) && is_numeric($phoneNumber->destination_number)) {
    //         //logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_number);
    //         FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_number);
    //     }

    //     /*
    //     if (isset($phoneNumber->destination_number)) {
    //         FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_number);

    //         if (isset($phoneNumber->destination_prefix)) {
    //             FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);

    //             // Assuming the "+" version is a variation of the prefix, and you want to clear it only if it wasn't cleared before.
    //             if ("+" . $phoneNumber->destination_prefix !== $phoneNumber->destination_prefix) {
    //                 FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":+" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);
    //             }
    //         }
    //     }*/
    // }

    // private function generateDialplanDetails(Destinations $phoneNumber, Dialplans $dialPlan): void
    // {
    //     // Remove existing device lines
    //     if ($dialPlan->dialplan_details()->exists()) {
    //         $dialPlan->dialplan_details()->delete();
    //     }

    //     $detailOrder = 20;
    //     $detailGroup = 0;

    //     $destination_condition_field = get_domain_setting('destination');

    //     if ($phoneNumber->destination_conditions) {
    //         $conditions = json_decode($phoneNumber->destination_conditions);
    //         foreach ($conditions as $condition) {
    //             $dialPlanDetails = new DialplanDetails();
    //             $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //             $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //             $dialPlanDetails->dialplan_detail_tag = "condition";
    //             $dialPlanDetails->dialplan_detail_type = 'regex';
    //             $dialPlanDetails->dialplan_detail_data = 'all';
    //             $dialPlanDetails->dialplan_detail_break = 'never';
    //             $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //             $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //             $dialPlanDetails->save();

    //             $detailOrder += 10;

    //             $dialPlanDetails = new DialplanDetails();
    //             //check the destination number
    //             $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //             $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //             $dialPlanDetails->dialplan_detail_tag = "regex";
    //             /*if (!empty($condition->condition_app)) {
    //                 $dialPlanDetails->dialplan_detail_type = $condition->condition_app;
    //             } else {
    //                 $dialPlanDetails->dialplan_detail_type = "regex";
    //             }*/
    //             $dialPlanDetails->dialplan_detail_type = $destination_condition_field;
    //             $dialPlanDetails->dialplan_detail_data = $phoneNumber->destination_number_regex;
    //             $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //             $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //             $dialPlanDetails->save();

    //             //die;

    //             $detailOrder += 10;

    //             $dialPlanDetails = new DialplanDetails();
    //             $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //             $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //             $dialPlanDetails->dialplan_detail_tag = "regex";
    //             $dialPlanDetails->dialplan_detail_type = $condition->condition_field;
    //             $dialPlanDetails->dialplan_detail_data = '^\+?' . $phoneNumber->destination_prefix . '?' . $condition->condition_expression . '$';
    //             $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //             $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //             $dialPlanDetails->save();

    //             $detailOrder += 10;

    //             $dialPlanDetails = new DialplanDetails();
    //             $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //             $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //             $dialPlanDetails->dialplan_detail_tag = "action";
    //             $dialPlanDetails->dialplan_detail_type = $condition->condition_app;
    //             $dialPlanDetails->dialplan_detail_data = $condition->condition_data;
    //             $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //             $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //             $dialPlanDetails->save();

    //             $detailOrder += 10;
    //             $detailGroup += 10;
    //         }
    //     }

    //     //check the destination number
    //     $dialPlanDetails = new DialplanDetails();
    //     $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //     $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //     $dialPlanDetails->dialplan_detail_tag = "condition";
    //     $dialPlanDetails->dialplan_detail_type = $destination_condition_field;
    //     //$dialPlanDetails->dialplan_detail_type = 'destination_number';
    //     $dialPlanDetails->dialplan_detail_data = $phoneNumber->destination_number_regex;
    //     $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //     $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //     $dialPlanDetails->save();

    //     $detailOrder += 10;

    //     if (!empty($phoneNumber->destination_cid_name_prefix)) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "effective_caller_id_name=" . $phoneNumber->destination_cid_name_prefix . "#\${caller_id_name}";
    //         $dialPlanDetails->dialplan_detail_inline = "false";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         $detailOrder += 10;
    //     }

    //     if (!empty($phoneNumber->destination_cid_name_prefix)) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "cnam_prefix=" . $phoneNumber->destination_cid_name_prefix;
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         $detailOrder += 10;
    //     }

    //     if (!empty($phoneNumber->destination_accountcode)) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "export";
    //         $dialPlanDetails->dialplan_detail_data = "accountcode=" . $phoneNumber->destination_accountcode;
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         $detailOrder += 10;
    //     }

    //     if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "export";
    //         $dialPlanDetails->dialplan_detail_data = "fax_enable_t38=true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         $detailOrder += 10;
    //     }

    //     if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "export";
    //         $dialPlanDetails->dialplan_detail_data = "fax_enable_t38_request=true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         $detailOrder += 10;
    //     }

    //     if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "export";
    //         $dialPlanDetails->dialplan_detail_data = "fax_use_ecm=true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         $detailOrder += 10;
    //     }

    //     if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "export";
    //         $dialPlanDetails->dialplan_detail_data = "inbound-proxy-media=true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         $detailOrder += 10;
    //     }


    //     if (!empty($phoneNumber->destination_hold_music)) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "export";
    //         $dialPlanDetails->dialplan_detail_data = "hold_music=" . $phoneNumber->destination_hold_music;
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;
    //     }

    //     if (!empty($phoneNumber->destination_distinctive_ring)) {
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "export";
    //         $dialPlanDetails->dialplan_detail_data = "sip_h_Alert-Info=" . $phoneNumber->destination_distinctive_ring;
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;
    //     }

    //     if (!empty($phoneNumber->fax_uuid)) {

    //         //add set tone detect_hits=1
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "tone_detect_hits=1";
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;

    //         //execute on tone detect
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "execute_on_tone_detect=transfer " . $phoneNumber->fax()->first()->fax_extension . " XML \${domain_name}";
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;

    //         //add tone_detect fax 1100 r +5000
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "tone_detect";
    //         $dialPlanDetails->dialplan_detail_data = "fax 1100 r +5000";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;
    //     }

    //     if ($phoneNumber->destination_record == 'true') {
    //         //add a variable
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "record_path=\${recordings_dir}/\${domain_name}/archive/\${strftime(%Y)}/\${strftime(%b)}/\${strftime(%d)}";
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;

    //         //add a variable
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "record_name=\${uuid}.\${record_ext}";
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;

    //         //add a variable
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "record_append=true";
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;

    //         //add a variable
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "record_in_progress=true";
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;

    //         //add a variable
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "set";
    //         $dialPlanDetails->dialplan_detail_data = "recording_follow_transfer=true";
    //         $dialPlanDetails->dialplan_detail_inline = "true";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;

    //         //add a variable
    //         $dialPlanDetails = new DialplanDetails();
    //         $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //         $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //         $dialPlanDetails->dialplan_detail_tag = "action";
    //         $dialPlanDetails->dialplan_detail_type = "record_session";
    //         $dialPlanDetails->dialplan_detail_data = "\${record_path}/\${record_name}";
    //         $dialPlanDetails->dialplan_detail_inline = "false";
    //         $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //         $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //         $dialPlanDetails->save();

    //         //increment the dialplan detail order
    //         $detailOrder += 10;
    //     }

    //     if ($phoneNumber->destination_actions) {
    //         $actions = json_decode($phoneNumber->destination_actions);
    //         foreach ($actions as $action) {
    //             //add to the dialplan_details array
    //             $dialPlanDetails = new DialplanDetails();
    //             $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
    //             $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
    //             $dialPlanDetails->dialplan_detail_tag = "action";
    //             $dialPlanDetails->dialplan_detail_type = $action->destination_app;
    //             $dialPlanDetails->dialplan_detail_data = $action->destination_data;
    //             $dialPlanDetails->dialplan_detail_group = $detailGroup;
    //             $dialPlanDetails->dialplan_detail_order = $detailOrder;
    //             $dialPlanDetails->save();
    //             $detailOrder += 10;
    //         }
    //     }
    // }

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['manage_recording_setting'] = userCheckPermission('destination_record');
        $permissions['manage_destination_prefix'] = userCheckPermission('destination_prefix');
        $permissions['manage_destination_domain'] = userCheckPermission('destination_domain');
        $permissions['destination_hold_music'] = userCheckPermission('destination_hold_music');

        return $permissions;
    }

    /**
     * @return JsonResponse
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectAll(): JsonResponse
    {
        try {
            $params = request()->all();

            $domain_uuid = session('domain_uuid');
            $params['domain_uuid'] = $domain_uuid;

            $data = QueryBuilder::for(Destinations::class, request()->merge($params))
                ->select([
                    'destination_uuid',
                    'domain_uuid',
                ])
                ->allowedFilters([
                    AllowedFilter::callback('showGlobal', function ($query, $value) use ($domain_uuid) {
                        // If showGlobal is falsey (0, '0', false, null), restrict to the current domain
                        if (!$value || $value === '0' || $value === 0 || $value === 'false') {
                            $query->where('domain_uuid', $domain_uuid);
                        }
                        // else, do nothing and show all domains
                    }),
                    AllowedFilter::callback('search', function ($query, $value) {
                        $query->where(function ($q) use ($value) {
                            $q->where('destination_number', 'ilike', "%{$value}%")
                                ->orWhere('destination_data', 'ilike', "%{$value}%")
                                ->orWhere('destination_description', 'ilike', "%{$value}%");
                        });
                    }),
                ])
                ->pluck('destination_uuid');

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $data,
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

    public function downloadTemplate()
    {
        // Download as CSV (third parameter sets the writer type)
        return Excel::download(new PhoneNumberTemplate, 'template.csv', ExcelWriter::CSV);
    }
}
