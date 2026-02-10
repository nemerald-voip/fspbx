<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Domain;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\UpdateAccountSettingsRequest;

class AccountSettingsController extends Controller
{
    public $model;
    protected $viewName = 'AccountSettings';

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
                'timezones' => function () {
                    return getGroupedTimezones();
                },
                'routes' => [
                    'dashboard_route' => route('dashboard'),
                    'settings_update' => route('account-settings.update'),
                    'emergency_calls' => route('emergency-calls.index'),
                    'emergency_calls_store' => route('emergency-calls.store'),
                    'emergency_calls_item_options' => route('emergency-calls.item.options'),
                    'emergency_calls_bulk_delete' => route('emergency-calls.bulk.delete'),
                    'emergency_calls_service_status' => route('emergency-calls.check.service.status'),
                    'locations' => route('locations.index'),
                    'locations_store' => route('locations.store'),
                    'locations_bulk_delete' => route('locations.bulk.delete'),
                    'templates' => route('provisioning-templates.index'),
                    'templates_item_options' =>route('provisioning-templates.item.options'),
                    'templates_store' => route('provisioning-templates.store'),
                    'templates_bulk_delete' => route('provisioning-templates.bulk.delete'),
                    'hotel_rooms' => route('hotel-rooms.index'),
                    'hotel_rooms_item_options' =>route('hotel-rooms.item.options'),
                    'hotel_rooms_bulk_delete' => route('hotel-rooms.bulk.delete'),
                    'hotel_room_status' => route('hotel-room-status.index'),
                    'hotel_room_status_item_options' =>route('hotel-room-status.item.options'),
                    'hotel_room_status_bulk_delete' =>route('hotel-room-status.bulk.delete'),
                    'housekeeping_item_options' =>route('housekeeping.item.options'),
                    'transcription_providers_route' => route('call-transcription.providers'),
                    'transcription_policy_route' => route('call-transcription.policy'),
                    'transcription_policy_store_route' => route('call-transcription.policy.store'),
                    'transcription_policy_destroy_route' => route('call-transcription.policy.destroy'),
                    'assemblyai_route' => route('call-transcription.assemblyai'),
                    'assemblyai_store_route' => route('call-transcription.assemblyai.store'),

                    //'bulk_update' => route('devices.bulk.update'),
                ],
                'permissions' => function () {
                    return $this->getUserPermissions();
                },

            ]
        );
    }


    /**
     * @return Collection
     */
    public function getData()
    {
        $data = $this->builder();

        $data = $data->first(); // This will return a collection

        $data->append('named_settings');

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

        // $data->with(['settings' => function ($query) {
        //     $query->select('domain_uuid', 'domain_setting_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_value', 'domain_setting_enabled');
        // }]);

        return $data;
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

            // Apply all existing‐row updates
            if (!empty($data['updatedSettings'])) {
                foreach ($data['updatedSettings'] as $s) {
                    // if the new value is NULL, we disable this setting
                    $enabled = is_null($s['domain_setting_value'])
                        ? false
                        : $s['domain_setting_enabled'];

                    DomainSettings::where('domain_setting_uuid', $s['domain_setting_uuid'])
                        ->update([
                            'domain_setting_value'   => $s['domain_setting_value'],
                            'domain_setting_enabled' => $enabled,
                        ]);
                }
            }

            // 3️⃣ Prepare and insert brand‐new settings in one go
            if (!empty($data['newSettings'])) {
                // extract the subcategories to look up
                $subs = collect($data['newSettings'])
                    ->pluck('domain_setting_subcategory')
                    ->unique()
                    ->all();

                // single query to get their default definitions
                $defaults = DB::table('v_default_settings')
                    ->whereIn('default_setting_subcategory', $subs)
                    ->get()
                    ->keyBy('default_setting_subcategory');

                foreach ($data['newSettings'] as $new) {
                    $sub = $new['domain_setting_subcategory'];

                    // skip any subcategory we couldn't find in defaults
                    if (! isset($defaults[$sub])) {
                        logger("No default_setting found for subcategory: {$sub}");
                        continue;
                    }

                    $def = $defaults[$sub];

                    // create the new override
                    $domain->settings()->create([
                        'domain_setting_uuid'        => Str::uuid()->toString(),
                        'domain_setting_category'    => $def->default_setting_category,
                        'domain_setting_subcategory' => $sub,
                        'domain_setting_name'        => $def->default_setting_name,
                        'domain_setting_value'       => $new['domain_setting_value'],
                        'domain_setting_enabled'     => true,
                        'domain_setting_description' => $def->default_setting_description,
                    ]);
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

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['location_view'] = userCheckPermission('location_view');
        $permissions['location_create'] = userCheckPermission('location_create');
        $permissions['location_update'] = userCheckPermission('location_update');
        $permissions['location_delete'] = userCheckPermission('location_delete');

        return $permissions;
    }
}
