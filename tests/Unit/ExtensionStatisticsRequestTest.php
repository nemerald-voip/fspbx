<?php

namespace Tests\Unit;

use App\Http\Requests\ExportExtensionStatisticsRequest;
use App\Http\Requests\ExtensionStatisticsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ExtensionStatisticsRequestTest extends TestCase
{
    public function test_view_and_export_actions_use_their_concrete_permissions(): void
    {
        session(['permissions' => [
            (object) ['permission_name' => 'xml_cdr_view'],
        ]]);

        $this->assertTrue((new ExtensionStatisticsRequest())->authorize());
        $this->assertFalse((new ExportExtensionStatisticsRequest())->authorize());

        session(['permissions' => [
            (object) ['permission_name' => 'xml_cdr_export'],
        ]]);

        $this->assertFalse((new ExtensionStatisticsRequest())->authorize());
        $this->assertTrue((new ExportExtensionStatisticsRequest())->authorize());
    }

    public function test_valid_statistics_filters_pass_validation(): void
    {
        $validator = Validator::make([
            'filter' => [
                'search' => '1001',
                'showGlobal' => 'false',
                'dateRange' => [
                    '2026-08-07T04:00:00.000Z',
                    '2026-08-08T03:59:59.999Z',
                ],
            ],
            'page' => 2,
            'per_page' => 100,
        ], (new ExtensionStatisticsRequest())->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_invalid_date_ranges_and_page_sizes_are_rejected(): void
    {
        $validator = Validator::make([
            'filter' => [
                'dateRange' => [
                    '2026-08-08T03:59:59.999Z',
                    '2026-08-07T04:00:00.000Z',
                ],
            ],
            'page' => 0,
            'per_page' => 75,
        ], (new ExtensionStatisticsRequest())->rules());

        $this->assertTrue($validator->errors()->has('filter.dateRange.1'));
        $this->assertTrue($validator->errors()->has('page'));
        $this->assertTrue($validator->errors()->has('per_page'));
    }

    public function test_cleared_date_range_uses_the_controller_default(): void
    {
        $validator = Validator::make([
            'filter' => ['dateRange' => null],
        ], (new ExtensionStatisticsRequest())->rules());

        $this->assertTrue($validator->passes());
    }
}
