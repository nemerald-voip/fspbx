<?php

namespace Tests\Unit;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DeviceLineOrderingContractTest extends TestCase
{
    public function test_device_line_numbers_are_validated_as_distinct_non_negative_integers(): void
    {
        $expectedRules = ['required', 'integer', 'min:0', 'distinct'];

        $this->assertSame(
            $expectedRules,
            (new StoreDeviceRequest())->rules()['device_lines.*.line_number']
        );
        $this->assertSame(
            $expectedRules,
            (new UpdateDeviceRequest())->rules()['device_lines.*.line_number']
        );

        $valid = Validator::make(
            ['device_lines' => array_map(
                fn (int $lineNumber) => ['line_number' => $lineNumber],
                [0, 1, 2, 3, 5, 6, 7, 9]
            )],
            ['device_lines.*.line_number' => $expectedRules]
        );
        $duplicate = Validator::make(
            ['device_lines' => [
                ['line_number' => 2],
                ['line_number' => 2],
            ]],
            ['device_lines.*.line_number' => $expectedRules]
        );

        $this->assertFalse($valid->fails());
        $this->assertTrue($duplicate->fails());
    }

    public function test_device_modal_options_sort_lines_numerically_and_reset_collection_indexes(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/DeviceController.php'));

        $this->assertStringContainsString(
            '->sortBy(fn ($line) => (int) $line->line_number)',
            $controller
        );
        $this->assertMatchesRegularExpression(
            '/->sortBy\(fn \(\$line\) => \(int\) \$line->line_number\)\s*->values\(\)/',
            $controller
        );
    }

    public function test_new_device_lines_default_to_the_standard_line_type(): void
    {
        foreach (['CreateDeviceForm.vue', 'UpdateDeviceForm.vue'] as $formName) {
            $form = file_get_contents(base_path(
                'resources/js/Pages/components/forms/'.$formName
            ));

            $this->assertMatchesRegularExpression(
                '/<SelectElement name="line_type_id"[^>]*default="line"/',
                $form
            );
        }
    }
}
