<?php

namespace Tests\Unit;

use App\Http\Controllers\SipStatusController;
use Illuminate\Support\Facades\Session;
use ReflectionMethod;
use Tests\TestCase;

class SipStatusEditLinksTest extends TestCase
{
    private const PROFILE = '2d6edddc-84c2-4a2f-ad1a-c13c225a52c9';
    private const GATEWAY = '11111111-1111-4111-8111-111111111111';

    private function grant(array $permissions): void
    {
        Session::put('permissions', array_map(fn ($name) => (object) ['permission_name' => $name], $permissions));
    }

    private function link(string $method, string $uuid): ?string
    {
        $reflection = new ReflectionMethod(SipStatusController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(new SipStatusController(), $uuid);
    }

    public function test_profile_link_opens_the_native_page_with_the_edit_form(): void
    {
        $this->grant(['sip_profile_view', 'sip_profile_edit']);

        $url = $this->link('sipProfileEditUrl', self::PROFILE);

        $this->assertSame(route('sip-profiles.index', ['edit' => self::PROFILE]), $url);
        $this->assertStringContainsString('/sip-profiles?edit=' . self::PROFILE, $url);
        $this->assertStringNotContainsString('.php', $url);
    }

    public function test_gateway_link_opens_the_native_page_with_the_edit_form(): void
    {
        $this->grant(['gateway_view', 'gateway_edit']);

        $url = $this->link('gatewayEditUrl', self::GATEWAY);

        $this->assertSame(route('gateways.index', ['edit' => self::GATEWAY]), $url);
        $this->assertStringContainsString('/gateways?edit=' . self::GATEWAY, $url);
        $this->assertStringNotContainsString('.php', $url);
    }

    public function test_no_link_without_both_view_and_edit_permissions(): void
    {
        foreach ([[], ['sip_profile_view'], ['sip_profile_edit']] as $permissions) {
            $this->grant($permissions);
            $this->assertNull($this->link('sipProfileEditUrl', self::PROFILE), implode(',', $permissions));
        }

        foreach ([[], ['gateway_view'], ['gateway_edit']] as $permissions) {
            $this->grant($permissions);
            $this->assertNull($this->link('gatewayEditUrl', self::GATEWAY), implode(',', $permissions));
        }
    }

    public function test_profile_permissions_do_not_grant_a_gateway_link_and_the_other_way_round(): void
    {
        $this->grant(['sip_profile_view', 'sip_profile_edit']);
        $this->assertNull($this->link('gatewayEditUrl', self::GATEWAY));

        $this->grant(['gateway_view', 'gateway_edit']);
        $this->assertNull($this->link('sipProfileEditUrl', self::PROFILE));
    }
}
