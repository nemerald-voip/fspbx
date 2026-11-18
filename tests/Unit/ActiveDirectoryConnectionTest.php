<?php

namespace Tests\Unit;

use App\Models\LdapDirectory;
use App\Services\ActiveDirectoryClient;
use App\Services\ActiveDirectoryException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ActiveDirectoryConnectionTest extends TestCase
{
    private ?Process $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop(0);
        parent::tearDown();
    }

    /** @dataProvider workingDirectories */
    public function test_connection_and_sync_search_the_same_bases(array $directory, array $bases): void
    {
        $client = $this->client($directory, ['bases' => array_fill_keys($bases, ['entries' => 1])]);

        $this->assertSame('127.0.0.1', $client->test()['host']);
        $this->assertCount(1, $client->users());
        $this->assertCount(1, $client->groups());
        $queries = $this->queries();
        $this->assertSame([$bases[0], $bases[1], $bases[0], $bases[1]], array_column($queries, 'base'));
        $this->assertSame([2, 2, 2, 2], array_column($queries, 'scope'));
        $this->assertSame([1, 1, 0, 0], array_column($queries, 'limit'));
        $this->assertSame(['1.1'], $queries[0]['attributes']);
    }

    public static function workingDirectories(): array
    {
        return [
            'AD defaults' => [['base_dn' => 'dc=example,dc=com'], ['dc=example,dc=com', 'dc=example,dc=com']],
            'AD relative branches' => [['base_dn' => 'dc=example,dc=com', 'user_dn' => 'ou=Users', 'group_dn' => 'ou=Groups'], ['ou=Users,dc=example,dc=com', 'ou=Groups,dc=example,dc=com']],
            'AD full paths' => [['base_dn' => 'dc=example,dc=com', 'user_dn' => 'ou=Users,dc=example,dc=com', 'group_dn' => 'ou=Groups,dc=example,dc=com'], ['ou=Users,dc=example,dc=com', 'ou=Groups,dc=example,dc=com']],
            'Cloudron unavailable common root' => [['base_dn' => 'dc=cloudron', 'user_dn' => 'ou=users', 'group_dn' => 'ou=groups'], ['ou=users,dc=cloudron', 'ou=groups,dc=cloudron']],
            'Cloudron independent paths' => [['base_dn' => '', 'user_dn' => 'ou=users,dc=cloudron', 'group_dn' => 'ou=groups,dc=cloudron'], ['ou=users,dc=cloudron', 'ou=groups,dc=cloudron']],
            'generic LDAP without DC suffixes' => [['base_dn' => '', 'user_dn' => 'ou=People,o=Example,c=US', 'group_dn' => 'ou=Groups,o=Other,c=GB'], ['ou=People,o=Example,c=US', 'ou=Groups,o=Other,c=GB']],
        ];
    }

    /** @dataProvider probeResults */
    public function test_search_probe_result_handling(int $code, int $entries, bool $success): void
    {
        $client = $this->client(['base_dn' => 'dc=example'], ['bases' => ['dc=example' => compact('code', 'entries')]]);
        if (! $success) {
            $this->expectException(ActiveDirectoryException::class);
            $this->expectExceptionMessage('User DN search failed under dc=example');
        }
        $this->assertSame('127.0.0.1', $client->test()['host']);
    }

    public static function probeResults(): array
    {
        return [
            'empty but searchable branch' => [0, 0, true],
            'bounded result' => [4, 1, true],
            'size failure without entries' => [4, 0, false],
            'missing branch' => [32, 0, false],
            'access denied' => [50, 0, false],
            'timeout with partial results' => [3, 1, false],
        ];
    }

    public function test_missing_group_branch_fails_even_when_users_are_accessible(): void
    {
        $client = $this->client(['base_dn' => '', 'user_dn' => 'ou=users,dc=cloudron', 'group_dn' => 'ou=groups,dc=cloudron'], [
            'bases' => ['ou=users,dc=cloudron' => ['entries' => 1]],
        ]);
        $this->expectException(ActiveDirectoryException::class);
        $this->expectExceptionMessage('Group DN search failed under ou=groups,dc=cloudron');
        $client->test();
    }

    public function test_invalid_credentials_still_fail(): void
    {
        $client = $this->client(['base_dn' => 'dc=example'], ['bind_code' => 49]);
        $this->expectException(ActiveDirectoryException::class);
        $this->expectExceptionMessage('Unable to bind to any configured host');
        $client->test();
    }

    public function test_sync_still_rejects_truncated_results(): void
    {
        $client = $this->client(['base_dn' => 'dc=example'], ['bases' => ['dc=example' => ['code' => 4, 'entries' => 1]]]);
        $client->test();
        $this->expectException(ActiveDirectoryException::class);
        $this->expectExceptionMessage('The directory returned incomplete search results');
        $client->users();
    }

    public function test_user_authentication_does_not_require_a_common_base(): void
    {
        $client = $this->client(['base_dn' => ''], []);
        $this->assertTrue($client->authenticate('cn=test,ou=users,dc=cloudron', 'test-password'));
        $this->assertSame([], $this->queries());
    }

    public function test_a_missing_search_path_is_not_sent_as_a_root_search(): void
    {
        $client = $this->client(['base_dn' => ''], []);
        $this->expectException(ActiveDirectoryException::class);
        $this->expectExceptionMessage('User DN is required when Base DN is blank.');
        $client->test();
    }

    private function client(array $attributes, array $configuration): ActiveDirectoryClient
    {
        if (! extension_loaded('ldap')) {
            $this->markTestSkipped('PHP LDAP is required.');
        }
        $this->server = new Process([PHP_BINARY, __DIR__ . '/../Fixtures/ldap/search-server.php', json_encode($configuration)]);
        $this->server->setTimeout(10);
        $this->server->start();
        $this->assertTrue($this->server->waitUntil(fn ($type, $output) => $type === Process::OUT && str_contains($output, "\n")));
        $endpoint = trim(explode("\n", $this->server->getOutput())[0]);
        $directory = new LdapDirectory($attributes + [
            'hosts' => '127.0.0.1', 'port' => (int) substr(strrchr($endpoint, ':'), 1), 'secure_connection' => 'none',
            'bind_username' => 'cn=reader,dc=example', 'bind_password' => 'test-password',
            'user_object_filter' => '(objectClass=*)', 'user_object_class' => 'user',
            'group_object_filter' => '(objectClass=*)', 'group_object_class' => 'group',
        ]);
        return new ActiveDirectoryClient($directory);
    }

    private function queries(): array
    {
        return array_map(fn ($line) => json_decode($line, true), array_slice(explode("\n", trim($this->server->getOutput())), 1));
    }
}
