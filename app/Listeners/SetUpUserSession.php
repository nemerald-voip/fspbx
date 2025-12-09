<?php

namespace App\Listeners;

use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Session;
use App\Services\SessionDomainService;


class SetUpUserSession
{
    protected SessionDomainService $sessionDomainService;

    /**
     * Create the event listener.
     */
    public function __construct(SessionDomainService $sessionDomainService)
    {
        $this->sessionDomainService = $sessionDomainService;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(Login $event)
    {
        //Push variable to Session
        Session::put('user_uuid', $event->user->user_uuid);
        Session::put('user.user_uuid', $event->user->user_uuid);
        Session::put('user.user_email', $event->user->user_email);
        Session::put('user.domain_uuid', $event->user->domain_uuid);
        $domain = Domain::where('domain_uuid', $event->user->domain_uuid)->first();
        Session::put('user.domain_name', $domain->domain_name);

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();


        //get the groups assigned to the user and then store in Session variable
        $groups = DB::table('v_user_groups')
            ->join('v_groups', 'v_user_groups.group_uuid', '=', 'v_groups.group_uuid')
            ->where([
                ['v_user_groups.user_uuid', '=', $event->user->user_uuid],
                //['v_user_groups.user_uuid', '=', "71d03aac-02e2-444f-90de-14a439892b0c"], //reseller
                ['v_user_groups.domain_uuid', '=', $event->user->domain_uuid],
            ])
            ->get([
                'v_user_groups.group_uuid',
                'v_user_groups.domain_uuid',
                'v_user_groups.user_uuid',
                'v_user_groups.group_uuid',
                'v_user_groups.group_name',
                'group_level'
            ]);

        Session::put('user.groups', $groups);

        //get the users highest level group
        Session::put('user.group_level', 0);
        foreach ($groups as $group) {
            if (Session::get('user.group_level') < $group->group_level) {
                Session::put('user.group_level', $group->group_level);
                Session::put('user.group_name', $group->group_name);
                $_SESSION["user"]["group_level"] = $group->group_level;
            }
            $group_names[] = $group->group_name;
            $group_uuids[] = $group->group_uuid;
        }

        // set menu id for the user
        $menu_uuid = DB::table('v_user_settings')
            ->where([
                ['user_uuid', '=', $event->user->user_uuid],
                ['user_setting_subcategory', '=', 'menu'],
            ])
            ->value('user_setting_value');
        // user_setting_value": "9c143165-fda6-4539-a607-4184eb05b065"

        // If user doesn't have a custom menu check if there is one set on the domain level
        if (is_null($menu_uuid)) {

            $menu_uuid = DomainSettings::where('domain_setting_category', 'domain')
                ->where('domain_setting_subcategory', 'menu')
                ->where('domain_uuid', $domain->domain_uuid)
                ->where('domain_setting_enabled', true)
                ->value('domain_setting_value');
        }

        // If domain doesn't have a custom menu assign a default one
        if (is_null($menu_uuid)) {
            $menu_uuid = DefaultSettings::where('default_setting_category', 'domain')
                ->where('default_setting_subcategory', 'menu')
                ->value('default_setting_value');
        }

        if (!is_null($menu_uuid)) {
            Session::put('user.menu_uuid', $menu_uuid);

            // Add variables required by Fusion to built the menu
            $_SESSION['domain']['menu']['uuid'] = $menu_uuid;
            $_SESSION['domain']['language']['code'] = get_domain_setting('language');
            // $_SESSION['groups'][0]['group_name'] = Session::get('user.group_name');
        }

        // Build top level menu
        $main_menu = DB::table('v_menu_items')
            ->join('v_menu_item_groups', 'v_menu_item_groups.menu_item_uuid', '=', 'v_menu_items.menu_item_uuid')
            ->where('v_menu_items.menu_uuid', '=', $menu_uuid)
            ->whereNull('v_menu_items.menu_item_parent_uuid')
            ->whereIn('v_menu_item_groups.group_uuid', $group_uuids)
            ->distinct()
            ->orderBy("menu_item_order")
            ->get([
                'v_menu_items.menu_item_uuid',
                'v_menu_items.menu_item_title',
                'v_menu_items.menu_item_link',
                'menu_item_icon',
                'menu_item_order',
            ]);


        //Build child menus
        foreach ($main_menu as $menu) {
            $child_menu = DB::table('v_menu_items')
                ->join('v_menu_item_groups', 'v_menu_item_groups.menu_item_uuid', '=', 'v_menu_items.menu_item_uuid')
                ->where([
                    ['menu_item_parent_uuid', '=', $menu->menu_item_uuid],
                    //['menu_item_parent_uuid', '=', ''],
                ])
                ->whereIn('v_menu_item_groups.group_uuid', $group_uuids)
                ->distinct()
                ->orderBy("menu_item_order")
                ->get([
                    'v_menu_items.menu_item_uuid',
                    'v_menu_items.menu_item_title',
                    'v_menu_items.menu_item_link',
                    'menu_item_icon',
                    'menu_item_order',
                ]);
            $menu->child_menu = $child_menu;
        }

        // Add menu to session variable
        Session::put('menu', $main_menu);

        // Build permissions.
        //get the permissions assigned to the groups that the user is a member of set the permissions in $_SESSION['permissions']

        $permissions = DB::table('v_permissions')
            ->join('v_group_permissions', 'v_permissions.permission_name', '=', 'v_group_permissions.permission_name')
            ->whereIn('v_group_permissions.group_uuid', $group_uuids)
            ->where('v_group_permissions.permission_assigned', 'true')
            ->where(function ($permissions) use ($domain) {
                $permissions->where('v_group_permissions.domain_uuid', '=', $domain->domain_uuid)
                    ->orWhereNull('v_group_permissions.domain_uuid');
            })
            //-> distinct()->toSql();
            ->get([
                'v_permissions.permission_uuid',
                'v_permissions.permission_name',
            ]);

        //dd($permissions);

        // Add permissions to session variable
        Session::put('permissions', $permissions);

        // Set up permissions for FusionPBX
        if (!empty($groups)) {
            $domain_uuid = $domain->domain_uuid;

            // Get the permissions assigned to the user through the assigned groups
            $query = DB::table('v_group_permissions')
                ->select('permission_name')
                ->distinct()
                ->where(function ($query) use ($domain_uuid) {
                    $query->where('domain_uuid', $domain_uuid)
                        ->orWhereNull('domain_uuid');
                })
                ->where('permission_assigned', 'true')
                ->whereIn('group_name', $groups->pluck('group_name'));

            $fusionPbxPermissions = $query->get();
            // Store permissions in the session
            if (!empty($permissions)) {
                foreach ($fusionPbxPermissions as $row) {
                    $_SESSION['permissions'][$row->permission_name] = true;
                    $_SESSION["user"]["permissions"][$row->permission_name] = true;
                }
            }
        }

        // Domains and current domain selection
        $this->sessionDomainService->refreshForUser($event->user);

        // Redirect FusionPBX to an intended URL if it's not a logout page
        if (
            isset(Session::get('url')['intended']) &&
            Session::get('url')['intended'] != '' &&
            !str_contains(Session::get('url')['intended'], '/logout')
        ) {
            $_SESSION['redirect_url'] = Session::get('url')['intended'];
        }
        if (
            isset(Session::get('url')['intended']) &&
            (Session::get('url')['intended'] == "https://" . $_SERVER['HTTP_HOST'] ||
                Session::get('url')['intended'] == "http://" . $_SERVER['HTTP_HOST'])
        ) {
            $_SESSION['redirect_url'] = RouteServiceProvider::HOME;
        }
        if (
            isset(Session::get('url')['intended']) &&
            Session::get('url')['intended'] != '' &&
            str_contains(Session::get('url')['intended'], '/logout')
        ) {
            Session::put('url')['intended'] = RouteServiceProvider::HOME;
            $_SESSION['redirect_url'] = RouteServiceProvider::HOME;
        }

        // Send session cookie name to FusionPBX
        $_SESSION['cookie_name'] = config('session.cookie');

        // Notify FusionPBX that user is authorized
        $_SESSION['authorized'] = true;

        //set the session variables
        $_SESSION["user_uuid"] = $event->user->user_uuid;
        $_SESSION['username'] = $event->user->username;

        //user session array
        $_SESSION["user"]["user_uuid"] = $event->user->user_uuid;
        $_SESSION["user"]["username"] = $event->user->username;

        $default_settings = DefaultSettings::where('default_setting_enabled', 'true')
            ->get()
            ->toArray();
        // foreach ($default_settings as $setting){
        //     $array[$setting['default_setting_category']][$setting['default_setting_subcategory']][$setting['default_setting_name']] = $setting['default_setting_value'];
        // }
        Session::put('default_settings', $default_settings);
        //dd(Session::all());
        //dd($_SESSION);
    }
}
