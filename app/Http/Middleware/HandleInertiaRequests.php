<?php

namespace App\Http\Middleware;

use Inertia\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Support\Localization\LocaleRegistry;

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
            'locale' => fn() => app()->getLocale(),

            // Base-first list of locale codes to merge for the frontend's
            // $t()/trans() (e.g. ['en-us', 'es-es', 'es-419', 'es-mx']) --
            // mirrors the dialect-chain merge LocaleFileLoader already does
            // for backend __() calls, since the frontend loads its JSON
            // bundle directly via Vite (see resources/js/vue.js) and has no
            // other way to know a dialect's fallback parents.
            'localeChain' => fn() => app(LocaleRegistry::class)->chain(app()->getLocale()),

            'menus' => Session::get('menu'),

            'menuUsesCatalogTranslations' => fn() => $this->menuUsesCatalogTranslations(),

            'domainSelectPermission' => Session::get('domain_select'),

            'selectedDomain' => Session::get('domain_description'),

            'selectedDomainUuid' => Session::get('domain_uuid'),

            'domains' => Session::get("domains"),

            'csrf_token' => csrf_token(),

            'auth' => [
                'user' => fn() => $request->user() ? [
                    'name'  => $request->user()->name_formatted ?: $request->user()->username,
                    'email' => $request->user()->user_email,
                ] : null,
                'can' => fn() => $this->getPermissions(),
            ],

            'flash' => [
                'message' => fn() => $request->session()->get('message'),
                'error' =>  fn() => $request->session()->get('error'),
            ],
        ]);
    }

    private function menuUsesCatalogTranslations(): bool
    {
        if (Session::has('user.menu_uses_catalog_translations')) {
            return (bool) Session::get('user.menu_uses_catalog_translations');
        }

        $menuUuid = Session::get('user.menu_uuid');
        $usesCatalog = $menuUuid
            && DB::table('v_menus')
                ->where('menu_uuid', $menuUuid)
                ->where('menu_name', 'fspbx')
                ->exists();

        Session::put('user.menu_uses_catalog_translations', (bool) $usesCatalog);

        return (bool) $usesCatalog;
    }

    public function getPermissions()
    {
        $permissions = [];
        $permissions['domain_select'] = session('domain_select');

        $permissions['cdrs_view_global'] = userCheckPermission('xml_cdr_all');
        $permissions['cdrs_export'] = userCheckPermission('xml_cdr_export');
        $permissions['cdr_view_details'] = userCheckPermission('xml_cdr_details');

        $permissions['ring_group_create'] = userCheckPermission('ring_group_add');
        $permissions['ring_group_update'] = userCheckPermission('ring_group_edit');
        $permissions['ring_group_destroy'] = userCheckPermission('ring_group_delete');

        $permissions['contact_create'] = userCheckPermission('contact_add');
        $permissions['contact_edit'] = userCheckPermission('contact_edit');
        $permissions['contact_delete'] = userCheckPermission('contact_delete');
        $permissions['contact_upload'] = userCheckPermission('contact_upload');

        $permissions['business_hours_create'] = userCheckPermission('business_hours_create');
        $permissions['business_hours_update'] = userCheckPermission('business_hours_update');
        $permissions['business_hours_destroy'] = userCheckPermission('business_hours_delete');


        // logger($permissions);
        return $permissions;
    }
}
