<?php

namespace Tests\Unit;

use App\Http\Requests\IndexAiAgentLogRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AiAgentLogContractTest extends TestCase
{
    public function test_ai_agent_email_does_not_expose_provider_call_details(): void
    {
        $html = file_get_contents(base_path('resources/views/emails/ai-agent/send-email.blade.php'));
        $text = file_get_contents(base_path('resources/views/emails/ai-agent/send-email-text.blade.php'));

        $this->assertStringContainsString('version: 1.0.1', $html);
        $this->assertStringNotContainsString('Retell', $html . $text);
        $this->assertStringNotContainsString('provider_call_id', $html . $text);
    }

    public function test_log_filters_accept_a_search_date_range_and_account(): void
    {
        $request = new IndexAiAgentLogRequest();
        $validator = Validator::make([
            'page' => 2,
            'filter' => [
                'search' => 'call_a9e0c4b39ce3001c823566b8f55',
                'domain_uuid' => '11111111-1111-4111-8111-111111111111',
                'dateRange' => ['2026-08-16T00:00:00Z', '2026-08-17T23:59:59Z'],
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails(), $validator->errors()->toJson());
    }

    public function test_log_surface_is_permission_and_tenant_scoped(): void
    {
        $request = file_get_contents(base_path('app/Http/Requests/IndexAiAgentLogRequest.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/AiAgentLogsController.php'));
        $page = file_get_contents(base_path('resources/js/Pages/Logs.vue'));

        $this->assertStringContainsString("userCheckPermission('logs_list_view')", $request);
        $this->assertStringContainsString("userCheckPermission('ai_agent_view')", $request);
        $this->assertStringContainsString("->where('domain_uuid', \$domainUuid)", $controller);
        $this->assertStringContainsString("->whereIn('domain_uuid', \$this->allowedDomainUuids())", $controller);
        $this->assertStringContainsString('v-if="permissions?.ai_agent_view"', $page);
    }

    public function test_log_keeps_the_provider_call_id_out_of_email_and_available_in_logs(): void
    {
        $service = file_get_contents(base_path('app/Services/AiTools/AiSendEmailToolService.php'));
        $job = file_get_contents(base_path('app/Jobs/SendAiToolEmail.php'));
        $component = file_get_contents(base_path('resources/js/Pages/components/AiAgentLogs.vue'));

        $this->assertStringContainsString("'domain_uuid' => \$agent->domain_uuid", $service);
        $this->assertStringNotContainsString("'provider_call_id' => \$invocation->provider_call_id", $job);
        $this->assertStringContainsString('{{ row.provider_call_id }}', $component);
        $this->assertStringContainsString('@click.stop="copyCallId(row.provider_call_id)"', $component);
    }

    public function test_log_rows_expand_like_the_existing_fax_and_webhook_logs(): void
    {
        $component = file_get_contents(base_path('resources/js/Pages/components/AiAgentLogs.vue'));

        $this->assertStringContainsString('@click="toggleExpand(row.ai_tool_invocation_uuid)"', $component);
        $this->assertStringContainsString('@keydown.enter.prevent="toggleExpand(row.ai_tool_invocation_uuid)"', $component);
        $this->assertStringContainsString("\$t('Overview')", $component);
        $this->assertStringContainsString("\$t('Invocation')", $component);
        $this->assertStringContainsString("\$t('Result')", $component);
        $this->assertStringNotContainsString('<ChevronDownIcon', $component);
    }

    public function test_validated_tool_payload_is_recorded_and_rendered_in_the_log_details(): void
    {
        $service = file_get_contents(base_path('app/Services/AiTools/AiSendEmailToolService.php'));
        $model = file_get_contents(base_path('app/Models/AiToolInvocation.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/AiAgentLogsController.php'));
        $component = file_get_contents(base_path('resources/js/Pages/components/AiAgentLogs.vue'));

        $this->assertStringContainsString("'request_payload' => \$requestPayload", $service);
        $this->assertStringContainsString("'request_payload' => 'array'", $model);
        $this->assertStringContainsString("'request_payload',", $controller);
        $this->assertStringContainsString("request_payload::text ILIKE", $controller);
        $this->assertStringContainsString("\$t('Payload')", $component);
        $this->assertStringContainsString('row.request_payload.recipient', $component);
        $this->assertStringContainsString('emailFields(row)', $component);
        $this->assertStringContainsString("\$t('Raw payload')", $component);
        $this->assertStringContainsString("\$t('Payload was not recorded for this invocation.')", $component);
    }
}
