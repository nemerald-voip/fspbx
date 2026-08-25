<?php

namespace Tests\Unit;

use App\Http\Requests\StoreAiAgentRequest;
use App\Http\Requests\UpdateAiAgentRequest;
use App\Models\AiAgent;
use Tests\TestCase;

class AiAgentDomainPermissionRequestTest extends TestCase
{
    private const SESSION_DOMAIN_UUID = '11111111-1111-4111-8111-111111111111';
    private const EXISTING_DOMAIN_UUID = '22222222-2222-4222-8222-222222222222';
    private const SUBMITTED_DOMAIN_UUID = '33333333-3333-4333-8333-333333333333';
    private const EXISTING_PROVIDER = 'retell';
    private const SUBMITTED_PROVIDER = 'future-provider';

    public function test_create_uses_session_account_without_manage_domain_permission(): void
    {
        $this->setOperator(true, []);

        $request = $this->storeRequest(self::SUBMITTED_DOMAIN_UUID);
        $request->normalizeForValidation();

        $this->assertSame(self::SESSION_DOMAIN_UUID, $request->input('domain_uuid'));
    }

    public function test_create_accepts_selected_account_for_permitted_superadmin(): void
    {
        $this->setOperator(true, ['ai_agent_manage_domain']);

        $request = $this->storeRequest(self::SUBMITTED_DOMAIN_UUID);
        $request->normalizeForValidation();

        $this->assertSame(self::SUBMITTED_DOMAIN_UUID, $request->input('domain_uuid'));
    }

    public function test_manage_domain_permission_does_not_apply_to_non_superadmin(): void
    {
        $this->setOperator(false, ['ai_agent_manage_domain']);

        $request = $this->storeRequest(self::SUBMITTED_DOMAIN_UUID);
        $request->normalizeForValidation();

        $this->assertSame(self::SESSION_DOMAIN_UUID, $request->input('domain_uuid'));
    }

    public function test_update_preserves_existing_account_without_manage_domain_permission(): void
    {
        $this->setOperator(true, []);

        $request = $this->updateRequest(self::SUBMITTED_DOMAIN_UUID);
        $request->normalizeForValidation();

        $this->assertSame(self::EXISTING_DOMAIN_UUID, $request->input('domain_uuid'));
    }

    public function test_update_accepts_selected_account_for_permitted_superadmin(): void
    {
        $this->setOperator(true, ['ai_agent_manage_domain']);

        $request = $this->updateRequest(self::SUBMITTED_DOMAIN_UUID);
        $request->normalizeForValidation();

        $this->assertSame(self::SUBMITTED_DOMAIN_UUID, $request->input('domain_uuid'));
    }

    public function test_create_uses_default_provider_without_manage_provider_permission(): void
    {
        $this->setOperator(true, []);

        $request = $this->storeRequest(self::SUBMITTED_DOMAIN_UUID, self::SUBMITTED_PROVIDER);
        $request->normalizeForValidation();

        $this->assertSame(self::EXISTING_PROVIDER, $request->input('provider'));
    }

    public function test_create_accepts_selected_provider_for_permitted_superadmin(): void
    {
        $this->setOperator(true, ['ai_agent_manage_provider']);

        $request = $this->storeRequest(self::SUBMITTED_DOMAIN_UUID, self::SUBMITTED_PROVIDER);
        $request->normalizeForValidation();

        $this->assertSame(self::SUBMITTED_PROVIDER, $request->input('provider'));
    }

    public function test_manage_provider_permission_does_not_apply_to_non_superadmin(): void
    {
        $this->setOperator(false, ['ai_agent_manage_provider']);

        $request = $this->storeRequest(self::SUBMITTED_DOMAIN_UUID, self::SUBMITTED_PROVIDER);
        $request->normalizeForValidation();

        $this->assertSame(self::EXISTING_PROVIDER, $request->input('provider'));
    }

    public function test_update_always_preserves_the_provisioned_provider(): void
    {
        $this->setOperator(true, ['ai_agent_manage_provider']);

        $request = $this->updateRequest(self::SUBMITTED_DOMAIN_UUID, self::SUBMITTED_PROVIDER);
        $request->normalizeForValidation();

        $this->assertSame(self::EXISTING_PROVIDER, $request->input('provider'));
    }

    public function test_account_field_is_in_permission_gated_advanced_tab(): void
    {
        $form = file_get_contents(base_path('resources/js/Pages/components/forms/AiAgentForm.vue'));

        $this->assertStringContainsString(
            '<FormTab v-if="canManageDomain" name="advanced"',
            $form,
        );
        $this->assertStringContainsString(
            '<SelectElement name="domain_uuid" :label="$t(\'Account\')"',
            $form,
        );
        $this->assertStringNotContainsString(
            "'settings_header', 'uuid', 'domain_uuid', 'provider'",
            $form,
        );
    }

    public function test_settings_fields_follow_identity_routing_provider_state_flow(): void
    {
        $form = file_get_contents(base_path('resources/js/Pages/components/forms/AiAgentForm.vue'));
        $start = strpos($form, '<StaticElement name="settings_header"');
        $end = strpos($form, '<GroupElement name="settings_button_container"');
        $settings = substr($form, $start, $end - $start);

        $positions = array_map(
            fn (string $field) => strpos($settings, "name=\"{$field}\""),
            ['name', 'extension', 'provider', 'enabled', 'description'],
        );

        foreach ($positions as $position) {
            $this->assertNotFalse($position);
        }

        $this->assertSame($positions, collect($positions)->sort()->values()->all());
        $this->assertStringContainsString(
            '<TextElement name="name" :label="$t(\'Name\')"',
            $settings,
        );
        $this->assertStringContainsString(
            ':columns="{ sm: { container: canManageProvider ? 8 : 6 } }"',
            $settings,
        );
    }

    public function test_provider_field_is_permission_gated_and_not_rendered_in_the_list(): void
    {
        $form = file_get_contents(base_path('resources/js/Pages/components/forms/AiAgentForm.vue'));
        $page = file_get_contents(base_path('resources/js/Pages/AiAgents.vue'));

        $this->assertStringContainsString(
            '<SelectElement v-if="canManageProvider" name="provider"',
            $form,
        );
        $this->assertStringContainsString(
            'if (!canManageProvider.value) delete data.provider;',
            $form,
        );
        $this->assertStringNotContainsString(
            '<TableColumnHeader :header="$t(\'Provider\')"',
            $page,
        );
        $this->assertStringNotContainsString('providerLabel(row.provider)', $page);
    }

    public function test_agent_uuid_has_the_standard_copy_control(): void
    {
        $form = file_get_contents(base_path('resources/js/Pages/components/forms/AiAgentForm.vue'));

        $this->assertStringContainsString(
            '@click="handleCopyToClipboard(options.item?.ai_agent_uuid)"',
            $form,
        );
        $this->assertStringContainsString(
            'import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";',
            $form,
        );
        $this->assertStringContainsString(
            'trans("Copied to clipboard.")',
            $form,
        );
    }

    public function test_account_is_not_rendered_in_the_list_view(): void
    {
        $page = file_get_contents(base_path('resources/js/Pages/AiAgents.vue'));

        $this->assertStringNotContainsString(
            '<TableColumnHeader :header="$t(\'Account\')"',
            $page,
        );
        $this->assertStringNotContainsString('row.domain?.', $page);
    }

    public function test_agent_uuid_is_not_rendered_under_the_list_name(): void
    {
        $page = file_get_contents(base_path('resources/js/Pages/AiAgents.vue'));

        $this->assertStringNotContainsString('@click="copy(row.ai_agent_uuid)"', $page);
        $this->assertStringNotContainsString('navigator.clipboard.writeText(value)', $page);
    }

    public function test_optional_email_recipient_is_not_presented_as_a_tool_sync_warning(): void
    {
        $page = file_get_contents(base_path('resources/js/Pages/AiAgents.vue'));

        $this->assertStringNotContainsString('toolConfigurationRequired', $page);
        $this->assertStringNotContainsString('trans("Email recipient required")', $page);
    }

    private function storeRequest(string $domainUuid, string $provider = self::EXISTING_PROVIDER): StoreAiAgentRequest
    {
        $request = new class extends StoreAiAgentRequest
        {
            public function normalizeForValidation(): void
            {
                $this->prepareForValidation();
            }
        };
        $request->setMethod('POST');
        $request->request->replace([
            'domain_uuid' => $domainUuid,
            'provider' => $provider,
        ]);

        return $request;
    }

    private function updateRequest(string $domainUuid, string $provider = self::EXISTING_PROVIDER): UpdateAiAgentRequest
    {
        $request = new class extends UpdateAiAgentRequest
        {
            public function normalizeForValidation(): void
            {
                $this->prepareForValidation();
            }
        };
        $request->setMethod('PUT');
        $request->request->replace([
            'domain_uuid' => $domainUuid,
            'provider' => $provider,
        ]);

        $agent = new AiAgent([
            'ai_agent_uuid' => '44444444-4444-4444-8444-444444444444',
            'domain_uuid' => self::EXISTING_DOMAIN_UUID,
            'provider' => self::EXISTING_PROVIDER,
        ]);

        $request->setRouteResolver(fn () => new class($agent)
        {
            public function __construct(private readonly AiAgent $agent)
            {
            }

            public function parameter(string $name, mixed $default = null): mixed
            {
                return $name === 'ai_agent' ? $this->agent : $default;
            }
        });

        return $request;
    }

    private function setOperator(bool $superadmin, array $permissions): void
    {
        session()->put('domain_uuid', self::SESSION_DOMAIN_UUID);
        session()->put('permissions', array_map(
            fn (string $permission) => (object) ['permission_name' => $permission],
            $permissions,
        ));
        session()->put('user.groups', $superadmin
            ? [(object) ['group_name' => 'superadmin', 'group_level' => 80]]
            : [(object) ['group_name' => 'admin', 'group_level' => 50]]);
    }
}
