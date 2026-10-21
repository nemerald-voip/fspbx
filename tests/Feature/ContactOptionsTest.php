<?php

namespace Tests\Feature;

use App\Http\Controllers\ContactController;
use App\Http\Controllers\MessageController;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        (require database_path('migrations/2026_10_19_000001_add_contact_ownership.php'))->up();
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

    public function test_shared_and_personal_contacts_are_visible_but_other_users_and_accounts_are_excluded(): void
    {
        $domain = (string) Str::uuid();
        $alice = (string) Str::uuid();
        $bob = (string) Str::uuid();
        $shared = $this->createContact($domain, 'Shared', 'Clinic');
        $personal = $this->createContact($domain, 'Alice', 'Clinic', null, $alice);
        $private = $this->createContact($domain, 'Bob', 'Clinic', null, $bob);
        $foreign = $this->createContact((string) Str::uuid(), 'Foreign', 'Clinic');
        $foreignOwned = $this->createContact($foreign->domain_uuid, 'Elsewhere', 'Clinic', null, $alice);
        foreach ([$shared, $personal, $private, $foreign, $foreignOwned] as $index => $contact) {
            $this->createPhone($contact, '+1415555010' . $index, 'fax');
        }

        foreach (['fax' => 'fax_send', 'sms' => 'messages_view'] as $channel => $permission) {
            $this->setSession($domain, $permission, $alice);
            foreach (['', 'clinic', '1415555'] as $search) {
                $options = (new ContactController())->options(Request::create('/api/contacts/options', 'GET', [
                    'channel' => $channel, 'query' => $search,
                ]))->getData(true)['options'];
                $this->assertEqualsCanonicalizing(
                    [$shared->contact_uuid, $personal->contact_uuid], array_column($options, 'contact_uuid')
                );
                $byContact = collect($options)->keyBy('contact_uuid');
                $this->assertSame('shared', $byContact[$shared->contact_uuid]['visibility']);
                $this->assertSame('private', $byContact[$personal->contact_uuid]['visibility']);
            }
        }

        $this->setSession($domain, 'fax_send', $bob);
        $options = (new ContactController())->options(Request::create('/api/contacts/options', 'GET', [
            'channel' => 'fax',
        ]))->getData(true)['options'];
        $this->assertEqualsCanonicalizing([$shared->contact_uuid, $private->contact_uuid], array_column($options, 'contact_uuid'));

        $this->assertNull((new ContactController())->show(Request::create('/'), '+14155550101')->getData(true)['contact']);
        $this->assertNull((new ContactController())->show(Request::create('/'), '+14155550103')->getData(true)['contact']);
        $this->assertSame($shared->contact_uuid, (new ContactController())->show(Request::create('/'), '+14155550100')->getData(true)['contact']['contact_uuid']);
    }

    public function test_saving_a_number_creates_separate_user_contacts_without_overwriting_shared_contacts(): void
    {
        $domain = (string) Str::uuid();
        $alice = (string) Str::uuid();
        $bob = (string) Str::uuid();
        $shared = $this->createContact($domain, 'Shared', 'Clinic');
        $this->createPhone($shared, '+14155550100', 'fax');
        $ids = [];

        foreach ([$alice => 'Alice', $bob => 'Bob'] as $user => $name) {
            $this->setSession($domain, 'fax_send', $user);
            $response = (new ContactController())->store(Request::create('/api/contacts', 'POST', [
                'phone_number' => '(415) 555-0100', 'first_name' => $name,
                // The client cannot assign an owner or choose another account.
                'owner_user_uuid' => $shared->contact_uuid, 'domain_uuid' => (string) Str::uuid(),
            ]));
            $this->assertSame(200, $response->getStatusCode());
            $ids[$user] = $response->getData(true)['contact']['contact_uuid'];
            $this->assertDatabaseHas('contacts', [
                'contact_uuid' => $ids[$user], 'owner_user_uuid' => $user, 'domain_uuid' => $domain,
            ]);
        }

        $this->assertNotSame($ids[$alice], $ids[$bob]);
        $this->assertSame('Shared', $shared->fresh()->first_name);
        $this->assertNull($shared->fresh()->owner_user_uuid);
        $this->assertSame(3, Contact::count());

        // A repeated save updates only the current user's contact, even if owner=NULL is submitted.
        $response = (new ContactController())->store(Request::create('/api/contacts', 'POST', [
            'phone_number' => '+14155550100', 'first_name' => 'Updated Bob', 'owner_user_uuid' => null,
        ]));
        $this->assertSame($ids[$bob], $response->getData(true)['contact']['contact_uuid']);
        $this->assertSame(3, Contact::count());
        $this->assertDatabaseHas('contacts', ['contact_uuid' => $ids[$alice], 'first_name' => 'Alice']);
        $this->assertDatabaseHas('contacts', ['contact_uuid' => $ids[$bob], 'owner_user_uuid' => $bob, 'first_name' => 'Updated Bob']);
    }

    public function test_new_contacts_can_be_shared_with_other_users_in_the_same_account(): void
    {
        $domain = (string) Str::uuid();
        $this->setSession($domain, 'fax_send');

        $response = $this->postJson('/api/contacts', [
            'phone_number' => '(415) 555-0100', 'first_name' => 'Shared Clinic',
            'phone_label' => 'fax', 'is_shared' => true,
            // Sharing still cannot select a different account or arbitrary owner.
            'domain_uuid' => (string) Str::uuid(), 'owner_user_uuid' => (string) Str::uuid(),
        ])->assertOk();
        $uuid = $response->json('contact.contact_uuid');
        $this->assertDatabaseHas('contacts', [
            'contact_uuid' => $uuid, 'domain_uuid' => $domain, 'owner_user_uuid' => null,
        ]);

        $this->setSession($domain, 'fax_send', (string) Str::uuid());
        $this->getJson('/api/contacts/options?channel=fax')->assertOk()
            ->assertJsonCount(1, 'options')->assertJsonPath('options.0.contact_uuid', $uuid)
            ->assertJsonPath('options.0.visibility', 'shared');
        $this->assertSame(['+14155550100' => 'Shared Clinic'], $this->messageDirectory($domain, ['+14155550100']));

        $this->setSession((string) Str::uuid(), 'fax_send', (string) Str::uuid());
        $this->getJson('/api/contacts/options?channel=fax')->assertOk()->assertJsonCount(0, 'options');
    }

    public function test_turning_sharing_off_creates_a_private_contact(): void
    {
        $domain = (string) Str::uuid();
        $this->setSession($domain, 'fax_send');
        $owner = auth()->id();

        $response = $this->postJson('/api/contacts', [
            'phone_number' => '+14155550100', 'first_name' => 'Private Clinic', 'is_shared' => false,
        ])->assertOk();
        $this->assertDatabaseHas('contacts', [
            'contact_uuid' => $response->json('contact.contact_uuid'),
            'domain_uuid' => $domain, 'owner_user_uuid' => $owner,
        ]);
        $this->getJson('/api/contacts/options?channel=fax')->assertOk()
            ->assertJsonPath('options.0.visibility', 'private');

        $this->setSession($domain, 'fax_send', (string) Str::uuid());
        $this->getJson('/api/contacts/options?channel=fax')->assertOk()->assertJsonCount(0, 'options');
    }

    public function test_sharing_a_new_contact_does_not_change_or_copy_existing_contacts_with_the_same_number(): void
    {
        $domain = (string) Str::uuid();
        $alice = (string) Str::uuid();
        $personal = $this->createContact($domain, 'Personal', 'Clinic', null, $alice);
        $shared = $this->createContact($domain, 'Existing shared', 'Clinic');
        $private = $this->createContact($domain, 'Other user', 'Clinic', null, (string) Str::uuid());
        $foreign = $this->createContact((string) Str::uuid(), 'Foreign', 'Clinic');
        foreach ([$personal, $shared, $private, $foreign] as $contact) {
            $this->createPhone($contact, '+14155550100', 'fax');
        }
        $personal->emails()->create(['label' => 'work', 'email_address' => 'private@example.test']);
        $personal->update(['notes' => 'Private notes']);
        $before = DB::table('contacts')->orderBy('contact_uuid')->get();
        $this->setSession($domain, 'fax_send', $alice);

        $response = $this->postJson('/api/contacts', [
            'phone_number' => '(415) 555-0100', 'first_name' => 'New shared clinic',
            'phone_label' => 'fax', 'is_shared' => true,
        ])->assertOk();
        $uuid = $response->json('contact.contact_uuid');
        $this->assertNotContains($uuid, $before->pluck('contact_uuid')->all());
        $this->assertSame(5, Contact::count());
        $this->assertDatabaseHas('contacts', [
            'contact_uuid' => $uuid, 'domain_uuid' => $domain,
            'owner_user_uuid' => null, 'notes' => null, 'organization_uuid' => null,
        ]);
        $this->assertSame(0, Contact::findOrFail($uuid)->emails()->count());
        $this->assertSame('private@example.test', $personal->emails()->sole()->email_address);
        $this->assertEquals($before->toArray(), DB::table('contacts')
            ->whereIn('contact_uuid', $before->pluck('contact_uuid'))->orderBy('contact_uuid')->get()->toArray());
    }

    public function test_invalid_sharing_values_are_rejected_before_creating_a_contact(): void
    {
        $this->setSession((string) Str::uuid(), 'fax_send');

        foreach (['yes', null, ['true']] as $value) {
            $this->postJson('/api/contacts', [
                'phone_number' => '+14155550100', 'first_name' => 'Clinic', 'is_shared' => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors('is_shared');
        }
        $this->assertSame(0, Contact::count());
    }

    public function test_personal_names_take_precedence_over_shared_names_in_fax_and_messages(): void
    {
        $domain = (string) Str::uuid();
        $alice = (string) Str::uuid();
        // Insert the personal contact first so directory precedence cannot depend on insertion order.
        $personal = $this->createContact($domain, 'My', 'Clinic', null, $alice);
        $shared = $this->createContact($domain, 'Account', 'Clinic');
        $private = $this->createContact($domain, 'Hidden', 'Clinic', null, (string) Str::uuid());
        $this->createPhone($personal, '+14155550100', 'work');
        $this->createPhone($shared, '+14155550100', 'fax');
        $this->createPhone($private, '+14155550100', 'fax');
        $this->setSession($domain, 'fax_send', $alice);

        $options = (new ContactController())->options(Request::create('/api/contacts/options', 'GET', [
            'channel' => 'fax',
        ]))->getData(true)['options'];
        $this->assertCount(1, $options);
        $this->assertSame($personal->contact_uuid, $options[0]['contact_uuid']);
        $this->assertSame('My Clinic', $options[0]['name']);
        $this->assertArrayNotHasKey('_ownership_priority', $options[0]);

        $detail = (new ContactController())->show(Request::create('/'), '(415) 555-0100')->getData(true)['contact'];
        $this->assertSame($personal->contact_uuid, $detail['contact_uuid']);
        $this->assertSame(['+14155550100' => 'My Clinic'], $this->messageDirectory($domain, ['+14155550100']));
    }

    public function test_users_can_explicitly_edit_and_delete_shared_contacts_without_changing_ownership(): void
    {
        $domain = (string) Str::uuid();
        $shared = $this->createContact($domain, 'Shared', 'Clinic');
        $this->createPhone($shared, '+14155550100', 'fax');
        $this->setSession($domain, 'messages_view');

        $response = (new ContactController())->store(Request::create('/api/contacts', 'POST', [
            'contact_uuid' => $shared->contact_uuid,
            'phone_number' => '(415) 555-0100', 'first_name' => 'Renamed',
            'email' => 'office@example.test', 'address_street' => '1 Main Street',
            'owner_user_uuid' => auth()->id(), 'is_shared' => false,
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($shared->contact_uuid, $response->getData(true)['contact']['contact_uuid']);
        $this->assertNull($shared->fresh()->owner_user_uuid);
        $this->assertSame(1, Contact::count());
        $this->assertSame(1, $shared->phones()->count());

        $this->setSession($domain, 'fax_send', (string) Str::uuid());
        $detail = (new ContactController())->show(Request::create('/'), '+14155550100')->getData(true)['contact'];
        $this->assertSame('Renamed', $detail['first_name']);
        $this->assertSame('office@example.test', $detail['email']);
        $this->assertSame('1 Main Street', $detail['address_street']);

        $this->assertSame(200, (new ContactController())->destroy($shared->contact_uuid)->getStatusCode());
        $this->assertDatabaseMissing('contacts', ['contact_uuid' => $shared->contact_uuid]);
        $this->assertDatabaseMissing('contact_phones', ['phoneable_id' => $shared->contact_uuid]);
        $this->assertDatabaseMissing('contact_emails', ['emailable_id' => $shared->contact_uuid]);
        $this->assertDatabaseMissing('contact_addresses', ['addressable_id' => $shared->contact_uuid]);
    }

    public function test_an_explicit_edit_or_delete_cannot_target_another_users_or_accounts_contact(): void
    {
        $domain = (string) Str::uuid();
        $alice = (string) Str::uuid();
        $private = $this->createContact($domain, 'Private', 'Clinic', null, (string) Str::uuid());
        $foreign = $this->createContact((string) Str::uuid(), 'Foreign', 'Clinic');
        $foreignOwned = $this->createContact($foreign->domain_uuid, 'Foreign owned', 'Clinic', null, $alice);
        $this->setSession($domain, 'messages_view', $alice);

        foreach ([$private, $foreign, $foreignOwned] as $contact) {
            $this->createPhone($contact, '+14155550100', 'fax');
            foreach (['edit', 'delete'] as $action) {
                try {
                    if ($action === 'edit') {
                        (new ContactController())->store(Request::create('/api/contacts', 'POST', [
                            'contact_uuid' => $contact->contact_uuid, 'phone_number' => '+14155550100',
                            'first_name' => 'Attacker', 'owner_user_uuid' => $alice, 'is_shared' => true,
                        ]));
                    } else {
                        (new ContactController())->destroy($contact->contact_uuid);
                    }
                    $this->fail('An inaccessible contact must be treated as not found.');
                } catch (ModelNotFoundException $exception) {
                    $this->assertSame(Contact::class, $exception->getModel());
                }
            }
            $this->assertSame($contact->first_name, $contact->fresh()->first_name);
            $this->assertSame(1, $contact->phones()->count());
        }
    }

    public function test_a_user_can_edit_and_delete_their_own_contact_without_affecting_another_copy(): void
    {
        $domain = (string) Str::uuid();
        $alice = (string) Str::uuid();
        $own = $this->createContact($domain, 'Alice', 'Clinic', null, $alice);
        $other = $this->createContact($domain, 'Bob', 'Clinic', null, (string) Str::uuid());
        $this->createPhone($own, '+14155550100', 'fax');
        $this->createPhone($other, '+14155550100', 'fax');
        $this->setSession($domain, 'messages_view', $alice);

        $response = (new ContactController())->store(Request::create('/api/contacts', 'POST', [
            'contact_uuid' => $own->contact_uuid, 'phone_number' => '+14155550100',
            'first_name' => 'Updated', 'owner_user_uuid' => null, 'is_shared' => true,
            'email' => 'alice@example.test', 'address_street' => 'Private Address',
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($alice, $own->fresh()->owner_user_uuid);
        $this->assertSame('Updated', $own->fresh()->first_name);
        $this->assertSame(200, (new ContactController())->destroy($own->contact_uuid)->getStatusCode());
        $this->assertDatabaseMissing('contacts', ['contact_uuid' => $own->contact_uuid]);
        $this->assertDatabaseMissing('contact_emails', ['emailable_id' => $own->contact_uuid]);
        $this->assertDatabaseMissing('contact_addresses', ['addressable_id' => $own->contact_uuid]);
        $this->assertSame('Bob', $other->fresh()->first_name);
        $this->assertSame(1, $other->phones()->count());
    }

    public function test_options_carry_the_fields_needed_to_edit_a_row(): void
    {
        $domain = (string) Str::uuid();
        $organizationUuid = (string) Str::uuid();
        DB::table('organizations')->insert([
            'organization_uuid' => $organizationUuid, 'domain_uuid' => $domain, 'name' => 'Northwind Clinic',
        ]);
        $contact = $this->createContact($domain, 'Ada', 'Lovelace', $organizationUuid);
        $this->createPhone($contact, '+14155550100', 'fax');
        $this->setSession($domain, 'fax_send');

        $option = (new ContactController())->options(Request::create('/', 'GET', ['channel' => 'fax']))
            ->getData(true)['options'][0];

        $this->assertSame('Ada', $option['first_name']);
        $this->assertSame('Lovelace', $option['last_name']);
        $this->assertSame($organizationUuid, $option['organization_uuid']);
        $this->assertSame('Northwind Clinic', $option['organization_name']);
        $this->assertSame($contact->phones()->first()->phone_uuid, $option['phone_uuid']);
    }

    public function test_editing_a_row_changes_its_name_company_and_number_only(): void
    {
        $domain = (string) Str::uuid();
        $shared = $this->createContact($domain, 'Shared', 'Clinic');
        $shared->update(['title' => 'Front desk', 'notes' => 'Call first']);
        $this->createPhone($shared, '+14155550100', 'fax');
        $this->createPhone($shared, '+14155550199', 'work');
        $faxPhone = $shared->phones()->where('label', 'fax')->first();
        $this->setSession($domain, 'fax_send');

        $response = (new ContactController())->update(Request::create('/', 'PUT', [
            'phone_uuid' => $faxPhone->phone_uuid, 'phone_number' => '(415) 555-0123',
            'first_name' => 'Renamed', 'last_name' => null, 'organization_name' => 'Northwind Clinic',
            // Without is_shared the sharing stays as it was, and an owner is never taken from input.
            'owner_user_uuid' => auth()->id(),
        ]), $shared->contact_uuid);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Renamed', $response->getData(true)['contact']['name']);
        $this->assertSame('(415) 555-0123', $response->getData(true)['contact']['phone_number_formatted']);

        $shared->refresh();
        $this->assertSame('Renamed', $shared->first_name);
        $this->assertNull($shared->last_name);
        $this->assertNull($shared->owner_user_uuid);
        $this->assertSame('Front desk', $shared->title);
        $this->assertSame('Call first', $shared->notes);
        $this->assertSame('Northwind Clinic', $shared->organization->name);
        $this->assertSame('+14155550123', $faxPhone->fresh()->phone_number);
        $this->assertSame('fax', $faxPhone->fresh()->label);
        $this->assertDatabaseHas('contact_phones', ['phone_number' => '+14155550199', 'label' => 'work']);
        $this->assertSame(2, $shared->phones()->count());
        $this->assertSame(1, Contact::count());
    }

    public function test_editing_a_row_can_share_and_unshare_the_contact(): void
    {
        $domain = (string) Str::uuid();
        $alice = (string) Str::uuid();
        $bob = (string) Str::uuid();
        $contact = $this->createContact($domain, 'Ada', 'Lovelace', null, $alice);
        $this->createPhone($contact, '+14155550100', 'fax');
        $phoneUuid = $contact->phones()->first()->phone_uuid;
        $edit = fn (bool $shared) => (new ContactController())->update(Request::create('/', 'PUT', [
            'phone_uuid' => $phoneUuid, 'phone_number' => '+14155550100', 'first_name' => 'Ada',
            'last_name' => 'Lovelace', 'is_shared' => $shared, 'owner_user_uuid' => $bob,
        ]), $contact->contact_uuid);
        $visibleTo = function (string $user) use ($domain) {
            $this->setSession($domain, 'fax_send', $user);

            return array_column((new ContactController())->options(
                Request::create('/', 'GET', ['channel' => 'fax'])
            )->getData(true)['options'], 'visibility');
        };

        $this->assertSame([], $visibleTo($bob));

        $this->setSession($domain, 'fax_send', $alice);
        $this->assertSame(200, $edit(true)->getStatusCode());
        $this->assertNull($contact->fresh()->owner_user_uuid);
        $this->assertSame(['shared'], $visibleTo($bob));

        // Bob can edit a shared contact, so un-sharing makes it his and hides it from Alice.
        $this->assertSame(200, $edit(false)->getStatusCode());
        $this->assertSame($bob, $contact->fresh()->owner_user_uuid);
        $this->assertSame(['private'], $visibleTo($bob));
        $this->assertSame([], $visibleTo($alice));
        $this->assertSame(1, Contact::count());

        try {
            (new ContactController())->update(Request::create('/', 'PUT', [
                'phone_uuid' => $phoneUuid, 'phone_number' => '+14155550100', 'first_name' => 'Ada',
                'is_shared' => 'everyone',
            ]), $contact->contact_uuid);
            $this->fail('An invalid sharing value must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('is_shared', $exception->errors());
        }
    }

    public function test_editing_a_row_requires_a_name_or_company_from_this_account(): void
    {
        $domain = (string) Str::uuid();
        $contact = $this->createContact($domain, 'Ada', 'Lovelace');
        $this->createPhone($contact, '+14155550100', 'fax');
        $phoneUuid = $contact->phones()->first()->phone_uuid;
        $foreignOrganization = (string) Str::uuid();
        DB::table('organizations')->insert([
            'organization_uuid' => $foreignOrganization, 'domain_uuid' => (string) Str::uuid(), 'name' => 'Elsewhere',
        ]);
        $this->setSession($domain, 'fax_send');

        try {
            (new ContactController())->update(Request::create('/', 'PUT', [
                'phone_uuid' => $phoneUuid, 'phone_number' => '+14155550100', 'first_name' => '',
            ]), $contact->contact_uuid);
            $this->fail('A contact without a name or company must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('first_name', $exception->errors());
        }

        $response = (new ContactController())->update(Request::create('/', 'PUT', [
            'phone_uuid' => $phoneUuid, 'phone_number' => '+14155550100',
            'first_name' => 'Ada', 'organization_uuid' => $foreignOrganization,
        ]), $contact->contact_uuid);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('organization_uuid', $response->getData(true)['errors']);
        $this->assertNull($contact->fresh()->organization_uuid);
        $this->assertSame('Lovelace', $contact->fresh()->last_name);
    }

    public function test_an_edit_cannot_target_another_users_contact_or_another_contacts_number(): void
    {
        $domain = (string) Str::uuid();
        $alice = (string) Str::uuid();
        $own = $this->createContact($domain, 'Alice', 'Clinic', null, $alice);
        $private = $this->createContact($domain, 'Private', 'Clinic', null, (string) Str::uuid());
        $foreign = $this->createContact((string) Str::uuid(), 'Foreign', 'Clinic');
        $this->createPhone($own, '+14155550100', 'fax');
        $this->createPhone($private, '+14155550101', 'fax');
        $this->createPhone($foreign, '+14155550102', 'fax');
        $this->setSession($domain, 'fax_send', $alice);

        $attempts = [
            [$private, $private->phones()->first()->phone_uuid, Contact::class],
            [$foreign, $foreign->phones()->first()->phone_uuid, Contact::class],
            // Their own contact, but a number that belongs to someone else's.
            [$own, $private->phones()->first()->phone_uuid, \App\Models\ContactPhone::class],
        ];

        foreach ($attempts as [$contact, $phoneUuid, $model]) {
            try {
                (new ContactController())->update(Request::create('/', 'PUT', [
                    'phone_uuid' => $phoneUuid, 'phone_number' => '+14155550199', 'first_name' => 'Attacker',
                ]), $contact->contact_uuid);
                $this->fail('An inaccessible contact or number must be treated as not found.');
            } catch (ModelNotFoundException $exception) {
                $this->assertSame($model, $exception->getModel());
            }
        }

        $this->assertSame('Alice', $own->fresh()->first_name);
        $this->assertSame('Private', $private->fresh()->first_name);
        $this->assertSame('Foreign', $foreign->fresh()->first_name);
        $this->assertDatabaseMissing('contact_phones', ['phone_number' => '+14155550199']);
    }

    public function test_messages_directory_excludes_private_and_foreign_contacts_but_keeps_shared_companies(): void
    {
        $domain = (string) Str::uuid();
        $foreignDomain = (string) Str::uuid();
        $shared = $this->createContact($domain, 'Shared', 'Clinic');
        $private = $this->createContact($domain, 'Secret', 'Clinic', null, (string) Str::uuid());
        $foreign = $this->createContact($foreignDomain, 'Foreign', 'Clinic');
        foreach ([$shared, $private, $foreign] as $index => $contact) {
            $this->createPhone($contact, '+1415555010' . $index, 'fax');
        }
        foreach ([$domain => 'Company', $foreignDomain => 'Foreign company'] as $account => $name) {
            $org = Organization::create(['domain_uuid' => $account, 'name' => $name]);
            $org->phones()->create(['phone_number' => '+14155550103', 'label' => 'work']);
        }
        $this->setSession($domain, 'messages_view');

        $directory = $this->messageDirectory($domain, ['+14155550100', '+14155550101', '+14155550102', '+14155550103']);
        $this->assertSame(['+14155550100' => 'Shared Clinic', '+14155550103' => 'Company'], $directory);
        $this->assertNull((new MessageController())->getContact(Request::create('/'), '+14155550101')->getData(true)['contact']);
    }

    public function test_legacy_messages_save_uses_the_same_ownership_rules(): void
    {
        $domain = (string) Str::uuid();
        $shared = $this->createContact($domain, 'Shared', 'Clinic');
        $this->createPhone($shared, '+14155550100', 'fax');
        $this->setSession($domain, 'messages_view');
        $response = (new MessageController())->storeContact(Request::create('/', 'POST', [
            'phone_number' => '+14155550100', 'first_name' => 'Personal',
            'organization' => 'Shared company', 'address' => 'Private Address',
        ]));
        $this->assertSame(200, $response->getStatusCode());
        $uuid = $response->getData(true)['contact']['contact_uuid'];
        $this->assertNotSame($shared->contact_uuid, $uuid);
        $this->assertDatabaseHas('contacts', ['contact_uuid' => $uuid, 'owner_user_uuid' => auth()->id()]);
        $detail = (new MessageController())->getContact(Request::create('/'), '+14155550100')->getData(true)['contact'];
        $this->assertSame('Private Address', $detail['address']);
        $this->assertSame('Shared company', $detail['organization']);
        $this->assertSame('Shared', $shared->fresh()->first_name);
    }

    public function test_migration_preserves_existing_contacts_as_shared(): void
    {
        $migration = require database_path('migrations/2026_10_19_000001_add_contact_ownership.php');
        // Prepare the pre-migration schema in this test's empty temporary database.
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['domain_uuid', 'owner_user_uuid']);
            $table->dropColumn('owner_user_uuid');
        });
        $uuid = (string) Str::uuid();
        DB::table('contacts')->insert(['contact_uuid' => $uuid, 'domain_uuid' => (string) Str::uuid(), 'first_name' => 'Legacy']);
        $migration->up();
        $this->assertDatabaseHas('contacts', ['contact_uuid' => $uuid, 'owner_user_uuid' => null, 'first_name' => 'Legacy']);
    }

    public function test_rerunning_migration_preserves_existing_contacts_and_ownership(): void
    {
        $domain = (string) Str::uuid();
        $this->createContact($domain, 'Shared', 'Clinic');
        $this->createContact($domain, 'Private', 'Clinic', null, (string) Str::uuid());
        $before = DB::table('contacts')->orderBy('contact_uuid')->get()->toArray();
        $columns = Schema::getColumnListing('contacts');
        $indexes = Schema::getIndexes('contacts');
        $migration = require database_path('migrations/2026_10_19_000001_add_contact_ownership.php');

        $migration->up();
        $migration->up();

        $this->assertEquals($before, DB::table('contacts')->orderBy('contact_uuid')->get()->toArray());
        $this->assertSame($columns, Schema::getColumnListing('contacts'));
        $this->assertSame($indexes, Schema::getIndexes('contacts'));
    }

    public function test_migration_adds_a_missing_index_when_the_owner_column_already_exists(): void
    {
        $owner = (string) Str::uuid();
        $contact = $this->createContact((string) Str::uuid(), 'Private', 'Clinic', null, $owner);
        Schema::table('contacts', fn (Blueprint $table) => $table->dropIndex(['domain_uuid', 'owner_user_uuid']));
        $migration = require database_path('migrations/2026_10_19_000001_add_contact_ownership.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasIndex('contacts', ['domain_uuid', 'owner_user_uuid']));
        $this->assertSame($owner, $contact->fresh()->owner_user_uuid);
        $this->assertSame('Private', $contact->fresh()->first_name);
    }

    public function test_migration_reuses_an_equivalent_index_with_a_different_name(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['domain_uuid', 'owner_user_uuid']);
            $table->index(['domain_uuid', 'owner_user_uuid'], 'existing_contact_owner_index');
        });
        $indexes = Schema::getIndexes('contacts');
        $migration = require database_path('migrations/2026_10_19_000001_add_contact_ownership.php');

        $migration->up();

        $this->assertSame($indexes, Schema::getIndexes('contacts'));
        $migration->down();
        $migration->down();
        $this->assertTrue(Schema::hasColumn('contacts', 'owner_user_uuid'));
        $this->assertSame($indexes, Schema::getIndexes('contacts'));
    }

    public function test_migration_rollback_preserves_ownership_and_can_be_reapplied(): void
    {
        $domain = (string) Str::uuid();
        $this->createContact($domain, 'Shared', 'Clinic');
        $this->createContact($domain, 'Private', 'Clinic', null, (string) Str::uuid());
        $before = DB::table('contacts')->orderBy('contact_uuid')->get()->toArray();
        $indexes = Schema::getIndexes('contacts');
        $migration = require database_path('migrations/2026_10_19_000001_add_contact_ownership.php');

        $migration->down();
        $migration->down();

        $this->assertEquals($before, DB::table('contacts')->orderBy('contact_uuid')->get()->toArray());
        $this->assertSame($indexes, Schema::getIndexes('contacts'));

        $migration->up();

        $this->assertEquals($before, DB::table('contacts')->orderBy('contact_uuid')->get()->toArray());
        $this->assertSame($indexes, Schema::getIndexes('contacts'));
    }

    public function test_deleting_a_user_does_not_clear_ownership_or_share_their_contacts(): void
    {
        Schema::create('v_users', fn (Blueprint $table) => $table->uuid('user_uuid')->primary());
        $domain = (string) Str::uuid();
        $owner = (string) Str::uuid();
        DB::table('v_users')->insert(['user_uuid' => $owner]);
        $contact = $this->createContact($domain, 'Private', 'Clinic', null, $owner);
        $this->createPhone($contact, '+14155550100', 'fax');
        DB::table('v_users')->where('user_uuid', $owner)->delete();
        $this->assertSame($owner, $contact->fresh()->owner_user_uuid);

        $this->setSession($domain, 'fax_send');
        $this->assertNull((new ContactController())->show(Request::create('/'), '+14155550100')->getData(true)['contact']);
    }

    public function test_contact_api_routes_enforce_ownership_for_authenticated_requests(): void
    {
        $domain = (string) Str::uuid();
        $private = $this->createContact($domain, 'Other user', 'Clinic', null, (string) Str::uuid());
        $this->createPhone($private, '+14155550100', 'fax');
        $shared = $this->createContact($domain, 'Shared', 'Clinic');
        $this->createPhone($shared, '+14155550101', 'fax');
        $this->setSession($domain, 'fax_send');

        $this->getJson('/api/contacts/options?channel=fax')
            ->assertOk()->assertJsonCount(1, 'options')
            ->assertJsonPath('options.0.contact_uuid', $shared->contact_uuid);
        $this->getJson('/api/contacts/%2B14155550100')->assertOk()->assertJsonPath('contact', null);
        $this->postJson('/api/contacts', [
            'contact_uuid' => $private->contact_uuid, 'phone_number' => '+14155550100', 'first_name' => 'Attacker',
        ])->assertNotFound();
        $this->putJson('/api/contacts/' . $private->contact_uuid, [
            'phone_uuid' => $private->phones()->first()->phone_uuid, 'phone_number' => '+14155550100',
            'first_name' => 'Attacker',
        ])->assertNotFound();
        $this->putJson('/api/contacts/' . $shared->contact_uuid, [
            'phone_uuid' => $shared->phones()->first()->phone_uuid, 'phone_number' => '+14155550101',
            'first_name' => 'Edited',
        ])->assertOk()->assertJsonPath('contact.name', 'Edited');
        $this->deleteJson('/api/contacts/' . $private->contact_uuid)->assertNotFound();

        $response = $this->postJson('/api/contacts', [
            'phone_number' => '+14155550100', 'first_name' => 'Mine', 'owner_user_uuid' => null,
        ])->assertOk();
        $this->assertDatabaseHas('contacts', [
            'contact_uuid' => $response->json('contact.contact_uuid'), 'owner_user_uuid' => auth()->id(),
        ]);
    }

    public function test_unauthenticated_requests_cannot_read_or_create_contacts(): void
    {
        // The SPA API uses the application's existing login redirect for guests.
        $this->getJson('/api/contacts/options?channel=fax')->assertRedirect(route('login'));
        $this->getJson('/api/contacts/%2B14155550100')->assertRedirect(route('login'));
        $this->postJson('/api/contacts', [
            'phone_number' => '+14155550100', 'first_name' => 'Anonymous',
        ])->assertRedirect(route('login'));
        $this->assertSame(0, Contact::count());
    }

    private function messageDirectory(string $domain, array $numbers): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'contactDirectory');
        $method->setAccessible(true);

        return $method->invoke(new MessageController(), $domain, $numbers);
    }

    private function setSession(string $domainUuid, string $permission, ?string $userUuid = null): void
    {
        session()->put('domain_uuid', $domainUuid);
        session()->put('permissions', [(object) ['permission_name' => $permission]]);
        $this->actingAs((new User())->forceFill([
            'user_uuid' => $userUuid ?? auth()->id() ?? (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
        ]));
    }

    private function createContact(
        string $domainUuid,
        string $firstName,
        string $lastName,
        ?string $organizationUuid = null,
        ?string $ownerUserUuid = null
    ): Contact {
        return Contact::create([
            'domain_uuid' => $domainUuid,
            'organization_uuid' => $organizationUuid,
            'owner_user_uuid' => $ownerUserUuid,
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
