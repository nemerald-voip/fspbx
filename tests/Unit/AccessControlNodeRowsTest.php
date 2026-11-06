<?php

namespace Tests\Unit;

use App\Services\AccessControlService;
use Tests\TestCase;

class AccessControlNodeRowsTest extends TestCase
{
    private function parse(array $rows): array
    {
        return (new AccessControlService())->parseNodeRows($rows);
    }

    public function test_accepts_valid_rows_and_normalizes_addresses(): void
    {
        $result = $this->parse([
            ['node_type' => 'allow', 'node_cidr' => '203.0.113.10', 'node_description' => 'Office'],
            ['node_type' => 'DENY', 'node_cidr' => ' 198.51.100.0/24 ', 'node_description' => ''],
            ['node_type' => '', 'node_cidr' => '2001:db8::/32', 'node_description' => null],
            ['node_type' => 'allow', 'node_cidr' => '2001:db8::1', 'node_description' => null],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame([
            ['node_type' => 'allow', 'node_cidr' => '203.0.113.10/32', 'node_description' => 'Office'],
            ['node_type' => 'deny', 'node_cidr' => '198.51.100.0/24', 'node_description' => null],
            ['node_type' => 'allow', 'node_cidr' => '2001:db8::/32', 'node_description' => null],
            ['node_type' => 'allow', 'node_cidr' => '2001:db8::1', 'node_description' => null],
        ], $result['rows']);
    }

    public function test_node_type_column_is_optional(): void
    {
        $result = $this->parse([['node_cidr' => '192.0.2.1']]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('allow', $result['rows'][0]['node_type']);
    }

    public function test_requires_the_cidr_column(): void
    {
        $result = $this->parse([['node_type' => 'allow']]);

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('node_cidr', $result['errors'][0]);
    }

    public function test_rejects_a_file_without_data_rows(): void
    {
        $this->assertCount(1, $this->parse([])['errors']);
    }

    public function test_rejects_too_many_rows(): void
    {
        $rows = array_fill(0, AccessControlService::NODE_IMPORT_MAX_ROWS + 1, ['node_cidr' => '192.0.2.1']);

        $this->assertStringContainsString((string) AccessControlService::NODE_IMPORT_MAX_ROWS, $this->parse($rows)['errors'][0]);
    }

    public function test_reports_invalid_addresses_with_spreadsheet_line_numbers(): void
    {
        $result = $this->parse([
            ['node_cidr' => '192.0.2.1'],
            ['node_cidr' => '999.1.1.1'],
            ['node_cidr' => '192.0.2.0/33'],
            ['node_cidr' => ''],
            ['node_cidr' => 'example.com'],
        ]);

        $this->assertSame([], $result['rows']);
        $this->assertCount(4, $result['errors']);
        $this->assertStringStartsWith('Row 3:', $result['errors'][0]);
        $this->assertStringContainsString('999.1.1.1', $result['errors'][0]);
        $this->assertStringStartsWith('Row 4:', $result['errors'][1]);
        $this->assertStringStartsWith('Row 5:', $result['errors'][2]);
        $this->assertStringStartsWith('Row 6:', $result['errors'][3]);
    }

    public function test_rejects_unknown_node_types(): void
    {
        $result = $this->parse([['node_type' => 'block', 'node_cidr' => '192.0.2.1']]);

        $this->assertStringContainsString('allow or deny', $result['errors'][0]);
    }

    public function test_rejects_bad_descriptions(): void
    {
        foreach ([str_repeat('a', 256), "line\nbreak", 'a"b', '<x>'] as $description) {
            $result = $this->parse([['node_cidr' => '192.0.2.1', 'node_description' => $description]]);

            $this->assertNotSame([], $result['errors'], $description);
        }
    }

    public function test_drops_exact_duplicates_but_keeps_same_address_with_other_action(): void
    {
        $result = $this->parse([
            ['node_type' => 'allow', 'node_cidr' => '192.0.2.1'],
            ['node_type' => 'allow', 'node_cidr' => '192.0.2.1/32'],
            ['node_type' => 'deny', 'node_cidr' => '192.0.2.1'],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(['allow', 'deny'], array_column($result['rows'], 'node_type'));
    }

    public function test_boolean_and_array_cells_are_treated_as_empty(): void
    {
        $result = $this->parse([['node_type' => true, 'node_cidr' => '192.0.2.1', 'node_description' => ['x']]]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('allow', $result['rows'][0]['node_type']);
        $this->assertNull($result['rows'][0]['node_description']);
    }
}
