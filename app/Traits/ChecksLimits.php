<?php

namespace App\Traits;

trait ChecksLimits
{
    /**
     * Friendly names for resources.
     */
    protected array $friendlyNames = [
        'users'                 => 'Users',
        'extensions'            => 'Extensions',
        'ring_groups'           => 'Ring Groups',
        'ivr_menus'             => 'Virtual Receptionists',
        'gateways'              => 'Gateways',
        'devices'               => 'Devices',
        'destinations'          => 'Phone Numbers',
        'call_center_queues'    => 'Call Center Queues',
        'mobile_app_user'       => 'Mobile App Users',
        // Add more here as needed
    ];

    /**
     * Enforce domain resource limit on any model.
     *
     * @param string $resourceKey — e.g. 'extensions', 'ring_groups', 'ivr_menus'
     * @param string $modelClass  — fully qualified model class
     * @param string $column      — domain column name (usually domain_uuid)
     * @param string $errorKey    — used for domain error message lookup
     */
    public function enforceLimit($resourceKey, $modelClass, $column = 'domain_uuid', $errorKey = null)
    {
        $domain = session('domain_uuid');

        $limit = get_limit_setting($resourceKey, $domain);
        if ($limit === null) {
            return; // No limit configured
        }

        // Friendly name lookup (fallback to readable version of resourceKey)
        $friendly = $this->friendlyNames[$resourceKey]
            ?? ucfirst(str_replace('_', ' ', $resourceKey));

        // Setting key, e.g. "user_limit_error"
        $errorSettingKey = $errorKey ?? "{$resourceKey}_limit_error";

        // Custom message OR fallback
        $errorText = get_domain_setting($errorSettingKey, $domain)
            ?? "You have reached the maximum number of {$friendly} allowed ({$limit}).";

        // Count resources in this domain
        $count = $modelClass::where($column, $domain)->count();

        // Check limit reached
        if ($count >= $limit) {
            return response()->json([
                'errors' => [
                    $resourceKey => [$errorText]
                ]
            ], 403);
        }

        return null;
    }
}
