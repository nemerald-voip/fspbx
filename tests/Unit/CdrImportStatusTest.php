<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class CdrImportStatusTest extends TestCase
{
    /** @dataProvider statuses */
    public function test_call_leg_status_is_independent_of_callback_confirmation(array $variables, string $expected, string $missed = 'false', string $destination = '101'): void
    {
        require_once __DIR__.'/../../app/Console/Daemons/cdr_import.php';
        $xml = new SimpleXMLElement('<variables/>');
        foreach ($variables as $key => $value) {
            $xml->addChild($key, (string) $value);
        }
        $this->assertSame($expected, \cdr_import::call_status($xml, $destination, $missed));
    }

    public static function statuses(): array
    {
        $callback = ['cc_callback_attempt' => '22222222-2222-4222-8222-222222222222'];
        $accepted = ['cc_callback_accepted' => '33333333-3333-4333-8333-333333333333',
            'cc_side' => 'member', 'cc_cause' => 'cancel', 'cc_cancel_reason' => 'EXIT_WITH_KEY',
            'billsec' => 38, 'hangup_cause' => 'NORMAL_CLEARING'];
        $loopback = $callback + ['channel_name' => 'loopback/app=lua:contact_center_callback_park.lua', 'answer_epoch' => 1789650000, 'billsec' => 29, 'hangup_cause' => 'CALL_REJECTED'];
        return [
            'confirmed callback request replaces queue miss' => [$accepted, 'callback_requested', 'true'],
            'caller hangs up during acceptance prompt' => [array_replace($accepted, ['hangup_cause' => 'ORIGINATOR_CANCEL']), 'callback_requested', 'true'],
            'callback request is not an answered call' => [$accepted, 'callback_requested'],
            'ordinary exit key remains missed' => [array_diff_key($accepted, ['cc_callback_accepted' => true]), 'missed', 'true'],
            'invalid acceptance marker ignored' => [array_replace($accepted, ['cc_callback_accepted' => 'true']), 'missed', 'true'],
            'empty acceptance marker ignored' => [array_replace($accepted, ['cc_callback_accepted' => '']), 'missed', 'true'],
            'agent leg cannot become a callback request' => [array_replace($accepted, ['cc_side' => 'agent', 'billsec' => 0, 'hangup_cause' => 'ORIGINATOR_CANCEL']), 'cancelled'],
            'attempt leg retains native answer' => [$accepted + $callback + ['channel_name' => 'sofia/internal/100', 'answer_epoch' => 1789650000], 'answered'],
            'inherited customer role is not original member' => [$accepted + ['cc_callback_role' => 'customer'], 'missed', 'true'],
            'answered agent, customer did not confirm' => [$loopback + ['cc_callback_cdr_answered' => 'true'], 'answered'],
            'answered customer, no confirmed bridge' => [$loopback + ['cc_callback_cdr_answered' => 'true'], 'answered', 'true'],
            'actual SIP answer followed by rejection' => [$callback + ['channel_name' => 'sofia/internal/101', 'answer_epoch' => 1789650000, 'billsec' => 0, 'hangup_cause' => 'CALL_REJECTED'], 'answered'],
            'loopback auto-answer is not a customer answer' => [$loopback, 'failed'],
            'SIP rejection before answer' => [$callback + ['channel_name' => 'sofia/internal/101', 'answer_epoch' => 0, 'billsec' => 0, 'hangup_cause' => 'CALL_REJECTED'], 'failed'],
            'callback busy' => [array_replace($loopback, ['hangup_cause' => 'USER_BUSY']), 'busy'],
            'callback no answer' => [array_replace($loopback, ['hangup_cause' => 'NO_ANSWER']), 'no_answer'],
            'callback routing failure' => [array_replace($loopback, ['hangup_cause' => 'NO_ROUTE_DESTINATION']), 'failed'],
            'normal call answered' => [['billsec' => 5, 'hangup_cause' => 'NORMAL_CLEARING'], 'answered'],
            'normal call rejected unchanged' => [['billsec' => 5, 'hangup_cause' => 'CALL_REJECTED', 'cc_callback_cdr_answered' => 'true'], 'failed'],
            'invalid callback marker ignored' => [['billsec' => 5, 'hangup_cause' => 'CALL_REJECTED', 'cc_callback_cdr_answered' => 'true', 'cc_callback_attempt' => 'invalid'], 'failed'],
            'ordinary missed call unchanged' => [['billsec' => 0, 'hangup_cause' => 'NORMAL_CLEARING'], 'missed', 'true'],
            'voicemail unchanged' => [['billsec' => 5, 'hangup_cause' => 'NORMAL_CLEARING'], 'voicemail', 'false', '*99101'],
            'agent rejection retained' => [['billsec' => 0, 'hangup_cause' => 'CALL_REJECTED', 'cc_side' => 'agent'], 'failed'],
            'agent busy retained' => [['billsec' => 0, 'hangup_cause' => 'USER_BUSY', 'cc_side' => 'agent'], 'busy'],
            'agent cancellation retained' => [['billsec' => 0, 'hangup_cause' => 'ORIGINATOR_CANCEL', 'cc_side' => 'agent'], 'cancelled'],
            'agent routing failure retained' => [['billsec' => 0, 'hangup_cause' => 'NO_ROUTE_DESTINATION', 'cc_side' => 'agent'], 'failed'],
            'unanswered agent fallback' => [['billsec' => 0, 'hangup_cause' => 'NORMAL_CLEARING', 'cc_side' => 'agent'], 'no_answer'],
            'encoded native callback channel' => [$callback + ['channel_name' => 'sofia%2Finternal%2F101', 'answer_epoch' => 1789650000, 'billsec' => 0, 'hangup_cause' => 'CALL_REJECTED'], 'answered'],
        ];
    }

    public function test_only_real_callback_endpoints_receive_the_persisted_link(): void
    {
        require_once __DIR__.'/../../app/Console/Daemons/cdr_import.php';
        $attempt = '22222222-2222-4222-8222-222222222222';
        $xml = new SimpleXMLElement('<variables><cc_callback_attempt>'.$attempt.'</cc_callback_attempt><cc_callback_role>customer</cc_callback_role><channel_name>sofia%2Finternal%2F101</channel_name></variables>');
        $this->assertSame(['cc_callback_attempt_uuid' => $attempt, 'cc_callback_role' => 'customer'], \cdr_import::callback_fields($xml));
        $xml->channel_name = 'loopback/101-a';
        $this->assertSame([], \cdr_import::callback_fields($xml));
        $xml->channel_name = 'sofia/internal/100';
        $xml->cc_callback_role = 'agent';
        $this->assertSame(['cc_callback_attempt_uuid' => $attempt, 'cc_callback_role' => 'agent'], \cdr_import::callback_fields($xml));
        $xml->cc_callback_attempt = 'invalid';
        $this->assertSame([], \cdr_import::callback_fields($xml));
    }
}
