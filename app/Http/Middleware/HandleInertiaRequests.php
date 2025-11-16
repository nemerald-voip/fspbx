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
                'can' => fn() => $this->getPermissions(),
            ],

            'flash' => [
                'message' => fn() => $request->session()->get('message'),
                'error' =>  fn() => $request->session()->get('error'),
            ],
        ]);
    }

    public function getPermissions()
    {
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

        $permissions['manage_cloud_provision_providers'] = userCheckPermission('manage_cloud_provision_providers');
        $permissions['polycom_api_token_edit'] = userCheckPermission('polycom_api_token_edit');

        $permissions['cdrs_view_global'] = userCheckPermission('xml_cdr_all');
        $permissions['cdrs_export'] = userCheckPermission('xml_cdr_export');
        $permissions['cdr_view_details'] = userCheckPermission('xml_cdr_details');


        $permissions['voicemail_create'] = userCheckPermission('voicemail_add');
        $permissions['voicemail_update'] = userCheckPermission('voicemail_edit');
        $permissions['voicemail_destroy'] = userCheckPermission('voicemail_delete');
        $permissions['voicemail_message_index'] = userCheckPermission('voicemail_message_view');

        $permissions['ring_group_create'] = userCheckPermission('ring_group_add');
        $permissions['ring_group_update'] = userCheckPermission('ring_group_edit');
        $permissions['ring_group_destroy'] = userCheckPermission('ring_group_delete');

        $permissions['registrations_view_global'] = userCheckPermission('registration_all');

        $permissions['active_calls_view_global'] = userCheckPermission('call_active_all');

        $permissions['virtual_receptionist_create'] = userCheckPermission('ivr_menu_add');
        $permissions['virtual_receptionist_update'] = userCheckPermission('ivr_menu_edit');
        $permissions['virtual_receptionist_destroy'] = userCheckPermission('ivr_menu_delete');

        $permissions['wakeup_calls_list_view'] = userCheckPermission('wakeup_calls_list_view');
        $permissions['wakeup_calls_create'] = userCheckPermission('wakeup_calls_create');
        $permissions['wakeup_calls_edit'] = userCheckPermission('wakeup_calls_edit');
        $permissions['wakeup_calls_delete'] = userCheckPermission('wakeup_calls_delete');
        $permissions['wakeup_calls_view_global'] = userCheckPermission('wakeup_calls_all');
        $permissions['wakeup_calls_view_settings'] = userCheckPermission('wakeup_calls_view_settings');

        $permissions['contact_create'] = userCheckPermission('contact_add');
        $permissions['contact_edit'] = userCheckPermission('contact_edit');
        $permissions['contact_delete'] = userCheckPermission('contact_delete');
        $permissions['contact_upload'] = userCheckPermission('contact_upload');

        $permissions['account_settings_index'] = userCheckPermission('account_settings_list_view');

        $permissions['business_hours_create'] = userCheckPermission('business_hours_create');
        $permissions['business_hours_update'] = userCheckPermission('business_hours_update');
        $permissions['business_hours_destroy'] = userCheckPermission('business_hours_delete');


        $permissions['group_create'] = userCheckPermission('group_add');
        $permissions['group_update'] = userCheckPermission('group_edit');
        $permissions['group_destroy'] = userCheckPermission('group_delete');
        $permissions['domain_groups_view'] = userCheckPermission('domain_groups_list_view');

        $permissions['user_create'] = userCheckPermission('user_add');
        $permissions['user_update'] = userCheckPermission('user_edit');
        $permissions['user_destroy'] = userCheckPermission('user_delete');

        $permissions['extension_create'] = userCheckPermission('extension_add');
        $permissions['extension_update'] = userCheckPermission('extension_edit');
        $permissions['extension_destroy'] = userCheckPermission('extension_delete');
        $permissions['extension_upload'] = userCheckPermission('extension_import');

        $permissions['fax_server_create'] = userCheckPermission('fax_add');
        $permissions['fax_server_update'] = userCheckPermission('fax_edit');
        $permissions['fax_server_destroy'] = userCheckPermission('fax_delete');
        $permissions['fax_send'] = userCheckPermission('fax_send');
        $permissions['fax_inbox_view'] = userCheckPermission('fax_inbox_view');
        $permissions['fax_sent_view'] = userCheckPermission('fax_sent_view');
        $permissions['fax_log_view'] = userCheckPermission('fax_log_view');

        // logger($permissions);
        return $permissions;
    }
}
