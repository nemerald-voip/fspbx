<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update222;
use App\Http\Controllers\FaxInboxController;
use App\Http\Controllers\FaxSentController;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class FaxDownloadPermissionsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/fax-download-'.Str::uuid();
        config([
            'database.default' => 'fax_download_test',
            'database.connections.fax_download_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'cache.default' => 'array',
            'logging.default' => 'null',
            'filesystems.disks.fax' => ['driver' => 'local', 'root' => $this->directory],
        ]);
        DB::purge('fax_download_test');
        session(['domain_uuid' => 'account-a']);
        Cache::put('account-a_timeZone', 'UTC');

        $this->table('v_fax', ['fax_uuid', 'domain_uuid', 'fax_extension', 'fax_name', 'fax_caller_id_number']);
        $this->table('v_domains', ['domain_uuid', 'domain_name']);
        $this->table('v_fax_files', ['fax_file_uuid', 'domain_uuid', 'fax_uuid', 'fax_file_path']);
        DB::table('v_fax')->insert([
            'fax_uuid' => 'fax-a', 'domain_uuid' => 'account-a', 'fax_extension' => '50000',
        ]);
        DB::table('v_domains')->insert(['domain_uuid' => 'account-a', 'domain_name' => 'account.test']);
        DB::table('v_fax_files')->insert([
            'fax_file_uuid' => 'file-a', 'domain_uuid' => 'account-a',
            'fax_uuid' => 'fax-a', 'fax_file_path' => 'document.tif',
        ]);
        foreach (['inbox', 'sent'] as $folder) {
            Storage::disk('fax')->put("account.test/50000/{$folder}/document.pdf", 'fax PDF fixture');
        }
    }

    protected function tearDown(): void
    {
        DB::disconnect('fax_download_test');
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public static function pages(): array
    {
        return [
            'inbox' => [FaxInboxController::class, 'inbox'],
            'sent' => [FaxSentController::class, 'sent'],
        ];
    }

    public static function permissionCases(): iterable
    {
        foreach (self::pages() as $page => [$controller, $folder]) {
            foreach ([false, true] as $download) {
                foreach ([false, true] as $delete) {
                    yield $page.' download='.(int) $download.' delete='.(int) $delete => [
                        $controller, $folder, $download, $delete,
                    ];
                }
            }
        }
    }

    /** @dataProvider permissionCases */
    public function test_download_permission_is_independent_of_delete(
        string $controllerClass, string $folder, bool $download, bool $delete
    ): void {
        $permissions = ["fax_{$folder}_view"];
        if ($download) {
            $permissions[] = "fax_{$folder}_download";
        }
        if ($delete) {
            $permissions[] = "fax_{$folder}_delete";
        }
        $this->permissions($permissions);
        $controller = new $controllerClass;
        $request = Request::create('/fax/fax-a/'.$folder);
        $request->headers->set('X-Inertia', 'true');
        $this->app->instance('request', $request);
        $page = $controller->index()->toResponse($request)->getData(true);
        $this->assertSame(['download' => $download, 'delete' => $delete], $page['props']['permissions']);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $controller->download('file-a');
        $this->assertSame($download ? 200 : 403, $response->getStatusCode());

        if ($download) {
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            ob_start();
            $response->sendContent();
            $this->assertSame('fax PDF fixture', ob_get_clean());
        } else {
            $this->assertSame([], DB::getQueryLog());
            $this->assertSame(['Access denied.'], $response->getData(true)['errors']['auth']);
        }

        if (! $delete) {
            $this->assertSame(403, $controller->bulkDelete()->getStatusCode());
        }
    }

    /** @dataProvider pages */
    public function test_download_permission_does_not_allow_another_accounts_file(string $controllerClass, string $folder): void
    {
        $this->permissions(["fax_{$folder}_download"]);
        session(['domain_uuid' => 'account-b']);

        $this->assertSame(403, (new $controllerClass)->download('file-a')->getStatusCode());
    }

    /** @dataProvider pages */
    public function test_legacy_and_other_folder_permissions_do_not_authorize_downloads(string $controllerClass, string $folder): void
    {
        $otherFolder = $folder === 'inbox' ? 'sent' : 'inbox';
        $this->permissions(['fax_download_view', "fax_{$otherFolder}_download", "fax_{$folder}_view", "fax_{$folder}_delete"]);

        $this->assertSame(403, (new $controllerClass)->download('file-a')->getStatusCode());
    }

    public function test_fresh_install_seeds_download_permissions_only_for_admin_and_superadmin(): void
    {
        $this->permissionTables();
        $this->seedPermissions();
        $this->seedPermissions();

        $this->assertNewDefaults();
        $this->assertSame(0, DB::table('v_permissions')->where('permission_name', 'fax_download_view')->count());
        $this->assertSame(0, DB::table('v_group_permissions')->where('permission_name', 'fax_download_view')->count());
    }

    public function test_update_removes_legacy_permission_without_carrying_grants_or_denials(): void
    {
        $this->permissionTables();
        DB::table('v_permissions')->insert([
            'permission_uuid' => (string) Str::uuid(), 'permission_name' => 'fax_download_view',
        ]);
        foreach (['superadmin' => 'false', 'admin' => 'false', 'user' => 'true', 'fax' => 'true', 'custom' => 'true'] as $group => $assigned) {
            DB::table('v_group_permissions')->insert([
                'group_permission_uuid' => (string) Str::uuid(), 'group_uuid' => $group,
                'group_name' => $group, 'permission_name' => 'fax_download_view', 'permission_assigned' => $assigned,
            ]);
        }
        DB::table('v_group_permissions')->insert([
            'group_permission_uuid' => (string) Str::uuid(), 'group_uuid' => 'custom',
            'group_name' => 'custom', 'permission_name' => 'fax_inbox_delete', 'permission_assigned' => 'false',
        ]);
        $unrelated = DB::table('v_group_permissions')->where('permission_name', 'fax_inbox_delete')->first();
        Cache::tags(['permissions'])->put('fax-download-test', 'cached');

        $this->assertTrue($this->applyUpdate());
        $this->assertNull(Cache::tags(['permissions'])->get('fax-download-test'));
        $this->assertSame(0, DB::table('v_permissions')->where('permission_name', 'fax_download_view')->count());
        $this->assertSame(0, DB::table('v_group_permissions')->where('permission_name', 'fax_download_view')->count());
        $this->assertSame(0, DB::table('v_group_permissions')
            ->whereIn('permission_name', ['fax_inbox_download', 'fax_sent_download'])->count());
        $this->seedPermissions();

        $this->assertNewDefaults();
        $this->assertEquals($unrelated, DB::table('v_group_permissions')
            ->where('group_permission_uuid', $unrelated->group_permission_uuid)->first());

        // Re-running cleanup and seeding must preserve decisions made using
        // the new permissions, without restoring the retired permission.
        DB::table('v_group_permissions')->where('group_name', 'admin')
            ->where('permission_name', 'fax_inbox_download')->update(['permission_assigned' => 'false']);
        $this->assertTrue($this->applyUpdate());
        $this->seedPermissions();
        $this->assertSame('false', DB::table('v_group_permissions')->where('group_name', 'admin')
            ->where('permission_name', 'fax_inbox_download')->value('permission_assigned'));
        $this->assertSame(4, DB::table('v_group_permissions')
            ->whereIn('permission_name', ['fax_inbox_download', 'fax_sent_download'])->count());
        $this->assertSame(0, DB::table('v_permissions')->where('permission_name', 'fax_download_view')->count());
    }

    public function test_failed_cleanup_rolls_back_permission_assignments(): void
    {
        $this->permissionTables();
        DB::table('v_permissions')->insert(['permission_name' => 'fax_download_view']);
        DB::table('v_group_permissions')->insert([
            'group_uuid' => 'custom', 'permission_name' => 'fax_download_view', 'permission_assigned' => 'true',
        ]);
        DB::statement("CREATE TRIGGER reject_permission_delete BEFORE DELETE ON v_permissions BEGIN SELECT RAISE(ABORT, 'test failure'); END");

        $this->assertFalse($this->applyUpdate());
        $this->assertSame(1, DB::table('v_permissions')->where('permission_name', 'fax_download_view')->count());
        $this->assertSame(1, DB::table('v_group_permissions')->where('permission_name', 'fax_download_view')->count());
    }

    private function permissionTables(): void
    {
        $this->table('v_permissions', ['permission_uuid', 'application_name', 'permission_name', 'insert_date']);
        $this->table('v_groups', ['group_uuid', 'group_name']);
        $this->table('v_group_permissions', [
            'group_permission_uuid', 'group_uuid', 'group_name', 'permission_name',
            'permission_protected', 'permission_assigned', 'insert_date',
        ]);
        foreach (['superadmin', 'admin', 'user', 'fax', 'custom'] as $group) {
            DB::table('v_groups')->insert(['group_uuid' => $group, 'group_name' => $group]);
        }
    }

    private function seedPermissions(): void
    {
        $seeder = new DatabaseSeeder;
        (new ReflectionMethod($seeder, 'createPermissions'))->invoke($seeder);
        (new ReflectionMethod($seeder, 'createGroupPermissions'))->invoke($seeder);
    }

    private function assertNewDefaults(): void
    {
        foreach (['fax_inbox_download', 'fax_sent_download'] as $permission) {
            $this->assertSame(1, DB::table('v_permissions')->where('permission_name', $permission)->count());
            $this->assertSame(2, DB::table('v_group_permissions')->where('permission_name', $permission)->count());
            $this->assertSame(['admin' => 'true', 'superadmin' => 'true'], DB::table('v_group_permissions')
                ->where('permission_name', $permission)->orderBy('group_name')
                ->pluck('permission_assigned', 'group_name')->all());
        }
    }

    private function applyUpdate(): bool
    {
        ob_start();
        try {
            return (new Update222)->apply();
        } finally {
            ob_end_clean();
        }
    }

    private function permissions(array $names): void
    {
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $names)]);
    }

    private function table(string $name, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->string($column)->nullable();
            }
        });
    }
}
