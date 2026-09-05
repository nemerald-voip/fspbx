<?php

namespace Tests\Unit;

use App\Models\LdapDirectory;
use App\Services\ActiveDirectoryClient;
use App\Services\ActiveDirectoryException;
use ReflectionMethod;
use Tests\TestCase;

class ActiveDirectoryClientTest extends TestCase
{
    public function test_it_accepts_a_successfully_parsed_search_result(): void
    {
        $client = new ActiveDirectoryClient(new LdapDirectory());
        $method = new ReflectionMethod($client, 'assertCompleteSearchResult');

        $this->assertNull($method->invoke($client, true, 0));
    }

    /** @dataProvider incompleteSearchResults */
    public function test_it_rejects_failed_or_incomplete_search_results(bool $parsed, ?int $errorCode): void
    {
        $client = new ActiveDirectoryClient(new LdapDirectory());
        $method = new ReflectionMethod($client, 'assertCompleteSearchResult');

        $this->expectException(ActiveDirectoryException::class);
        $this->expectExceptionMessage('The directory returned incomplete search results');
        $method->invoke($client, $parsed, $errorCode);
    }

    public static function incompleteSearchResults(): array
    {
        return [
            'server error' => [true, 1],
            'time limit exceeded' => [true, 3],
            'size limit exceeded' => [true, 4],
            'parse failure without a result code' => [false, null],
            'parse failure with a success code' => [false, 0],
            'missing result code' => [true, null],
        ];
    }

    public function test_it_converts_active_directory_binary_object_guid(): void
    {
        $binary = pack('VvvH*', 0x00112233, 0x4455, 0x6677, '8899aabbccddeeff');

        $this->assertSame('00112233-4455-6677-8899-aabbccddeeff', ActiveDirectoryClient::externalId($binary));
    }

    public function test_it_reads_attributes_case_insensitively_from_normalized_entries(): void
    {
        $entry = ['samaccountname' => ['jsmith'], 'memberof' => ['CN=Users,DC=example,DC=com']];

        $this->assertSame('jsmith', ActiveDirectoryClient::first($entry, 'sAMAccountName'));
        $this->assertSame(['CN=Users,DC=example,DC=com'], ActiveDirectoryClient::values($entry, 'memberOf'));
    }

    public function test_relative_search_bases_are_resolved_under_the_base_dn(): void
    {
        $directory = new LdapDirectory([
            'base_dn' => 'DC=example,DC=com',
            'user_dn' => 'OU=People',
            'group_dn' => null,
        ]);

        $this->assertSame('OU=People,DC=example,DC=com', $directory->userSearchBase());
        $this->assertSame('DC=example,DC=com', $directory->groupSearchBase());

        $directory->user_dn = 'OU=People,DC=example,DC=com';
        $this->assertSame('OU=People,DC=example,DC=com', $directory->userSearchBase());
    }
}
