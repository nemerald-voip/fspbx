<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhoneNumberRequest;
use App\Http\Requests\UpdatePhoneNumberRequest;
use App\Models\Destinations;
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
            $dialPlan->dialplan_continue = 'false';
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
}
