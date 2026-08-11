<?php

namespace Tests\Unit;

use App\Http\Requests\StoreBridgeRequest;
use Tests\TestCase;

class StoreBridgeRequestTest extends TestCase
{
    public function test_it_normalizes_the_legacy_vueform_object_string(): void
    {
        $request = new class extends StoreBridgeRequest
        {
            public function normalize(): void
            {
                $this->prepareForValidation();
            }
        };

        $request->initialize([], ['bridge_variables' => '[object Object]'], [], [], [], [
            'REQUEST_METHOD' => 'POST',
        ]);
        $request->normalize();

        $this->assertNull($request->input('bridge_variables'));
    }

    public function test_it_decodes_json_encoded_bridge_variables(): void
    {
        $request = new class extends StoreBridgeRequest
        {
            public function normalize(): void
            {
                $this->prepareForValidation();
            }
        };

        $request->initialize([], ['bridge_variables' => '{"continue_on_fail":"true"}'], [], [], [], [
            'REQUEST_METHOD' => 'POST',
        ]);
        $request->normalize();

        $this->assertSame(
            ['continue_on_fail' => 'true'],
            $request->input('bridge_variables')
        );
    }
}
