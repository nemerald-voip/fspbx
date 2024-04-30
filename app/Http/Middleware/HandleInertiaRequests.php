<?php

namespace App\Http\Middleware;

use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

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
                'error' => fn () =>  $request->session()->get('error'),
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
        
        // logger($permissions);
        return $permissions;
    }
}
