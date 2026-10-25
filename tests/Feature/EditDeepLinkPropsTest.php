<?php

namespace Tests\Feature;

use App\Http\Controllers\GatewayController;
use App\Http\Controllers\SipProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class EditDeepLinkPropsTest extends TestCase
{
    private const UUID = '2d6edddc-84c2-4a2f-ad1a-c13c225a52c9';

    protected function setUp(): void
    {
        parent::setUp();

        // These pages only build props; keep any accidental query away from a real database.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
        ]);
    }

    private function grant(array $permissions): void
    {
        Session::put('permissions', array_map(fn ($name) => (object) ['permission_name' => $name], $permissions));
    }

    /** Render the page the way Inertia does for an XHR visit and return its props. */
    private function props(object $controller, string $path, array $query): array
    {
        $request = Request::create($path, 'GET', $query);
        $request->headers->set('X-Inertia', 'true');
        app()->instance('request', $request);

        return $controller->index()->toResponse($request)->getData(true)['props'];
    }

    public function test_sip_profiles_page_passes_a_valid_edit_uuid_to_users_who_can_edit(): void
    {
        $this->grant(['sip_profile_view', 'sip_profile_edit']);

        $props = $this->props(app(SipProfileController::class), '/sip-profiles', ['edit' => self::UUID]);

        $this->assertSame(self::UUID, $props['editUuid']);
    }

    public function test_sip_profiles_page_ignores_the_parameter_without_edit_permission(): void
    {
        $this->grant(['sip_profile_view']);

        $props = $this->props(app(SipProfileController::class), '/sip-profiles', ['edit' => self::UUID]);

        $this->assertNull($props['editUuid']);
    }

    public function test_sip_profiles_page_ignores_values_that_are_not_uuids(): void
    {
        $this->grant(['sip_profile_view', 'sip_profile_edit']);

        foreach (['internal', "' or 1=1", '<script>'] as $value) {
            $props = $this->props(app(SipProfileController::class), '/sip-profiles', ['edit' => $value]);
            $this->assertNull($props['editUuid'], $value);
        }

        $props = $this->props(app(SipProfileController::class), '/sip-profiles', []);
        $this->assertNull($props['editUuid']);
    }

    public function test_gateways_page_passes_a_valid_edit_uuid_to_users_who_can_edit(): void
    {
        $this->grant(['gateway_view', 'gateway_edit']);

        $props = $this->props(app(GatewayController::class), '/gateways', ['edit' => strtoupper(self::UUID)]);

        $this->assertSame(self::UUID, $props['editUuid']);
    }

    public function test_gateways_page_ignores_the_parameter_without_edit_permission(): void
    {
        $this->grant(['gateway_view']);

        $props = $this->props(app(GatewayController::class), '/gateways', ['edit' => self::UUID]);

        $this->assertNull($props['editUuid']);
    }

    public function test_gateways_page_ignores_values_that_are_not_uuids(): void
    {
        $this->grant(['gateway_view', 'gateway_edit']);

        $props = $this->props(app(GatewayController::class), '/gateways', ['edit' => 'not-a-uuid']);

        $this->assertNull($props['editUuid']);
    }

    public function test_users_without_view_permission_are_redirected_and_never_see_props(): void
    {
        $this->grant([]);

        $this->assertSame(302, app(SipProfileController::class)->index()->getStatusCode());
        $this->assertSame(302, app(GatewayController::class)->index()->getStatusCode());
    }
}
