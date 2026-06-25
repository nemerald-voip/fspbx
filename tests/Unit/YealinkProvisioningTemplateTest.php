<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class YealinkProvisioningTemplateTest extends TestCase
{
    public function test_t4_expansion_keys_roll_over_after_sixty_slots(): void
    {
        $rendered = $this->renderYealinkTemplate('t44u', [
            $this->expansionKey(60, '2363', 'Staff Room'),
            $this->expansionKey(61, '2365', 'Gale Ghougassian'),
        ]);

        $this->assertStringContainsString('expansion_module.1.key.60.value = 2363', $rendered);
        $this->assertStringContainsString('expansion_module.2.key.1.value = 2365', $rendered);
        $this->assertStringNotContainsString('expansion_module.1.key.61.value = 2365', $rendered);
    }

    public function test_t7_t8_expansion_keys_roll_over_after_seventy_eight_slots(): void
    {
        $rendered = $this->renderYealinkTemplate('t74u', [
            $this->expansionKey(78, '2378', 'Last EXP55 Slot'),
            $this->expansionKey(79, '2379', 'Second EXP55'),
        ]);

        $this->assertStringContainsString('expansion_module.1.key.78.value = 2378', $rendered);
        $this->assertStringContainsString('expansion_module.2.key.1.value = 2379', $rendered);
        $this->assertStringNotContainsString('expansion_module.1.key.79.value = 2379', $rendered);
    }

    public function test_w70b_does_not_render_expansion_keys(): void
    {
        $rendered = $this->renderYealinkTemplate('w70b', [
            $this->expansionKey(1, '2365', 'Ignored Sidecar Key'),
        ]);

        $this->assertStringNotContainsString('expansion_module.', $rendered);
        $this->assertStringNotContainsString('Ignored Sidecar Key', $rendered);
    }

    private function renderYealinkTemplate(string $template, array $expansionKeys): string
    {
        return Blade::render(
            file_get_contents(resource_path("provisioning/yealink/{$template}/template.blade.php")),
            [
                'flavor' => 'mac.cfg',
                'lines' => [],
                'main_keys' => [],
                'expansion_keys' => $expansionKeys,
                'settings' => [],
                'domain_name' => 'example.test',
            ]
        );
    }

    private function expansionKey(int $id, string $value, string $label): array
    {
        return [
            'id' => $id,
            'type' => '16',
            'line' => 1,
            'label' => $label,
            'value' => $value,
            'extension' => $value,
        ];
    }
}
