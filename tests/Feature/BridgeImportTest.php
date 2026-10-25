<?php

namespace Tests\Feature;

use App\Http\Controllers\BridgeController;
use App\Services\BridgeService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BridgeImportTest extends TestCase
{
    private const DOMAIN = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const OTHER_DOMAIN = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const GATEWAY = '11111111-1111-4111-8111-111111111111';
    private const OTHER_GATEWAY = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');

        Schema::create('v_bridges', function ($table) {
            $table->string('bridge_uuid')->primary();
            foreach (['domain_uuid', 'bridge_name', 'bridge_destination', 'bridge_enabled', 'bridge_description',
                'insert_date', 'insert_user', 'update_date', 'update_user'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::create('v_gateways', function ($table) {
            $table->string('gateway_uuid')->primary();
            foreach (['domain_uuid', 'gateway', 'enabled'] as $column) {
                $table->string($column)->nullable();
            }
        });

        DB::table('v_gateways')->insert([
            ['gateway_uuid' => self::GATEWAY, 'domain_uuid' => self::DOMAIN, 'gateway' => 'mine', 'enabled' => 'true'],
            ['gateway_uuid' => self::OTHER_GATEWAY, 'domain_uuid' => self::OTHER_DOMAIN, 'gateway' => 'theirs', 'enabled' => 'true'],
        ]);

        Session::put('domain_uuid', self::DOMAIN);
        Session::put('user_uuid', 'cccccccc-cccc-4ccc-8ccc-cccccccccccc');
        $this->grant(['bridge_import']);
    }

    private function grant(array $permissions): void
    {
        Session::put('permissions', array_map(fn ($name) => (object) ['permission_name' => $name], $permissions));
    }

    private function upload(string $csv, string $name = 'bridges.csv'): Request
    {
        return Request::create('/bridges/import', 'POST', [], [], [
            'file' => UploadedFile::fake()->createWithContent($name, $csv),
        ]);
    }

    private function import(string $csv)
    {
        return app(BridgeController::class)->import($this->upload($csv), app(BridgeService::class));
    }

    private function csv(array $lines): string
    {
        return implode("\n", array_merge(['bridge_name,bridge_destination,bridge_enabled,bridge_description'], $lines)) . "\n";
    }

    public function test_import_creates_every_row_in_the_current_domain(): void
    {
        $response = $this->import($this->csv([
            'Carrier A,sofia/gateway/' . self::GATEWAY . '/15555550100,true,Main',
            'Local ext,user/1001,false,',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['2 bridges imported successfully.'], $response->getData(true)['messages']['success']);

        $rows = DB::table('v_bridges')->orderBy('bridge_name')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([self::DOMAIN], $rows->pluck('domain_uuid')->unique()->all());
        $this->assertSame(['Carrier A', 'Local ext'], $rows->pluck('bridge_name')->all());
        $this->assertSame(['true', 'false'], $rows->pluck('bridge_enabled')->all());
        $this->assertSame('user/1001', $rows[1]->bridge_destination);
        $this->assertNotEmpty($rows[0]->bridge_uuid);
        $this->assertSame('cccccccc-cccc-4ccc-8ccc-cccccccccccc', $rows[0]->insert_user);
    }

    public function test_one_invalid_row_rejects_the_whole_file(): void
    {
        $response = $this->import($this->csv([
            'Good,user/1001,true,',
            'Bad,not-a-destination,true,',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringStartsWith('Row 3:', $response->getData(true)['errors'][0][0]);
        $this->assertSame(0, DB::table('v_bridges')->count());
    }

    public function test_gateway_of_another_domain_is_rejected(): void
    {
        $response = $this->import($this->csv(['X,sofia/gateway/' . self::OTHER_GATEWAY . '/1,true,']));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, DB::table('v_bridges')->count());
    }

    public function test_any_gateway_permission_allows_gateways_of_other_domains(): void
    {
        $this->grant(['bridge_import', 'outbound_route_any_gateway']);

        $response = $this->import($this->csv(['X,sofia/gateway/' . self::OTHER_GATEWAY . '/1,true,']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, DB::table('v_bridges')->count());
    }

    public function test_import_requires_the_bridge_import_permission(): void
    {
        $this->grant(['bridge_add']);

        $response = $this->import($this->csv(['Good,user/1001,true,']));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, DB::table('v_bridges')->count());
    }

    public function test_missing_file_and_wrong_type_are_validation_errors(): void
    {
        $controller = app(BridgeController::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->import(Request::create('/bridges/import', 'POST'), app(BridgeService::class));
    }

    public function test_file_with_only_a_header_row_is_rejected(): void
    {
        $response = $this->import($this->csv([]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, DB::table('v_bridges')->count());
    }

    public function test_quoted_fields_and_blank_lines_are_handled(): void
    {
        $response = $this->import($this->csv([
            '"Carrier, with comma","sofia/gateway/' . self::GATEWAY . '/1",true,"A ""quoted"" note"',
            '',
            'Second,loopback/5,,',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Carrier, with comma', DB::table('v_bridges')->orderBy('bridge_name')->value('bridge_name'));
        $this->assertSame('A "quoted" note', DB::table('v_bridges')->where('bridge_name', 'Carrier, with comma')->value('bridge_description'));
    }

    public function test_text_cells_are_not_converted_to_booleans_or_numbers(): void
    {
        $response = $this->import($this->csv([
            '007,user/1001,FALSE,',
            'Plain,user/1002,TRUE,',
        ]));

        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));
        $this->assertSame('false', DB::table('v_bridges')->where('bridge_name', '007')->value('bridge_enabled'));
        $this->assertSame('true', DB::table('v_bridges')->where('bridge_name', 'Plain')->value('bridge_enabled'));
    }

    public function test_template_download_requires_permission(): void
    {
        $this->grant([]);

        $this->expectException(HttpException::class);
        app(BridgeController::class)->downloadTemplate();
    }

    public function test_template_contains_the_expected_headings(): void
    {
        $response = app(BridgeController::class)->downloadTemplate();

        $firstLine = strtok($response->getFile()->getContent(), "\n");
        $this->assertSame(
            ['bridge_name', 'bridge_destination', 'bridge_enabled', 'bridge_description'],
            str_getcsv(trim($firstLine))
        );
    }

    public function test_the_downloaded_template_can_be_uploaded_after_filling_in_a_gateway(): void
    {
        $template = app(BridgeController::class)->downloadTemplate()->getFile()->getContent();
        $filled = str_replace('00000000-0000-0000-0000-000000000000', self::GATEWAY, $template);

        $response = $this->import($filled);

        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));
        $this->assertSame(1, DB::table('v_bridges')->count());
    }

    public function test_the_unedited_template_is_rejected_because_its_gateway_does_not_exist(): void
    {
        $template = app(BridgeController::class)->downloadTemplate()->getFile()->getContent();

        $response = $this->import($template);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(0, DB::table('v_bridges')->count());
    }
}
