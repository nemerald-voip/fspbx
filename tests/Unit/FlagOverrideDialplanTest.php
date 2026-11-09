<?php

namespace Tests\Unit;

use App\Models\FlagOverride;
use App\Models\IvrMenus;
use App\Models\RingGroups;
use App\Services\FlagFeature;
use Tests\TestCase;

class FlagOverrideDialplanTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const FLAG_A = '22222222-2222-4222-8222-222222222222';
    private const FLAG_B = '44444444-4444-4444-8444-444444444444';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(FlagFeature::class)->shouldReceive('enabled')->andReturn(true);
    }

    public function test_ring_group_checks_flags_in_order_before_normal_route(): void
    {
        $ringGroup = new RingGroups([
            'domain_uuid' => self::DOMAIN,
            'ring_group_name' => 'Sales',
            'ring_group_extension' => '9000',
        ]);
        $ringGroup->setRelation('flagOverrides', collect([
            $this->override(self::FLAG_A, 'transfer', '1001 XML example.test'),
            $this->override(self::FLAG_B, 'transfer', '*991002 XML example.test'),
        ]));

        $xpath = $this->render('layouts.xml.ring-group-dial-plan-template', ['ring_group' => $ringGroup]);
        $conditions = $xpath->query('/extension/condition');

        $this->assertSame(4, $conditions->length);
        $this->assertSame(
            'flag_match=${lua(lua/flag_check.lua ' . self::DOMAIN . ' ' . self::FLAG_A . ' ' . self::FLAG_B . ')}',
            $xpath->evaluate('string(/extension/condition[1]/action[@inline="true"]/@data)')
        );

        foreach ([2 => ['^1$', '1001 XML example.test'], 3 => ['^2$', '*991002 XML example.test']] as $position => [$expression, $data]) {
            $condition = $conditions->item($position - 1);
            $this->assertSame('${flag_match}', $condition->getAttribute('field'));
            $this->assertSame($expression, $condition->getAttribute('expression'));
            $this->assertSame('on-true', $condition->getAttribute('break'));
            $this->assertSame($data, $xpath->evaluate("string(/extension/condition[{$position}]/action[@application=\"transfer\"]/@data)"));
        }

        $this->assertSame('app.lua ring_groups', $xpath->evaluate('string(/extension/condition[4]/action[@application="lua"]/@data)'));
    }

    public function test_disabled_xml_matches_main_without_reading_override_relations(): void
    {
        $this->mock(FlagFeature::class)->shouldReceive('enabled')->andReturn(false);
        $ring = new RingGroups([
            'domain_uuid' => self::DOMAIN, 'dialplan_uuid' => self::FLAG_A,
            'ring_group_name' => 'Sales', 'ring_group_extension' => '9000',
        ]);
        $ring->ring_group_uuid = '33333333-3333-4333-8333-333333333333';
        $ivr = new IvrMenus([
            'domain_uuid' => self::DOMAIN, 'dialplan_uuid' => self::FLAG_A,
            'ivr_menu_name' => 'Main', 'ivr_menu_extension' => '9150',
            'ivr_menu_ringback' => '${us-ring}', 'ivr_menu_exit_app' => 'hangup',
            'ivr_menu_cid_prefix' => 'Reception',
        ]);
        $ivr->ivr_menu_uuid = $ring->ring_group_uuid;
        // Captured from main bc491890. No Flags tables or loaded relations are available.
        $renderer = app(\App\Services\FlagDialplanRenderer::class);
        $this->assertSame(file_get_contents(base_path('tests/fixtures/flags/ring-group-disabled.xml')), $renderer->ringGroup($ring));
        $this->assertSame(file_get_contents(base_path('tests/fixtures/flags/ivr-disabled.xml')), $renderer->ivr($ivr));
    }

    public function test_inactive_overrides_are_passed_with_their_state(): void
    {
        $ringGroup = new RingGroups([
            'domain_uuid' => self::DOMAIN,
            'ring_group_name' => 'Sales',
            'ring_group_extension' => '9000',
        ]);
        // The same flag can be used once when active and once when inactive.
        $ringGroup->setRelation('flagOverrides', collect([
            $this->override(self::FLAG_A, 'transfer', '1001 XML example.test'),
            $this->override(self::FLAG_A, 'transfer', '*991002 XML example.test', FlagOverride::STATE_INACTIVE),
        ]));

        $xpath = $this->render('layouts.xml.ring-group-dial-plan-template', ['ring_group' => $ringGroup]);

        $this->assertSame(
            'flag_match=${lua(lua/flag_check.lua ' . self::DOMAIN . ' ' . self::FLAG_A . ' ' . self::FLAG_A . ':inactive)}',
            $xpath->evaluate('string(/extension/condition[1]/action[@inline="true"]/@data)')
        );
        $this->assertSame('*991002 XML example.test', $xpath->evaluate('string(/extension/condition[3]/action[@application="transfer"]/@data)'));
    }

    public function test_ring_group_without_flags_is_unchanged(): void
    {
        $ringGroup = new RingGroups([
            'domain_uuid' => self::DOMAIN,
            'ring_group_name' => 'Sales',
            'ring_group_extension' => '9000',
        ]);
        $ringGroup->setRelation('flagOverrides', collect());

        $xpath = $this->render('layouts.xml.ring-group-dial-plan-template', ['ring_group' => $ringGroup]);

        $this->assertSame(1, $xpath->query('/extension/condition')->length);
        $this->assertSame(0, $xpath->query('//action[contains(@data, "flag_check.lua")]')->length);
    }

    public function test_virtual_receptionist_recording_override_hangs_up_after_playback(): void
    {
        $ivr = new IvrMenus([
            'domain_uuid' => self::DOMAIN,
            'ivr_menu_name' => 'Main',
            'ivr_menu_extension' => '9150',
        ]);
        $ivr->ivr_menu_uuid = '33333333-3333-4333-8333-333333333333';
        $ivr->setRelation('flagOverrides', collect([
            $this->override(self::FLAG_A, 'lua', 'streamfile.lua closed.wav'),
        ]));

        $xpath = $this->render('layouts.xml.ivr-dial-plan-template', ['ivr' => $ivr, 'dialplan_continue' => 'false']);

        $this->assertSame('streamfile.lua closed.wav', $xpath->evaluate('string(/extension/condition[2]/action[@application="lua"]/@data)'));
        $this->assertSame(1, $xpath->query('/extension/condition[2]/action[@application="hangup"]')->length);
        $this->assertSame(3, $xpath->query('/extension/condition')->length);
    }

    private function override(string $flagUuid, string $app, string $data, string $state = FlagOverride::STATE_ACTIVE): FlagOverride
    {
        return new FlagOverride([
            'domain_uuid' => self::DOMAIN,
            'flag_uuid' => $flagUuid,
            'match_state' => $state,
            'override_app' => $app,
            'override_data' => $data,
        ]);
    }

    private function render(string $view, array $data): \DOMXPath
    {
        $xml = view($view, $data)->render();
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML(trim($xml)));

        return new \DOMXPath($document);
    }
}
