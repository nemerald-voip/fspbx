<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class SessionDomainService
{
    /**
     * Rebuild domains-related session state for this user.
     *
     * - Rebuilds Session::get('domains') and $_SESSION['domains']
     * - Ensures domain_uuid/domain_name/context are still valid
     */
    public function refreshForUser($user): void
    {
        if (!$user) {
            return;
        }

        // If user can't select domains, just lock them to their own domain
        if (!userCheckPermission('domain_select')) {
            $domain = Domain::where('domain_uuid', $user->domain_uuid)->first();

            if ($domain) {
                Session::put('domain_uuid', $domain->domain_uuid);
                Session::put('domain_name', $domain->domain_name);
                Session::put('domain_description', $domain->domain_description);

                $_SESSION['domain_name']                 = $domain->domain_name;
                $_SESSION['user']['domain_name']         = $domain->domain_name;
                $_SESSION['domain_uuid']                 = $domain->domain_uuid;
                $_SESSION['context']                     = $domain->domain_name;
                $_SESSION['user']['domain_uuid']         = $domain->domain_uuid;
            }

            // Clear domains list for single-domain users
            Session::forget('domains');
            unset($_SESSION['domains']);

            return;
        }

        // Multi-domain admin
        Session::put('domain_select', true);

        // Get groups for this user (needed to detect superadmin)
        $groups = DB::table('v_user_groups')
            ->join('v_groups', 'v_user_groups.group_uuid', '=', 'v_groups.group_uuid')
            ->where('v_user_groups.user_uuid', '=', $user->user_uuid)
            ->where('v_user_groups.domain_uuid', '=', $user->domain_uuid)
            ->get(['v_user_groups.group_name']);

        $group_names = $groups->pluck('group_name')->toArray();

        // Build the list of allowed domains
        if (in_array('superadmin', $group_names)) {
            // Superadmin: all enabled domains
            $domains = DB::table('v_domains')
                ->where('domain_enabled', '=', 't')
                ->orderBy('domain_name', 'asc')
                ->orderBy('domain_description', 'asc')
                ->selectRaw('coalesce(domain_description, domain_name) as domain_description')
                ->addSelect([
                    'domain_uuid',
                    'domain_parent_uuid',
                    'domain_name',
                    'domain_enabled',
                ])
                ->get();
        } else {
            // Non-superadmin: from user_permissions
            $domains = Domain::where('v_domains.domain_enabled', '=', 't')
                ->whereHas('user_permissions', function ($query) use ($user) {
                    $query->where('user_uuid', '=', $user->user_uuid);
                })
                ->selectRaw('coalesce(domain_description, domain_name) as domain_description')
                ->addSelect([
                    'v_domains.domain_uuid',
                    'v_domains.domain_parent_uuid',
                    'v_domains.domain_name',
                    'v_domains.domain_enabled',
                ])
                ->get();

            // Plus domains via domain groups
            $domains_from_groups = Domain::join('domain_group_relations', 'v_domains.domain_uuid', '=', 'domain_group_relations.domain_uuid')
                ->join('domain_groups', 'domain_group_relations.domain_group_uuid', '=', 'domain_groups.domain_group_uuid')
                ->join('user_domain_group_permissions', 'user_domain_group_permissions.domain_group_uuid', '=', 'domain_groups.domain_group_uuid')
                ->where('v_domains.domain_enabled', '=', 't')
                ->where('user_uuid', '=', $user->user_uuid)
                ->select([
                    'v_domains.domain_uuid',
                    'v_domains.domain_parent_uuid',
                    'v_domains.domain_name',
                    'v_domains.domain_enabled',
                    DB::raw('coalesce(v_domains.domain_description , v_domains.domain_name) as domain_description'),
                ])
                ->get();

            $domains = $domains->merge($domains_from_groups)
                ->unique('domain_uuid')
                ->sortBy('domain_description')
                ->values();
        }

        // Save in Laravel session
        Session::put('domains', $domains);

        // Save in Fusion-style $_SESSION
        $_SESSION['domains'] = [];
        foreach (json_decode(json_encode($domains), true) as $row) {
            $_SESSION['domains'][$row['domain_uuid']] = $row;
        }

        // Determine which domain should be "current"
        $currentDomainUuid = Session::get('domain_uuid') ?: $user->domain_uuid;

        $selected = $domains->firstWhere('domain_uuid', $currentDomainUuid);

        // If user’s domain isn't in the list, fall back to the first allowed domain
        if (!$selected && $domains->isNotEmpty()) {
            $selected = $domains->first();
        }

        if ($selected) {
            Session::put('domain_uuid', $selected->domain_uuid);
            Session::put('domain_name', $selected->domain_name);
            Session::put(
                'domain_description',
                !empty($selected->domain_description) ? $selected->domain_description : $selected->domain_name
            );

            $_SESSION['domain_name']             = $selected->domain_name;
            $_SESSION['user']['domain_name']     = $selected->domain_name;
            $_SESSION['domain_uuid']             = $selected->domain_uuid;
            $_SESSION['context']                 = $selected->domain_name;
            $_SESSION['user']['domain_uuid']     = $selected->domain_uuid;
        }
    }
}
