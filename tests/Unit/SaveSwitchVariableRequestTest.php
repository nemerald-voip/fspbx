<?php

namespace Tests\Unit;

use App\Http\Requests\SaveSwitchVariableRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SaveSwitchVariableRequestTest extends TestCase
{
    public function test_order_is_optional_when_saving_switch_variable(): void
    {
        $request = new SaveSwitchVariableRequest();

        $validator = Validator::make([
            'var_category' => 'Defaults',
            'var_name' => 'example_var',
            'var_value' => 'example',
            'var_command' => 'set',
            'var_enabled' => true,
        ], $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_order_accepts_null_when_saving_switch_variable(): void
    {
        $request = new SaveSwitchVariableRequest();

        $validator = Validator::make([
            'var_category' => 'Defaults',
            'var_name' => 'example_var',
            'var_value' => 'example',
            'var_command' => 'set',
            'var_enabled' => true,
            'var_order' => null,
        ], $request->rules());

        $this->assertTrue($validator->passes());
    }
}
