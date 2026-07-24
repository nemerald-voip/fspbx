<?php

namespace Tests\Unit;

use App\Services\EmailTemplateDefaultsInitializer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailTemplateDefaultsInitializerTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.email_template_initializer_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'email_template_initializer_test');
        DB::purge('email_template_initializer_test');

        Schema::create('email_templates', function (Blueprint $table) {
            $table->uuid('email_template_uuid')->primary();
            $table->string('template_type');
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('email_template_initializer_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_it_seeds_when_no_default_templates_exist(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('email:templates:seed', ['--no-interaction' => true])
            ->andReturn(0);

        $this->assertTrue(app(EmailTemplateDefaultsInitializer::class)->ensureSeeded());
    }

    public function test_it_does_not_seed_when_a_default_template_exists(): void
    {
        DB::table('email_templates')->insert([
            'email_template_uuid' => '00000000-0000-0000-0000-000000000001',
            'template_type' => 'default',
        ]);

        Artisan::shouldReceive('call')->never();

        $this->assertFalse(app(EmailTemplateDefaultsInitializer::class)->ensureSeeded());
    }
}
