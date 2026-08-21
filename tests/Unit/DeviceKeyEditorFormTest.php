<?php

namespace Tests\Unit;

use Tests\TestCase;

class DeviceKeyEditorFormTest extends TestCase
{
    public function test_key_value_selects_preserve_absent_values_when_rows_are_reordered(): void
    {
        foreach ([
            'CreateDeviceForm.vue',
            'UpdateDeviceForm.vue',
            'DeviceKeyTemplateKeyList.vue',
        ] as $formName) {
            $form = file_get_contents(base_path(
                'resources/js/Pages/components/forms/'.$formName
            ));

            preg_match_all(
                '/<SelectElement name="key_value_select".*?\/>/s',
                $form,
                $matches
            );

            $this->assertNotEmpty($matches[0], $formName.' has no key value selects.');

            foreach ($matches[0] as $select) {
                $this->assertStringContainsString('allow-absent', $select, $formName);
            }
        }
    }

    public function test_device_key_template_rows_keep_stable_identity_when_reordered(): void
    {
        $form = file_get_contents(base_path(
            'resources/js/Pages/components/forms/DeviceKeyTemplateKeyList.vue'
        ));

        $this->assertStringContainsString(
            ':key="formData?.[name]?.[index]?.key_uuid"',
            $form
        );
    }
}
