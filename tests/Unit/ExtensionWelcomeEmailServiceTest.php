<?php

namespace Tests\Unit;

use App\Mail\ExtensionWelcome;
use App\Services\ExtensionWelcomeEmailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\Compiler;
use ReflectionProperty;
use Tests\TestCase;

class ExtensionWelcomeEmailServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir().'/fspbx-extension-welcome-'.bin2hex(random_bytes(8)).'.sqlite';
        touch($this->databasePath);

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'mail.from.address' => 'noreply@example.test',
            'mail.from.name' => 'FS PBX',
            'app.url' => 'https://pbx.example.test',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();

        $compiledPath = '/tmp/fspbx-extension-welcome-view-tests';
        File::ensureDirectoryExists($compiledPath);
        config(['view.compiled' => $compiledPath]);

        $cachePath = new ReflectionProperty(Compiler::class, 'cachePath');
        $cachePath->setValue(app('blade.compiler'), $compiledPath);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_options_are_tenant_scoped_and_include_the_voicemail_pin(): void
    {
        $domainUuid = $this->insertDomain('Acme Phones');
        $otherDomainUuid = $this->insertDomain('Other Account');
        $extensionUuid = $this->insertExtension($domainUuid, '1001');
        $otherExtensionUuid = $this->insertExtension($otherDomainUuid, '1001');
        $this->insertVoicemail($domainUuid, '1001', '4829', 'jordan@example.test');
        $this->insertVoicemail($otherDomainUuid, '1001', '9999', 'other@example.test');
        DB::table('v_destinations')->insert([
            'destination_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'destination_number' => '2025550101',
            'destination_actions' => json_encode([[
                'destination_app' => 'transfer',
                'destination_data' => '1001 XML acme-phones.example.test',
            ]]),
            'destination_enabled' => 'true',
        ]);

        $result = app(ExtensionWelcomeEmailService::class)->options(
            [$extensionUuid, $otherExtensionUuid],
            $domainUuid
        );

        $this->assertSame(2, $result['summary']['selected']);
        $this->assertSame(1, $result['summary']['eligible']);
        $this->assertSame('4829', $result['items'][0]['voicemail_pin']);
        $this->assertSame('jordan@example.test', $result['items'][0]['recipient']);
        $this->assertSame(['(202) 555-0101'], $result['items'][0]['direct_numbers']);
        $this->assertFalse($result['items'][1]['eligible']);
        $this->assertSame('Extension not found.', $result['items'][1]['reason']);
        $this->assertNull($result['items'][1]['voicemail_pin']);
    }

    public function test_single_recipient_override_and_portal_details_are_resolved(): void
    {
        $domainUuid = $this->insertDomain('Acme Phones');
        $extensionUuid = $this->insertExtension($domainUuid, '1001');
        $this->insertVoicemail($domainUuid, '1001', '4829', null);
        $this->insertUser($domainUuid, $extensionUuid, 'jordan@example.test');

        $service = app(ExtensionWelcomeEmailService::class);
        $result = $service->options(
            [$extensionUuid],
            $domainUuid,
            'Jordan@Example.Test'
        );

        $this->assertTrue($result['items'][0]['eligible']);
        $this->assertSame('jordan@example.test', $result['items'][0]['recipient']);

        $attributes = $service->attributesForSend(
            $extensionUuid,
            $domainUuid,
            'jordan@example.test'
        );

        $this->assertSame('4829', $attributes['voicemail_pin']);
        $this->assertSame('jordan@example.test', $attributes['portal_email']);
        $this->assertSame('Jordan Lee', $attributes['recipient_name']);
        $this->assertSame(route('login'), $attributes['portal_login_url']);
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('sip_password', $attributes);
    }

    public function test_disabled_voicemail_is_skipped(): void
    {
        $domainUuid = $this->insertDomain('Acme Phones');
        $extensionUuid = $this->insertExtension($domainUuid, '1001');
        $this->insertVoicemail(
            $domainUuid,
            '1001',
            '4829',
            'jordan@example.test',
            'false'
        );

        $result = app(ExtensionWelcomeEmailService::class)->options(
            [$extensionUuid],
            $domainUuid
        );

        $this->assertSame(0, $result['summary']['eligible']);
        $this->assertSame(1, $result['summary']['skipped']);
        $this->assertSame('Voicemail is disabled.', $result['items'][0]['reason']);
    }

    public function test_welcome_mailable_renders_html_and_text_with_the_pin(): void
    {
        $attributes = [
            'domain_uuid' => (string) Str::uuid(),
            'recipient_name' => 'Jordan Lee',
            'account_name' => 'Acme Phones',
            'extension' => '1001',
            'direct_numbers' => ['(202) 555-0101'],
            'voicemail_id' => '1001',
            'voicemail_pin' => '4829',
            'portal_email' => 'jordan@example.test',
            'portal_login_url' => 'https://example.test/login',
            'password_request_url' => 'https://example.test/forgot-password',
        ];

        $mail = new ExtensionWelcome($attributes);
        $html = $mail->render();

        $this->assertStringContainsString('Jordan Lee', $html);
        $this->assertStringContainsString('4829', $html);
        $this->assertStringContainsString('(202) 555-0101', $html);
        $this->assertStringContainsString('*97', $html);
        $this->assertStringNotContainsString('*98', $html);
        $this->assertStringNotContainsString('<strong>Account:</strong>', $html);
        $this->assertStringNotContainsString('<strong>Phone system:</strong>', $html);
        $this->assertStringNotContainsString('Account access', $html);
        $this->assertStringNotContainsString('jordan@example.test', $html);
        $this->assertStringNotContainsString('forgot-password', $html);
        $this->assertStringContainsString('Welcome aboard,<br>FS PBX', $html);
        $this->assertStringNotContainsString('Welcome aboard,<br>Acme Phones', $html);
        $this->assertStringNotContainsString('Unsubscribe from this list', $html);
        $this->assertStringNotContainsString('&copy;', $html);
        $this->assertStringNotContainsString('SIP password', $html);
    }

    public function test_email_footer_uses_the_smtp_from_setting_for_unsubscribe_requests(): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(),
            'default_setting_category' => 'email',
            'default_setting_subcategory' => 'smtp_from',
            'default_setting_name' => 'text',
            'default_setting_value' => 'unsubscribe@example.test',
            'default_setting_enabled' => 'true',
        ]);

        $mail = new ExtensionWelcome([
            'domain_uuid' => (string) Str::uuid(),
            'recipient_name' => 'Jordan Lee',
            'account_name' => 'Acme Phones',
            'extension' => '1001',
            'direct_numbers' => [],
            'voicemail_id' => '1001',
            'voicemail_pin' => '4829',
        ]);
        $html = $mail->render();

        $this->assertStringContainsString('Unsubscribe from this list', $html);
        $this->assertStringContainsString('mailto:unsubscribe@example.test', $html);
    }

    private function insertDomain(string $description): string
    {
        $uuid = (string) Str::uuid();

        DB::table('v_domains')->insert([
            'domain_uuid' => $uuid,
            'domain_name' => Str::slug($description).'.example.test',
            'domain_description' => $description,
            'domain_enabled' => 'true',
        ]);

        return $uuid;
    }

    private function insertExtension(string $domainUuid, string $extension): string
    {
        $uuid = (string) Str::uuid();

        DB::table('v_extensions')->insert([
            'extension_uuid' => $uuid,
            'domain_uuid' => $domainUuid,
            'extension' => $extension,
            'effective_caller_id_name' => 'Jordan Lee',
            'directory_first_name' => 'Jordan',
            'directory_last_name' => 'Lee',
        ]);

        return $uuid;
    }

    private function insertVoicemail(
        string $domainUuid,
        string $voicemailId,
        ?string $pin,
        ?string $email,
        string $enabled = 'true'
    ): void {
        DB::table('v_voicemails')->insert([
            'voicemail_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'voicemail_id' => $voicemailId,
            'voicemail_password' => $pin,
            'voicemail_mail_to' => $email,
            'voicemail_enabled' => $enabled,
        ]);
    }

    private function insertUser(string $domainUuid, string $extensionUuid, string $email): void
    {
        $userUuid = (string) Str::uuid();

        DB::table('v_users')->insert([
            'user_uuid' => $userUuid,
            'domain_uuid' => $domainUuid,
            'extension_uuid' => $extensionUuid,
            'username' => 'jordan_lee',
            'user_email' => $email,
            'user_enabled' => 'true',
        ]);
        DB::table('users_adv_fields')->insert([
            'id' => (string) Str::uuid(),
            'user_uuid' => $userUuid,
            'first_name' => 'Portal',
            'last_name' => 'User',
        ]);
        DB::table('v_user_settings')->insert([
            'user_setting_uuid' => (string) Str::uuid(),
            'user_uuid' => $userUuid,
            'domain_uuid' => $domainUuid,
            'user_setting_category' => 'domain',
            'user_setting_subcategory' => 'language',
            'user_setting_name' => 'code',
            'user_setting_value' => 'en-us',
            'user_setting_enabled' => 'true',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('v_domains', function (Blueprint $table) {
            $table->string('domain_uuid')->primary();
            $table->string('domain_name');
            $table->string('domain_description')->nullable();
            $table->string('domain_enabled')->nullable();
        });
        Schema::create('v_extensions', function (Blueprint $table) {
            $table->string('extension_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('extension');
            $table->string('effective_caller_id_name')->nullable();
            $table->string('directory_first_name')->nullable();
            $table->string('directory_last_name')->nullable();
        });
        Schema::create('extension_advanced_settings', function (Blueprint $table) {
            $table->string('setting_uuid')->primary();
            $table->string('extension_uuid');
            $table->boolean('suspended')->default(false);
        });
        Schema::create('v_voicemails', function (Blueprint $table) {
            $table->string('voicemail_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('voicemail_id');
            $table->string('voicemail_password')->nullable();
            $table->string('voicemail_mail_to')->nullable();
            $table->string('voicemail_enabled')->nullable();
        });
        Schema::create('v_destinations', function (Blueprint $table) {
            $table->string('destination_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('destination_number')->nullable();
            $table->text('destination_actions')->nullable();
            $table->string('destination_enabled')->nullable();
        });
        Schema::create('v_dialplans', function (Blueprint $table) {
            $table->string('dialplan_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('dialplan_name')->nullable();
            $table->string('dialplan_number')->nullable();
            $table->text('dialplan_xml')->nullable();
            $table->integer('dialplan_order')->nullable();
            $table->string('dialplan_enabled')->nullable();
            $table->string('dialplan_context')->nullable();
        });
        Schema::create('v_users', function (Blueprint $table) {
            $table->string('user_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('extension_uuid')->nullable();
            $table->string('username')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_enabled')->nullable();
            $table->string('password')->nullable();
        });
        Schema::create('users_adv_fields', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_uuid');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
        Schema::create('v_user_settings', function (Blueprint $table) {
            $table->string('user_setting_uuid')->primary();
            $table->string('user_uuid');
            $table->string('domain_uuid');
            $table->string('user_setting_category')->nullable();
            $table->string('user_setting_subcategory')->nullable();
            $table->string('user_setting_name')->nullable();
            $table->string('user_setting_value')->nullable();
            $table->string('user_setting_enabled')->nullable();
        });
        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_name')->nullable();
            $table->text('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });
        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_setting_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_name')->nullable();
            $table->text('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });
    }
}
