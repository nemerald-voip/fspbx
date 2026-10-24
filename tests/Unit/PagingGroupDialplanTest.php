<?php

namespace Tests\Unit;

use App\Models\PagingGroup;
use App\Services\PagingGroupService;
use Tests\TestCase;

class PagingGroupDialplanTest extends TestCase
{
    private function group(array $attributes = []): PagingGroup
    {
        $group = new PagingGroup(array_merge([
            'name' => 'Warehouse & Dock',
            'extension' => '7000',
            'busy_mode' => PagingGroup::BUSY_SKIP,
            'mode' => PagingGroup::MODE_LIVE,
            'intro_beep' => false,
            'dialplan_uuid' => 'd1a1b2c3-0000-4000-8000-000000000001',
        ], $attributes));
        $group->uuid = 'a1a1b2c3-0000-4000-8000-000000000002';

        return $group;
    }

    public function test_anyone_in_the_account_can_page_without_allowed_callers(): void
    {
        $xml = PagingGroupService::dialplanXml($this->group(), ['200', '205'], []);

        $this->assertStringContainsString('<extension name="Warehouse &amp; Dock" continue="false" uuid="d1a1b2c3-0000-4000-8000-000000000001">', $xml);
        $this->assertStringContainsString('<condition field="destination_number" expression="^7000$">', $xml);
        $this->assertStringContainsString('data="paging_destinations=200,205"', $xml);
        $this->assertStringContainsString('data="paging_mode=live"', $xml);
        $this->assertStringContainsString('data="paging_busy=skip"', $xml);
        $this->assertStringContainsString('data="paging_beep=false"', $xml);
        $this->assertStringContainsString('data="paging_intro="', $xml);
        $this->assertStringContainsString('<action application="lua" data="lua/paging_group.lua"/>', $xml);
        $this->assertStringNotContainsString('sip_auth_username', $xml);
        $this->assertStringNotContainsString('pin_number', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_allowed_callers_are_checked_by_authenticated_username(): void
    {
        $xml = PagingGroupService::dialplanXml($this->group(), ['200'], ['205', '210']);

        $this->assertStringContainsString('<condition field="destination_number" expression="^7000$"/>', $xml);
        $this->assertStringContainsString('<condition field="${sip_auth_username}" expression="^(205|210)$">', $xml);
        $this->assertStringContainsString('<anti-action application="playback" data="ivr/ivr-not_have_permission.wav"/>', $xml);
        $this->assertStringContainsString('<anti-action application="hangup" data="CALL_REJECTED"/>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_busy_phones_can_be_paged_anyway(): void
    {
        $xml = PagingGroupService::dialplanXml($this->group(['busy_mode' => PagingGroup::BUSY_FORCE]), ['200'], []);

        $this->assertStringContainsString('data="paging_busy=force"', $xml);
    }

    public function test_recorded_page_with_beep_and_announcement(): void
    {
        $xml = PagingGroupService::dialplanXml($this->group([
            'mode' => PagingGroup::MODE_RECORDED,
            'intro_beep' => true,
            'intro_recording' => 'incoming announcement.wav',
        ]), ['200'], []);

        $this->assertStringContainsString('data="paging_mode=recorded"', $xml);
        $this->assertStringContainsString('data="paging_beep=true"', $xml);
        $this->assertStringContainsString('data="paging_intro=${recordings_dir}/${domain_name}/incoming announcement.wav"', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }
}
