<?php

namespace Tests\Unit;

use App\Models\LdapDirectory;
use App\Services\ActiveDirectoryClient;
use Tests\TestCase;

class ActiveDirectoryClientTest extends TestCase
{
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
