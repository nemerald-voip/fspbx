<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update213;
use App\Services\DialplanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{DB, File, Http, Schema};
use Mockery;
use Tests\TestCase;

class Update213Test extends TestCase
{
    private const TEMPLATE_DIRECTORY = 'app/dialplans/resources/switch/conf/dialplan/';
    private const TEMPLATE_URL = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/'.self::TEMPLATE_DIRECTORY;
    private const CATALOG_PATH = 'app/dialplans/app_config.php';
    private const CATALOG_URL = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/'.self::CATALOG_PATH;
    private const CONTENTS_URL = 'https://api.github.com/repos/nemerald-voip/fusionpbx/contents/';
    private const TEMPLATES = [
        '230_group-intercept.xml' => '15332e83-12f5-44d3-8472-633736eb4b9b',
        '290_intercept-ext.xml' => '2b7b2f82-edfe-4339-8cc5-7d0cf36e1e68',
    ];

    private string $temporaryPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryPublicPath = sys_get_temp_dir().'/fspbx-update213-'.bin2hex(random_bytes(8));
        $this->app->usePublicPath($this->temporaryPublicPath);
        Http::preventStrayRequests();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('v_dialplans', function (Blueprint $table) {
            foreach (['dialplan_uuid', 'app_uuid', 'dialplan_xml', 'update_date'] as $column) { $table->text($column)->nullable(); }
        });
        Schema::create('v_dialplan_details', function (Blueprint $table) {
            foreach (['dialplan_uuid', 'dialplan_detail_tag', 'dialplan_detail_type', 'dialplan_detail_data',
                'dialplan_detail_group', 'update_date'] as $column) { $table->text($column)->nullable(); }
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryPublicPath);
        DB::purge('sqlite');
        parent::tearDown();
    }

    private function xml(string $action = '<action application="intercept" data="$1"/>'): string
    {
        return '<extension><condition field="${sip_h_X-intercept_uuid}" expression="^(.+)$" break="on-true">'
            .$action.'</condition></extension>';
    }

    private function insert(string $id, string $app, string $xml): void
    {
        DB::table('v_dialplans')->insert(['dialplan_uuid' => $id, 'app_uuid' => $app, 'dialplan_xml' => $xml]);
        DB::table('v_dialplan_details')->insert([
            ['dialplan_uuid' => $id, 'dialplan_detail_tag' => 'condition', 'dialplan_detail_type' => '${sip_h_X-intercept_uuid}',
                'dialplan_detail_data' => '^(.+)$', 'dialplan_detail_group' => '2'],
            ['dialplan_uuid' => $id, 'dialplan_detail_tag' => 'action', 'dialplan_detail_type' => 'intercept',
                'dialplan_detail_data' => '$1', 'dialplan_detail_group' => '2'],
        ]);
    }

    private function template(string $fileName): string
    {
        return str_replace('<extension>', '<extension app_uuid="'.self::TEMPLATES[$fileName].'">',
            $this->xml('<action application="lua" data="intercept.lua"/>'));
    }

    private function fakeDownloads(array $overrides = [], array $masterResponses = []): void
    {
        $responses = [];
        foreach (self::TEMPLATES as $fileName => $appUuid) {
            $responses[self::TEMPLATE_URL.$fileName] = $overrides[$fileName] ?? Http::response($this->template($fileName));
        }
        $responses[self::CATALOG_URL] = $overrides['app_config.php'] ?? Http::response($this->catalog());
        foreach ([...array_keys(self::TEMPLATES), 'app_config.php'] as $fileName) {
            $path = $fileName === 'app_config.php' ? self::CATALOG_PATH : self::TEMPLATE_DIRECTORY.$fileName;
            $responses[self::CONTENTS_URL.$path.'?ref=master'] = $masterResponses[$fileName]
                ?? Http::response(['message' => 'Unavailable'], 503);
        }
        Http::fake($responses);
    }

    private function masterResponse(string $fileName, string $contents): array
    {
        return ['type' => 'file', 'encoding' => 'base64', 'content' => base64_encode($contents),
            'path' => $fileName === 'app_config.php' ? self::CATALOG_PATH : self::TEMPLATE_DIRECTORY.$fileName];
    }

    private function catalog(bool $obsolete = false): string
    {
        $contents = <<<'PHP'
<?php
$apps[$x]['uuid'] = '742714e5-8cdf-32fd-462c-cbe7e3d655db';
$apps[$x]['permissions'][0]['name'] = 'dialplan_view';

PHP;
        if ($obsolete) {
            $contents .= <<<'PHP'
$apps[$x]['default_settings'][0]['default_setting_uuid'] = 'd3c5a59d-e6c0-4ded-ac79-9a7fcd9e1269';
$apps[$x]['default_settings'][0]['default_setting_category'] = 'dialplan';
$apps[$x]['default_settings'][0]['default_setting_subcategory'] = 'destination';
PHP;
        }
        return $contents;
    }

    public function test_apply_replaces_existing_public_templates_and_catalog_and_is_safe_to_repeat(): void
    {
        $this->fakeDownloads();
        File::ensureDirectoryExists(public_path(self::TEMPLATE_DIRECTORY));
        File::put(public_path(self::CATALOG_PATH), $this->catalog(true));
        foreach (self::TEMPLATES as $fileName => $appUuid) {
            File::put(public_path(self::TEMPLATE_DIRECTORY.$fileName), 'old template');
            $this->insert($fileName, $appUuid, $this->xml());
        }
        $untouched = ['290_intercept-ext-polycom.xml', '330_vmain_user.xml', '999_custom.xml'];
        foreach ($untouched as $fileName) {
            File::put(public_path(self::TEMPLATE_DIRECTORY.$fileName), 'unchanged '.$fileName);
        }
        $custom = $this->xml('<action application="intercept" data="custom-uuid"/>');
        $this->insert('custom', self::TEMPLATES['230_group-intercept.xml'], $custom);
        $cache = Mockery::mock(DialplanService::class);
        $cache->shouldReceive('clearDialplanCache')->twice()->with('global');
        $this->app->instance(DialplanService::class, $cache);
        $update = new class extends Update213 {
            public int $reloads = 0;
            public int $queueCleanups = 0;
            public function removeLegacyQueueServices(): bool { $this->queueCleanups++; return false; }
            protected function reloadXml(): void { $this->reloads++; }
        };

        ob_start();
        try {
            $this->assertTrue($update->apply());
            $this->assertTrue($update->apply());
        } finally {
            $output = ob_get_clean();
        }

        foreach (self::TEMPLATES as $fileName => $appUuid) {
            $this->assertSame($this->template($fileName), File::get(public_path(self::TEMPLATE_DIRECTORY.$fileName)));
            $this->assertSame($this->xml('<action application="lua" data="intercept.lua"/>'),
                DB::table('v_dialplans')->where('dialplan_uuid', $fileName)->value('dialplan_xml'));
        }
        foreach ($untouched as $fileName) {
            $this->assertSame('unchanged '.$fileName, File::get(public_path(self::TEMPLATE_DIRECTORY.$fileName)));
        }
        $this->assertSame($this->catalog(), File::get(public_path(self::CATALOG_PATH)));
        $this->assertSame($custom, DB::table('v_dialplans')->where('dialplan_uuid', 'custom')->value('dialplan_xml'));
        $this->assertStringContainsString('Updated 2 stock pickup dialplan(s).', $output);
        $this->assertStringContainsString('Updated 0 stock pickup dialplan(s).', $output);
        $this->assertSame(2, $update->reloads);
        $this->assertSame(2, $update->queueCleanups);
        $this->assertSame(['fs-cdr-service'], $update->getSupervisorProgramsToRestart());
        Http::assertSentCount(6);
    }

    public function test_stale_raw_files_are_refetched_from_master_and_validated_before_replacement(): void
    {
        $raw = [];
        $master = [];
        foreach (self::TEMPLATES as $name => $appUuid) {
            $raw[$name] = Http::response(str_replace('application="lua" data="intercept.lua"',
                'application="intercept" data="$1"', $this->template($name)));
            $master[$name] = Http::response($this->masterResponse($name, $this->template($name)));
        }
        $raw['app_config.php'] = Http::response($this->catalog(true));
        $master['app_config.php'] = Http::response($this->masterResponse('app_config.php', $this->catalog()));
        $this->fakeDownloads($raw, $master);
        $cache = Mockery::mock(DialplanService::class);
        $cache->shouldReceive('clearDialplanCache')->once()->with('global');
        $this->app->instance(DialplanService::class, $cache);
        $update = new class extends Update213 {
            public function removeLegacyQueueServices(): bool { return true; }
            protected function reloadXml(): void {}
        };
        ob_start();
        try {
            $this->assertTrue($update->apply());
        } finally {
            $output = ob_get_clean();
        }
        foreach (self::TEMPLATES as $name => $appUuid) {
            $this->assertSame($this->template($name), File::get(public_path(self::TEMPLATE_DIRECTORY.$name)));
        }
        $this->assertSame($this->catalog(), File::get(public_path(self::CATALOG_PATH)));
        $this->assertStringContainsString('ref=master', $output);
        Http::assertSentCount(6);
    }

    /** @dataProvider invalidMasterResponses */
    public function test_unusable_master_fallback_preserves_every_local_file(string $failure): void
    {
        $name = '290_intercept-ext.xml';
        $file = $this->masterResponse($name, $this->template($name));
        $response = match ($failure) {
            'rate-limited' => Http::response(['message' => 'API rate limit exceeded'], 403),
            'wrong-path' => Http::response(array_replace($file, ['path' => self::CATALOG_PATH])),
            'invalid-base64' => Http::response(array_replace($file, ['content' => '!not base64!'])),
            'missing-content' => Http::response(['type' => 'file']),
            'outdated-xml' => Http::response($this->masterResponse($name,
                str_replace('application="lua" data="intercept.lua"', 'application="intercept" data="$1"', $this->template($name)))),
        };
        $this->fakeDownloads([$name => Http::response('temporarily unavailable', 503)], [$name => $response]);
        File::ensureDirectoryExists(public_path(self::TEMPLATE_DIRECTORY));
        File::put(public_path(self::CATALOG_PATH), $this->catalog(true));
        foreach (self::TEMPLATES as $fileName => $appUuid) {
            File::put(public_path(self::TEMPLATE_DIRECTORY.$fileName), 'previous '.$fileName);
        }
        $cache = Mockery::mock(DialplanService::class);
        $cache->shouldNotReceive('clearDialplanCache');
        $this->app->instance(DialplanService::class, $cache);
        $update = new class extends Update213 {
            public function removeLegacyQueueServices(): bool { return true; }
            protected function reloadXml(): void { throw new \LogicException('Unexpected reload'); }
        };
        ob_start();
        try {
            $this->assertFalse($update->apply());
        } finally {
            ob_end_clean();
        }
        foreach (self::TEMPLATES as $fileName => $appUuid) {
            $this->assertSame('previous '.$fileName, File::get(public_path(self::TEMPLATE_DIRECTORY.$fileName)));
        }
        $this->assertSame($this->catalog(true), File::get(public_path(self::CATALOG_PATH)));
    }

    public static function invalidMasterResponses(): array
    {
        return array_map(fn ($failure) => [$failure], ['rate-limited', 'wrong-path', 'invalid-base64', 'missing-content', 'outdated-xml']);
    }

    /** @dataProvider downloadFailures */
    public function test_download_failure_preserves_local_files_and_saved_dialplans(string $failure, string $fileName): void
    {
        $isCatalog = $fileName === 'app_config.php';
        $response = match ($failure) {
            'http' => Http::response('unavailable', 503),
            'empty' => Http::response(''),
            'html' => Http::response('<html>Unavailable</html>'),
            'malformed' => Http::response($isCatalog ? $this->catalog().'$broken =' : '<extension'),
            'outdated' => Http::response($isCatalog ? $this->catalog(true) : str_replace('application="lua" data="intercept.lua"',
                'application="intercept" data="$1"', $this->template($fileName))),
            'wrong-app' => Http::response($isCatalog
                ? str_replace('742714e5-8cdf-32fd-462c-cbe7e3d655db', '15332e83-12f5-44d3-8472-633736eb4b9b', $this->catalog())
                : $this->template('230_group-intercept.xml')),
            'connection' => fn () => throw new ConnectionException('Connection timed out'),
        };
        $this->fakeDownloads([$fileName => $response]);
        File::ensureDirectoryExists(public_path(self::TEMPLATE_DIRECTORY));
        File::put(public_path(self::CATALOG_PATH), $this->catalog(true));
        foreach (self::TEMPLATES as $name => $appUuid) {
            File::put(public_path(self::TEMPLATE_DIRECTORY.$name), 'previous '.$name);
            $this->insert($name, $appUuid, $this->xml());
        }
        $cache = Mockery::mock(DialplanService::class);
        $cache->shouldNotReceive('clearDialplanCache');
        $this->app->instance(DialplanService::class, $cache);
        $update = new class extends Update213 {
            public function removeLegacyQueueServices(): bool { return true; }
            protected function reloadXml(): void { throw new \LogicException('Unexpected reload'); }
        };
        ob_start();
        try {
            $this->assertFalse($update->apply());
        } finally {
            $output = ob_get_clean();
        }
        $this->assertStringContainsString('Error applying update 2.1.3:', $output);
        $this->assertSame($this->catalog(true), File::get(public_path(self::CATALOG_PATH)));
        foreach (self::TEMPLATES as $name => $appUuid) {
            $this->assertSame('previous '.$name, File::get(public_path(self::TEMPLATE_DIRECTORY.$name)));
            $this->assertSame($this->xml(), DB::table('v_dialplans')->where('dialplan_uuid', $name)->value('dialplan_xml'));
        }
    }

    public static function downloadFailures(): array
    {
        $cases = [];
        foreach (['290_intercept-ext.xml', 'app_config.php'] as $fileName) {
            foreach (['http', 'empty', 'html', 'malformed', 'outdated', 'wrong-app', 'connection'] as $failure) {
                $cases[$fileName.' '.$failure] = [$failure, $fileName];
            }
        }
        return $cases;
    }

    public function test_stock_xml_and_rules_are_repaired_once_without_touching_custom_or_voicemail_routes(): void
    {
        $app = '15332e83-12f5-44d3-8472-633736eb4b9b';
        $this->insert('group', $app, $this->xml());
        $this->insert('direct', '2b7b2f82-edfe-4339-8cc5-7d0cf36e1e68', $this->xml());
        $custom = $this->xml('<action application="intercept" data="custom-uuid"/>');
        $this->insert('custom', $app, $custom);
        $voicemail = '<extension><condition field="destination_number" expression="^\*97$"><action application="lua" data="app.lua voicemail"/></condition></extension>';
        $this->insert('voicemail', '5d47ab13-f25d-4f62-a68e-2a7d945d05b7', $voicemail);
        $update = new Update213();
        $this->assertSame(2, $update->patchDialplans());
        $this->assertSame(0, $update->patchDialplans());
        foreach (['group', 'direct'] as $id) {
            $this->assertSame($this->xml('<action application="lua" data="intercept.lua"/>'), DB::table('v_dialplans')->where('dialplan_uuid', $id)->value('dialplan_xml'));
            $action = DB::table('v_dialplan_details')->where('dialplan_uuid', $id)->where('dialplan_detail_tag', 'action')->first();
            $this->assertSame('lua', $action->dialplan_detail_type);
            $this->assertSame('intercept.lua', $action->dialplan_detail_data);
        }
        $this->assertSame($custom, DB::table('v_dialplans')->where('dialplan_uuid', 'custom')->value('dialplan_xml'));
        $this->assertSame($voicemail, DB::table('v_dialplans')->where('dialplan_uuid', 'voicemail')->value('dialplan_xml'));
        $this->assertSame(2, DB::table('v_dialplan_details')->where('dialplan_detail_type', 'intercept')->count());
    }

    public function test_reload_failure_warns_and_keeps_the_importer_restart_registered(): void
    {
        $this->fakeDownloads();
        $cache = Mockery::mock(DialplanService::class);
        $cache->shouldReceive('clearDialplanCache')->twice()->with('global');
        $this->app->instance(DialplanService::class, $cache);
        $update = new class extends Update213 {
            public function removeLegacyQueueServices(): bool { return true; }
            protected function reloadXml(): void { throw new \RuntimeException('offline'); }
        };
        ob_start();
        try {
            $this->assertTrue($update->apply());
            $this->assertTrue($update->apply());
        } finally {
            $output = ob_get_clean();
        }
        $this->assertStringContainsString('Continuing the application update', $output);
        $this->assertStringContainsString('fs_cli -x reloadxml', $output);
        $this->assertSame(['fs-cdr-service'], $update->getSupervisorProgramsToRestart());
    }

    public function test_extra_custom_actions_and_other_header_conditions_are_untouched(): void
    {
        foreach ([$this->xml('<action application="log" data="custom"/><action application="intercept" data="$1"/>'),
            str_replace('X-intercept_uuid', 'X-custom', $this->xml())] as $xml) {
            $this->assertSame($xml, Update213::patchXml($xml));
        }
    }
}
