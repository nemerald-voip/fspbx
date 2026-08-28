<?php

namespace Tests\Unit;

use App\Models\LdapDirectory;
use App\Services\LdapDirectorySyncService;
use PHPUnit\Framework\TestCase;

class LdapDirectorySyncServiceTest extends TestCase
{
    public function test_it_combines_group_side_user_side_and_primary_group_memberships(): void
    {
        $service = new class extends LdapDirectorySyncService
        {
            public function mergeMemberships(array $groupSide, array $userSide, ?string $primary): array
            {
                return $this->mergeGroupMemberships($groupSide, $userSide, $primary);
            }
        };

        $this->assertSame(
            ['scientists', 'italians', 'staff'],
            $service->mergeMemberships(
                ['scientists', 'italians'],
                ['scientists'],
                'staff'
            )
        );
    }

    public function test_group_side_membership_is_kept_when_user_has_no_member_of_attribute(): void
    {
        $service = new class extends LdapDirectorySyncService
        {
            public function mergeMemberships(array $groupSide, array $userSide, ?string $primary): array
            {
                return $this->mergeGroupMemberships($groupSide, $userSide, $primary);
            }
        };

        $this->assertSame(
            ['scientists'],
            $service->mergeMemberships(['scientists'], [], null)
        );
    }

    public function test_it_removes_the_last_name_from_a_full_name_mapped_as_first_name(): void
    {
        $service = $this->testableService();

        $this->assertSame(
            ['Carl Friedrich', 'Gauss'],
            $service->normalizeName('Carl Friedrich Gauss', 'Gauss', 'Carl Friedrich Gauss')
        );
    }

    public function test_it_preserves_a_normal_active_directory_given_name(): void
    {
        $service = $this->testableService();

        $this->assertSame(
            ['Isaac', 'Newton'],
            $service->normalizeName('Isaac', 'Newton', 'Isaac Newton')
        );
    }

    public function test_it_keeps_email_empty_when_the_directory_does_not_provide_one(): void
    {
        $profile = $this->testableService()->profile($this->directory(), [
            'uid' => ['einstein'],
        ]);

        $this->assertNull($profile['email']);
    }

    public function test_it_normalizes_an_email_provided_by_the_directory(): void
    {
        $profile = $this->testableService()->profile($this->directory(), [
            'uid' => ['einstein'],
            'mail' => ['Einstein@Example.COM'],
        ]);

        $this->assertSame('einstein@example.com', $profile['email']);
    }

    public function test_it_ignores_directory_email_when_the_email_attribute_mapping_is_blank(): void
    {
        $directory = $this->directory();
        $directory->user_email_attribute = '';

        $profile = $this->testableService()->profile($directory, [
            'uid' => ['einstein'],
            'mail' => ['einstein@ldap.forumsys.com'],
        ]);

        $this->assertNull($profile['email']);
    }

    private function directory(): LdapDirectory
    {
        return new LdapDirectory([
            'user_name_attribute' => 'uid',
            'user_email_attribute' => 'mail',
            'user_first_name_attribute' => 'givenName',
            'user_last_name_attribute' => 'sn',
            'user_display_name_attribute' => 'displayName',
        ]);
    }

    private function testableService(): LdapDirectorySyncService
    {
        return new class extends LdapDirectorySyncService
        {
            public function profile(LdapDirectory $directory, array $entry): array
            {
                return $this->userProfile($directory, $entry);
            }

            public function normalizeName(?string $first, ?string $last, ?string $display): array
            {
                return $this->normalizePersonName($first, $last, $display);
            }
        };
    }
}
