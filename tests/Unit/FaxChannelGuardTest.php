<?php

namespace Tests\Unit;

use App\Services\Fax\FaxChannelGuard;
use PHPUnit\Framework\TestCase;

class FaxChannelGuardTest extends TestCase
{
    private const FAX = '44444444-4444-4444-8444-444444444444';

    private const ROW_UUID = '55555555-5555-4555-8555-555555555555';

    private const GATEWAY = '9bb9c36a-1111-4111-8111-111111111111';

    public function test_budget_matches_the_reaper_page_budget(): void
    {
        $this->assertSame(21600, FaxChannelGuard::budgetSeconds(null));
        $this->assertSame(21600, FaxChannelGuard::budgetSeconds(0));
        $this->assertSame(900, FaxChannelGuard::budgetSeconds(1));
        $this->assertSame(900, FaxChannelGuard::budgetSeconds(7));
        $this->assertSame(1800, FaxChannelGuard::budgetSeconds(20));
    }

    public function test_inline_extension_hangs_up_on_the_same_channel_as_txfax(): void
    {
        $this->assertSame(
            'sched_hangup:+900,txfax:/var/fax/out.tif,hangup:NORMAL_CLEARING',
            FaxChannelGuard::inlineExtension('/var/fax/out.tif', 900)
        );
        $this->assertSame('sched_hangup +2', FaxChannelGuard::resultHangupApplication());
    }

    public function test_a_comma_or_quote_in_the_path_stays_a_single_txfax_app(): void
    {
        $this->assertSame(
            'txfax:/var/fax/a,b.tif',
            FaxChannelGuard::inlineExtension('/var/fax/a,b.tif', 900)
        );
        $this->assertSame(
            "txfax:/var/fax/a'b.tif",
            FaxChannelGuard::inlineExtension("/var/fax/a'b.tif", 900)
        );
    }

    public function test_match_uses_the_fax_id_or_any_origination_uuid(): void
    {
        $gateway = [
            'uuid' => self::GATEWAY,
            'outbound_fax_uuid' => self::FAX,
            'origination_uuid' => '32a3238b-2222-4222-8222-222222222222',
            'fax_success' => 1,
        ];

        $this->assertTrue(FaxChannelGuard::matches($gateway, self::FAX, self::ROW_UUID));
        $this->assertTrue(FaxChannelGuard::matches(
            ['uuid' => 'other', 'origination_uuid' => self::ROW_UUID],
            'different-fax',
            self::ROW_UUID
        ));
        $this->assertTrue(FaxChannelGuard::matches(
            ['originating_leg_uuid' => self::ROW_UUID],
            '',
            self::ROW_UUID
        ));
        $this->assertTrue(FaxChannelGuard::matches(
            ['call_uuid' => self::ROW_UUID],
            '',
            self::ROW_UUID
        ));
        $this->assertFalse(FaxChannelGuard::matches(
            ['uuid' => self::GATEWAY, 'outbound_fax_uuid' => '11111111-1111-4111-8111-111111111111'],
            self::FAX,
            self::ROW_UUID
        ));
    }

    public function test_success_on_the_live_channel_is_sent_when_the_row_uuid_is_already_gone(): void
    {
        $matched = [[
            'uuid' => self::GATEWAY,
            'outbound_fax_uuid' => self::FAX,
            'fax_success' => 1,
        ]];

        $this->assertSame('sent', FaxChannelGuard::phaseADecision(true, $matched, false));
        $this->assertSame('leave', FaxChannelGuard::phaseADecision(true, [[
            'uuid' => self::GATEWAY,
            'outbound_fax_uuid' => self::FAX,
            'fax_success' => '0',
        ]], false));
        $this->assertSame('redispatch', FaxChannelGuard::phaseADecision(true, [], false));
        $this->assertSame('leave', FaxChannelGuard::phaseADecision(false, [], null));
        $this->assertSame('leave', FaxChannelGuard::phaseADecision(false, [], false));
    }

    public function test_uuid_exists_boolean_false_from_json_means_the_call_is_gone(): void
    {
        $this->assertFalse(FaxChannelGuard::uuidExists(false));
        $this->assertTrue(FaxChannelGuard::uuidExists(true));
        $this->assertTrue(FaxChannelGuard::uuidExists('true'));
        $this->assertFalse(FaxChannelGuard::uuidExists('false'));
        $this->assertNull(FaxChannelGuard::uuidExists(null));
        $this->assertNull(FaxChannelGuard::uuidExists('-ERR no such uuid'));
    }

    public function test_kill_list_includes_every_match_and_the_row_uuid(): void
    {
        $uuids = FaxChannelGuard::killUuids([
            ['uuid' => self::GATEWAY],
            ['uuid' => 'not-a-uuid'],
            ['uuid' => self::GATEWAY],
        ], self::ROW_UUID);

        $this->assertSame([self::GATEWAY, self::ROW_UUID], $uuids);
    }

    public function test_terminal_status_does_not_fail_a_successful_fax(): void
    {
        $this->assertSame('sent', FaxChannelGuard::terminalStatus(true, false));
        $this->assertSame('trying', FaxChannelGuard::terminalStatus(false, true));
        $this->assertSame('failed', FaxChannelGuard::terminalStatus(false, false));
    }

    public function test_over_budget_uses_the_channel_budget_plus_grace(): void
    {
        $created = 1_000_000;
        $channel = [
            'application' => 'txfax',
            'created_epoch' => $created,
            'budget_seconds' => 900,
        ];

        $this->assertFalse(FaxChannelGuard::overBudget($channel, $created + 900 + 900));
        $this->assertTrue(FaxChannelGuard::overBudget($channel, $created + 900 + 900 + 1));
        $this->assertFalse(FaxChannelGuard::overBudget([
            'application' => 'txfax',
            'created_epoch' => $created,
            'budget_seconds' => 0,
        ], $created + 21600));
        $this->assertTrue(FaxChannelGuard::overBudget([
            'application' => 'txfax',
            'created_epoch' => $created,
            'budget_seconds' => 0,
        ], $created + 21600 + 1));
        $this->assertFalse(FaxChannelGuard::overBudget([
            'application' => 'bridge',
            'created_epoch' => $created,
            'budget_seconds' => 0,
        ], $created + 999999));
    }

    public function test_alert_body_keeps_the_condition_stable_and_transitions_once(): void
    {
        $body = FaxChannelGuard::alertBody([
            'uuid' => self::GATEWAY,
            'destination' => '+12025550123',
            'age_seconds' => 400000,
            'fax_success' => '1',
            'outbound_fax_uuid' => self::FAX,
        ], 'pbx-02', 'triggered');

        $this->assertStringStartsWith("Source: fspbx\nNode: pbx-02\nChannel: ".self::GATEWAY."\nStatus: triggered\n", $body);
        $this->assertStringContainsString('Condition: '.FaxChannelGuard::CONDITION."\n", $body);
        $this->assertStringNotContainsString(self::GATEWAY, FaxChannelGuard::CONDITION);

        $first = FaxChannelGuard::alertTransitions([], [self::GATEWAY]);
        $this->assertSame([self::GATEWAY], $first['trigger']);
        $this->assertSame([], $first['resolve']);

        $still = FaxChannelGuard::alertTransitions([self::GATEWAY], [self::GATEWAY]);
        $this->assertSame([], $still['trigger']);
        $this->assertSame([], $still['resolve']);

        $gone = FaxChannelGuard::alertTransitions([self::GATEWAY], []);
        $this->assertSame([], $gone['trigger']);
        $this->assertSame([self::GATEWAY], $gone['resolve']);
    }

    public function test_norm_treats_undef_and_errors_as_empty_and_numeric_one_as_success(): void
    {
        $this->assertSame('', FaxChannelGuard::norm('_undef_'));
        $this->assertSame('', FaxChannelGuard::norm('-ERR no such channel'));
        $this->assertSame('', FaxChannelGuard::norm(null));
        $this->assertSame('1', FaxChannelGuard::norm(1));
        $this->assertSame('1', FaxChannelGuard::norm(true));
        $this->assertSame('0', FaxChannelGuard::norm(false));
        $this->assertTrue(FaxChannelGuard::anySucceeded([['fax_success' => 1]]));
        $this->assertFalse(FaxChannelGuard::anySucceeded([['fax_success' => '_undef_']]));
    }
}
