<?php

namespace Tests\Unit;

use App\Support\CdrPickupHistory;
use PHPUnit\Framework\TestCase;

class CdrPickupHistoryTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const TARGET = '22222222-2222-4222-8222-222222222222';
    private const PICKER = '33333333-3333-4333-8333-333333333333';

    private function event(string $code = '**302'): array
    {
        return ['domain_uuid' => self::DOMAIN, 'target_uuid' => self::TARGET, 'picker_uuid' => self::PICKER,
            'ringing_extension' => '302', 'pickup_extension' => '304', 'code' => $code,
            'at' => 1003000000, 'ringing_started' => 1000000000];
    }

    private function variables(array $events, string $uuid = self::TARGET): array
    {
        return ['uuid' => $uuid, 'domain_uuid' => self::DOMAIN, 'start_uepoch' => '1000000000',
            'end_uepoch' => '1013000000', 'fspbx_pickup_events' => urlencode(json_encode($events))];
    }

    private function profiles(string $partner = self::PICKER): array
    {
        return [['caller_profile' => ['destination_number' => '302',
            'originator' => ['originator_caller_profile' => ['uuid' => $partner]]],
            'times' => ['profile_created_time' => '1003100000', 'bridged_time' => '1003200000']]];
    }

    public function test_all_pickup_codes_require_an_actual_bridge_and_bare_97_is_excluded(): void
    {
        foreach (['**302', '*97302', '*8', '**'] as $code) {
            $events = CdrPickupHistory::events($this->variables([$this->event($code)]), $this->profiles());
            $this->assertCount(1, $events);
            $this->assertSame(1003100000, $events[0]['connected_at']);
            $this->assertSame([], CdrPickupHistory::events($this->variables([$this->event($code)]), []));
        }
        foreach (['*97', '*99302', '*97302garbage'] as $code) {
            $this->assertSame([], CdrPickupHistory::events($this->variables([$this->event($code)]), $this->profiles()));
        }
    }

    public function test_foreign_account_wrong_channel_stale_and_malformed_events_are_rejected(): void
    {
        foreach ([['domain_uuid' => self::PICKER], ['target_uuid' => self::DOMAIN], ['at' => 990000000],
            ['at' => 1020000000], ['ringing_extension' => []], ['picker_uuid' => 'not-a-uuid']] as $change) {
            $this->assertSame([], CdrPickupHistory::events($this->variables([array_replace($this->event(), $change)]), $this->profiles()));
        }
        $this->assertSame([], CdrPickupHistory::events(['fspbx_pickup_events' => 'broken'], []));
        $this->assertSame([], CdrPickupHistory::events($this->variables([$this->event()]), $this->profiles(self::DOMAIN)));
    }

    public function test_both_legs_parse_without_database_or_import_order_dependencies(): void
    {
        require_once __DIR__.'/../../app/Console/Daemons/cdr_import.php';
        foreach ([[self::PICKER, self::TARGET], [self::TARGET, self::PICKER]] as $order) {
            foreach ($order as $uuid) {
                $variables = $this->variables([$this->event()], $uuid);
                $profiles = $this->profiles($uuid === self::TARGET ? self::PICKER : self::TARGET);
                $events = CdrPickupHistory::events($variables, $profiles);
                $xml = new \SimpleXMLElement('<variables/>');
                foreach ($variables as $key => $value) { $xml->addChild($key, htmlspecialchars($value)); }
                $this->assertSame($uuid === self::PICKER ? self::TARGET : '', \cdr_import::originating_leg_uuid($xml, $events));
                $xml->originating_leg_uuid = self::DOMAIN;
                $this->assertSame(self::DOMAIN, \cdr_import::originating_leg_uuid($xml, $events));
                $importer = (new \ReflectionClass(\cdr_import::class))->newInstanceWithoutConstructor();
                $importer->call_details = ['variables' => $variables, 'callflow' => $profiles];
                $this->assertSame($events, $importer->call_flow()[0]['pickup_events']);
            }
        }
    }

    public function test_losing_concurrent_pickup_is_not_linked_and_successive_pickups_survive(): void
    {
        $second = array_replace($this->event(), ['picker_uuid' => '44444444-4444-4444-8444-444444444444', 'at' => 1006000000]);
        $failed = array_replace($this->event(), ['picker_uuid' => '55555555-5555-4555-8555-555555555555']);
        $profiles = [...$this->profiles(), ...$this->profiles($second['picker_uuid'])];
        $profiles[1]['times']['profile_created_time'] = 1006100000;
        $events = CdrPickupHistory::events($this->variables([$this->event(), $failed, $second]), $profiles);
        $this->assertSame([self::PICKER, $second['picker_uuid']], array_column($events, 'picker_uuid'));
    }

    public function test_original_channel_variables_survive_reversed_native_sip_caller_profiles(): void
    {
        // Shape of the reported 107 -> 101 call, picked up by 100: even
        // the oldest caller profile has been reversed by FreeSWITCH.
        $event = array_replace($this->event('*97101'), ['ringing_extension' => '101', 'pickup_extension' => '100']);
        $variables = $this->variables([$event]) + ['caller_id_number' => '107', 'caller_id_name' => 'Evan%20Brooks'];
        $profiles = $this->profiles();
        $profiles[0]['caller_profile'] += ['caller_id_number' => '100', 'callee_id_number' => '107'];
        $profiles[] = ['caller_profile' => ['caller_id_number' => '101', 'callee_id_number' => '107'],
            'times' => ['profile_created_time' => 1000000000]];
        $events = CdrPickupHistory::events($variables, $profiles);
        $this->assertSame(['caller_id_name' => 'Evan Brooks', 'caller_id_number' => '107'],
            CdrPickupHistory::originalCaller($variables, $events));
    }

    public function test_original_caller_override_requires_a_confirmed_target_in_the_same_account(): void
    {
        $variables = $this->variables([$this->event()]) + ['caller_id_number' => '107'];
        $events = CdrPickupHistory::events($variables, $this->profiles());
        $this->assertSame([], CdrPickupHistory::originalCaller($variables, []));
        $this->assertSame([], CdrPickupHistory::originalCaller($variables, [$this->event()]));
        $this->assertSame([], CdrPickupHistory::originalCaller(array_replace($variables, ['uuid' => self::PICKER]), $events));
        $this->assertSame([], CdrPickupHistory::originalCaller(array_replace($variables, ['domain_uuid' => self::PICKER]), $events));
        unset($variables['caller_id_number']);
        $this->assertSame([], CdrPickupHistory::originalCaller($variables, $events));
    }
}
