<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Models\PaymentGateway;
use App\Models\DefaultSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Services\Settings\SettingsManagementService;
use App\Services\Settings\SystemSettingsSchema;

class SystemSettingsController extends Controller
{
    public $model;
    protected $viewName = 'SystemSettings';

    public function __construct(
        private readonly SettingsManagementService $settings,
        private readonly SystemSettingsSchema $schema,
    ) {
        $this->model = new DefaultSettings();
    }

    /**
     * Display a listing of the resource.
     *
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
                'routes' => [
                    'dashboard_route' => route('dashboard'),
                    'settings_update' => route('system-settings.update'),
                    'payment_gateways' => route('system-settings.payment_gateways'),
                    'payment_gateway_update' => route('gateway.update'),
                    'payment_gateway_deactivate' => route('gateway.deactivate'),
                    'payment_gateway_test' => route('gateway.test'),
                    'transcription_providers_route' => route('call-transcription.providers'),
                    'transcription_policy_route' => route('call-transcription.policy'),
                    'transcription_policy_store_route' => route('call-transcription.policy.store'),
                    'transcription_policy_destroy_route' => route('call-transcription.policy.destroy'),
                    'assemblyai_route' => route('call-transcription.assemblyai'),
                    'assemblyai_store_route' => route('call-transcription.assemblyai.store'),
                ],
                // Schema-driven General tab. Same declarative fields as the
                // account surface, but these are the global default_settings
                // values every account inherits unless it sets its own.
                'settings_schema' => $this->schema->fields(),
                'settings_options' => function () {
                    return $this->schema->options();
                },
                'settings_values' => function () {
                    return $this->schema->values();
                },
                'permissions' => function () {
                    return $this->getUserPermissions();
                },
            ]
        );
    }

    /**
     * Update the global default settings shown on the General tab.
     */
    public function update(UpdateSystemSettingsRequest $request): JsonResponse
    {
        if (!userCheckPermission('default_setting_edit')) {
            return response()->json(['errors' => ['authorization' => ['Access denied.']]], 403);
        }

        try {
            DB::beginTransaction();

            $this->applyDefaults($request->validated()['settings'] ?? []);

            DB::commit();

            return response()->json([
                'messages' => ['server' => ['Settings updated successfully.']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while processing your request.']]
            ], 500);
        }
    }

    /**
     * Persist the submitted {key: value} map against the schema's default
     * settings. The schema carries each field's category/subcategory/name.
     * A default is the root of the inheritance chain, so an empty value is
     * never written (it would leave nothing to fall back to); an unchanged
     * value is skipped; a changed value updates the default in place.
     *
     * @param array<string, mixed> $submitted
     */
    private function applyDefaults(array $submitted): void
    {
        $fields = collect($this->schema->fields())->keyBy('key');

        $existing = DefaultSettings::query()
            ->whereIn('default_setting_subcategory', $fields->pluck('subcategory')->all())
            ->get()
            ->keyBy('default_setting_subcategory');

        foreach ($submitted as $key => $value) {
            $field = $fields->get($key);
            if (! $field) {
                continue; // validated already; defensive
            }

            $value = is_string($value) ? trim($value) : $value;
            if ($value === null || $value === '') {
                continue; // never blank a global default
            }

            $row = $existing->get($field['subcategory']);
            if ($row && (string) $row->default_setting_value === (string) $value) {
                continue; // unchanged
            }

            $this->settings->saveDefault([
                'default_setting_category' => $field['category'],
                'default_setting_subcategory' => $field['subcategory'],
                'default_setting_name' => $field['name'],
                'default_setting_value' => $value,
                'default_setting_order' => $row?->default_setting_order,
                'default_setting_enabled' => $row ? (bool) $row->default_setting_enabled : true,
                'default_setting_description' => $row?->default_setting_description,
            ], $row);
        }
    }

    public function getPaymentGatewayData(Request $request)
    {
        try {
            $gateways = PaymentGateway::with('settings')->get()
                ->map(function ($gw) {
                    return [
                        'uuid'       => $gw->uuid,
                        'slug'       => $gw->slug,
                        'name'       => $gw->name,
                        'is_enabled' => (bool) $gw->is_enabled,
                        'settings'   => $gw->settings->pluck('setting_value', 'setting_key')->toArray(),
                    ];
                });

            return response()->json($gateways);
        } catch (\Throwable $e) {
            logger(
                'PaymentGateway fetch error: '
                    . $e->getMessage()
                    . ' in ' . $e->getFile()
                    . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while loading payment gateways.']],
            ], 500);
        }
    }

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['payment_gateways_view'] = userCheckPermission('payment_gateways_view');
        $permissions['call_transcription_settings_view'] = userCheckPermission('call_transcription_settings_view');
        $permissions['default_setting_view'] = userCheckPermission('default_setting_view');
        $permissions['default_setting_edit'] = userCheckPermission('default_setting_edit');

        return $permissions;
    }
}
