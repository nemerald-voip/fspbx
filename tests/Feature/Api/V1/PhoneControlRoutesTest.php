<?php

namespace Tests\Feature\Api\V1;

use App\Http\Controllers\Api\V1\PhoneControlController;
use Illuminate\Http\Request;
use Tests\TestCase;

class PhoneControlRoutesTest extends TestCase
{
    public static function routes(): array
    {
        return [
            'targets' => [
                'GET',
                '/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/phone-control/targets',
                'targets',
                'user.authorize:phone_control_view',
            ],
            'calls' => [
                'GET',
                '/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/phone-control/calls',
                'calls',
                'user.authorize:phone_control_view',
            ],
            'actions' => [
                'POST',
                '/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/phone-control/actions',
                'store',
                'user.authorize:phone_control_call',
            ],
        ];
    }

    /**
     * @dataProvider routes
     */
    public function test_phone_control_api_route_is_registered_with_its_permission(
        string $method,
        string $uri,
        string $controllerMethod,
        string $permissionMiddleware
    ): void {
        $route = app('router')->getRoutes()->match(Request::create($uri, $method));

        $this->assertSame(
            PhoneControlController::class . '@' . $controllerMethod,
            $route->getActionName()
        );
        $this->assertContains($permissionMiddleware, $route->gatherMiddleware());
    }
}
