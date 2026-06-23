<?php

namespace Tests\Unit\CallFlow;

use App\Models\BusinessHour;
use App\Models\BusinessHourHoliday;
use App\Models\BusinessHourPeriod;
use App\Services\CallFlow\BusinessHoursEvaluator;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class BusinessHoursEvaluatorTest extends TestCase
{
    private BusinessHoursEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new BusinessHoursEvaluator();
    }

    private function period(int $dow, string $start, string $end): BusinessHourPeriod
    {
        $p = new BusinessHourPeriod();
        $p->day_of_week = $dow;
        $p->start_time = $start;
        $p->end_time = $end;
        return $p;
    }

    private function buildBusinessHour(array $periods = [], array $holidays = [], string $tz = 'UTC'): BusinessHour
    {
        $bh = new BusinessHour();
        $bh->timezone = $tz;
        $bh->setRelation('periods', new Collection($periods));
        $bh->setRelation('holidays', new Collection($holidays));
        return $bh;
    }

    private function at(string $iso, string $tz = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($iso, new DateTimeZone($tz));
    }

    public function test_weekday_9_to_5_in_hours(): void
    {
        // FreeSWITCH day_of_week: 1=Sun…7=Sat. Monday=2, Friday=6.
        $bh = $this->buildBusinessHour([
            $this->period(2, '09:00', '17:00'),
            $this->period(3, '09:00', '17:00'),
            $this->period(4, '09:00', '17:00'),
            $this->period(5, '09:00', '17:00'),
            $this->period(6, '09:00', '17:00'),
        ]);
        // 2026-04-23 is Thu (wday=5 FreeSWITCH).
        $r = $this->evaluator->evaluate($bh, $this->at('2026-04-23T10:00:00Z'));
        $this->assertTrue($r['is_in_hours']);
        $this->assertNotNull($r['period']);
    }

    public function test_weekend_is_out_of_hours(): void
    {
        $bh = $this->buildBusinessHour([
            $this->period(2, '09:00', '17:00'),
        ]);
        // Sat 2026-04-25.
        $r = $this->evaluator->evaluate($bh, $this->at('2026-04-25T10:00:00Z'));
        $this->assertFalse($r['is_in_hours']);
        $this->assertNull($r['period']);
    }

    public function test_evening_is_out_of_hours(): void
    {
        $bh = $this->buildBusinessHour([
            $this->period(5, '09:00', '17:00'),
        ]);
        $r = $this->evaluator->evaluate($bh, $this->at('2026-04-23T19:00:00Z'));
        $this->assertFalse($r['is_in_hours']);
    }

    public function test_timezone_shifts_boundary(): void
    {
        // 09:00 London BST = 08:00 UTC.
        $bh = $this->buildBusinessHour(
            [$this->period(5, '09:00', '17:00')],
            [],
            'Europe/London',
        );

        // 08:30 UTC in July = 09:30 BST — in hours.
        $r = $this->evaluator->evaluate($bh, $this->at('2026-07-16T08:30:00Z'));
        $this->assertTrue($r['is_in_hours']);

        // 07:30 UTC = 08:30 BST — still out.
        $r = $this->evaluator->evaluate($bh, $this->at('2026-07-16T07:30:00Z'));
        $this->assertFalse($r['is_in_hours']);
    }

    public function test_overnight_period_wraps(): void
    {
        // 22:00 → 02:00 next day.
        $bh = $this->buildBusinessHour([
            $this->period(5, '22:00', '02:00'),
        ]);
        // 2026-04-23 is Thursday = FS wday 5; 23:00 should match.
        $r = $this->evaluator->evaluate($bh, $this->at('2026-04-23T23:00:00Z'));
        $this->assertTrue($r['is_in_hours']);
    }

    public function test_single_date_holiday_closes(): void
    {
        $holiday = new BusinessHourHoliday();
        $holiday->holiday_type = 'single_date';
        $holiday->start_date = \Carbon\Carbon::parse('2026-04-23');
        $holiday->description = 'Test Holiday';

        $bh = $this->buildBusinessHour(
            [$this->period(5, '09:00', '17:00')],
            [$holiday],
        );

        $r = $this->evaluator->evaluate($bh, $this->at('2026-04-23T10:00:00Z'));
        $this->assertFalse($r['is_in_hours']);
        $this->assertNotNull($r['holiday']);
    }

    public function test_recurring_holiday_emits_warning(): void
    {
        $holiday = new BusinessHourHoliday();
        $holiday->holiday_type = 'us_holiday';
        $holiday->description = 'Thanksgiving';

        $bh = $this->buildBusinessHour(
            [$this->period(5, '09:00', '17:00')],
            [$holiday],
        );

        $r = $this->evaluator->evaluate($bh, $this->at('2026-04-23T10:00:00Z'));
        $this->assertTrue($r['is_in_hours']);
        $this->assertNotEmpty($r['warnings']);
    }
}
