<?php

namespace Tests\Unit;

use App\Http\Requests\InvokeAiSendEmailToolRequest;
use App\Services\AiTools\AiProviderToolCatalog;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InvokeAiSendEmailToolRequestTest extends TestCase
{
    public function test_it_requires_the_retell_recipient_placeholder_to_be_replaced(): void
    {
        $request = new InvokeAiSendEmailToolRequest();
        $validator = Validator::make(
            $this->payload(AiProviderToolCatalog::SEND_EMAIL_RECIPIENT_PLACEHOLDER),
            $request->rules(),
        );

        $this->assertTrue($validator->errors()->has('args.recipient'));
    }

    public function test_it_accepts_a_retell_configured_recipient_and_flow_specific_fields(): void
    {
        $request = new InvokeAiSendEmailToolRequest();
        $payload = $this->payload('team@example.org');
        $payload['args']['fields'][] = ['label' => 'Account', 'value' => 'A-1024'];

        $validator = Validator::make($payload, $request->rules());

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }

    private function payload(string $recipient): array
    {
        return [
            'name' => AiProviderToolCatalog::SEND_EMAIL_TOOL_NAME,
            'call' => [
                'call_id' => 'call-1',
                'agent_id' => 'retell-agent-1',
                'custom_sip_headers' => [
                    'X-FSPBX-Agent-UUID' => '40a021c1-6016-4c62-af08-43dcb4c44528',
                ],
            ],
            'args' => [
                'recipient' => $recipient,
                'subject' => 'Caller follow-up',
                'fields' => [
                    ['label' => 'Name', 'value' => 'Jordan Lee'],
                    ['label' => 'Callback', 'value' => '+1 202-555-0142'],
                ],
                'notes' => 'Please call this afternoon.',
            ],
        ];
    }
}
