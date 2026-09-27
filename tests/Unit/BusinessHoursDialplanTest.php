<?php

namespace Tests\Unit;

use App\Models\BusinessHour;
use App\Models\BusinessHourHoliday;
use App\Models\BusinessHourPeriod;
use App\Models\DynamicRoute;
use App\Services\CallRoutingOptionsService;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\Compiler;
use ReflectionProperty;
use Tests\TestCase;

class BusinessHoursDialplanTest extends TestCase
{
    /** @dataProvider routingTargets */
    public function test_selected_targets_are_used_in_every_routing_branch(string $action, ?string $model, ?string $field, ?string $extension, string $application, string $data): void
    {
        $compiledPath = sys_get_temp_dir().'/fspbx-business-hours-tests-'.getmypid();
        File::ensureDirectoryExists($compiledPath);
        $compiler = app('blade.compiler');
        $cachePath = new ReflectionProperty(Compiler::class, 'cachePath');
        $originalPath = $cachePath->getValue($compiler);
        $cachePath->setValue($compiler, $compiledPath);

        try {
            session(['domain_name' => 'example.test', 'domain_uuid' => RoutingDestinations::DOMAIN]);
            $target = RoutingDestinations::target($model, $field, $extension);
            $businessHour = new BusinessHour([
                'domain_uuid' => RoutingDestinations::DOMAIN,
                'name' => 'Routing test',
                'extension' => '9200',
                'timezone' => 'America/Los_Angeles',
                'after_hours_action' => $action,
            ]);
            $businessHour->setRelation('after_hours_target', $target);
            $holiday = new BusinessHourHoliday([
                'holiday_type' => 'us_holiday',
                'mon' => '12',
                'mday' => '25',
                'action' => $action,
            ]);
            $holiday->setRelation('target', $target);
            $businessHour->setRelation('holidays', collect([$holiday]));
            $period = new BusinessHourPeriod([
                'day_of_week' => 2,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'action' => $action,
            ]);
            $period->setRelation('target', $target);
            $businessHour->setRelation('periods', collect([$period]));

            $xml = view('layouts.xml.business-hours-dial-plan-template', compact('businessHour'))->render();
            $document = new \DOMDocument();
            $this->assertTrue($document->loadXML($xml));
            $xpath = new \DOMXPath($document);
            foreach ([
                '//condition[@mon="12" and @mday="25"]',
                '//condition[@wday="2" and @time-of-day="09:00-17:00"]',
                '//condition[@field="${slot_matched}"]',
            ] as $condition) {
                $actions = $xpath->query($condition.'/action[@application!="set"]');
                $this->assertGreaterThan(0, $actions->length);
                $this->assertSame($application, $actions->item(0)->getAttribute('application'));
                $this->assertSame($data, $actions->item(0)->getAttribute('data'));
            }
            $this->assertSame(0, $xpath->query('//action[@application="transfer" and @data="9200 XML example.test"]')->length);
        } finally {
            $cachePath->setValue($compiler, $originalPath);
            File::deleteDirectory($compiledPath);
        }
    }

    public static function routingTargets(): array
    {
        $cases = [];
        foreach (RoutingDestinations::cases() as $type => $case) {
            $cases[$type] = [$type, ...$case];
        }

        return $cases;
    }

    public function test_every_selectable_type_has_a_regression_case(): void
    {
        $this->assertEqualsCanonicalizing(CallRoutingOptionsService::destinationTypes(), array_keys(RoutingDestinations::cases()));
    }

    public function test_holiday_only_schedule_routes_non_holidays_to_selected_dynamic_route(): void
    {
        $compiledPath = sys_get_temp_dir().'/fspbx-business-hours-tests-'.getmypid();
        File::ensureDirectoryExists($compiledPath);
        $compiler = app('blade.compiler');
        $cachePath = new ReflectionProperty(Compiler::class, 'cachePath');
        $originalPath = $cachePath->getValue($compiler);
        $cachePath->setValue($compiler, $compiledPath);

        try {
            session(['domain_name' => 'example.test', 'domain_uuid' => RoutingDestinations::DOMAIN]);
            $businessHour = new BusinessHour([
                'domain_uuid' => RoutingDestinations::DOMAIN,
                'name' => 'Check Holidays',
                'extension' => '9200',
                'timezone' => 'America/Los_Angeles',
                'after_hours_action' => 'dynamic_routes',
            ]);
            $businessHour->setRelation('periods', collect());
            $businessHour->setRelation('after_hours_target', new DynamicRoute(['extension' => '9504', 'domain_uuid' => RoutingDestinations::DOMAIN]));
            $holiday = new BusinessHourHoliday([
                'holiday_type' => 'us_holiday',
                'mon' => '12',
                'mday' => '25',
                'action' => 'ivrs',
            ]);
            $holiday->setRelation('target', RoutingDestinations::target(\App\Models\IvrMenus::class, 'ivr_menu_extension', '9155'));
            $businessHour->setRelation('holidays', collect([$holiday]));

            $xml = view('layouts.xml.business-hours-dial-plan-template', compact('businessHour'))->render();
            $document = new \DOMDocument();
            $this->assertTrue($document->loadXML($xml));
            $xpath = new \DOMXPath($document);
            $this->assertSame('9155 XML example.test', $xpath->evaluate('string(//condition[@mon="12" and @mday="25"]/action[@application="transfer"]/@data)'));
            $this->assertSame('9504 XML example.test', $xpath->evaluate('string(//condition[@field="${slot_matched}"]/action[@application="transfer"]/@data)'));
            $this->assertSame(0, $xpath->query('//action[@application="transfer" and @data="9200 XML example.test"]')->length);
        } finally {
            $cachePath->setValue($compiler, $originalPath);
            File::deleteDirectory($compiledPath);
        }
    }
}
