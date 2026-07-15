<?php

namespace Tests\Unit;

use App\Services\Provisioning\ProvisioningAuthPolicy;
use PHPUnit\Framework\TestCase;

class ProvisioningAuthPolicyTest extends TestCase
{
    public function test_empty_cidr_list_does_not_restrict_the_request(): void
    {
        $policy = new ProvisioningAuthPolicy();

        $this->assertTrue($policy->clientIpAllowed([], '198.51.100.10'));
    }

    public function test_configured_cidr_must_match_the_client_ip(): void
    {
        $policy = new ProvisioningAuthPolicy();
        $cidrs = ['192.0.2.10/32', '2001:db8:1234::/48'];

        $this->assertTrue($policy->clientIpAllowed($cidrs, '192.0.2.10'));
        $this->assertTrue($policy->clientIpAllowed($cidrs, '2001:db8:1234::25'));
        $this->assertFalse($policy->clientIpAllowed($cidrs, '198.51.100.10'));
    }

    public function test_invalid_configured_cidrs_do_not_grant_access(): void
    {
        $policy = new ProvisioningAuthPolicy();

        $this->assertFalse($policy->clientIpAllowed(['not-a-cidr'], '198.51.100.10'));
    }

    public function test_http_authentication_requires_both_username_and_password(): void
    {
        $policy = new ProvisioningAuthPolicy();

        $this->assertFalse($policy->requiresHttpAuthentication([]));
        $this->assertFalse($policy->requiresHttpAuthentication([
            'http_auth_username' => 'provision',
        ]));
        $this->assertFalse($policy->requiresHttpAuthentication([
            'http_auth_password' => ['secret'],
        ]));
        $this->assertFalse($policy->requiresHttpAuthentication([
            'http_auth_username' => 'provision',
            'http_auth_password' => ['', '   '],
        ]));
        $this->assertTrue($policy->requiresHttpAuthentication([
            'http_auth_username' => 'provision',
            'http_auth_password' => ['', 'secret', 'rotated-secret'],
        ]));
    }

    public function test_array_values_are_trimmed_deduplicated_and_empty_values_removed(): void
    {
        $policy = new ProvisioningAuthPolicy();
        $settings = [
            'cidr' => [' 192.0.2.10/32 ', '', '192.0.2.10/32'],
            'http_auth_password' => [' first ', '', 'second', 'first'],
        ];

        $this->assertSame(['192.0.2.10/32'], $policy->cidrs($settings));
        $this->assertSame(['first', 'second'], $policy->passwords($settings));
    }
}
