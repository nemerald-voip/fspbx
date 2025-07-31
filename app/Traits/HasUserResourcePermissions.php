<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasUserResourcePermissions
{
    /**
     * Scope: Filter results to only those the user has access to for a given resource type.
     *
     * @param  Builder  $query
     * @param  mixed|null  $user
     * @param  string|null  $resourceType
     * @return Builder
     */
    public function scopeAllowedForUser(Builder $query, $user = null, $resourceType = null)
    {
        $user = $user ?: auth()->user();
        $resourceType = $resourceType ?: $query->getModel()->getTable();

        // If admin, skip user_resource_permissions filtering
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return $query;
        }

        $allowedUuids = \DB::table('user_resource_permissions')
            ->where('user_uuid', $user->user_uuid)
            ->where('resource_type', $resourceType)
            ->pluck('resource_uuid');

        // Will return nothing if $allowedUuids is empty
        return $query->whereIn($query->getModel()->getKeyName(), $allowedUuids);
    }
}
