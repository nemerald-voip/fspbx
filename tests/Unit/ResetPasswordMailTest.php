<?php

namespace Tests\Unit;

use App\Mail\ResetPasswordMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResetPasswordMailTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.reset_password_mail_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'reset_password_mail_test');
        DB::purge('reset_password_mail_test');

        // Minimal schema for BaseMailable::mergeDefaultSettings(); no email_templates
        // table, so the mail renders from the shipped file-based template source
        // (App\Services\EmailTemplateSourceService), exercising the real template
        // file on disk.
        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->uuid('default_setting_uuid')->primary();
            $table->string('default_setting_category');
            $table->string('default_setting_subcategory');
            $table->string('default_setting_value')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('reset_password_mail_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_the_reset_password_notification_is_routed_through_the_branded_mailable(): void
    {
        $notifiable = new class
        {
            public string $name_formatted = 'Jordan';

            public function getEmailForPasswordReset(): string
            {
                return 'jordan@example.test';
            }
        };

        $mail = (new ResetPassword('test-token'))->toMail($notifiable);

        $this->assertInstanceOf(ResetPasswordMail::class, $mail);
        $this->assertSame('jordan@example.test', $mail->attributes['email']);
        $this->assertSame('Jordan', $mail->attributes['name']);
        $this->assertStringContainsString('/test-token', $mail->attributes['url']);
        $this->assertStringContainsString('email=jordan%40example.test', $mail->attributes['url']);
    }

    public function test_content_renders_the_reset_link_and_expiry(): void
    {
        $mail = new ResetPasswordMail([
            'email' => 'jordan@example.test',
            'name' => 'Jordan',
            'url' => 'https://pbx.example.test/reset-password/test-token?email=jordan%40example.test',
            'expire_minutes' => 60,
        ]);

        $rendered = $mail->render();

        $this->assertStringContainsString(
            'https://pbx.example.test/reset-password/test-token',
            $rendered
        );
        $this->assertStringContainsString('60 minutes', $rendered);
        $this->assertStringContainsString('Jordan', $rendered);
    }

    public function test_subject_falls_back_to_the_shipped_template_subject(): void
    {
        $mail = new ResetPasswordMail([
            'email' => 'jordan@example.test',
            'name' => 'Jordan',
            'url' => 'https://pbx.example.test/reset-password/test-token',
            'expire_minutes' => 60,
        ]);

        $this->assertSame('Reset Password Notification', $mail->attributes['email_subject']);
    }
}
