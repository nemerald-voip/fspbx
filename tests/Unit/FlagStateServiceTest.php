<?php

namespace Tests\Unit;

use App\Models\Flag;
use App\Services\FlagStateService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FlagStateServiceTest extends TestCase
{
    private function local(string $localTime): Carbon
    {
        return Carbon::parse($localTime, 'America/Toronto');
    }

    private function weekly(string $weekdays, string $from, string $to): array
    {
        return ['rule_type' => 'weekly', 'weekdays' => $weekdays, 'start_time' => $from . ':00', 'end_time' => $to . ':00'];
    }

    /** @dataProvider weeklyCases */
    public function test_weekly_rules(string $now, bool $expected): void
    {
        // Mon-Thu 07:00-11:00 and Fri 09:00-11:00 (FreeSWITCH wday: 2=Mon ... 6=Fri).
        $rules = [
            $this->weekly('2,3,4,5', '07:00', '11:00'),
            $this->weekly('6', '09:00', '11:00'),
        ];

        $this->assertSame($expected, (new FlagStateService())->rulesMatch($rules, $this->local($now)));
    }

    public static function weeklyCases(): array
    {
        return [
            'Monday before start' => ['2026-10-12 06:59', false],
            'Monday at start' => ['2026-10-12 07:00', true],
            'Thursday mid window' => ['2026-10-15 10:30', true],
            'Thursday at end is inactive' => ['2026-10-15 11:00', false],
            'Friday before its later start' => ['2026-10-16 08:00', false],
            'Friday window' => ['2026-10-16 09:15', true],
            'Saturday' => ['2026-10-17 09:15', false],
            'Sunday' => ['2026-10-18 09:15', false],
        ];
    }

    public function test_overnight_weekly_rule_continues_into_next_day(): void
    {
        $rules = [$this->weekly('6', '22:00', '02:00')]; // Friday 22:00 to Saturday 02:00
        $service = new FlagStateService();

        $this->assertTrue($service->rulesMatch($rules, $this->local('2026-10-16 23:30')));
        $this->assertTrue($service->rulesMatch($rules, $this->local('2026-10-17 01:59')));
        $this->assertFalse($service->rulesMatch($rules, $this->local('2026-10-17 02:00')));
        $this->assertFalse($service->rulesMatch($rules, $this->local('2026-10-17 23:30')));
    }

    public function test_date_range_without_times_covers_whole_days(): void
    {
        $rules = [['rule_type' => 'date_range', 'start_date' => '2026-12-24', 'end_date' => '2026-12-26']];
        $service = new FlagStateService();

        $this->assertFalse($service->rulesMatch($rules, $this->local('2026-12-23 23:59')));
        $this->assertTrue($service->rulesMatch($rules, $this->local('2026-12-24 00:00')));
        $this->assertTrue($service->rulesMatch($rules, $this->local('2026-12-26 23:59')));
        $this->assertFalse($service->rulesMatch($rules, $this->local('2026-12-27 00:00')));
    }

    public function test_date_range_with_times_runs_continuously(): void
    {
        $rules = [[
            'rule_type' => 'date_range',
            'start_date' => '2026-12-24',
            'start_time' => '12:00:00',
            'end_date' => '2026-12-26',
            'end_time' => '09:00:00',
        ]];
        $service = new FlagStateService();

        $this->assertFalse($service->rulesMatch($rules, $this->local('2026-12-24 11:59')));
        $this->assertTrue($service->rulesMatch($rules, $this->local('2026-12-24 12:00')));
        $this->assertTrue($service->rulesMatch($rules, $this->local('2026-12-25 03:00')));
        $this->assertFalse($service->rulesMatch($rules, $this->local('2026-12-26 09:00')));
    }

    public function test_manual_and_disabled_flags(): void
    {
        $service = new FlagStateService();

        $this->assertTrue($service->isActive(new Flag(['type' => 'manual', 'active' => true, 'enabled' => true])));
        $this->assertFalse($service->isActive(new Flag(['type' => 'manual', 'active' => false, 'enabled' => true])));
        $this->assertFalse($service->isActive(new Flag(['type' => 'manual', 'active' => true, 'enabled' => false])));
    }

    public function test_scheduled_flag_uses_its_own_timezone(): void
    {
        $flag = new Flag(['type' => 'scheduled', 'enabled' => true, 'timezone' => 'America/Vancouver']);
        $flag->setRelation('rules', collect([
            new \App\Models\FlagRule($this->weekly('2', '07:00', '09:00')),
        ]));

        // 15:30 UTC on Monday is 08:30 in Vancouver and 11:30 in Toronto.
        $this->assertTrue((new FlagStateService())->isActive($flag, Carbon::parse('2026-10-12 15:30', 'UTC')));
    }
}
