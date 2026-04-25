<?php

namespace Tests\Feature\Api\V1\CallFlow;

use Tests\TestCase;

/**
 * Route-level middleware smoke tests. End-to-end simulation with a seeded
 * tenant is exercised via the Ansible-deployed staging host after merge;
 * these cover the auth chain so an unauthenticated caller can't reach the
 * controller.
 */
class CallFlowRouteProtectionTest extends TestCase
{
    private const DOMAIN = '00000000-0000-0000-0000-000000000001';

    public function test_tenant_simulate_requires_authentication(): void
    {
        $this->getJson('/api/v1/domains/' . self::DOMAIN . '/call-flow/simulate?phone_number=%2B441225800810')
            ->assertStatus(401);
    }

    public function test_global_simulate_requires_authentication(): void
    {
        $this->getJson('/api/v1/call-flow/simulate?domain_uuid=' . self::DOMAIN . '&phone_number=%2B441225800810')
            ->assertStatus(401);
    }
}
