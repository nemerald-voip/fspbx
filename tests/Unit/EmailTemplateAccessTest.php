<?php

namespace Tests\Unit;

use App\Http\Controllers\EmailTemplateController;
use App\Models\EmailTemplate;
use ReflectionMethod;
use Tests\TestCase;

class EmailTemplateAccessTest extends TestCase
{
    public function test_visible_query_is_limited_to_defaults_global_custom_and_current_account_custom(): void
    {
        session(['domain_uuid' => '11111111-1111-4111-8111-111111111111']);

        $method = new ReflectionMethod(EmailTemplateController::class, 'visibleQuery');
        $query = $method->invoke(new EmailTemplateController());

        $this->assertSame([
            'default',
            'custom',
            'custom',
            '11111111-1111-4111-8111-111111111111',
        ], $query->getBindings());
        $this->assertSame(2, substr_count($query->toSql(), '"domain_uuid" is null'));
        $this->assertStringContainsString('"domain_uuid" = ?', $query->toSql());
    }

    public function test_global_custom_templates_require_the_global_management_permission(): void
    {
        session([
            'domain_uuid' => '11111111-1111-4111-8111-111111111111',
            'permissions' => [],
        ]);

        $method = new ReflectionMethod(EmailTemplateController::class, 'canManage');
        $controller = new EmailTemplateController();

        $current = new EmailTemplate([
            'template_type' => 'custom',
            'domain_uuid' => '11111111-1111-4111-8111-111111111111',
        ]);
        $other = new EmailTemplate([
            'template_type' => 'custom',
            'domain_uuid' => '22222222-2222-4222-8222-222222222222',
        ]);
        $global = new EmailTemplate([
            'template_type' => 'custom',
            'domain_uuid' => null,
        ]);
        $default = new EmailTemplate([
            'template_type' => 'default',
            'domain_uuid' => null,
        ]);

        $this->assertTrue($method->invoke($controller, $current));
        $this->assertFalse($method->invoke($controller, $other));
        $this->assertFalse($method->invoke($controller, $global));
        $this->assertFalse($method->invoke($controller, $default));

        session(['permissions' => [
            (object) ['permission_name' => 'email_templates_manage_global'],
        ]]);

        $this->assertTrue($method->invoke($controller, $global));
        $this->assertFalse($method->invoke($controller, $other));
        $this->assertFalse($method->invoke($controller, $default));
    }
}
