<?php

namespace App\Http\Middleware;

use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use PhpParser\Node\Expr\FuncCall;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'layouts/app-inertia';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'menus' => Session::get('menu'),

            'domainSelectPermission' => Session::get('domain_select'),

            'selectedDomain' => Session::get('domain_description'),

            'selectedDomainUuid' => Session::get('domain_uuid'),

            'domains' => Session::get("domains"),

            'csrf_token' => csrf_token(),

            'auth' => [
                'can' => fn () => $this->getPermissions(),
            ],

            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' =>  fn () => $request->session()->get('error'),
            ],
        ]);
    }

    public function getPermissions() {
        $permissions = [];
        $permissions['domain_select'] = session('domain_select');
        $permissions['device_create'] = userCheckPermission('device_add');
        $permissions['device_view_global'] = userCheckPermission('device_all');
        $permissions['device_destroy'] = userCheckPermission('device_delete');
        $permissions['device_update'] = userCheckPermission('device_edit');
        $permissions['device_import'] = userCheckPermission('device_import'); //not yet implemented
        $permissions['device_export'] = userCheckPermission('device_export'); //not yet implemented
        $permissions['device_edit_domain'] = userCheckPermission('device_domain');
        $permissions['device_edit_address'] = userCheckPermission('device_address');
        $permissions['device_edit_line'] = userCheckPermission('device_line_edit');
        $permissions['device_edit_template'] = userCheckPermission('device_template');

        $permissions['device_profile_index'] = userCheckPermission('device_profile_view');

        $permissions['destination_add'] = userCheckPermission('destination_add');
        $permissions['destination_edit'] = userCheckPermission('destination_edit');
        $permissions['destination_delete'] = userCheckPermission('destination_delete');
        $permissions['destination_edit_domain'] = userCheckPermission('destination_domain');

        $permissions['cdrs_view_global'] = userCheckPermission('xml_cdr_all');
        $permissions['cdrs_export'] = userCheckPermission('xml_cdr_export');

        $permissions['voicemail_create'] = userCheckPermission('voicemail_add');
        $permissions['voicemail_update'] = userCheckPermission('voicemail_edit');
        $permissions['voicemail_destroy'] = userCheckPermission('voicemail_delete');
        $permissions['voicemail_message_index'] = userCheckPermission('voicemail_message_view');

        $permissions['registrations_view_global'] = userCheckPermission('registration_all');

        $permissions['virtual_receptionist_create'] = userCheckPermission('ivr_menu_add');
        $permissions['virtual_receptionist_update'] = userCheckPermission('ivr_menu_edit');
        $permissions['virtual_receptionist_destroy'] = userCheckPermission('ivr_menu_delete');

        $permissions['account_settings_index'] = userCheckPermission('account_settings_list_view');

        // logger($permissions);
        return $permissions;
    }
}
