<?php

namespace Tests\Feature;

use App\Exports\ExtensionsExport;
use App\Exports\PhoneNumbersExport;
use App\Http\Requests\Api\V1\StoreExtensionRequest as ApiStoreExtensionRequest;
use App\Http\Requests\Api\V1\UpdateExtensionRequest as ApiUpdateExtensionRequest;
use App\Http\Requests\BulkUpdateExtensionRequest;
use App\Http\Requests\UpdateExtensionRequest;
use App\Models\CDR;
use App\Models\Destinations;
use App\Models\Extensions;
use App\Rules\ValidPhoneNumber;
use App\Services\PhoneNumberService;
use libphonenumber\PhoneNumberFormat;
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

        DB::table('v_default_settings')->insert([
            'default_setting_uuid' => (string) Str::uuid(),
            'default_setting_subcategory' => 'country',
            'default_setting_value' => 'US',
            'default_setting_enabled' => 'true',
        ]);

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

    public function test_phone_validation_uses_account_country_and_accepts_explicit_international_numbers(): void
    {
        $defaultDomain = (string) Str::uuid();
        $this->assertTrue(validator(['number' => '7282396453'], ['number' => new ValidPhoneNumber($defaultDomain)])->passes());
        $this->assertTrue(validator(['number' => '02079460958'], ['number' => new ValidPhoneNumber($this->domainUuid)])->passes());
        $this->assertTrue(validator(['number' => '+39066982'], ['number' => new ValidPhoneNumber($this->domainUuid)])->passes());
        $this->assertFalse(validator(['number' => '02079460958'], ['number' => new ValidPhoneNumber($defaultDomain)])->passes());
        foreach (['1001', 'not-a-number', '72823964XX', []] as $value) {
            $this->assertFalse(validator(['number' => $value], ['number' => new ValidPhoneNumber($defaultDomain)])->passes());
        }
    }

    public function test_missing_country_does_not_assume_a_country(): void
    {
        DB::table('v_default_settings')->delete();
        $domain = (string) Str::uuid();
        $numbers = app(PhoneNumberService::class);
        $this->assertSame('ZZ', $numbers->countryCodeForDomain($domain));
        $this->assertSame('7282396453', $numbers->formatForDomain('7282396453', $domain, PhoneNumberFormat::E164));
        $this->assertSame('+17282396453', $numbers->formatForDomain('+17282396453', $domain, PhoneNumberFormat::E164));
        $this->assertFalse($numbers->isValidForDomain('7282396453', $domain));
        $this->assertTrue($numbers->isValidForDomain('+442079460958', $domain));
    }

    /** @dataProvider internationalNumbers */
    public function test_phone_requests_use_target_account_and_keep_international_conditions(string $country, string $national, string $prefix, string $significant): void
    {
        $domain = (string) Str::uuid();
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => (string) Str::uuid(), 'domain_uuid' => $domain,
            'domain_setting_subcategory' => 'country', 'domain_setting_value' => $country,
            'domain_setting_enabled' => 'true',
        ]);
        $route = new class($domain) {
            public function __construct(private string $domain) {}
            public function parameter(string $key, mixed $default = null): mixed {
                return $key === 'domain_uuid' ? $this->domain : $default;
            }
        };
        foreach ([
            \App\Http\Requests\StorePhoneNumberRequest::class,
            \App\Http\Requests\Api\V1\StorePhoneNumberRequest::class,
            \App\Http\Requests\Api\V1\UpdatePhoneNumberRequest::class,
        ] as $requestClass) {
            $request = $requestClass::create('/numbers', 'POST', [
                'domain_uuid' => $domain, 'destination_number' => $national,
                'destination_conditions' => [['condition_expression' => $national]],
            ]);
            $request->setContainer(app());
            $request->setRouteResolver(fn () => $route);
            $request->prepareForValidation();
            $this->assertSame($significant, $request->input('destination_number'));
            $this->assertSame($prefix, $request->input('destination_prefix'));
            if ($request instanceof \App\Http\Requests\StorePhoneNumberRequest) {
                $this->assertSame('+'.$prefix.$significant, $request->input('destination_conditions.0.condition_expression'));
            }
        }

        $update = \App\Http\Requests\UpdatePhoneNumberRequest::create('/numbers', 'PUT', [
            'domain_uuid' => $domain, 'destination_conditions' => [['condition_expression' => $national]],
        ]);
        $update->setContainer(app());
        $update->prepareForValidation();
        $this->assertSame('+'.$prefix.$significant, $update->input('destination_conditions.0.condition_expression'));
        $this->assertFalse($update->has('destination_number'));
    }

    public static function internationalNumbers(): array
    {
        return [
            'US' => ['US', '(728) 239-6453', '1', '7282396453'],
            'UK' => ['GB', '020 7946 0958', '44', '2079460958'],
            'Italy' => ['IT', '06 6982', '39', '066982'],
            'Russia' => ['RU', '8 910 858 34 58', '7', '9108583458'],
            'Australia' => ['AU', '02 9374 4000', '61', '293744000'],
            'Japan' => ['JP', '03-1234-5678', '81', '312345678'],
            'Brazil' => ['BR', '11 91234-5678', '55', '11912345678'],
            'explicit international overrides account' => ['GB', '+81 3-1234-5678', '81', '312345678'],
        ];
    }

    public function test_phone_creation_preserves_patterns_and_explicit_country_prefixes(): void
    {
        $numbers = app(PhoneNumberService::class);
        foreach (['72823964XX', '(1001|1002)', '1001'] as $value) {
            $this->assertSame(['destination_number' => $value, 'destination_prefix' => null],
                $numbers->destinationParts($value, null, $this->domainUuid));
        }
        $this->assertSame(['destination_number' => '7282396453', 'destination_prefix' => '1'],
            $numbers->destinationParts('7282396453', '1', $this->domainUuid));
        $this->assertSame(['destination_number' => '066982', 'destination_prefix' => '39'],
            $numbers->destinationParts('+39 06 6982', '44', $this->domainUuid));
    }

    public function test_phone_api_patch_does_not_add_omitted_number_fields(): void
    {
        $request = \App\Http\Requests\Api\V1\UpdatePhoneNumberRequest::create('/numbers', 'PATCH', [
            'destination_description' => 'Updated label',
        ]);
        $request->setContainer(app());
        $request->prepareForValidation();
        $this->assertSame(['destination_description' => 'Updated label'], $request->all());
    }

    public function test_fax_message_and_speed_dial_display_use_the_records_account(): void
    {
        foreach ([
            [\App\Models\Faxes::class, 'fax_caller_id_number', 'fax_caller_id_number_formatted'],
            [\App\Models\FaxFiles::class, 'fax_destination', 'fax_destination_formatted'],
            [\App\Models\FaxQueues::class, 'fax_number', 'fax_number_formatted'],
            [\App\Models\Messages::class, 'source', 'source_formatted'],
            [\App\Models\MessageSetting::class, 'destination', 'destination_formatted'],
            [\App\Models\SpeedDialPhone::class, 'phone_number', 'phone_number_formatted'],
        ] as [$class, $field, $formatted]) {
            session()->put('domain_uuid', (string) Str::uuid());
            $model = new $class();
            $model->setRawAttributes(['domain_uuid' => $this->domainUuid, $field => '2079460958']);
            $this->assertSame('020 7946 0958', $model->{$formatted}, $class);
        }
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

    public function test_cdr_formatting_uses_each_records_country_with_or_without_a_session(): void
    {
        $italianDomain = (string) Str::uuid();
        DB::table('v_domain_settings')->insert([
            'domain_setting_uuid' => (string) Str::uuid(),
            'domain_uuid' => $italianDomain,
            'domain_setting_subcategory' => 'country',
            'domain_setting_value' => 'IT',
            'domain_setting_enabled' => 'true',
        ]);
        $defaultDomain = (string) Str::uuid();
        $fields = ['caller_id_number', 'caller_id_name', 'caller_destination', 'destination_number'];

        DB::enableQueryLog();

        // The session starts in the UK; exports also need to work without it.
        foreach ([true, false] as $withSession) {
            if (!$withSession) {
                session()->forget('domain_uuid');
            }

            foreach ([
                [$this->domainUuid, '2079460958', '020 7946 0958'],
                [$italianDomain, '3512345678', '351 234 5678'],
                [$defaultDomain, '3512345678', '(351) 234-5678'],
                [$italianDomain, '3511234567', '351 123 4567'],
                [$defaultDomain, '+393512345678', '351 234 5678'],
            ] as [$domain, $number, $expected]) {
                $cdr = new CDR();
                $attributes = ['domain_uuid' => $domain] + array_fill_keys($fields, $number);
                $cdr->setRawAttributes($attributes);

                foreach ($fields as $field) {
                    // Array access is also used by the CSV export.
                    $this->assertSame($expected, $cdr[$field . '_formatted']);
                }
                $this->assertSame($attributes, $cdr->getAttributes());
            }
        }

        $countryQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains($query['query'], 'v_domain_settings'));

        $this->assertCount(3, $countryQueries, 'Resolve the country once per account, not per field or row.');
    }

    public function test_cdr_formatting_preserves_caller_names_and_non_phone_values(): void
    {
        $cdr = new CDR();
        $cdr->domain_uuid = $this->domainUuid;

        foreach ([null, '', 'Alice Smith', '1800FLOWERS', '3512345678 Support', 'anonymous'] as $name) {
            $cdr->caller_id_name = $name;
            $this->assertSame($name, $cdr->caller_id_name_formatted);
        }

        foreach ([null, '', '100', '*99100', 'not-a-number'] as $number) {
            foreach (['caller_id_number', 'caller_destination', 'destination_number'] as $field) {
                $cdr->{$field} = $number;
                $this->assertSame($number === '' ? null : $number, $cdr[$field . '_formatted']);
            }
        }
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
