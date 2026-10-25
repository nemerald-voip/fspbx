<?php

namespace Tests\Unit;

use App\Services\BridgeService;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class BridgeImportRowsTest extends TestCase
{
    private const GATEWAY = '11111111-1111-4111-8111-111111111111';
    private const OTHER_GATEWAY = '22222222-2222-4222-8222-222222222222';

    private function validate(array $rows, array $gateways = [self::GATEWAY]): array
    {
        return (new BridgeService())->validateImportRows($rows, $gateways);
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'bridge_name' => 'Carrier A',
            'bridge_destination' => 'sofia/gateway/' . self::GATEWAY . '/15555550100',
            'bridge_enabled' => 'true',
            'bridge_description' => 'Main carrier',
        ], $overrides);
    }

    public function test_accepts_valid_rows_and_normalizes_enabled(): void
    {
        $result = $this->validate([
            $this->row(),
            $this->row(['bridge_name' => 'B', 'bridge_enabled' => 'No', 'bridge_description' => '']),
            $this->row(['bridge_name' => 'C', 'bridge_enabled' => '']),
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(['true', 'false', 'true'], array_column($result['rows'], 'bridge_enabled'));
        $this->assertNull($result['rows'][1]['bridge_description']);
    }

    public function test_boolean_cells_keep_their_meaning(): void
    {
        $result = $this->validate([
            $this->row(['bridge_enabled' => false]),
            $this->row(['bridge_enabled' => true]),
        ]);

        $this->assertSame(['false', 'true'], array_column($result['rows'], 'bridge_enabled'));
    }

    public function test_accepts_user_loopback_profile_and_multiple_gateway_legs(): void
    {
        $result = $this->validate([
            $this->row(['bridge_destination' => 'user/1001@example.com']),
            $this->row(['bridge_destination' => 'loopback/18005550100']),
            $this->row(['bridge_destination' => 'sofia/internal/18005550100']),
            $this->row(['bridge_destination' => 'sofia/gateway/' . self::GATEWAY . '/1,sofia/gateway/' . self::GATEWAY . '/2']),
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertCount(4, $result['rows']);
    }

    public function test_reports_missing_required_columns(): void
    {
        $result = $this->validate([['bridge_name' => 'x']]);

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('bridge_destination', $result['errors'][0]);
    }

    public function test_rejects_a_file_without_data_rows(): void
    {
        $this->assertCount(1, $this->validate([])['errors']);
    }

    public function test_rejects_too_many_rows(): void
    {
        $rows = array_fill(0, BridgeService::IMPORT_MAX_ROWS + 1, $this->row());

        $this->assertStringContainsString((string) BridgeService::IMPORT_MAX_ROWS, $this->validate($rows)['errors'][0]);
    }

    public function test_error_messages_use_spreadsheet_line_numbers(): void
    {
        $result = $this->validate([
            $this->row(),
            $this->row(['bridge_name' => '']),
            $this->row(),
            $this->row(['bridge_destination' => 'nonsense']),
        ]);

        $this->assertSame([], $result['rows']);
        $this->assertStringStartsWith('Row 3:', $result['errors'][0]);
        $this->assertStringStartsWith('Row 5:', $result['errors'][1]);
    }

    public function test_rejects_unknown_destination_formats(): void
    {
        foreach (['nonsense', 'sofia/gateway/', 'user/', 'sofia/gateway/' . self::GATEWAY . '/1,nonsense', 'http://example.com'] as $destination) {
            $result = $this->validate([$this->row(['bridge_destination' => $destination])]);

            $this->assertNotSame([], $result['errors'], $destination);
        }
    }

    public function test_rejects_gateways_the_user_cannot_select(): void
    {
        $other = $this->validate([$this->row(['bridge_destination' => 'sofia/gateway/' . self::OTHER_GATEWAY . '/1'])]);
        $notUuid = $this->validate([$this->row(['bridge_destination' => 'sofia/gateway/abc/1'])]);

        $this->assertStringContainsString('not available', $other['errors'][0]);
        $this->assertStringContainsString('not available', $notUuid['errors'][0]);
    }

    public function test_checks_gateways_in_every_leg(): void
    {
        $result = $this->validate([$this->row([
            'bridge_destination' => 'sofia/internal/1,sofia/gateway/' . self::OTHER_GATEWAY . '/1',
        ])]);

        $this->assertStringContainsString('not available', $result['errors'][0]);
    }

    public function test_gateway_comparison_ignores_case(): void
    {
        $result = $this->validate(
            [$this->row(['bridge_destination' => 'sofia/gateway/' . strtoupper(self::GATEWAY) . '/1'])],
            [strtoupper(self::GATEWAY)]
        );

        $this->assertSame([], $result['errors']);
    }

    public function test_variables_are_limited_to_configured_names_and_sip_headers(): void
    {
        Session::put('bridge.variable', ['origination_caller_id_number=']);
        $leg = 'sofia/gateway/' . self::GATEWAY . '/1';

        $configured = $this->validate([$this->row(['bridge_destination' => '{origination_caller_id_number=5551234}' . $leg])]);
        $header = $this->validate([$this->row(['bridge_destination' => '{sip_h_X-Account=42}' . $leg])]);
        $unknown = $this->validate([$this->row(['bridge_destination' => '{execute_on_answer=hangup}' . $leg])]);
        $unclosed = $this->validate([$this->row(['bridge_destination' => '{a=b' . $leg])]);

        $this->assertSame([], $configured['errors']);
        $this->assertSame([], $header['errors']);
        $this->assertStringContainsString('execute_on_answer', $unknown['errors'][0]);
        $this->assertStringContainsString('unclosed', $unclosed['errors'][0]);
    }

    public function test_rejects_xml_hostile_and_control_characters(): void
    {
        foreach (["user/1\n2", 'user/1"><x', 'user/<1>'] as $destination) {
            $this->assertNotSame([], $this->validate([$this->row(['bridge_destination' => $destination])])['errors'], $destination);
        }
    }

    public function test_rejects_bad_enabled_values_and_long_fields(): void
    {
        $badEnabled = $this->validate([$this->row(['bridge_enabled' => 'maybe'])]);
        $longName = $this->validate([$this->row(['bridge_name' => str_repeat('a', 256)])]);
        $longDescription = $this->validate([$this->row(['bridge_description' => str_repeat('a', 256)])]);

        $this->assertStringContainsString('bridge_enabled', $badEnabled['errors'][0]);
        $this->assertNotSame([], $longName['errors']);
        $this->assertNotSame([], $longDescription['errors']);
    }

    public function test_ignores_non_scalar_cells(): void
    {
        $result = $this->validate([$this->row(['bridge_name' => ['x']])]);

        $this->assertNotSame([], $result['errors']);
    }
}
