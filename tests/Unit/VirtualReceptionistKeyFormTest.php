<?php

namespace Tests\Unit;

use App\Http\Controllers\VirtualReceptionistController;
use App\Http\Requests\CreateVirtualReceptionistKeyRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class VirtualReceptionistKeyFormTest extends TestCase
{
    private const MENU_UUID = 'cc6d964a-dde4-4645-ba3d-0e139bf4ddc9';
    private const DOMAIN_UUID = '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5';
    private const AGENT_UUID = 'b7c018a8-99cb-40f8-a6b6-9493115ca472';

    public function test_structured_vueform_selections_are_normalized_to_scalars(): void
    {
        $request = $this->request([
            'menu_uuid' => self::MENU_UUID,
            'domain_uuid' => self::DOMAIN_UUID,
            'key' => '4',
            'status' => 'true',
            'action' => ['value' => 'ai_agents', 'name' => 'AI Agent'],
            'target' => [
                'value' => self::AGENT_UUID,
                'extension' => '9450',
                'name' => '9450 - Emma',
            ],
            'extension' => null,
            'description' => null,
        ]);

        $request->normalizeForValidation();

        $this->assertSame('ai_agents', $request->input('action'));
        $this->assertSame(self::AGENT_UUID, $request->input('target'));
        $this->assertSame('9450', $request->input('extension'));
        $this->assertTrue($request->boolean('status'));
        $this->assertFalse(Validator::make($request->all(), $request->rules())->fails());
    }

    public function test_ai_agent_key_builds_a_tenant_transfer_string(): void
    {
        session(['domain_name' => 'account.example.com']);

        $controller = (new \ReflectionClass(VirtualReceptionistController::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'buildKeyDestinationAction');

        $this->assertSame(
            'transfer 9450 XML account.example.com',
            $method->invoke($controller, [
                'action' => 'ai_agents',
                'target' => self::AGENT_UUID,
                'extension' => '9450',
            ])
        );
    }

    public function test_key_editor_uses_vueform_controls(): void
    {
        $form = file_get_contents(
            dirname(__DIR__, 2) . '/resources/js/Pages/components/forms/VirtualReceptionistKeyForm.vue'
        );

        $this->assertStringContainsString('<Vueform', $form);
        $this->assertStringContainsString('<ToggleElement', $form);
        $this->assertStringContainsString('<SelectElement', $form);
        $this->assertStringContainsString('<ButtonElement', $form);
        $this->assertStringNotContainsString('<ComboBox', $form);
        $this->assertStringNotContainsString('<form', $form);
    }

    private function request(array $payload): CreateVirtualReceptionistKeyRequest
    {
        $request = new class extends CreateVirtualReceptionistKeyRequest
        {
            public function normalizeForValidation(): void
            {
                $this->prepareForValidation();
            }
        };

        $request->setMethod('POST');
        $request->request->replace($payload);

        return $request;
    }
}
