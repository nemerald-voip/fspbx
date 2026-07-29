<?php

namespace Tests\Feature;

use App\Http\Controllers\ContactController;
use App\Models\Contact;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactOptionsTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-contact-options-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'logging.default' => 'null',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_fax_options_are_domain_scoped_and_prioritize_fax_numbers(): void
    {
        $domainUuid = (string) Str::uuid();
        $otherDomainUuid = (string) Str::uuid();

        $localContact = $this->createContact($domainUuid, 'Ada', 'Lovelace');
        $this->createPhone($localContact, '+15550000001', 'work');
        $this->createPhone($localContact, '+15550000002', 'fax');

        $otherContact = $this->createContact($otherDomainUuid, 'Other', 'Account');
        $this->createPhone($otherContact, '+15550000003', 'fax');

        $this->setSession($domainUuid, 'fax_send');

        $response = (new ContactController())->options(
            Request::create('/api/contacts/options', 'GET', [
                'channel' => 'fax',
            ])
        );

        $options = $response->getData(true)['options'];

        $this->assertSame(
            ['+15550000002', '+15550000001'],
            array_column($options, 'value')
        );
        $this->assertSame('Ada Lovelace · Fax · +15550000002', $options[0]['label']);
        $this->assertNotContains('+15550000003', array_column($options, 'value'));
    }

    public function test_search_matches_contact_name_and_organization(): void
    {
        $domainUuid = (string) Str::uuid();
        $organizationUuid = (string) Str::uuid();

        DB::table('organizations')->insert([
            'organization_uuid' => $organizationUuid,
            'domain_uuid' => $domainUuid,
            'name' => 'Northwind Clinic',
        ]);

        $contact = $this->createContact($domainUuid, 'Grace', 'Hopper', $organizationUuid);
        $this->createPhone($contact, '+15550000004', 'fax');

        $this->setSession($domainUuid, 'fax_send');

        foreach (['grace', 'northwind', '000004'] as $query) {
            $response = (new ContactController())->options(
                Request::create('/api/contacts/options', 'GET', [
                    'channel' => 'fax',
                    'query' => $query,
                ])
            );

            $this->assertSame(
                ['+15550000004'],
                array_column($response->getData(true)['options'], 'value')
            );
        }
    }

    public function test_truncation_is_reported_once_more_contacts_exist_than_the_limit(): void
    {
        $domainUuid = (string) Str::uuid();

        // One past the 50 contact limit, so the caller can warn the list is partial.
        for ($i = 0; $i < 51; $i++) {
            $contact = $this->createContact($domainUuid, 'Contact', str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            $this->createPhone($contact, '155500' . str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'fax');
        }

        $this->setSession($domainUuid, 'fax_send');

        $payload = (new ContactController())->options(
            Request::create('/api/contacts/options', 'GET', ['channel' => 'fax'])
        )->getData(true);

        $this->assertTrue($payload['truncated']);
        $this->assertSame(50, $payload['limit']);
        $this->assertCount(50, $payload['options']);
    }

    public function test_truncation_is_not_reported_when_everything_fits(): void
    {
        $domainUuid = (string) Str::uuid();

        $contact = $this->createContact($domainUuid, 'Ada', 'Lovelace');
        $this->createPhone($contact, '+15550000001', 'fax');

        $this->setSession($domainUuid, 'fax_send');

        $payload = (new ContactController())->options(
            Request::create('/api/contacts/options', 'GET', ['channel' => 'fax'])
        )->getData(true);

        $this->assertFalse($payload['truncated']);
        $this->assertCount(1, $payload['options']);
    }

    public function test_company_is_reported_even_when_it_matches_the_contact_name(): void
    {
        $domainUuid = (string) Str::uuid();
        $namedOrgUuid = (string) Str::uuid();
        $companyOnlyOrgUuid = (string) Str::uuid();

        DB::table('organizations')->insert([
            ['organization_uuid' => $namedOrgUuid, 'domain_uuid' => $domainUuid, 'name' => 'Papa Jones'],
            ['organization_uuid' => $companyOnlyOrgUuid, 'domain_uuid' => $domainUuid, 'name' => 'Northwind Clinic'],
        ]);

        // A person whose company happens to carry the same name.
        $person = $this->createContact($domainUuid, 'Papa', 'Jones', $namedOrgUuid);
        $this->createPhone($person, '5304792220', 'fax');

        // A contact with no personal name: the company becomes its display name.
        $companyOnly = $this->createContact($domainUuid, '', '', $companyOnlyOrgUuid);
        $this->createPhone($companyOnly, '5304792221', 'fax');

        $this->setSession($domainUuid, 'fax_send');

        $options = collect((new ContactController())->options(
            Request::create('/api/contacts/options', 'GET', ['channel' => 'fax'])
        )->getData(true)['options'])->keyBy('value');

        $this->assertSame('Papa Jones', $options['5304792220']['name']);
        $this->assertSame('Papa Jones', $options['5304792220']['organization']);

        $this->assertSame('Northwind Clinic', $options['5304792221']['name']);
        $this->assertNull($options['5304792221']['organization']);
    }

    public function test_numbers_stored_with_formatting_are_found_by_digits(): void
    {
        $domainUuid = (string) Str::uuid();

        $contact = $this->createContact($domainUuid, 'Ada', 'Lovelace');
        $this->createPhone($contact, '(415) 555-0134', 'fax');

        $this->setSession($domainUuid, 'fax_send');

        $response = (new ContactController())->options(
            Request::create('/api/contacts/options', 'GET', [
                'channel' => 'fax',
                'query' => '4155550134',
            ])
        );

        $options = $response->getData(true)['options'];

        $this->assertSame(['(415) 555-0134'], array_column($options, 'value'));
        $this->assertSame('Ada Lovelace', $options[0]['name']);
    }

    public function test_numbers_are_stored_in_e164_and_returned_formatted(): void
    {
        $domainUuid = (string) Str::uuid();

        $this->setSession($domainUuid, 'fax_send');

        // Typed in national shape; stored as E.164 for the domain's country.
        $response = (new ContactController())->store(
            Request::create('/api/contacts', 'POST', [
                'phone_number' => '(415) 266-1234',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'phone_label' => 'fax',
            ])
        );

        $this->assertSame('+14152661234', $response->getData(true)['contact']['phone_number']);
        $this->assertSame('(415) 266-1234', $response->getData(true)['contact']['phone_number_formatted']);
        $this->assertDatabaseHas('contact_phones', ['phone_number' => '+14152661234']);

        // Options report the stored value plus its display shape.
        $options = (new ContactController())->options(
            Request::create('/api/contacts/options', 'GET', ['channel' => 'fax'])
        )->getData(true)['options'];

        $this->assertSame('+14152661234', $options[0]['value']);
        $this->assertSame('(415) 266-1234', $options[0]['number_formatted']);
    }

    public function test_a_number_is_found_however_it_was_typed(): void
    {
        $domainUuid = (string) Str::uuid();

        $contact = $this->createContact($domainUuid, 'Ada', 'Lovelace');
        $this->createPhone($contact, '+14152661234', 'fax');

        $this->setSession($domainUuid, 'fax_send');

        foreach (['+14152661234', '14152661234', '4152661234', '(415) 266-1234', '415-266-1234'] as $typed) {
            $payload = (new ContactController())->show(
                Request::create('/api/contacts/' . urlencode($typed), 'GET'),
                $typed
            )->getData(true);

            $this->assertNotNull($payload['contact'], "Lookup failed for {$typed}");
            $this->assertSame('Ada Lovelace', $payload['contact']['name']);
            $this->assertSame('(415) 266-1234', $payload['contact']['phone_number_formatted']);
        }
    }

    public function test_storing_a_fax_recipient_labels_the_number_as_fax(): void
    {
        $domainUuid = (string) Str::uuid();

        $this->setSession($domainUuid, 'fax_send');

        $response = (new ContactController())->store(
            Request::create('/api/contacts', 'POST', [
                'phone_number' => '15550000006',
                'first_name' => 'Fax',
                'last_name' => 'Recipient',
                'phone_label' => 'fax',
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Fax Recipient', $response->getData(true)['contact']['name']);
        $this->assertDatabaseHas('contact_phones', [
            'phone_number' => '15550000006',
            'label' => 'fax',
        ]);
    }

    public function test_a_company_contact_can_be_saved_without_a_first_name(): void
    {
        $domainUuid = (string) Str::uuid();
        $organizationUuid = (string) Str::uuid();

        DB::table('organizations')->insert([
            'organization_uuid' => $organizationUuid,
            'domain_uuid' => $domainUuid,
            'name' => 'Northwind Clinic',
        ]);

        $this->setSession($domainUuid, 'fax_send');

        $response = (new ContactController())->store(
            Request::create('/api/contacts', 'POST', [
                'phone_number' => '15550000007',
                'organization_uuid' => $organizationUuid,
                'phone_label' => 'fax',
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Northwind Clinic', $response->getData(true)['contact']['name']);
        $this->assertDatabaseHas('contacts', [
            'domain_uuid' => $domainUuid,
            'organization_uuid' => $organizationUuid,
            'first_name' => null,
        ]);
    }

    public function test_a_typed_company_is_created_without_enabling_billing(): void
    {
        $domainUuid = (string) Str::uuid();

        $this->setSession($domainUuid, 'fax_send');

        $response = (new ContactController())->store(
            Request::create('/api/contacts', 'POST', [
                'phone_number' => '15550000008',
                'organization_name' => 'Northwind Clinic',
                'phone_label' => 'fax',
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Northwind Clinic', $response->getData(true)['contact']['organization']);

        // Billing stays off, so the company never shows up as a billing customer.
        $this->assertDatabaseHas('organizations', [
            'domain_uuid' => $domainUuid,
            'name' => 'Northwind Clinic',
            'billing_enabled' => false,
        ]);
    }

    public function test_an_existing_company_is_reused_regardless_of_case(): void
    {
        $domainUuid = (string) Str::uuid();
        $organizationUuid = (string) Str::uuid();

        DB::table('organizations')->insert([
            'organization_uuid' => $organizationUuid,
            'domain_uuid' => $domainUuid,
            'name' => 'Northwind Clinic',
            'billing_enabled' => true,
        ]);

        $this->setSession($domainUuid, 'fax_send');

        (new ContactController())->store(
            Request::create('/api/contacts', 'POST', [
                'phone_number' => '15550000009',
                'organization_name' => 'northwind clinic',
                'phone_label' => 'fax',
            ])
        );

        $this->assertSame(1, DB::table('organizations')->where('domain_uuid', $domainUuid)->count());
        $this->assertDatabaseHas('contacts', [
            'domain_uuid' => $domainUuid,
            'organization_uuid' => $organizationUuid,
        ]);
    }

    public function test_a_company_from_another_domain_is_rejected(): void
    {
        $domainUuid = (string) Str::uuid();
        $otherDomainUuid = (string) Str::uuid();
        $organizationUuid = (string) Str::uuid();

        DB::table('organizations')->insert([
            'organization_uuid' => $organizationUuid,
            'domain_uuid' => $otherDomainUuid,
            'name' => 'Someone Elses Clinic',
        ]);

        $this->setSession($domainUuid, 'fax_send');

        $response = (new ContactController())->store(
            Request::create('/api/contacts', 'POST', [
                'phone_number' => '15550000010',
                'first_name' => 'Ada',
                'organization_uuid' => $organizationUuid,
            ])
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseMissing('contacts', ['domain_uuid' => $domainUuid]);
    }

    public function test_storing_a_duplicate_number_does_not_update_another_domain(): void
    {
        $domainUuid = (string) Str::uuid();
        $otherDomainUuid = (string) Str::uuid();
        $number = '+15550000005';

        $otherContact = $this->createContact($otherDomainUuid, 'Original', 'Name');
        $this->createPhone($otherContact, $number, 'work');

        $this->setSession($domainUuid, 'messages_view');

        $response = (new ContactController())->store(
            Request::create('/api/contacts', 'POST', [
                'phone_number' => $number,
                'first_name' => 'Local',
                'last_name' => 'Contact',
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseHas('contacts', [
            'contact_uuid' => $otherContact->contact_uuid,
            'domain_uuid' => $otherDomainUuid,
            'first_name' => 'Original',
        ]);
        $this->assertDatabaseHas('contacts', [
            'domain_uuid' => $domainUuid,
            'first_name' => 'Local',
            'last_name' => 'Contact',
        ]);
    }

    private function setSession(string $domainUuid, string $permission): void
    {
        session()->put('domain_uuid', $domainUuid);
        session()->put('permissions', [(object) ['permission_name' => $permission]]);
    }

    private function createContact(
        string $domainUuid,
        string $firstName,
        string $lastName,
        ?string $organizationUuid = null
    ): Contact {
        return Contact::create([
            'domain_uuid' => $domainUuid,
            'organization_uuid' => $organizationUuid,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    private function createPhone(Contact $contact, string $number, string $label): void
    {
        $contact->phones()->create([
            'phone_number' => $number,
            'label' => $label,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->string('organization_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('name');
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('billing_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->string('contact_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('organization_uuid')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('title')->nullable();
            $table->string('department')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_phones', function (Blueprint $table) {
            $table->string('phone_uuid')->primary();
            $table->string('phoneable_type');
            $table->string('phoneable_id');
            $table->string('phone_number');
            $table->string('label')->default('work');
            $table->timestamps();
        });

        Schema::create('contact_emails', function (Blueprint $table) {
            $table->string('email_uuid')->primary();
            $table->string('emailable_type');
            $table->string('emailable_id');
            $table->string('email_address');
            $table->string('label')->default('work');
            $table->timestamps();
        });

        // get_domain_setting('country') backs every E.164/display conversion.
        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_setting_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('domain_setting_category')->nullable();
            $table->string('domain_setting_subcategory')->nullable();
            $table->string('domain_setting_name')->nullable();
            $table->string('domain_setting_value')->nullable();
            $table->string('domain_setting_enabled')->nullable();
        });

        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_uuid')->primary();
            $table->string('default_setting_category')->nullable();
            $table->string('default_setting_subcategory')->nullable();
            $table->string('default_setting_name')->nullable();
            $table->string('default_setting_value')->nullable();
            $table->string('default_setting_enabled')->nullable();
        });

        Schema::create('contact_addresses', function (Blueprint $table) {
            $table->string('address_uuid')->primary();
            $table->string('domain_uuid');
            $table->string('addressable_type');
            $table->string('addressable_id');
            $table->string('label')->default('main');
            $table->string('street')->nullable();
            $table->string('extended')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code')->default('US');
            $table->timestamps();
        });
    }
}
