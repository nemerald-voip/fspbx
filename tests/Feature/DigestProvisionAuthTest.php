<?php

namespace Tests\Feature;

use App\Http\Middleware\DigestProvisionAuth;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigestProvisionAuthTest extends TestCase
{
    private string $databasePath;
    private string $domainUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-provision-auth-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('v_devices', function (Blueprint $table) {
            $table->string('device_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('device_address')->nullable();
            $table->string('serial_number')->nullable();
        });

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_name')->nullable();
            $table->text('default_setting_value')->nullable();
            $table->string('default_setting_order')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });

        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_setting_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_name')->nullable();
            $table->text('domain_setting_value')->nullable();
            $table->string('domain_setting_order')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });

        $this->domainUuid = (string) Str::uuid();

        DB::table('v_devices')->insert([
            'device_uuid' => (string) Str::uuid(),
            'domain_uuid' => $this->domainUuid,
            'device_address' => '001122334455',
            'serial_number' => null,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_cidr_must_match_even_when_credentials_are_valid(): void
    {
        $this->configureCidr('192.0.2.10/32');
        $this->configureBasicCredentials();

        $response = $this->runMiddleware('198.51.100.10', $this->basicAuthorization());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_matching_cidr_still_requires_configured_credentials(): void
    {
        $this->configureCidr('192.0.2.10/32');
        $this->configureBasicCredentials();

        $response = $this->runMiddleware('192.0.2.10');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringStartsWith('Basic ', (string) $response->headers->get('WWW-Authenticate'));
    }

    public function test_matching_cidr_and_valid_credentials_are_allowed(): void
    {
        $this->configureCidr('192.0.2.10/32');
        $this->configureBasicCredentials();

        $response = $this->runMiddleware('192.0.2.10', $this->basicAuthorization());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_cidr_only_configuration_skips_http_authentication(): void
    {
        $this->configureCidr('192.0.2.10/32');

        $response = $this->runMiddleware('192.0.2.10');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_credentials_only_configuration_skips_cidr_restriction(): void
    {
        $this->configureBasicCredentials();

        $response = $this->runMiddleware('198.51.100.10', $this->basicAuthorization());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_digest_authentication_accepts_any_configured_password(): void
    {
        $this->insertDefault('http_auth_type', 'text', 'digest');
        $this->insertDefault('http_auth_username', 'text', 'provision');
        $this->insertDefault('http_auth_password', 'array', 'old-secret');
        $this->insertDefault('http_auth_password', 'array', 'rotated-secret');

        $challenge = $this->runMiddleware('198.51.100.10');
        $authorization = $this->digestAuthorization(
            (string) $challenge->headers->get('WWW-Authenticate'),
            'rotated-secret'
        );

        $response = $this->runMiddleware('198.51.100.10', $authorization);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_partial_credentials_do_not_enable_http_authentication(): void
    {
        $this->insertDefault('http_auth_username', 'text', 'provision');

        $response = $this->runMiddleware('198.51.100.10');

        $this->assertSame(200, $response->getStatusCode());
    }

    private function configureCidr(string $cidr): void
    {
        $this->insertDefault('cidr', 'array', $cidr);
    }

    private function configureBasicCredentials(): void
    {
        $this->insertDefault('http_auth_type', 'text', 'basic');
        $this->insertDefault('http_auth_username', 'text', 'provision');
        $this->insertDefault('http_auth_password', 'array', 'secret');
    }

    private function insertDefault(string $subcategory, string $name, string $value): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(),
            'default_setting_category' => 'provision',
            'default_setting_subcategory' => $subcategory,
            'default_setting_name' => $name,
            'default_setting_value' => $value,
            'default_setting_order' => null,
            'default_setting_enabled' => 'true',
        ]);
    }

    private function basicAuthorization(): string
    {
        return 'Basic ' . base64_encode('provision:secret');
    }

    private function digestAuthorization(string $challenge, string $password): string
    {
        preg_match('/realm="([^"]+)"/', $challenge, $realmMatch);
        preg_match('/nonce="([^"]+)"/', $challenge, $nonceMatch);
        preg_match('/opaque="([^"]+)"/', $challenge, $opaqueMatch);

        $realm = $realmMatch[1];
        $nonce = $nonceMatch[1];
        $opaque = $opaqueMatch[1];
        $uri = '/prov/001122334455.cfg';
        $nc = '00000001';
        $cnonce = 'test-client-nonce';
        $ha1 = md5("provision:{$realm}:{$password}");
        $ha2 = md5("GET:{$uri}");
        $response = md5("{$ha1}:{$nonce}:{$nc}:{$cnonce}:auth:{$ha2}");

        return 'Digest username="provision", '
            . "realm=\"{$realm}\", "
            . "nonce=\"{$nonce}\", "
            . "uri=\"{$uri}\", "
            . "response=\"{$response}\", "
            . 'qop=auth, '
            . "nc={$nc}, "
            . "cnonce=\"{$cnonce}\", "
            . "opaque=\"{$opaque}\"";
    }

    private function runMiddleware(string $clientIp, ?string $authorization = null)
    {
        $server = ['REMOTE_ADDR' => $clientIp];

        if ($authorization !== null) {
            $server['HTTP_AUTHORIZATION'] = $authorization;
        }

        $request = Request::create('/prov/001122334455.cfg', 'GET', [], [], [], $server);

        return app(DigestProvisionAuth::class)->handle(
            $request,
            fn () => response('allowed', 200)
        );
    }
}
