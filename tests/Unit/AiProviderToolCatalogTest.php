<?php

namespace Tests\Unit;

use App\Services\AiTools\AiProviderToolCatalog;
use Tests\TestCase;

class AiProviderToolCatalogTest extends TestCase
{
    public function test_send_email_uses_a_retell_configured_recipient_and_flexible_collected_fields(): void
    {
        $tool = app(AiProviderToolCatalog::class)->definitions('retell')[0];

        $this->assertSame('fspbx_send_email', $tool['name']);
        $this->assertSame('fspbx_managed_send_email', $tool['tool_id']);
        $this->assertSame(2, AiProviderToolCatalog::REVISION);
        $this->assertSame(
            AiProviderToolCatalog::SEND_EMAIL_RECIPIENT_PLACEHOLDER,
            $tool['parameters']['properties']['recipient']['const'],
        );
        $this->assertContains('recipient', $tool['parameters']['required']);
        $this->assertSame('array', $tool['parameters']['properties']['fields']['type']);
        $this->assertSame(['label', 'value'], $tool['parameters']['properties']['fields']['items']['required']);
        $this->assertFalse($tool['speak_after_execution']);
        $this->assertStringContainsString('Never tell the caller the email was sent', $tool['description']);
        $this->assertSame(url('/api/ai-tools/retell/send-email'), $tool['url']);
    }
}
