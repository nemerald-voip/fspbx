<?php

namespace Tests\Unit\CallFlow;

use App\Services\CallFlow\TimeConditionEvaluator;
use App\Services\CallFlow\TimeConditionParseException;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

class TimeConditionEvaluatorTest extends TestCase
{
    private TimeConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new TimeConditionEvaluator();
    }

    private function at(string $iso, string $tz = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($iso, new DateTimeZone($tz));
    }

    private function dialplan(string $body): string
    {
        return <<<XML
<extension name="test">
  $body
</extension>
XML;
    }

    public function test_hour_range_matches(): void
    {
        $xml = $this->dialplan('
            <condition hour="9-17">
              <action application="transfer" data="201 XML example.com"/>
            </condition>
            <condition>
              <action application="transfer" data="202 XML example.com"/>
            </condition>
        ');

        // 2026-04-23T10:00 UTC is Thu 10:00 — in the range.
        $result = $this->evaluator->evaluate($xml, $this->at('2026-04-23T10:00:00Z'), 'UTC');
        $this->assertSame(['destination_app' => 'transfer', 'destination_data' => '201 XML example.com'], $result['matched_action']);
        $this->assertSame(['destination_app' => 'transfer', 'destination_data' => '202 XML example.com'], $result['fallback_action']);
    }

    public function test_hour_range_falls_through_to_fallback(): void
    {
        $xml = $this->dialplan('
            <condition hour="9-17">
              <action application="transfer" data="201 XML example.com"/>
            </condition>
            <condition>
              <action application="transfer" data="*99201 XML example.com"/>
            </condition>
        ');

        // 19:00 is outside 9-17 — no match; caller uses fallback.
        $result = $this->evaluator->evaluate($xml, $this->at('2026-04-23T19:00:00Z'), 'UTC');
        $this->assertNull($result['matched_action']);
        $this->assertSame('*99201 XML example.com', $result['fallback_action']['destination_data']);
    }

    public function test_multiple_time_fields_must_all_match(): void
    {
        $xml = $this->dialplan('
            <condition hour="9-17" wday="2-6">
              <action application="transfer" data="201 XML example.com"/>
            </condition>
        ');

        // Thu = FreeSWITCH wday 5, 10:00 — both match.
        $thu = $this->evaluator->evaluate($xml, $this->at('2026-04-23T10:00:00Z'), 'UTC');
        $this->assertNotNull($thu['matched_action']);

        // Sun = FreeSWITCH wday 1, 10:00 — wday fails.
        $sun = $this->evaluator->evaluate($xml, $this->at('2026-04-26T10:00:00Z'), 'UTC');
        $this->assertNull($sun['matched_action']);
    }

    public function test_timezone_is_honoured(): void
    {
        $xml = $this->dialplan('
            <condition hour="9-17">
              <action application="transfer" data="201 XML example.com"/>
            </condition>
        ');

        // 03:00 UTC = 04:00 BST in Europe/London on this date — outside 9-17.
        $result = $this->evaluator->evaluate($xml, $this->at('2026-07-15T03:00:00Z'), 'Europe/London');
        $this->assertNull($result['matched_action']);

        // 10:00 UTC = 11:00 BST — inside.
        $result = $this->evaluator->evaluate($xml, $this->at('2026-07-15T10:00:00Z'), 'Europe/London');
        $this->assertNotNull($result['matched_action']);
    }

    public function test_comma_list_and_single_value(): void
    {
        $xml = $this->dialplan('
            <condition wday="2,4,6">
              <action application="transfer" data="a XML example.com"/>
            </condition>
            <condition wday="7">
              <action application="transfer" data="b XML example.com"/>
            </condition>
        ');

        // Sat = FreeSWITCH wday 7 → second condition.
        $result = $this->evaluator->evaluate($xml, $this->at('2026-04-25T10:00:00Z'), 'UTC');
        $this->assertSame('b XML example.com', $result['matched_action']['destination_data']);
    }

    public function test_time_of_day_with_overnight_wrap(): void
    {
        $xml = $this->dialplan('
            <condition time-of-day="22:00-06:00">
              <action application="transfer" data="night XML example.com"/>
            </condition>
        ');

        $this->assertNotNull($this->evaluator->evaluate($xml, $this->at('2026-04-23T23:30:00Z'), 'UTC')['matched_action']);
        $this->assertNotNull($this->evaluator->evaluate($xml, $this->at('2026-04-23T04:30:00Z'), 'UTC')['matched_action']);
        $this->assertNull($this->evaluator->evaluate($xml, $this->at('2026-04-23T12:00:00Z'), 'UTC')['matched_action']);
    }

    public function test_date_time_range(): void
    {
        $xml = $this->dialplan('
            <condition date-time="2026-04-20 00:00~2026-04-24 23:59">
              <action application="transfer" data="closed XML example.com"/>
            </condition>
        ');

        $this->assertNotNull($this->evaluator->evaluate($xml, $this->at('2026-04-22T12:00:00Z'), 'UTC')['matched_action']);
        $this->assertNull($this->evaluator->evaluate($xml, $this->at('2026-05-01T12:00:00Z'), 'UTC')['matched_action']);
    }

    public function test_minute_of_day_boundary(): void
    {
        $xml = $this->dialplan('
            <condition minute-of-day="540-1020">
              <action application="transfer" data="x XML example.com"/>
            </condition>
        ');
        // 09:00 = 9*60+0+1 = 541 — in range.
        $this->assertNotNull($this->evaluator->evaluate($xml, $this->at('2026-04-23T09:00:00Z'), 'UTC')['matched_action']);
        // 17:00 = 17*60+0+1 = 1021 — out.
        $this->assertNull($this->evaluator->evaluate($xml, $this->at('2026-04-23T17:00:00Z'), 'UTC')['matched_action']);
    }

    public function test_set_and_export_actions_are_skipped(): void
    {
        $xml = $this->dialplan('
            <condition hour="0-23">
              <action application="set" data="foo=bar"/>
              <action application="export" data="baz=qux"/>
              <action application="transfer" data="201 XML example.com"/>
            </condition>
        ');
        $result = $this->evaluator->evaluate($xml, $this->at('2026-04-23T10:00:00Z'), 'UTC');
        $this->assertSame('transfer', $result['matched_action']['destination_app']);
        $this->assertSame('201 XML example.com', $result['matched_action']['destination_data']);
    }

    public function test_malformed_xml_throws(): void
    {
        $this->expectException(TimeConditionParseException::class);
        $this->evaluator->evaluate('<not valid', $this->at('2026-04-23T10:00:00Z'), 'UTC');
    }
}
