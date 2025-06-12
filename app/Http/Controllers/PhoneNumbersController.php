<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StorePhoneNumberRequest;
use App\Http\Requests\UpdatePhoneNumberRequest;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\BulkUpdatePhoneNumberRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PhoneNumbersController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'PhoneNumbers';
    protected $searchable = ['destination_number', 'destination_data', 'destination_description'];

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
    public function index(
        Request $request
    ): Redirector|Response|RedirectResponse|Application {
        if (!userCheckPermission("destination_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
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
                    //'bulk_delete' => route('messages.settings.bulk.delete'),
                    //'bulk_update' => route('devices.bulk.update'),
                ],
                'conditions' => [
                    [
                        'name' => 'Caller ID Number',
                        'value' => 'caller_id_number'
                    ]
                ],
                'domain' => Session::get('domain_uuid')
            ]
        );
    }


    public function getItemOptions()
    {
        try {

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

            // Base navigation array without Greetings
            $navigation = [
                [
                    'name' => 'Settings',
                    'icon' => 'Cog6ToothIcon',
                    'slug' => 'settings',
                ],
                [
                    'name' => 'Advanced',
                    'icon' => 'AdjustmentsHorizontalIcon',
                    'slug' => 'advanced',
                ],
            ];

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
                    'name' => $fax->fax_extension . ' ' . $fax->fax_name,
                    'value' => $fax->fax_uuid
                ];
            }

            $domains = [];
            $domainsCollection = Session::get("domains");
            if ($domainsCollection) {
                foreach ($domainsCollection as $domain) {
                    $domains[] = [
                        'value' => $domain->domain_uuid,
                        'name' => $domain->domain_description
                    ];
                }
            }

            // Check if item_uuid exists to find an existing voicemail
            if ($item_uuid) {
                // Find existing item by item_uuid
                $phoneNumber = $this->model::where($this->model->getKeyName(), $item_uuid)->first();

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

            $routes = [
                'update_route' => $updateRoute ?? null,
                'get_routing_options' => route('routing.options'),

            ];


            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'phone_number' => $phoneNumber,
                'permissions' => $permissions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'faxes' => $faxes,
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
     * @param  \App\Http\Requests\StorePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function store(StorePhoneNumberRequest $request): JsonResponse
    {
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

            $this->generateDialPlanXML($instance);

            return response()->json([
                'messages' => ['success' => ['New item created']]
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
     * Display the specified resource.
     *
     * @param  Destinations  $destinations
     * @return \Illuminate\Http\Response
     */
    public function show(Destinations $destinations)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Destinations  $phone_number
     * @return JsonResponse
     */
    public function edit(Request $request, Destinations $phone_number)
    {
        //
    }

    /**
     * Bulk update requested items
     *
     * @param  BulkUpdatePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function bulkUpdate(BulkUpdatePhoneNumberRequest  $request): JsonResponse
    {
        // $request->items has items IDs that need to be updated
        // $request->validated has the update data

        try {
            // Prepare the data for updating
            $inputs = collect($request->validated())
                ->filter(function ($value) {
                    return $value !== null;
                })->toArray();

            $inputs = $this->processActionConditionInputs($inputs);

            if ($inputs['destination_actions'] == null) {
                unset($inputs['destination_actions']);
            }

            if ($inputs['destination_conditions'] == null) {
                unset($inputs['destination_conditions']);
            }

            //var_dump($inputs);

            /*if (isset($inputs['device_template'])) {
                $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0];
                if ($inputs['device_vendor'] === 'poly') {
                    $inputs['device_vendor'] = 'polycom';
                }
            }

            if (isset($inputs['extension'])) {
                $extension = $inputs['extension'];
                unset($inputs['extension']);
            } else {
                $extension = null;
            }*/

            if (sizeof($inputs) > 0) {
                $updated = $this->model::whereIn($this->model->getKeyName(), request()->items)
                    ->update($inputs);
            }

            /*if ($extension) {
                // First, we are deleting all existing device lines
                $this->deleteDeviceLines(request('items'));

                // Create new lines
                $this->createDeviceLines(request('items'), $extension);
            }*/

            return response()->json([
                'messages' => ['success' => ['Selected items updated']],
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update selected items']]
            ], 500); // 500 Internal Server Error for any other errors
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
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());

            // logger($inputs);

            // Process routing_options to form destination_actions
            $destination_actions = [];
            if (!empty($inputs['routing_options'])) {
                foreach ($inputs['routing_options'] as $option) {
                    $destination_actions[] = buildDestinationAction($option);
                }
            }

            // Assign the formatted actions to the destination_actions field
            $inputs['destination_actions'] = json_encode($destination_actions);

            $phone_number->update($inputs);

            $this->generateDialPlanXML($phone_number);

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
     *
     * @param  Destinations  $phone_number
     * @return RedirectResponse
     */
    public function destroy(Destinations $phoneNumber)
    {
        try {
            //Get dialplan details
            $dialPlan = Dialplans::where('dialplan_uuid', $phoneNumber->dialplan_uuid)->first();

            // Delete dialplan
            if ($dialPlan) {
                $dialPlan->delete();
            }

            // Delete Phone Number
            $phoneNumber->delete();

            //clear fusionpbx cache
            $this->clearCache($phoneNumber);

            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e);
            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
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
                ->get(['destination_uuid']);

            foreach ($items as $item) {
                // Delete the item itself
                $item->delete();
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
            logger($e);
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    private function generateDialPlanXML(Destinations $phoneNumber): void
    {

        // logger($phoneNumber);
        // Data to pass to the Blade template
        $data = [
            'phone_number' => $phoneNumber,
            'domain_name' => Session::get('domain_name'),
            'fax_data' => $phoneNumber->fax()->first() ?? null,
            'dialplan_continue' => 'false',
            'destination_condition_field' => get_domain_setting('destination'),
        ];

        // Render the Blade template and get the XML content as a string
        $xml = trim(view('layouts.xml.phone-number-dial-plan-template', $data)->render());

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;  // Removes extra spaces
        $dom->loadXML($xml);
        $dom->formatOutput = true;         // Formats XML properly
        $xml = $dom->saveXML($dom->documentElement);


        $dialPlan = Dialplans::where('dialplan_uuid', $phoneNumber->dialplan_uuid)->first();

        if (!$dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $phoneNumber->dialplan_uuid;
            $dialPlan->app_uuid = 'c03b422e-13a8-bd1b-e42b-b6b9b4d27ce4';
            $dialPlan->domain_uuid = Session::get('domain_uuid');
            $dialPlan->dialplan_name = $phoneNumber->destination_number;
            $dialPlan->dialplan_number = $phoneNumber->destination_number;
            if (isset($phoneNumber->destination_context)) {
                $dialPlan->dialplan_context = $phoneNumber->destination_context;
            }
            $dialPlan->dialplan_continue = $data['dialplan_continue'];
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 100;
            $dialPlan->dialplan_enabled = $phoneNumber->destination_enabled;
            $dialPlan->dialplan_description = $phoneNumber->destination_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = Session::get('user_uuid');
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $phoneNumber->destination_number;
            $dialPlan->dialplan_number = $phoneNumber->destination_number;
            $dialPlan->dialplan_enabled = $phoneNumber->destination_enabled;
            $dialPlan->dialplan_description = $phoneNumber->destination_description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = Session::get('user_uuid');
        }

        $dialPlan->save();

        $this->generateDialplanDetails($phoneNumber, $dialPlan);

        //clear fusionpbx cache
        $this->clearCache($phoneNumber);
    }

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

    private function clearCache($phoneNumber): void
    {
        // Handling for multiple dialplan mode
        FusionCache::clear("dialplan:public");

        // Handling for single dialplan mode
        if (isset($phoneNumber->destination_prefix) && is_numeric($phoneNumber->destination_prefix) && isset($phoneNumber->destination_number) && is_numeric($phoneNumber->destination_number)) {
            //  logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_prefix.$phoneNumber->destination_number);
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);
            //logger("dialplan:". $phoneNumber->destination_context.":+".$phoneNumber->destination_prefix.$phoneNumber->destination_number);
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":+" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);
        }
        if (isset($phoneNumber->destination_number) && str_starts_with($phoneNumber->destination_number, '+') && is_numeric(str_replace('+', '', $phoneNumber->destination_number))) {
            //logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_number);
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_number);
        }
        if (isset($phoneNumber->destination_number) && is_numeric($phoneNumber->destination_number)) {
            //logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_number);
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_number);
        }

        /*
        if (isset($phoneNumber->destination_number)) {
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_number);

            if (isset($phoneNumber->destination_prefix)) {
                FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);

                // Assuming the "+" version is a variation of the prefix, and you want to clear it only if it wasn't cleared before.
                if ("+" . $phoneNumber->destination_prefix !== $phoneNumber->destination_prefix) {
                    FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":+" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);
                }
            }
        }*/
    }

    private function generateDialplanDetails(Destinations $phoneNumber, Dialplans $dialPlan): void
    {
        // Remove existing device lines
        if ($dialPlan->dialplan_details()->exists()) {
            $dialPlan->dialplan_details()->delete();
        }

        $detailOrder = 20;
        $detailGroup = 0;

        $destination_condition_field = get_domain_setting('destination');

        if ($phoneNumber->destination_conditions) {
            $conditions = json_decode($phoneNumber->destination_conditions);
            foreach ($conditions as $condition) {
                $dialPlanDetails = new DialplanDetails();
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "condition";
                $dialPlanDetails->dialplan_detail_type = 'regex';
                $dialPlanDetails->dialplan_detail_data = 'all';
                $dialPlanDetails->dialplan_detail_break = 'never';
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();

                $detailOrder += 10;

                $dialPlanDetails = new DialplanDetails();
                //check the destination number
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "regex";
                /*if (!empty($condition->condition_app)) {
                    $dialPlanDetails->dialplan_detail_type = $condition->condition_app;
                } else {
                    $dialPlanDetails->dialplan_detail_type = "regex";
                }*/
                $dialPlanDetails->dialplan_detail_type = $destination_condition_field;
                $dialPlanDetails->dialplan_detail_data = $phoneNumber->destination_number_regex;
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();

                //die;

                $detailOrder += 10;

                $dialPlanDetails = new DialplanDetails();
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "regex";
                $dialPlanDetails->dialplan_detail_type = $condition->condition_field;
                $dialPlanDetails->dialplan_detail_data = '^\+?' . $phoneNumber->destination_prefix . '?' . $condition->condition_expression . '$';
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();

                $detailOrder += 10;

                $dialPlanDetails = new DialplanDetails();
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "action";
                $dialPlanDetails->dialplan_detail_type = $condition->condition_app;
                $dialPlanDetails->dialplan_detail_data = $condition->condition_data;
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();

                $detailOrder += 10;
                $detailGroup += 10;
            }
        }

        //check the destination number
        $dialPlanDetails = new DialplanDetails();
        $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
        $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
        $dialPlanDetails->dialplan_detail_tag = "condition";
        $dialPlanDetails->dialplan_detail_type = $destination_condition_field;
        //$dialPlanDetails->dialplan_detail_type = 'destination_number';
        $dialPlanDetails->dialplan_detail_data = $phoneNumber->destination_number_regex;
        $dialPlanDetails->dialplan_detail_group = $detailGroup;
        $dialPlanDetails->dialplan_detail_order = $detailOrder;
        $dialPlanDetails->save();

        $detailOrder += 10;

        if (!empty($phoneNumber->destination_cid_name_prefix)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "effective_caller_id_name=" . $phoneNumber->destination_cid_name_prefix . "#\${caller_id_name}";
            $dialPlanDetails->dialplan_detail_inline = "false";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_cid_name_prefix)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "cnam_prefix=" . $phoneNumber->destination_cid_name_prefix;
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_accountcode)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "accountcode=" . $phoneNumber->destination_accountcode;
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "fax_enable_t38=true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "fax_enable_t38_request=true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "fax_use_ecm=true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "inbound-proxy-media=true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }


        if (!empty($phoneNumber->destination_hold_music)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "hold_music=" . $phoneNumber->destination_hold_music;
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_distinctive_ring)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "sip_h_Alert-Info=" . $phoneNumber->destination_distinctive_ring;
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;
        }

        if (!empty($phoneNumber->fax_uuid)) {

            //add set tone detect_hits=1
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "tone_detect_hits=1";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //execute on tone detect
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "execute_on_tone_detect=transfer " . $phoneNumber->fax()->first()->fax_extension . " XML \${domain_name}";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add tone_detect fax 1100 r +5000
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "tone_detect";
            $dialPlanDetails->dialplan_detail_data = "fax 1100 r +5000";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;
        }

        if ($phoneNumber->destination_record == 'true') {
            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "record_path=\${recordings_dir}/\${domain_name}/archive/\${strftime(%Y)}/\${strftime(%b)}/\${strftime(%d)}";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "record_name=\${uuid}.\${record_ext}";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "record_append=true";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "record_in_progress=true";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "recording_follow_transfer=true";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "record_session";
            $dialPlanDetails->dialplan_detail_data = "\${record_path}/\${record_name}";
            $dialPlanDetails->dialplan_detail_inline = "false";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;
        }

        if ($phoneNumber->destination_actions) {
            $actions = json_decode($phoneNumber->destination_actions);
            foreach ($actions as $action) {
                //add to the dialplan_details array
                $dialPlanDetails = new DialplanDetails();
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "action";
                $dialPlanDetails->dialplan_detail_type = $action->destination_app;
                $dialPlanDetails->dialplan_detail_data = $action->destination_data;
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();
                $detailOrder += 10;
            }
        }
    }

    public function getUserPermissions()
    {
        $permissions = [];
        // $permissions['manage_voicemail_copies'] = userCheckPermission('voicemail_forward');

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
            if (request()->get('showGlobal')) {
                $uuids = $this->model::get($this->model->getKeyName())->pluck($this->model->getKeyName());
            } else {
                $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                    ->get($this->model->getKeyName())->pluck($this->model->getKeyName());
            }

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
