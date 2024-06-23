<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhoneNumberRequest;
use App\Http\Requests\UpdatePhoneNumberRequest;
use App\Models\Destinations;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Faxes;
use App\Models\FreeswitchSettings;
use App\Models\FusionCache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class PhoneNumbersController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'PhoneNumbers';
    protected $searchable = ['destination_number', 'destination_data','destination_description'];

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
    public function index(Request $request
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
                'itemData' => Inertia::lazy(
                    fn () =>
                    $this->getItemData()
                ),
                'itemOptions' => Inertia::lazy(
                    fn () =>
                    $this->getItemOptions()
                ),
                'routes' => [
                    'current_page' => route('phone-numbers.index'),
                    'store' => route('phone-numbers.store'),
                    'select_all' => route('phone-numbers.select.all'),
                    'bulk_delete' => route('phone-numbers.bulk.delete'),
                   // 'select_all' => route('messages.settings.select.all'),
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

    public function getItemData()
    {
        // Get item data
        $itemData = $this->model::where($this->model->getKeyName(), request('itemUuid'))
            ->select([
                'destination_uuid',
                'domain_uuid',
                'fax_uuid',
                'destination_prefix',
                'destination_number',
                'destination_actions',
                'destination_conditions',
                'destination_hold_music',
                'destination_description',
                'destination_enabled',
                'destination_record',
                'destination_cid_name_prefix',
                'destination_accountcode',
                'destination_distinctive_ring',
            ])
            ->first();

        // Add update url route info
        $itemData->update_url = route('phone-numbers.update', $itemData);
        return $itemData;
    }

    public function getItemOptions()
    {
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
                'name' => $fax->fax_extension.' '.$fax->fax_name,
                'value' => $fax->fax_uuid
            ];
        }

        $domains = [];
        $domainsCollection = Session::get("domains");
        foreach ($domainsCollection as $domain) {
            $domains[] = [
                'value' => $domain->domain_uuid,
                'name' => $domain->domain_description
            ];
        }
        $timeoutDestinations = getTimeoutDestinations();

        unset($faxesCollection, $domainsCollection, $fax, $domain);

        return [
            'music_on_hold' => getMusicOnHoldCollection(),
            'faxes' => $faxes,
            'domains' => $domains,
            'timeout_destinations_categories' => array_values($timeoutDestinations['categories']),
            'timeout_destinations_targets' => $timeoutDestinations['targets']
        ];
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
            'destination_data',
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

            $inputs = $this->processActionConditionInputs($inputs);

            $instance = $this->model;
            $instance->fill([
                'domain_uuid' => $inputs['domain_uuid'],
                'fax_uuid' => $inputs['fax_uuid'] ?? null,
                'destination_type' => 'inbound',
                'destination_prefix' => $inputs['destination_prefix'],
                'destination_number' => $inputs['destination_number'],
                'destination_actions' => $inputs['destination_actions'],
                'destination_conditions' => $inputs['destination_conditions'],
                'destination_hold_music' => $inputs['destination_hold_music'] ?? null,
                'destination_description' => $inputs['destination_description'] ?? null,
                'destination_enabled' => $inputs['destination_enabled'] ?? true,
                'destination_record' => $inputs['destination_record'] ?? false,
                'destination_cid_name_prefix' => $inputs['destination_cid_name_prefix'] ?? null,
                'destination_accountcode' => $inputs['destination_accountcode'] ?? null,
                'destination_distinctive_ring' => $inputs['destination_distinctive_ring'] ?? null,
            ]);
;           $instance->save();

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

            $inputs = $this->processActionConditionInputs($inputs);

            $phone_number->update($inputs);

            $this->generateDialPlanXML($phone_number);

        } catch (\Exception $e) {
            logger($e);
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
    public function destroy(Destinations $phone_number)
    {
        try {
            // throw new \Exception;

            // Delete Phone Number
            $phone_number->delete();

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
        ];

        // Render the Blade template and get the XML content as a string
        $xml = view('layouts.xml.phone-number-dial-plan-template', $data)->render();

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
            $dialPlan->dialplan_order = 101;
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

        $phoneNumber->dialplan_uuid = $dialPlan->dialplan_uuid;
        $phoneNumber->save();

        $this->generateDialplanDetails($phoneNumber, $dialPlan);

        $freeswitchSettings = FreeswitchSettings::first();
        $fp = event_socket_create(
            $freeswitchSettings['event_socket_ip_address'],
            $freeswitchSettings['event_socket_port'],
            $freeswitchSettings['event_socket_password']
        );
        event_socket_request($fp, 'bgapi reloadxml');

        //clear fusionpbx cache
        FusionCache::clear("dialplan:" . $phoneNumber->destination_context);
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
                if (!empty($action['value']['value'])) {
                    $actions[] = [
                        'destination_app' => 'transfer',
                        'destination_data' => $action['value']['value'],
                    ];
                }
            }
            $inputs['destination_actions'] = json_encode($actions);
        } else {
            $inputs['destination_actions'] = null;
        }
        if (!empty($inputs['destination_conditions'])) {
            $conditions = [];
            foreach ($inputs['destination_conditions'] as $condition) {
                if (!empty($condition['value']['value'])) {
                    $conditions[] = [
                        'condition_field' => $condition['condition_field'],
                        'condition_expression' => $condition['condition_expression'],
                        'condition_app' => 'transfer',
                        'condition_data' => $condition['value']['value']
                    ];
                }
            }
            $inputs['destination_conditions'] = json_encode($conditions);
        } else {
            $inputs['destination_conditions'] = null;
        }

        return $inputs;
    }

    private function generateDialplanDetails(Destinations $phoneNumber, Dialplans $dialPlan)
    {
        // Remove existing device lines
        if ($dialPlan->dialplan_details()->exists()) {
            $dialPlan->dialplan_details()->delete();
        }

        $detailOrder = 20;
        $detailGroup = 0;

        if($phoneNumber->destination_conditions) {
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
                $dialPlanDetails->dialplan_detail_type = '${sip_req_user}';
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
                $dialPlanDetails->dialplan_detail_data = '^\+?'.$phoneNumber->destination_prefix.'?'.$condition->condition_expression.'$';
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
        $dialPlanDetails->dialplan_detail_type = '${sip_req_user}';
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
            $dialPlanDetails->dialplan_detail_data = "effective_caller_id_name=".$phoneNumber->destination_cid_name_prefix."#\${caller_id_name}";
            $dialPlanDetails->dialplan_detail_inline = "false";
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
            $dialPlanDetails->dialplan_detail_data = "accountcode=".$phoneNumber->destination_accountcode;
            $dialPlanDetails->dialplan_detail_inline = "true";
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
            $dialPlanDetails->dialplan_detail_data = "hold_music=".$phoneNumber->destination_hold_music;
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
            $dialPlanDetails->dialplan_detail_data = "sip_h_Alert-Info=".$phoneNumber->destination_distinctive_ring;
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
            $dialPlanDetails->dialplan_detail_data = "execute_on_tone_detect=transfer ".$phoneNumber->fax()->first()->fax_extension." XML \${domain_name}";
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

        if ($phoneNumber->destination_record) {
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

        if($phoneNumber->destination_actions) {
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



        //set initial value of the row id
        /*
        $y=0;

        //increment the dialplan detail order
        $dialplan_detail_order = $dialplan_detail_order + 10;
        $dialplan_detail_group = 0;

        //add the dialplan detail destination conditions
        if (!empty($conditions)) {
            foreach($conditions as $row) {
                //prepare the expression
                if (is_numeric($row['condition_expression']) && strlen($destination_number) == strlen($row['condition_expression']) && !empty($destination_prefix)) {
                    $condition_expression = '\+?'.$destination_prefix.'?'.$row['condition_expression'];
                }
                else {
                    $condition_expression = str_replace("+", "\+", $row['condition_expression']);
                }

                //add to the dialplan_details array - condition regex='all'
                $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
                $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
                $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "condition";
                $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = 'regex';
                $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = 'all';
                $dialplan["dialplan_details"][$y]["dialplan_detail_break"] = 'never';
                $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
                $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
                $y++;

                //increment the dialplan detail order
                $dialplan_detail_order = $dialplan_detail_order + 10;

                //check the destination number
                $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
                $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
                $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "regex";
                if (!empty($destination_condition_field)) {
                    $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = $destination_condition_field;
                }
                elseif (!empty($_SESSION['dialplan']['destination']['text'])) {
                    $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = $_SESSION['dialplan']['destination']['text'];
                }
                else {
                    $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "regex";
                }
                $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = $destination_number_regex;
                $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
                $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
                $y++;

                //increment the dialplan detail order
                $dialplan_detail_order = $dialplan_detail_order + 10;

                $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
                $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
                $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "regex";
                $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = $row['condition_field'];
                $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = '^'.$condition_expression.'$';
                $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
                $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
                $y++;

                if (isset($row['condition_app']) && !empty($row['condition_app'])) {
                    if ($destination->valid($row['condition_app'].':'.$row['condition_data'])) {

                        //increment the dialplan detail order
                        $dialplan_detail_order = $dialplan_detail_order + 10;

                        $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
                        $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
                        $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
                        $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = xml::sanitize($row['condition_app']);
                        $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = xml::sanitize($row['condition_data']);
                        $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
                        $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
                        $y++;

                    }
                }

                //increment the dialplan detail order
                $dialplan_detail_order = $dialplan_detail_order + 10;
                $dialplan_detail_group = $dialplan_detail_group + 10;
            }
        }

        =----

        //check the destination number
        $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
        $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
        $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "condition";
        if (!empty($destination_condition_field)) {
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = $destination_condition_field;
        }
        elseif (!empty($_SESSION['dialplan']['destination']['text'])) {
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = $_SESSION['dialplan']['destination']['text'];
        }
        else {
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "destination_number";
        }
        $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = $destination_number_regex;
        $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
        $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;

        $y++;

        //increment the dialplan detail order
        $dialplan_detail_order = $dialplan_detail_order + 10;

        //add this only if using application bridge
        if (!empty($destination_app) && $destination_app == 'bridge') {
            //add hangup_after_bridge
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "hangup_after_bridge=true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //add continue_on_fail
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "continue_on_fail=true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;
        }

        //increment the dialplan detail order
        $dialplan_detail_order = $dialplan_detail_order + 10;

        //set the caller id name prefix
        if (!empty($destination_cid_name_prefix)) {
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "effective_caller_id_name=".$destination_cid_name_prefix."#\${caller_id_name}";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "false";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;
        }

        //set the call accountcode
        if (!empty($destination_accountcode)) {
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "export";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "accountcode=".$destination_accountcode;
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;
        }

        //set the call carrier
        if (!empty($destination_carrier)) {
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "carrier=$destination_carrier";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;
        }

        //set the hold music
        if (!empty($destination_hold_music)) {
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "export";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "hold_music=".$destination_hold_music;
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;
        }

        //set the distinctive ring
        if (!empty($destination_distinctive_ring)) {
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "export";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "sip_h_Alert-Info=".$destination_distinctive_ring;
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;
        }

        //add fax detection
        if (is_uuid($fax_uuid)) {

            //add set tone detect_hits=1
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "tone_detect_hits=1";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //execute on tone detect
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "execute_on_tone_detect=transfer ".$fax_extension." XML \${domain_name}";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //add tone_detect fax 1100 r +5000
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "tone_detect";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "fax 1100 r +5000";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;
        }

        //add option record to the dialplan
        if ($destination_record == "true") {

            //add a variable
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "record_path=\${recordings_dir}/\${domain_name}/archive/\${strftime(%Y)}/\${strftime(%b)}/\${strftime(%d)}";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //add a variable
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "record_name=\${uuid}.\${record_ext}";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //add a variable
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "record_append=true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //add a variable
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "record_in_progress=true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //add a variable
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "set";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "recording_follow_transfer=true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "true";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;

            //add a variable
            $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
            $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
            $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = "record_session";
            $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = "\${record_path}/\${record_name}";
            $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = "false";
            $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
            $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;
            $y++;

            //increment the dialplan detail order
            $dialplan_detail_order = $dialplan_detail_order + 10;
        }

        //add the actions
        foreach($destination_actions as $field) {
            $action_array = explode(":", $field, 2);
            $action_app = $action_array[0] ?? null;
            $action_data = $action_array[1] ?? null;
            if (isset($action_array[0]) && !empty($action_array[0])) {
                if ($destination->valid($action_app.':'.$action_data)) {
                    //add to the dialplan_details array
                    $dialplan["dialplan_details"][$y]["domain_uuid"] = $domain_uuid;
                    $dialplan["dialplan_details"][$y]["dialplan_uuid"] = $dialplan_uuid;
                    $dialplan["dialplan_details"][$y]["dialplan_detail_tag"] = "action";
                    $dialplan["dialplan_details"][$y]["dialplan_detail_type"] = $action_app;
                    $dialplan["dialplan_details"][$y]["dialplan_detail_data"] = $action_data;
                    $dialplan["dialplan_details"][$y]["dialplan_detail_group"] = $dialplan_detail_group;
                    $dialplan["dialplan_details"][$y]["dialplan_detail_order"] = $dialplan_detail_order;

                    //set inline to true
                    if ($action_app == 'set' || $action_app == 'export') {
                        $dialplan["dialplan_details"][$y]["dialplan_detail_inline"] = 'true';
                    }
                    $y++;

                    //increment the dialplan detail order
                    $dialplan_detail_order = $dialplan_detail_order + 10;
                }
            }
        }
        */
    }
}
