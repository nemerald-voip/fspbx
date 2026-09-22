<?php

namespace Tests\Unit;

use App\Http\Controllers\DeviceController;
use App\Http\Requests\BulkUpdateDeviceRequest;
use App\Models\Devices;
use App\Services\DeviceActionService;
use App\Services\DeviceService;
use App\Services\FreeswitchEslService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class DeviceBulkAssignmentTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const PROFILE = '22222222-2222-4222-8222-222222222222';
    private const TEMPLATE = '33333333-3333-4333-8333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        session([
            'domain_uuid' => self::DOMAIN,
            'permissions' => [(object) ['permission_name' => 'device_key_template_assign']],
        ]);

        Schema::create('v_devices', function (Blueprint $table) {
            $table->string('device_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('device_profile_uuid')->nullable();
            $table->uuid('device_key_template_uuid')->nullable();
            $table->string('device_description')->nullable();
        });
        Schema::create('v_device_profiles', function (Blueprint $table) {
            $table->uuid('device_profile_uuid')->primary();
        });
        Schema::create('device_key_templates', function (Blueprint $table) {
            $table->uuid('device_key_template_uuid')->primary();
            $table->uuid('domain_uuid');
        });
        DB::table('v_device_profiles')->insert(['device_profile_uuid' => self::PROFILE]);
        DB::table('device_key_templates')->insert([
            'device_key_template_uuid' => self::TEMPLATE,
            'domain_uuid' => self::DOMAIN,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    /** @dataProvider assignmentChanges */
    public function test_bulk_save_persists_assignments_only_on_selected_devices(
        ?string $profile,
        ?string $template,
        array $changes,
        ?string $expectedProfile,
        ?string $expectedTemplate
    ): void {
        // Cross the controller's ten-device chunk boundary.
        $ids = array_map(fn ($number) => 'device-'.$number, range(1, 12));
        foreach ([...$ids, 'unselected'] as $id) {
            $this->device($id, $profile, $template);
        }

        $response = $this->save(['items' => $ids] + $changes);

        $this->assertSame(200, $response->getStatusCode());
        foreach ($ids as $id) {
            $this->assertDatabaseHas('v_devices', [
                'device_uuid' => $id,
                'device_profile_uuid' => $expectedProfile,
                'device_key_template_uuid' => $expectedTemplate,
                'device_description' => $changes['device_description'] ?? 'Original',
            ]);
        }
        $this->assertDatabaseHas('v_devices', [
            'device_uuid' => 'unselected',
            'device_profile_uuid' => $profile,
            'device_key_template_uuid' => $template,
            'device_description' => 'Original',
        ]);
    }

    public static function assignmentChanges(): array
    {
        $profile = self::PROFILE;
        $template = self::TEMPLATE;

        return [
            'profile to template' => [$profile, null, ['device_key_template_uuid' => $template], null, $template],
            'template to profile' => [null, $template, ['device_profile_uuid' => $profile], $profile, null],
            'reselect template clears legacy conflict' => [$profile, $template, ['device_key_template_uuid' => $template], null, $template],
            'reselect profile clears legacy conflict' => [$profile, $template, ['device_profile_uuid' => $profile], $profile, null],
            'unrelated edit preserves profile' => [$profile, null, ['device_description' => 'Updated'], $profile, null],
            'unrelated edit preserves template' => [null, $template, ['device_description' => 'Updated'], null, $template],
            'none profile preserves template' => [$profile, $template, ['device_profile_uuid' => 'NULL'], null, $template],
            'none template preserves profile' => [$profile, $template, ['device_key_template_uuid' => 'NULL'], $profile, null],
            'null profile preserves template' => [$profile, $template, ['device_profile_uuid' => null], null, $template],
            'null template preserves profile' => [$profile, $template, ['device_key_template_uuid' => null], $profile, null],
        ];
    }

    public function test_submitting_both_assignments_is_still_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->request([
            'items' => ['device-1'],
            'device_profile_uuid' => self::PROFILE,
            'device_key_template_uuid' => self::TEMPLATE,
        ]);
    }

    public function test_key_template_assignment_still_requires_permission(): void
    {
        session(['permissions' => []]);
        $this->device('device-1', self::PROFILE, null);

        $response = $this->save([
            'items' => ['device-1'],
            'device_key_template_uuid' => self::TEMPLATE,
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertDatabaseHas('v_devices', [
            'device_uuid' => 'device-1',
            'device_profile_uuid' => self::PROFILE,
            'device_key_template_uuid' => null,
        ]);
    }

    private function device(string $id, ?string $profile, ?string $template): void
    {
        DB::table('v_devices')->insert([
            'device_uuid' => $id,
            'domain_uuid' => self::DOMAIN,
            'device_profile_uuid' => $profile,
            'device_key_template_uuid' => $template,
            'device_description' => 'Original',
        ]);
    }

    private function request(array $data): BulkUpdateDeviceRequest
    {
        $request = BulkUpdateDeviceRequest::create('/api/devices/bulk/update', 'POST', $data);
        $request->prepareForValidation();
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);
        $validator->validate();
        $request->setValidator($validator);

        return $request;
    }

    private function save(array $data): JsonResponse
    {
        // Keep this persistence test isolated from cloud-provisioning observers and ESL.
        return Devices::withoutEvents(fn () => (new DeviceController())->bulkUpdate(
            $this->request($data),
            Mockery::mock(DeviceService::class),
            Mockery::mock(FreeswitchEslService::class),
            Mockery::mock(DeviceActionService::class),
        ));
    }
}
