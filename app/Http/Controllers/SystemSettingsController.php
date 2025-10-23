<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Domain;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\PaymentGateway;
use App\Models\DefaultSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\UpdateAccountSettingsRequest;

class SystemSettingsController extends Controller
{
    public $model;
    protected $viewName = 'SystemSettings';

    public function __construct()
    {
        $this->model = new DefaultSettings();
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
                // 'data' => function () {
                //     return $this->getData();
                // },

                'routes' => [
                    'dashboard_route' => route('dashboard'),
                    'settings_update' => route('system-settings.update'),
                    'payment_gateways' => route('system-settings.payment_gateways'),
                    'payment_gateway_update' => route('gateway.update'),
                    'payment_gateway_deactivate' => route('gateway.deactivate'),
                    'transcription_providers_route' => route('call-transcription.providers'),
                    'transcription_policy_route' => route('call-transcription.policy'),
                    'transcription_policy_store_route' => route('call-transcription.policy.store'),
                    'transcription_policy_destroy_route' => route('call-transcription.policy.destroy'),
                ],
                'permissions' => function () {
                    return $this->getUserPermissions();
                },
            ]
        );
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


    public function getPaymentGatewayData(Request $request)
    {
        // 1) Permission check (adjust the permission slug as needed)
        // if (! userCheckPermission('system_settings_view')) {
        //     return response()->json([
        //         'messages' => ['error' => ['Access denied.']],
        //     ], 403);
        // }

        try {
            // 2) Fetch + transform
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

            // 3) Success response
            return response()->json($gateways);
        } catch (\Throwable $e) {
            // 4) Log & error response
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
        $permissions['call_transcription_view'] = userCheckPermission('call_transcription_view');

        return $permissions;
    }
}
