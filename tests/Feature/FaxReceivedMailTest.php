<?php

namespace Tests\Feature;

use App\Jobs\ProcessFaxWebhookEventJob;
use App\Mail\FaxReceived;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class FaxReceivedMailTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-fax-received-mail-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'mail.from.address' => 'fallback@example.test',
            'mail.from.name' => 'Fallback Sender',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_it_uses_default_fax_sender_settings_when_available(): void
    {
        $this->insertDefaultSetting('smtp_from', 'fax@example.test');
        $this->insertDefaultSetting('smtp_from_name', 'Fax Desk');

        $from = (new FaxReceived($this->attributes()))->envelope()->from;

        $this->assertSame('fax@example.test', $from->address);
        $this->assertSame('Fax Desk', $from->name);
    }

    public function test_it_uses_domain_fax_sender_settings_before_default_settings(): void
    {
        $domainUuid = (string) Str::uuid();

        $this->insertDefaultSetting('smtp_from', 'default-fax@example.test');
        $this->insertDefaultSetting('smtp_from_name', 'Default Fax');
        $this->insertDomainSetting($domainUuid, 'smtp_from', 'domain-fax@example.test');
        $this->insertDomainSetting($domainUuid, 'smtp_from_name', 'Domain Fax');

        $from = (new FaxReceived($this->attributes(['domain_uuid' => $domainUuid])))->envelope()->from;

        $this->assertSame('domain-fax@example.test', $from->address);
        $this->assertSame('Domain Fax', $from->name);
    }

    public function test_it_falls_back_to_mail_config_when_fax_sender_settings_are_missing_or_blank(): void
    {
        $domainUuid = (string) Str::uuid();

        $this->insertDefaultSetting('smtp_from', '');
        $this->insertDefaultSetting('smtp_from_name', '   ');
        $this->insertDomainSetting($domainUuid, 'smtp_from', '');
        $this->insertDomainSetting($domainUuid, 'smtp_from_name', '   ');

        $from = (new FaxReceived($this->attributes(['domain_uuid' => $domainUuid])))->envelope()->from;

        $this->assertSame('fallback@example.test', $from->address);
        $this->assertSame('Fallback Sender', $from->name);
    }

    public function test_received_fax_date_is_formatted_in_the_domain_timezone(): void
    {
        $domainUuid = (string) Str::uuid();

        $this->insertDomainSetting($domainUuid, 'time_zone', 'America/New_York', category: 'domain');

        $job = new ProcessFaxWebhookEventJob('fax.received', '1779963600', []);
        $method = new ReflectionMethod(ProcessFaxWebhookEventJob::class, 'formattedFaxDate');
        $method->setAccessible(true);

        $this->assertSame(
            '6:20:00 AM May 28, 2026',
            $method->invoke($job, 1779963600, $domainUuid)
        );
    }

    public function test_received_fax_email_displays_the_received_date_when_available(): void
    {
        $mail = new FaxReceived($this->attributes([
            'fax_date' => '9:00:00 PM May 27, 2026',
        ]));

        $this->assertSame('9:00:00 PM May 27, 2026', $mail->attributes['fax_date']);
    }

    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'domain_uuid' => (string) Str::uuid(),
            'caller_id_number' => '+15551234567',
            'fax_pages' => '1',
        ], $overrides);
    }

    private function insertDefaultSetting(string $subcategory, ?string $value, string $enabled = 'true'): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(),
            'default_setting_category' => 'fax',
            'default_setting_subcategory' => $subcategory,
            'default_setting_name' => 'text',
            'default_setting_value' => $value,
            'default_setting_enabled' => $enabled,
        ]);
    }

    private function insertDomainSetting(
        string $domainUuid,
        string $subcategory,
        ?string $value,
        string $enabled = 'true',
        string $category = 'fax'
    ): void
    {
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'domain_setting_category' => $category,
            'domain_setting_subcategory' => $subcategory,
            'domain_setting_name' => 'text',
            'domain_setting_value' => $value,
            'domain_setting_enabled' => $enabled,
        ]);
    }

    private function createSchema(): void
    {
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
            $table->string('domain_uuid')->nullable();
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_name')->nullable();
            $table->text('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });
    }
}
