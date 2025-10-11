<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use App\Models\Domain;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\UpdateAccountSettingsRequest;

class LogsController extends Controller
{
    protected $viewName = 'Logs';

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return Redirector|Response|RedirectResponse|Application
     */
    public function index()
    {
        if (!userCheckPermission("logs_list_view")) {
            return redirect('/');
        }

        $domain_uuid = session('domain_uuid');
        $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfDay()->setTimeZone('UTC');
        $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))->endOfDay()->setTimeZone('UTC');

        return Inertia::render(
            $this->viewName,
            [
                'startPeriod' => function () use ($startPeriod) {
                    return $startPeriod;
                },
                'endPeriod' => function ()  use ($endPeriod) {
                    return $endPeriod;
                },
                'timezone' => function () use ($domain_uuid) {
                    return get_local_time_zone($domain_uuid);
                },
                'routes' => [

                    'email_logs' => route('email-logs.index'),
                    'inbound_webhooks' => route('inbound-webhooks.index'),

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
