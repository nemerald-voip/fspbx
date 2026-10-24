<?php

namespace Tests\Unit;

use App\Http\Controllers\HomerApiController;
use App\Models\CDR;
use App\Models\HomerApiSettings;
use App\Services\HomerApiService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HomerApiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Http::preventStrayRequests();
    }

    public function test_export_resolves_correlated_legs_and_uses_the_cdr_time_window(): void
    {
        $pcap = hex2bin('d4c3b2a1').str_repeat("\0", 36);
        Http::fake([
            'https://homer.example/api/v4/transactions/messages' => Http::response(['data' => ['items' => [
                $this->message('original@pbx'), $this->message('outbound@pbx'),
            ]]]),
            'https://homer.example/api/v4/transactions/export/pcap' => Http::response($pcap),
        ]);

        $this->assertSame($pcap, $this->service()->trace($this->cdr(), true));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages')
            && $request['session_id'] === 'original@pbx'
            && $request['timestamp'] === ['from' => 1699999940000, 'to' => 1700000120000]
            && $request->hasHeader('Auth-Token', 'private-token'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/pcap')
            && $request['session_ids'] === ['original@pbx', 'outbound@pbx']
            && ! isset($request['session_id']));
        Http::assertSentCount(2);
    }

    public function test_trace_returns_only_display_fields_and_keeps_sip_text_inert(): void
    {
        $row = $this->message('original@pbx') + ['secret_internal_column' => 'hidden'];
        $row['payload'] = '<script>alert(1)</script>';
        Http::fake(['*' => Http::response(['data' => ['items' => [$row]]])]);
        $result = $this->service()->trace($this->cdr());
        $this->assertArrayNotHasKey('secret_internal_column', $result[0]);
        $this->assertSame('<script>alert(1)</script>', $result[0]['payload']);
    }

    public function test_txt_export_includes_all_pages_and_correlated_legs_without_browser_selection(): void
    {
        $this->withCdrDatabase(function ($id) {
            Session::put('domain_uuid', 'account-a');
            $rows = [];
            for ($index = 0; $index < 125; $index++) {
                $rows[] = $this->message('leg-'.($index % 4).'@pbx');
            }
            $rows[124]['payload'] = "BYE sip:100@example.com SIP/2.0\r\nX-Last-Message: yes\r\n\r\n<script>inert</script>";
            Http::fake(['*/messages' => Http::response(['data' => ['items' => $rows]])]);
            $response = (new HomerApiController)->trace(Request::create('/', 'POST', [
                'cdr_uuid' => $id, 'download' => true, 'format' => 'txt',
                'page' => 2, 'session_id' => 'selected-leg', 'selected' => 124,
            ]), $this->service());
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertSame('attachment; filename="call-'.$id.'.txt"', $response->headers->get('Content-Disposition'));
            $body = $response->getContent();
            $this->assertSame(125, substr_count($body, '--- Message '));
            $this->assertStringContainsString('Messages: 125 | Call legs: 4', $body);
            $this->assertStringContainsString('X-Last-Message: yes', $body);
            $this->assertStringContainsString('<script>inert</script>', $body);
            Http::assertSentCount(1);
            Http::assertSent(fn ($request) => $request['session_id'] === 'original@pbx'
                && ! isset($request['page']) && ! isset($request['selected']));
        });
    }

    public function test_txt_export_rejects_truncated_results_instead_of_returning_partial_text(): void
    {
        Http::fake(['*' => Http::response(['data' => ['items' => array_fill(0, 10000, $this->message('original@pbx'))]])]);
        $this->expectException(RuntimeException::class);
        $this->service()->trace($this->cdr(), true, 'txt');
    }

    public function test_txt_export_keeps_account_scoping_and_validates_export_format(): void
    {
        $this->withCdrDatabase(function ($id) {
            Session::put('domain_uuid', 'account-b');
            try {
                (new HomerApiController)->trace(Request::create('/', 'POST', [
                    'cdr_uuid' => $id, 'download' => true, 'format' => 'txt',
                ]), $this->service());
                $this->fail('TXT export must enforce account scope.');
            } catch (ModelNotFoundException $exception) {
                Http::assertNothingSent();
            }
            Session::put('domain_uuid', 'account-a');
            $this->expectException(ValidationException::class);
            (new HomerApiController)->trace(Request::create('/', 'POST', [
                'cdr_uuid' => $id, 'download' => true, 'format' => 'html',
            ]), $this->service());
        });
    }

    public function test_disabled_integration_does_not_contact_homer(): void
    {
        $service = $this->service(['enabled' => false]);
        try {
            $service->trace($this->cdr());
            $this->fail('Disabled integration must reject the request.');
        } catch (RuntimeException $exception) {
            Http::assertNothingSent();
        }
    }

    public function test_blank_call_id_cannot_trigger_a_broad_search(): void
    {
        $cdr = $this->cdr();
        $cdr->sip_call_id = '';
        try {
            $this->service()->trace($cdr);
            $this->fail('Blank Call-ID must fail closed.');
        } catch (RuntimeException $exception) {
            Http::assertNothingSent();
        }
    }

    public function test_upstream_errors_do_not_expose_response_bodies(): void
    {
        Http::fake(['*' => Http::response('private-token and sensitive SIP payload', 401)]);
        try {
            $this->service()->trace($this->cdr());
            $this->fail('Upstream error must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('private-token', $exception->getMessage());
            $this->assertStringNotContainsString('sensitive SIP', $exception->getMessage());
        }
    }

    public function test_empty_results_are_not_returned_as_pcaps(): void
    {
        Http::fake(['*' => Http::response(['data' => ['items' => null]])]);
        $this->expectException(RuntimeException::class);
        $this->service()->trace($this->cdr(), true);
    }

    public function test_html_download_is_rejected(): void
    {
        Http::fake([
            '*/messages' => Http::response(['data' => ['items' => [$this->message('original@pbx')]]]),
            '*/pcap' => Http::response('<html>Login page returned by a reverse proxy</html>'),
        ]);
        $this->expectException(RuntimeException::class);
        $this->service()->trace($this->cdr(), true);
    }

    public function test_changing_the_destination_requires_a_fresh_token(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->configured([
            'enabled' => true, 'base_url' => 'https://different.example', 'auth_type' => 'auth_token',
        ]);
    }

    public function test_tokens_are_encrypted_and_hidden_from_serialization(): void
    {
        $settings = $this->service()->settings();
        $this->assertSame('private-token', $settings->api_token);
        $this->assertStringNotContainsString('private-token', $settings->getAttributes()['api_token']);
        $this->assertArrayNotHasKey('api_token', $settings->toArray());
    }

    public function test_global_connection_uses_a_non_incrementing_uuid_primary_key(): void
    {
        $settings = new HomerApiSettings(['homer_api_setting_uuid' => HomerApiSettings::CONNECTION_UUID]);
        $this->assertSame('homer_api_setting_uuid', $settings->getKeyName());
        $this->assertSame('string', $settings->getKeyType());
        $this->assertFalse($settings->getIncrementing());
        $this->assertTrue(\Illuminate\Support\Str::isUuid($settings->getKey()));
    }

    public function test_only_https_urls_without_embedded_credentials_or_query_are_allowed(): void
    {
        foreach (['http://homer.example', 'https://user:pass@homer.example', 'https://homer.example?token=abc',
            'https://homer.example/#fragment', 'file:///etc/passwd', "https://homer.example/\n"] as $url) {
            $this->assertFalse(HomerApiService::validBaseUrl($url), $url);
        }
        $this->assertTrue(HomerApiService::validBaseUrl('https://10.20.30.40:8443/homer'));
    }

    public function test_assigning_pcap_permission_to_a_tenant_does_not_expose_correlated_captures(): void
    {
        Session::put('permissions', collect(['xml_cdr_view', 'xml_cdr_view_all_records', 'xml_cdr_pcap'])
            ->map(fn ($name) => (object) ['permission_name' => $name])->all());
        Session::put('user.groups', [(object) ['group_name' => 'admin', 'group_level' => 50]]);
        $this->assertFalse(HomerApiService::canView());
        $this->expectException(HttpException::class);
        (new HomerApiController)->trace(Request::create('/'), $this->service());
    }

    public function test_superadmin_still_needs_the_dedicated_action_and_record_scope_permissions(): void
    {
        Session::put('user.groups', [(object) ['group_name' => 'superadmin', 'group_level' => 80]]);
        Session::put('permissions', [(object) ['permission_name' => 'xml_cdr_view']]);
        $this->assertFalse(HomerApiService::canView());
        Session::put('permissions', collect(['xml_cdr_view', 'xml_cdr_view_all_records', 'xml_cdr_pcap'])
            ->map(fn ($name) => (object) ['permission_name' => $name])->all());
        $this->assertTrue(HomerApiService::canView());
    }

    public function test_controller_rejects_another_account_cdr_before_contacting_homer(): void
    {
        $this->withCdrDatabase(function ($id) {
            Session::put('domain_uuid', 'account-b');
            $this->expectException(ModelNotFoundException::class);
            try {
                (new HomerApiController)->trace(Request::create('/', 'POST', ['cdr_uuid' => $id]), $this->service());
            } finally {
                Http::assertNothingSent();
            }
        });
    }

    public function test_controller_derives_lookup_from_cdr_and_marks_trace_private(): void
    {
        $this->withCdrDatabase(function ($id) {
            Session::put('domain_uuid', 'account-a');
            Http::fake(['*' => Http::response(['data' => ['items' => [$this->message('original@pbx')]]])]);
            $request = Request::create('/', 'POST', [
                'cdr_uuid' => $id, 'session_id' => 'attacker-supplied', 'timestamp' => ['from' => 1],
            ]);
            $response = (new HomerApiController)->trace($request, $this->service());
            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            Http::assertSent(fn ($request) => $request['session_id'] === 'original@pbx'
                && $request['timestamp']['from'] === 1699999940000);
        });
    }

    public function test_trace_limit_does_not_silently_export_a_partial_capture(): void
    {
        Http::fake(['*' => Http::response(['data' => ['items' => array_fill(0, 10000, $this->message('original@pbx'))]])]);
        try {
            $this->service()->trace($this->cdr(), true);
            $this->fail('Oversized trace must be rejected.');
        } catch (RuntimeException $exception) {
            Http::assertSentCount(1);
        }
    }

    private function withCdrDatabase(callable $callback): void
    {
        $original = config('database.default');
        config(['database.default' => 'homer_test', 'database.connections.homer_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('homer_test');
        try {
            Schema::create('v_xml_cdr', function (Blueprint $table) {
                $table->string('xml_cdr_uuid')->primary();
                $table->string('domain_uuid');
                $table->string('sip_call_id');
                $table->bigInteger('start_epoch');
                $table->bigInteger('end_epoch');
                $table->integer('duration');
            });
            $id = 'e1ee26c8-80c8-4d7a-b56b-39f2e9e8d9ef';
            DB::table('v_xml_cdr')->insert($this->cdr()->getAttributes() + ['xml_cdr_uuid' => $id, 'domain_uuid' => 'account-a']);
            Session::put('user.groups', [(object) ['group_name' => 'superadmin', 'group_level' => 80]]);
            Session::put('permissions', collect(['xml_cdr_view', 'xml_cdr_view_all_records', 'xml_cdr_pcap'])
                ->map(fn ($name) => (object) ['permission_name' => $name])->all());
            $callback($id);
        } finally {
            DB::purge('homer_test');
            config(['database.default' => $original]);
        }
    }

    private function service(array $overrides = []): HomerApiService
    {
        $settings = new HomerApiSettings(array_merge([
            'enabled' => true, 'base_url' => 'https://homer.example', 'auth_type' => 'auth_token', 'api_token' => 'private-token',
        ], $overrides));

        return new class($settings) extends HomerApiService {
            public function __construct(private HomerApiSettings $config) {}
            public function settings(): HomerApiSettings { return $this->config; }
        };
    }

    private function cdr(): CDR
    {
        $cdr = new CDR;
        $cdr->forceFill(['sip_call_id' => 'original@pbx', 'start_epoch' => 1700000000, 'end_epoch' => 1700000060, 'duration' => 60]);
        return $cdr;
    }

    private function message(string $id): array
    {
        return ['session_id' => $id, 'timestamp' => '2023-11-14T22:13:20Z',
            'src_ip' => '192.0.2.1', 'src_port' => 5060, 'dst_ip' => '192.0.2.2', 'dst_port' => 5060,
            'payload' => "INVITE sip:100@example.com SIP/2.0\r\nCall-ID: $id\r\n\r\n"];
    }
}
