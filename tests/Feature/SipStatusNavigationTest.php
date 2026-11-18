<?php

namespace Tests\Feature;

use App\Http\Controllers\SipStatusController;
use App\Http\Middleware\{CheckFusionPBXLogin, HandleInertiaRequests, NoStoreByDefault};
use App\Models\User;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SipStatusNavigationTest extends TestCase
{
    private const PROFILE = '11111111-1111-4111-8111-111111111111';
    private const GATEWAY = '22222222-2222-4222-8222-222222222222';
    private const ACCOUNT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sip_navigation_test',
            'database.connections.sip_navigation_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array',
        ]);
        Http::preventStrayRequests();
        $this->withoutMiddleware([CheckFusionPBXLogin::class, NoStoreByDefault::class, HandleInertiaRequests::class]);
        $this->actingAs((new User)->forceFill(['user_uuid' => self::PROFILE, 'domain_uuid' => self::ACCOUNT, 'user_enabled' => 'true']));
        $this->withSession(['domain_uuid' => self::ACCOUNT, 'domain_name' => 'a.invalid', 'domain.language.code' => 'en-us']);
    }

    public function test_status_links_open_native_editors_for_the_selected_record(): void
    {
        $this->permissions('sip_profile_view', 'sip_profile_edit', 'gateway_view', 'gateway_edit');
        $rows = $this->summary();
        $this->assertSame(url('/sip-profiles?edit=' . self::PROFILE), $rows[0]['edit_url']);
        $this->assertSame(url('/gateways?edit=' . self::GATEWAY), $rows[1]['edit_url']);

        foreach (['sip-profiles' => self::PROFILE, 'gateways' => self::GATEWAY] as $page => $uuid) {
            $this->get('/' . $page . '?edit=' . $uuid, ['X-Inertia' => 'true'])->assertOk()
                ->assertJsonPath('props.edit_uuid', $uuid)->assertJsonPath('props.permissions.update', true);
        }
    }

    public function test_status_links_require_both_page_and_edit_permissions(): void
    {
        foreach ([[], ['sip_profile_view', 'gateway_view'], ['sip_profile_edit', 'gateway_edit']] as $permissions) {
            $this->permissions(...$permissions);
            $rows = $this->summary();
            $this->assertNull($rows[0]['edit_url']);
            $this->assertNull($rows[1]['edit_url']);
        }
        $this->permissions('sip_profile_view', 'gateway_view');
        foreach (['sip-profiles', 'gateways'] as $page) {
            $this->get('/' . $page . '?edit=' . self::PROFILE, ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.edit_uuid', null);
            $this->getJson('/' . $page . '?edit=invalid')->assertUnprocessable()->assertJsonValidationErrors('edit');
        }
    }

    public function test_gateway_link_matches_account_uuid_and_does_not_offer_unknown_records(): void
    {
        $this->permissions('sip_profile_view', 'sip_profile_edit', 'gateway_view', 'gateway_edit');
        // Matching names must not grant access to a different account's gateway.
        $rows = $this->summary('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
        $this->assertNull($rows[1]['edit_url']);
        $rows = $this->summary(self::ACCOUNT, false);
        $this->assertNull($rows[0]['edit_url']);
        $this->assertNull($rows[1]['edit_url']);
    }

    private function summary(string $gatewayAccount = self::ACCOUNT, bool $known = true): array
    {
        $esl = $this->createMock(FreeswitchEslService::class);
        $esl->method('executeCommand')->willReturnMap([
            ['sofia xmlstatus', false, '<profiles><profile><name>internal</name><type>profile</type><data>sip:example</data><state>RUNNING</state></profile></profiles>'],
            ['sofia xmlstatus gateway', false, '<gateways><gateway><name>' . self::GATEWAY . '</name><to>sip:example</to><state>REGED</state><profile>internal</profile></gateway></gateways>'],
        ]);
        $controller = new class extends SipStatusController {
            protected function canRunCommands(): bool { return false; }
            public function summary(FreeswitchEslService $esl, array $profiles, array $gateways): array
            {
                return $this->getSummary($esl, $profiles, $gateways);
            }
        };

        return $controller->summary($esl, $known ? [['sip_profile_uuid' => self::PROFILE, 'sip_profile_name' => 'internal']] : [],
            $known ? [self::GATEWAY => ['gateway_uuid' => self::GATEWAY, 'gateway' => 'provider', 'domain_uuid' => $gatewayAccount, 'domain_name' => 'a.invalid']] : []);
    }

    private function permissions(string ...$names): void
    {
        $this->withSession(['permissions' => collect($names)->map(fn ($name) => (object) ['permission_name' => $name])->all()]);
    }
}
