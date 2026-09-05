<?php

namespace App\Services;

use App\Models\LdapDirectory;
use LDAP\Connection;
use LDAP\Result;
use Closure;

class ActiveDirectoryClient
{
    private ?Connection $connection = null;
    private string $connectedHost = '';

    public function __construct(private readonly LdapDirectory $directory, private readonly ?Closure $checkExecution = null)
    {
    }

    public function test(): array
    {
        $connection = $this->connect();
        $result = @ldap_read($connection, $this->directory->base_dn, '(objectClass=*)', ['distinguishedName']);

        if ($result === false) {
            throw $this->exception('The directory bind succeeded, but the Base DN could not be read');
        }

        return ['host' => $this->connectedHost, 'base_dn' => $this->directory->base_dn];
    }

    public function users(): array
    {
        return $this->search(
            $this->directory->userSearchBase(),
            $this->combinedFilter($this->directory->user_object_filter, $this->directory->user_object_class),
            array_values(array_unique(array_filter([
                $this->directory->unique_identifier_attribute,
                $this->directory->common_name_attribute,
                $this->directory->description_attribute,
                $this->directory->user_name_attribute,
                $this->directory->user_first_name_attribute,
                $this->directory->user_last_name_attribute,
                $this->directory->user_display_name_attribute,
                $this->directory->user_group_attribute,
                $this->directory->user_email_attribute,
                $this->directory->user_title_attribute,
                $this->directory->user_company_attribute,
                $this->directory->user_department_attribute,
                $this->directory->user_home_phone_attribute,
                $this->directory->user_work_phone_attribute,
                $this->directory->user_cell_phone_attribute,
                $this->directory->user_fax_attribute,
                $this->directory->user_extension_attribute,
                'distinguishedName', 'userAccountControl', 'primaryGroupID',
            ])))
        );
    }

    public function groups(): array
    {
        return $this->search(
            $this->directory->groupSearchBase(),
            $this->combinedFilter($this->directory->group_object_filter, $this->directory->group_object_class),
            array_values(array_unique(array_filter([
                $this->directory->unique_identifier_attribute,
                $this->directory->common_name_attribute,
                $this->directory->description_attribute,
                $this->directory->group_members_attribute,
                'distinguishedName', 'primaryGroupToken',
            ])))
        );
    }

    public function authenticate(string $distinguishedName, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        try {
            $connection = $this->connectWithCredentials($distinguishedName, $password);
            @ldap_unbind($connection);
            return true;
        } catch (ActiveDirectoryException) {
            return false;
        }
    }

    public static function first(array $entry, ?string $attribute): ?string
    {
        if (blank($attribute)) {
            return null;
        }

        $value = $entry[strtolower($attribute)] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        return strtolower($attribute) === 'objectguid' ? $value : trim($value);
    }

    public static function values(array $entry, ?string $attribute): array
    {
        if (blank($attribute)) {
            return [];
        }

        $value = $entry[strtolower($attribute)] ?? [];

        return collect(is_array($value) ? $value : [$value])
            ->filter(fn($item) => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn($item) => trim((string) $item))
            ->values()
            ->all();
    }

    public static function externalId(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (strlen($value) !== 16) {
            return strtolower($value);
        }

        $parts = unpack('Vdata1/vdata2/vdata3/H16data4', $value);

        if (! is_array($parts)) {
            return null;
        }

        return sprintf(
            '%08x-%04x-%04x-%s-%s',
            $parts['data1'], $parts['data2'], $parts['data3'],
            substr($parts['data4'], 0, 4), substr($parts['data4'], 4)
        );
    }

    private function connect(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $username = trim($this->directory->bind_username);

        if (! str_contains($username, '@') && ! str_contains($username, '=')) {
            $username .= '@' . $this->directory->ad_domain;
        }

        $connection = $this->connectWithCredentials($username, (string) $this->directory->bind_password);

        $this->connection = $connection;

        return $connection;
    }

    private function connectWithCredentials(string $username, string $password): Connection
    {
        if (! extension_loaded('ldap')) {
            throw new ActiveDirectoryException('The PHP LDAP extension is not installed.');
        }

        $errors = [];

        foreach ($this->hosts() as $host) {
            ($this->checkExecution)?->__invoke();
            $uri = $this->directory->secure_connection === 'ldaps'
                ? "ldaps://{$host}:{$this->directory->port}"
                : "ldap://{$host}:{$this->directory->port}";
            $connection = @ldap_connect($uri);

            if (! $connection instanceof Connection) {
                $errors[] = "{$host}: connection could not be initialized";
                continue;
            }

            ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 5);
            ldap_set_option($connection, LDAP_OPT_TIMEOUT, 30);

            if ($this->directory->secure_connection === 'starttls' && ! @ldap_start_tls($connection)) {
                $errors[] = "{$host}: StartTLS failed";
                @ldap_unbind($connection);
                continue;
            }

            if (! @ldap_bind($connection, $username, $password)) {
                $errors[] = "{$host}: " . trim((string) @ldap_error($connection));
                @ldap_unbind($connection);
                continue;
            }

            $this->connectedHost = $host;

            return $connection;
        }

        throw new ActiveDirectoryException('Unable to bind to any configured host. ' . implode(' ', $errors));
    }

    private function hosts(): array
    {
        return collect(preg_split('/[\s,]+/', trim($this->directory->hosts)) ?: [])
            ->map(fn($host) => trim((string) $host))
            ->filter()->unique()->values()->all();
    }

    private function combinedFilter(string $filter, string $objectClass): string
    {
        return '(&' . trim($filter) . '(objectClass=' . ldap_escape($objectClass, '', LDAP_ESCAPE_FILTER) . '))';
    }

    private function search(string $baseDn, string $filter, array $attributes): array
    {
        $connection = $this->connect();
        $entries = [];
        $cookie = '';

        do {
            ($this->checkExecution)?->__invoke();
            $requestControls = [[
                'oid' => LDAP_CONTROL_PAGEDRESULTS,
                'iscritical' => true,
                'value' => ['size' => 500, 'cookie' => $cookie],
            ]];
            $result = @ldap_search(
                $connection, $baseDn, $filter, $attributes,
                0, 0, 30, LDAP_DEREF_NEVER, $requestControls
            );

            if (! $result instanceof Result) {
                throw $this->exception("Directory search failed under {$baseDn}", $connection);
            }

            $page = ldap_get_entries($connection, $result);

            if (! is_array($page)) {
                throw $this->exception('Directory search results could not be read', $connection);
            }

            for ($index = 0; $index < ($page['count'] ?? 0); $index++) {
                $entries[] = $this->normalizeEntry($page[$index]);
            }

            $responseControls = [];
            $errorCode = null;
            $parsed = @ldap_parse_result($connection, $result, $errorCode, $matchedDn, $errorMessage, $referrals, $responseControls);
            $this->assertCompleteSearchResult($parsed, $errorCode, $connection);
            $cookie = $responseControls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
        } while ($cookie !== '');

        return $entries;
    }

    private function assertCompleteSearchResult(bool $parsed, ?int $errorCode, ?Connection $connection = null): void
    {
        // PHP's LDAP extension returns success as 0, without an LDAP_SUCCESS constant.
        if (! $parsed || $errorCode !== 0) {
            throw $this->exception('The directory returned incomplete search results', $connection);
        }
    }

    private function normalizeEntry(array $entry): array
    {
        $normalized = ['dn' => $entry['dn'] ?? null];

        foreach ($entry as $key => $value) {
            if (is_int($key) || $key === 'count' || $key === 'dn') {
                continue;
            }

            $attribute = strtolower((string) $key);

            if (is_array($value)) {
                unset($value['count']);
                $normalized[$attribute] = array_values($value);
            } else {
                $normalized[$attribute] = $value;
            }
        }

        return $normalized;
    }

    private function exception(string $message, ?Connection $connection = null): ActiveDirectoryException
    {
        $detail = $connection instanceof Connection ? trim((string) @ldap_error($connection)) : '';

        return new ActiveDirectoryException($detail === '' || $detail === 'Success' ? $message : "{$message}: {$detail}");
    }

    public function __destruct()
    {
        if ($this->connection instanceof Connection) {
            @ldap_unbind($this->connection);
        }
    }
}
