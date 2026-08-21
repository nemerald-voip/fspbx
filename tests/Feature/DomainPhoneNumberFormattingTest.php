<?php

namespace Tests\Feature;

use App\Exports\ExtensionsExport;
use App\Exports\PhoneNumbersExport;
use App\Http\Requests\Api\V1\StoreExtensionRequest as ApiStoreExtensionRequest;
use App\Http\Requests\Api\V1\UpdateExtensionRequest as ApiUpdateExtensionRequest;
use App\Http\Requests\BulkUpdateExtensionRequest;
use App\Http\Requests\UpdateExtensionRequest;
use App\Models\Destinations;
use App\Models\Extensions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainPhoneNumberFormattingTest extends TestCase
{
    private string $databasePath;
    private string $domainUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-phone-formatting-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'logging.default' => 'null',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();

        $this->domainUuid = (string) Str::uuid();
        session()->put('domain_uuid', $this->domainUuid);

        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => (string) Str::uuid(),
            'domain_uuid' => $this->domainUuid,
            'domain_setting_subcategory' => 'country',
            'domain_setting_value' => 'gb',
            'domain_setting_enabled' => 'true',
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

    public function test_extension_and_destination_accessors_use_the_domain_country(): void
    {
        DB::enableQueryLog();

        $extension = new Extensions([
            'domain_uuid' => $this->domainUuid,
            'outbound_caller_id_number' => '2079460958',
            'emergency_caller_id_number' => '2079460958',
        ]);

        $destination = new Destinations([
            'domain_uuid' => $this->domainUuid,
            'destination_number' => '2079460958',
            'destination_description' => 'London',
        ]);

        $this->assertSame('+442079460958', $extension->outbound_caller_id_number_e164);
        $this->assertSame('+442079460958', $extension->emergency_caller_id_number_e164);
        $this->assertSame('020 7946 0958', $extension->outbound_caller_id_number_formatted);
        $this->assertSame('+442079460958', $destination->destination_number_e164);
        $this->assertSame('020 7946 0958', $destination->destination_number_formatted);
        $this->assertSame('020 7946 0958 - London', $destination->label);

        $countryQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'v_domain_settings'));

        $this->assertCount(1, $countryQueries, 'The domain country should be resolved once per request.');
    }

    public function test_extension_form_requests_normalize_caller_ids_to_domain_e164(): void
    {
        $update = UpdateExtensionRequest::create('/extensions/example', 'PUT', [
            'extension' => '1001',
            'directory_first_name' => 'London',
            'outbound_caller_id_number' => '2079460958',
            'emergency_caller_id_number' => '+442079460958',
        ]);
        $update->setContainer(app());
        $update->prepareForValidation();

        $this->assertSame('+442079460958', $update->input('outbound_caller_id_number'));
        $this->assertSame('+442079460958', $update->input('emergency_caller_id_number'));

        $bulk = BulkUpdateExtensionRequest::create('/extensions/bulk', 'PUT', [
            'items' => [(string) Str::uuid()],
            'outbound_caller_id_number' => '2079460958',
            'emergency_caller_id_number' => null,
        ]);
        $bulk->setContainer(app());
        $bulk->prepareForValidation();

        $this->assertSame('+442079460958', $bulk->input('outbound_caller_id_number'));
        $this->assertNull($bulk->input('emergency_caller_id_number'));
    }

    public function test_extension_api_requests_normalize_caller_ids_to_domain_e164(): void
    {
        $route = new class ($this->domainUuid) {
            public function __construct(private readonly string $domainUuid)
            {
            }

            public function parameter(string $key, mixed $default = null): mixed
            {
                return $key === 'domain_uuid' ? $this->domainUuid : $default;
            }
        };

        foreach ([ApiStoreExtensionRequest::class, ApiUpdateExtensionRequest::class] as $requestClass) {
            $request = $requestClass::create('/api/v1/extensions', 'POST', [
                'outbound_caller_id_number' => '2079460958',
                'emergency_caller_id_number' => '+442079460958',
            ]);
            $request->setContainer(app());
            $request->setRouteResolver(fn () => $route);
            $request->prepareForValidation();

            $this->assertSame('+442079460958', $request->input('outbound_caller_id_number'));
            $this->assertSame('+442079460958', $request->input('emergency_caller_id_number'));
        }
    }

    public function test_extension_and_phone_number_exports_use_the_domain_country(): void
    {
        DB::table('v_extensions')->insert([
            'extension_uuid' => (string) Str::uuid(),
            'domain_uuid' => $this->domainUuid,
            'extension' => '1001',
            'directory_first_name' => 'London',
            'directory_last_name' => 'Office',
            'outbound_caller_id_number' => '2079460958',
            'emergency_caller_id_number' => '+442079460958',
            'description' => 'UK extension',
        ]);

        DB::table('v_destinations')->insert([
            'destination_uuid' => (string) Str::uuid(),
            'domain_uuid' => $this->domainUuid,
            'destination_prefix' => '44',
            'destination_number' => '+442079460958',
            'destination_actions' => null,
            'destination_description' => 'London DID',
        ]);

        app()->instance('request', Request::create('/exports', 'GET'));

        $extensionRow = (new ExtensionsExport())->collection()->first();
        $phoneNumberRow = (new PhoneNumbersExport())->collection()->first();

        $this->assertSame('020 7946 0958', $extensionRow['outbound_caller_id_number']);
        $this->assertSame('020 7946 0958', $extensionRow['emergency_caller_id_number']);
        $this->assertSame('020 7946 0958', $phoneNumberRow['phone_number']);
    }

    private function createSchema(): void
    {
        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_setting_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });

        Schema::create('v_extensions', function (Blueprint $table) {
            $table->string('extension_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('extension');
            $table->string('directory_first_name')->nullable();
            $table->string('directory_last_name')->nullable();
            $table->string('outbound_caller_id_number')->nullable();
            $table->string('emergency_caller_id_number')->nullable();
            $table->string('description')->nullable();
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
            $table->string('voicemail_mail_to')->nullable();
        });

        Schema::create('v_destinations', function (Blueprint $table) {
            $table->string('destination_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('destination_prefix')->nullable();
            $table->string('destination_number');
            $table->text('destination_actions')->nullable();
            $table->string('destination_description')->nullable();
        });
    }
}
