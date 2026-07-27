<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Domain;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Services\PmsProviderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\UpdateAccountSettingsRequest;
use App\Services\Settings\AccountSettingsSchema;
use App\Services\Settings\SettingsManagementService;

class AccountSettingsController extends Controller
{
    public $model;
    protected $viewName = 'AccountSettings';

    public function __construct(
        private readonly SettingsManagementService $settings,
        private readonly DomainSettingsController $domainSettingsController,
        private readonly AccountSettingsSchema $schema,
    )
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
                    'pms_provider' => route('account-settings.pms-provider'),
                    'pms_provider_update' => route('account-settings.pms-provider.update'),
                    'call_webhook_save' => route('call-webhooks.save'),
                    'call_webhook_show' => route('call-webhooks.show'),
                    'call_webhook_test' => route('call-webhooks.test'),
                    'call_webhook_rotate_secret' => route('call-webhooks.rotate-secret'),
                    'call_webhook_destroy' => route('call-webhooks.destroy'),

                    //'bulk_update' => route('devices.bulk.update'),
                ],
                'pms_provider_options' => app(PmsProviderSettings::class)->options(),
                // Schema-driven General-tab settings: the declarative field
                // list, its resolved option lists, and this account's own
                // override values (null = inheriting the default).
                'settings_schema' => $this->schema->fields(),
                'settings_options' => function () {
                    return $this->schema->options(Domain::query()->find(session('domain_uuid')));
                },
                'settings_values' => function () {
                    $domain = Domain::query()->find(session('domain_uuid'));

                    return $domain ? $this->schema->values($domain) : [];
                },
                'permissions' => function () {
                    return $this->getUserPermissions();
                },
                'domainSettings' => function () {
                    if (! userCheckPermission('domain_setting_view')) {
                        return null;
                    }

                    $domain = Domain::query()->find(session('domain_uuid'));

                    return $domain ? $this->domainSettingsController->pageProps($domain) : null;
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

            $this->applySettings($domain, $data['settings'] ?? []);

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
     * Persist the submitted {key: value} settings map against the schema.
     * The schema carries each field's category/subcategory/name, so there's
     * no guessing which form fields are settings and no subcategory lookup.
     * An empty value removes the account's own override (reverting to the
     * inherited default); a changed value upserts it; an unchanged value is
     * skipped so re-saving the tab writes nothing.
     *
     * @param array<string, mixed> $submitted
     */
    private function applySettings(Domain $domain, array $submitted): void
    {
        $fields = collect($this->schema->fields())->keyBy('key');

        $existing = DomainSettings::query()
            ->where('domain_uuid', $domain->domain_uuid)
            ->whereIn('domain_setting_subcategory', $fields->pluck('subcategory')->all())
            ->get()
            ->keyBy('domain_setting_subcategory');

        foreach ($submitted as $key => $value) {
            $field = $fields->get($key);
            if (! $field) {
                continue; // validated already; defensive against unknown keys
            }

            $value = is_string($value) ? trim($value) : $value;
            $row = $existing->get($field['subcategory']);

            if ($value === null || $value === '') {
                if ($row) {
                    $this->settings->revertDomain($domain, [$row->domain_setting_uuid]);
                }
                continue;
            }

            if ($row && (string) $row->domain_setting_value === (string) $value) {
                continue; // unchanged
            }

            $this->settings->saveDomainOverride($domain, [
                'domain_setting_category' => $field['category'],
                'domain_setting_subcategory' => $field['subcategory'],
                'domain_setting_name' => $field['name'],
                'domain_setting_value' => $value,
                'domain_setting_order' => $row?->domain_setting_order,
                'domain_setting_enabled' => true,
                'domain_setting_description' => $row?->domain_setting_description,
            ], $row);
        }
    }

    public function pmsProvider(PmsProviderSettings $settings): JsonResponse
    {
        if (!userCheckPermission("account_settings_list_view")) {
            return response()->json(['errors' => ['authorization' => ['Access denied.']]], 403);
        }

        return response()->json([
            'provider' => $settings->provider(session('domain_uuid')),
            'options' => $settings->options(),
        ]);
    }

    public function updatePmsProvider(Request $request, PmsProviderSettings $settings): JsonResponse
    {
        if (!userCheckPermission("account_settings_list_view")) {
            return response()->json(['errors' => ['authorization' => ['Access denied.']]], 403);
        }

        $validated = $request->validate([
            'pms_provider' => ['required', 'string', 'in:charpms,tigertms'],
        ]);

        $settings->saveProvider((string) session('domain_uuid'), $validated['pms_provider']);

        return response()->json([
            'provider' => $validated['pms_provider'],
            'messages' => ['server' => ['PMS provider updated.']],
        ]);
    }

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['location_view'] = userCheckPermission('location_view');
        $permissions['location_create'] = userCheckPermission('location_create');
        $permissions['location_update'] = userCheckPermission('location_update');
        $permissions['location_delete'] = userCheckPermission('location_delete');
        $permissions['call_webhook_view'] = userCheckPermission('call_webhook_view');
        $permissions['call_webhook_create'] = userCheckPermission('call_webhook_create');
        $permissions['call_webhook_update'] = userCheckPermission('call_webhook_update');
        $permissions['call_webhook_delete'] = userCheckPermission('call_webhook_delete');
        $permissions['call_webhook_test'] = userCheckPermission('call_webhook_test');

        return $permissions;
    }
}
