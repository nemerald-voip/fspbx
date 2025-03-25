<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Faxes;
use Inertia\Response;
use App\Models\Domain;
use App\Models\Dialplans;
use Illuminate\Support\Str;
use App\Models\Destinations;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\StorePhoneNumberRequest;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\UpdateAccountSettingsRequest;

class AccountSettingsController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'AccountSettings';
    protected $searchable = ['destination_number', 'destination_data', 'destination_description'];

    public function __construct()
    {
        $this->model = new Domain();
    }

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return Redirector|Response|RedirectResponse|Application
     */
    public function index()
    {
        if (!userCheckPermission("account_settings_list_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'navigation' => function () {
                    return $this->getNavigation();
                },
                'timezones' => function () {
                    return getGroupedTimezones();
                },
                'routes' => [
                    'update' => route('account-settings.update'),
                    // 'store' => route('phone-numbers.store'),
                    // 'select_all' => route('phone-numbers.select.all'),
                    // 'bulk_update' => route('phone-numbers.bulk.update'),
                    // 'bulk_delete' => route('phone-numbers.bulk.delete'),
                    // 'item_options' => route('phone-numbers.item.options'),
                    //'bulk_delete' => route('messages.settings.bulk.delete'),
                    //'bulk_update' => route('devices.bulk.update'),
                ],

            ]
        );
    }


    /**
     * @return Collection
     */
    public function getData()
    {
        // // Check if search parameter is present and not empty
        // if (!empty(request('filterData.search'))) {
        //     $this->filters['search'] = request('filterData.search');
        // }

        // // Add sorting criteria
        // $this->sortField = request()->get('sortField', 'destination_number'); // Default to 'destination'
        // $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to ascending

        $data = $this->builder($this->filters);

        $data = $data->first(); // This will return a collection

        // logger($data);

        return $data;
    }


    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = []): Builder
    {
        $data =  $this->model::query();

        $domainUuid = Session::get('domain_uuid');
        $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);

        $data->select(
            'domain_uuid',
            'domain_name',
            'domain_description',
            'domain_enabled',
        );

        $data->with(['settings' => function ($query) {
            $query->select('domain_uuid', 'domain_setting_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_value', 'domain_setting_enabled');
        }]);

        return $data;
    }


    /**
     * @return Array
     */
    public function getNavigation()
    {
        $navigation = [
            [
                'name' => 'Settings',
                'icon' => 'Cog6ToothIcon',
                'slug' => 'settings',
            ],
            [
                'name' => 'Billing',
                'icon' => 'CreditCardIcon',
                'slug' => 'billing',
            ],
        ];

        return $navigation;
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
                    $destination_actions[] = $this->buildDestinationAction($option);
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
     * Update the specified resource in storage.
     *
     * @param  UpdateAccountSettingsRequest  $request
     * @return JsonResponse
     */
    public function update(UpdateAccountSettingsRequest $request)
    {
        try {
            // Begin Transaction
            DB::beginTransaction();
            // Retrieve validated data
            $data = $request->validated();

            // Update domain details
            $domain = Domain::where('domain_uuid', $data['domain_uuid'])->first();

            if (!$domain) {
                throw new \Exception('Domain not found.');
            }

            $domain->update([
                'domain_name'        => $data['domain_name'],
                'domain_description' => $data['domain_description'],
                'domain_enabled'     => $data['domain_enabled'],
            ]);


            // Update settings if provided
            if (!empty($data['settings'])) {
                foreach ($data['settings'] as $setting) {
                    DomainSettings::where('domain_setting_uuid', $setting['uuid'])
                        ->update(['domain_setting_value' => $setting['value']]);
                }
            }

            // Commit Transaction
            DB::commit();

            return response()->json([
                'messages' => ['server' => ['Settings updated successfully.']],
            ], 200);
        } catch (\Exception $e) {
            // Rollback Transaction if any error occurs
            DB::rollBack();

            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while processing your request.']]
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
}
