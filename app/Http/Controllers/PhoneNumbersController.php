<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhoneNumberRequest;
use App\Http\Requests\UpdatePhoneNumberRequest;
use App\Models\Destinations;
use App\Models\DeviceLines;
use App\Models\Devices;
use App\Models\Extensions;
use App\Models\Faxes;
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
    protected $searchable = ['destination_number', 'destination_caller_id_name'];

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

        // die('asdasdasd');

        /*$this->filters = [];

        $this->filters['search'] = $request->filterData['search'] ?? null;

        if (!empty($request->filterData['showGlobal'])) {
            $this->filters['showGlobal'] = $request->filterData['showGlobal'] == 'true';
        }*/

        return Inertia::render(
            'Phonenumbers',
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

                /*
                'menus' => function () {
                    return Session::get('menu');
                },
                'domainSelectPermission' => function () {
                    return Session::get('domain_select');
                },
                'domains' => function () {
                    return Session::get("domains");
                },
                'selectedDomain' => function () {
                    return Session::get('domain_name');
                },
                'selectedDomainUuid' => function () {
                    return Session::get('domain_uuid');
                },
                /*'destinationTypes' => function () {
                    return [
                        [
                            "name" => "Inbound",
                            "value" => "inbound"
                        ],
                        [
                            "name" => "Outbound",
                            "value" => "outbound"
                        ],
                        [
                            "name" => "Local",
                            "value" => "local"
                        ]
                    ];
                },*/
                //'deviceGlobalView' => (isset($this->filters['showGlobal']) && $this->filters['showGlobal']),
                //'routePhoneNumbersStore' => route('phone-numbers.store'),
                //'routePhoneNumbersOptions' => route('phoneNumbers.options'),
                //'routeDevicesOptions' => route('devices.options'),
                //'routeDevicesBulkUpdate' => route('devices.bulkUpdate'),
                //'routePhoneNumbers' => route('phone-numbers.index'),
                //'routeSendEventNotifyAll' => route('extensions.send-event-notify-all')
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
                'name' => $domain->domain_name,
                'value' => $domain->domain_uuid
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

        //if (isset($this->filters['showGlobal']) and $this->filters['showGlobal']) {
            // Access domains through the session and filter extensions by those domains
        //    $domainUuids = Session::get('domains')->pluck('domain_uuid');
        //}

        //foreach ($data as $phoneNumber) {
            /*$device->device_address_tokenized = $device->device_address;
            $device->device_address = formatMacAddress($device->device_address);
            if ($device->lines()->first() && $device->lines()->first()->extension()) {
                $device->extension = $device->lines()->first()->extension()->extension;
                $device->extension_description = ($device->lines()->first()->extension()->effective_caller_id_name) ? '('.trim($device->lines()->first()->extension()->effective_caller_id_name).')' : '';
                $device->extension_uuid = $device->lines()->first()->extension()->extension_uuid;
                $device->extension_edit_path = route('extensions.edit', $device->lines()->first()->extension());
                $device->send_notify_path = route('extensions.send-event-notify',
                    $device->lines()->first()->extension());
            }*/
            //$phoneNumber->edit_path = route('phone-numbers.edit', $phoneNumber);
            //$phoneNumber->destroy_path = route('phone-numbers.destroy', $phoneNumber);
        //}
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
            logger($e->getMessage());
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
        $inputs = $request->validated();

        try {

            $instance = $this->model;
            $instance->fill([
                'domain_uuid' => $inputs['domain_uuid'],
                'fax_uuid' => $inputs['fax_uuid'] ?? null,
                'destination_type' => 'inbound',
                'destination_prefix' => $inputs['destination_prefix'],
                'destination_number' => $inputs['destination_number'],
                'destination_actions' => $inputs['destination_actions'] ?? null,
                'destination_hold_music' => $inputs['destination_hold_music'] ?? null,
                'destination_description' => $inputs['destination_description'] ?? null,
                'destination_enabled' => $inputs['destination_enabled'] ?? true,
                'destination_record' => $inputs['destination_record'] ?? false,
                'destination_cid_name_prefix' => $inputs['destination_cid_name_prefix'] ?? null,
                'destination_accountcode' => $inputs['destination_accountcode'] ?? null,
                'destination_distinctive_ring' => $inputs['destination_distinctive_ring'] ?? null,
            ]);
            $instance->save();

            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage());

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
        /*if (!$request->ajax()) {
            return response()->json([
                'message' => 'XHR request expected'
            ], 405);
        }

        $phone_number->update_path = route('phone-numbers.update', $phone_number);

        return response()->json([
            'status' => 'success',
            'phone_number' => $phone_number
        ]);*/
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

            logger($phone_number);
            logger($inputs);
            $phone_number->update($inputs);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Item updated.']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
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
     * @return Response
     */
    public function destroy(Destinations $phone_number)
    {
        $phone_number->delete();

        return Inertia::render('Phonenumbers', [
            'data' => function () {
                return $this->getData();
            },
            'status' => 'success',
            'phone_number' => $phone_number,
            'message' => 'Phone number has been deleted'
        ]);
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
            logger($e->getMessage());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
}
