<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait FilterableByLocation
{
    /**
     * Scope a query to only include models available in the authenticated user's locations.
     * This scope is now self-contained and doesn't require a User object to be passed in.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInUsersLocations(Builder $query)
    {
        // 1. Get the authenticated user. If no user is logged in, return nothing.
        if (!auth()->check()) {
            return $query->whereRaw('1 = 0'); // Return no results
        }
        // Super admins bypass (adapt to your logic)
        $user = auth()->user();
        if ($user && isSuperAdmin()) return;

        // 2. Get the user's location IDs
        $userLocationIds = $user->locations()->pluck('locations.location_uuid')->toArray();

        // 3. If the user has no locations, they can't see anything with a location,
        // but they can still see items without any locations.
        if (empty($userLocationIds)) {
            // Only return items that have NO locations.
            return $query->doesntHave('locations');
        }

        // 4. Modify the filtering logic to include both:
        //    a) Items in the user's locations
        //    b) Items that have no locations assigned
        return $query->where(function ($q) use ($userLocationIds) {
            $q->whereHas('locations', function ($subQuery) use ($userLocationIds) {
                $subQuery->whereIn('locations.location_uuid', $userLocationIds);
            })->orWhereDoesntHave('locations');
        });
    }
}
