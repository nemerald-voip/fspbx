<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use App\Services\EmailTemplateSourceService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
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
            $table->uuid('domain_uuid')->nullable();
            $table->string('template_key')->default('fax.received');
            $table->string('template_language')->default('en-us');
            $table->string('template_category')->nullable();
            $table->string('template_subcategory')->nullable();
            $table->string('template_layout')->default('standard');
            $table->string('version')->nullable();
            $table->string('base_version')->nullable();
            $table->text('template_subject')->nullable();
            $table->text('template_html')->nullable();
            $table->text('template_text')->nullable();
            $table->boolean('template_enabled')->default(true);
            $table->text('template_description')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
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

    public function test_all_languages_are_imported_idempotently_with_summary_output(): void
    {
        $output = new BufferedOutput();
        $this->assertSame(0, Artisan::call('email:templates:seed', [], $output));
        $this->assertSame(
            'Email template seed complete. Inserted: 115, Updated: 0, Skipped: 0, Failed: 0.',
            trim($output->fetch())
        );
        foreach (['en-us', 'ru', 'fr', 'es-419', 'pt-br'] as $language) {
            $this->assertSame(23, EmailTemplate::where('template_language', $language)->count());
        }
        $before = DB::table('email_templates')->orderBy('email_template_uuid')->get()->toJson();

        $this->assertSame(0, Artisan::call('email:templates:seed', [], $output));
        $this->assertSame(
            'Email template seed complete. Inserted: 0, Updated: 0, Skipped: 115, Failed: 0.',
            trim($output->fetch())
        );
        $this->assertSame($before, DB::table('email_templates')->orderBy('email_template_uuid')->get()->toJson());
    }

    public function test_verbose_dry_run_lists_languages_without_writing(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        $this->assertSame(0, Artisan::call('email:templates:seed', ['--dry-run' => true], $output));
        $details = $output->fetch();
        foreach (['en-us', 'ru', 'fr', 'es-419', 'pt-br'] as $language) {
            $this->assertStringContainsString("[dry] authentication.reset-password [{$language}]", $details);
        }
        $this->assertSame(0, EmailTemplate::count());
    }

    public function test_dedupe_does_not_merge_identical_content_across_languages_or_purposes(): void
    {
        $this->insertDuplicateTemplates();
        foreach ([['fr', 'fax.received'], ['en-us', 'fax.sent']] as [$language, $key]) {
            DB::table('email_templates')->insert([
                'email_template_uuid' => (string) Str::uuid(),
                'template_type' => 'default',
                'template_key' => $key,
                'template_language' => $language,
                'checksum' => str_repeat('a', 64),
            ]);
        }
        $this->assertSame(0, Artisan::call('email:templates:seed', ['--dedupe-only' => true]));
        $this->assertSame(3, EmailTemplate::where('template_type', 'default')->count());
        $this->assertDatabaseHas('email_templates', ['template_key' => 'fax.received', 'template_language' => 'fr']);
        $this->assertDatabaseHas('email_templates', ['template_key' => 'fax.sent', 'template_language' => 'en-us']);
    }

    public function test_versioned_update_preserves_other_languages_and_custom_overrides(): void
    {
        Artisan::call('email:templates:seed');
        $french = EmailTemplate::where('template_key', 'authentication.reset-password')->where('template_language', 'fr')->firstOrFail();
        $original = $french->getAttributes();
        $french->forceFill(['version' => '0.9.0', 'checksum' => 'old', 'template_html' => '<p>Older version</p>'])->save();
        $custom = $french->replicate();
        $custom->forceFill([
            'email_template_uuid' => (string) Str::uuid(),
            'template_type' => 'custom',
            'base_template_uuid' => $french->getKey(),
            'template_html' => '<p>My custom wording</p>',
        ])->save();
        $unaffected = DB::table('email_templates')->where('email_template_uuid', '!=', $french->getKey())
            ->orderBy('email_template_uuid')->get()->toJson();

        $this->assertSame(0, Artisan::call('email:templates:seed'));
        $this->assertSame($original['template_html'], $french->fresh()->template_html);
        $this->assertSame($original['version'], $french->fresh()->version);
        $this->assertSame($unaffected, DB::table('email_templates')->where('email_template_uuid', '!=', $french->getKey())
            ->orderBy('email_template_uuid')->get()->toJson());
    }

    public function test_content_changes_without_a_version_bump_still_report_an_error_at_normal_verbosity(): void
    {
        Artisan::call('email:templates:seed');
        $french = EmailTemplate::where('template_key', 'authentication.reset-password')->where('template_language', 'fr')->firstOrFail();
        $french->update(['checksum' => 'changed']);
        $output = new BufferedOutput();

        $this->assertSame(1, Artisan::call('email:templates:seed', [], $output));
        $details = $output->fetch();
        $this->assertStringContainsString('[authentication.reset-password | fr] Content changed without a newer version.', $details);
        $this->assertStringContainsString('Failed: 1.', $details);
        $this->assertStringNotContainsString('[skip]', $details);
        $this->assertSame('changed', $french->fresh()->checksum);
    }

    public function test_database_errors_report_the_cause_without_sql_or_template_bodies(): void
    {
        $definition = app(EmailTemplateSourceService::class)->find('app', 'credentials', 'ru');
        $this->mock(EmailTemplateSourceService::class, function ($mock) use ($definition) {
            $mock->shouldReceive('definitions')->once()->andReturn([$definition]);
        });
        DB::connection()->beforeExecuting(function ($query, $bindings, $connection) {
            if (str_starts_with($query, 'insert into "email_templates"')) {
                throw new QueryException(
                    $connection->getName(),
                    $query,
                    $bindings,
                    new \PDOException("SQLSTATE[22021]: invalid byte sequence for encoding UTF8\nCONTEXT: parameter 12")
                );
            }
        });
        $output = new BufferedOutput();
        $this->assertSame(1, Artisan::call('email:templates:seed', [], $output));
        $lines = preg_split('/\R/u', trim($output->fetch()));
        $this->assertSame([
            '[app.credentials | ru] SQLSTATE[22021]: invalid byte sequence for encoding UTF8',
            'Email template seed complete. Inserted: 0, Updated: 0, Skipped: 0, Failed: 1.',
        ], $lines);
    }

    public function test_selection_prefers_requested_language_and_preserves_account_global_and_english_fallbacks(): void
    {
        Artisan::call('email:templates:seed');
        app()->setLocale('ru');
        $service = app(EmailTemplateService::class);
        $account = (string) Str::uuid();
        $french = $service->resolve('authentication', 'reset-password', $account, 'fr');
        $this->assertSame('fr', $french->template_language);
        $this->assertSame('default', $french->template_type);

        $global = $french->replicate();
        $global->forceFill([
            'email_template_uuid' => (string) Str::uuid(),
            'template_type' => 'custom',
        ])->save();
        $custom = $global->replicate();
        $custom->forceFill([
            'email_template_uuid' => (string) Str::uuid(),
            'domain_uuid' => $account,
        ])->save();
        $this->assertSame($custom->getKey(), $service->resolve('authentication', 'reset-password', $account, 'fr')->getKey());
        $this->assertSame($global->getKey(), $service->resolve('authentication', 'reset-password', (string) Str::uuid(), 'fr')->getKey());
        $custom->update(['template_enabled' => false]);
        $this->assertSame($global->getKey(), $service->resolve('authentication', 'reset-password', $account, 'fr')->getKey());
        $global->update(['template_enabled' => false]);
        $this->assertSame($french->getKey(), $service->resolve('authentication', 'reset-password', $account, 'fr')->getKey());
        $this->assertSame('en-us', $service->resolve('authentication', 'reset-password', $account, 'de')->template_language);
        $this->assertSame('en-us', app(EmailTemplateSourceService::class)->find('authentication', 'reset-password', 'es-mx')['template_language']);
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
