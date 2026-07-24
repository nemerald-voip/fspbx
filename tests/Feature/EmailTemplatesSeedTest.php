<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailTemplatesSeedTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.email_template_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'email_template_test');
        DB::purge('email_template_test');

        Schema::create('email_templates', function (Blueprint $table) {
            $table->uuid('email_template_uuid')->primary();
            $table->uuid('base_template_uuid')->nullable();
            $table->string('template_type');
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('email_template_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_dedupe_keeps_oldest_default_and_repoints_custom_templates(): void
    {
        $this->insertDuplicateTemplates();

        $this->artisan('email:templates:seed', ['--dedupe-only' => true])
            ->expectsOutput('Dedupe complete. Removed duplicates: 1, Re-pointed custom templates: 1.')
            ->assertSuccessful();

        $this->assertDatabaseHas('email_templates', [
            'email_template_uuid' => '00000000-0000-0000-0000-000000000001',
            'template_type' => 'default',
        ]);
        $this->assertDatabaseMissing('email_templates', [
            'email_template_uuid' => '00000000-0000-0000-0000-000000000002',
        ]);
        $this->assertDatabaseHas('email_templates', [
            'email_template_uuid' => '00000000-0000-0000-0000-000000000003',
            'base_template_uuid' => '00000000-0000-0000-0000-000000000001',
            'template_type' => 'custom',
        ]);
    }

    public function test_dedupe_dry_run_does_not_change_templates(): void
    {
        $this->insertDuplicateTemplates();

        $this->artisan('email:templates:seed', [
            '--dedupe-only' => true,
            '--dry-run' => true,
        ])
            ->expectsOutput('Dedupe complete. Removed duplicates: 1, Re-pointed custom templates: 0.')
            ->assertSuccessful();

        $this->assertDatabaseHas('email_templates', [
            'email_template_uuid' => '00000000-0000-0000-0000-000000000002',
        ]);
        $this->assertDatabaseHas('email_templates', [
            'email_template_uuid' => '00000000-0000-0000-0000-000000000003',
            'base_template_uuid' => '00000000-0000-0000-0000-000000000002',
        ]);
    }

    private function insertDuplicateTemplates(): void
    {
        DB::table('email_templates')->insert([
            [
                'email_template_uuid' => '00000000-0000-0000-0000-000000000001',
                'base_template_uuid' => null,
                'template_type' => 'default',
                'checksum' => str_repeat('a', 64),
                'created_at' => '2026-07-22 00:00:00',
                'updated_at' => '2026-07-22 00:00:00',
            ],
            [
                'email_template_uuid' => '00000000-0000-0000-0000-000000000002',
                'base_template_uuid' => null,
                'template_type' => 'default',
                'checksum' => str_repeat('a', 64),
                'created_at' => '2026-07-23 00:00:00',
                'updated_at' => '2026-07-23 00:00:00',
            ],
            [
                'email_template_uuid' => '00000000-0000-0000-0000-000000000003',
                'base_template_uuid' => '00000000-0000-0000-0000-000000000002',
                'template_type' => 'custom',
                'checksum' => str_repeat('b', 64),
                'created_at' => '2026-07-24 00:00:00',
                'updated_at' => '2026-07-24 00:00:00',
            ],
        ]);
    }
}
