<?php

namespace App\Http\Requests;

class UpdateDynamicRouteRequest extends StoreDynamicRouteRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('dynamic_route_update');
    }

    protected function dynamicRouteUuid(): ?string
    {
        $dynamicRoute = $this->route('dynamic_route');

        return is_string($dynamicRoute) ? $dynamicRoute : $dynamicRoute?->dynamic_route_uuid;
    }
}
