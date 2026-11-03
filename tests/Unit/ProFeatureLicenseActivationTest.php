<?php

namespace Tests\Unit;

use App\Models\ProFeatures;
use App\Services\KeygenAPIService;
use App\Services\ProFeaturesService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProFeatureLicenseActivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application(sys_get_temp_dir());
        $app->instance('translator', new Translator(new ArrayLoader(), 'en-us'));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        Cache::swap(Mockery::mock());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);
        parent::tearDown();
    }

    private function response(string $code = 'FINGERPRINT_SCOPE_MISMATCH', int $count = 1, ?int $limit = 2): array
    {
        return [
            'meta' => ['valid' => $code === 'VALID', 'code' => $code],
            'data' => [
                'id' => 'test-license-id',
                'attributes' => ['maxMachines' => $limit],
                'relationships' => ['machines' => ['meta' => ['count' => $count]]],
            ],
        ];
    }

    private function model(bool $saved): ProFeatures
    {
        $model = Mockery::mock(ProFeatures::class);
        if ($saved) {
            $model->shouldReceive('update')->once()->with(['license' => 'test-key'])->andReturnTrue();
            foreach (['stir_shaken_license_validation_test-key', 'license_validation_test-key', 'pro_feature:fspbx:license'] as $key) {
                Cache::shouldReceive('forget')->once()->with($key);
            }
        } else {
            $model->shouldNotReceive('update');
        }
        return $model;
    }

    public function test_full_machine_allocation_is_reported_without_attempting_activation(): void
    {
        $api = Mockery::mock(KeygenAPIService::class);
        $api->shouldReceive('validateLicenseKey')->once()->with('test-key')->andReturn($this->response(count: 2));
        $api->shouldNotReceive('activateMachine');

        $result = (new ProFeaturesService($api))->updateLicense($this->model(false), ['license' => 'test-key']);

        $this->assertSame(['Max machine limit reached'], $result['errors']);
        $this->assertSame([], $result['updated']);
    }

    public function test_rejected_activation_is_not_reported_as_success(): void
    {
        $api = Mockery::mock(KeygenAPIService::class);
        $api->shouldReceive('validateLicenseKey')->once()->andReturn($this->response());
        $api->shouldReceive('activateMachine')->once()->with('test-key', 'test-license-id')->andReturnFalse();
        $api->shouldNotReceive('isLicenseValid');

        $result = (new ProFeaturesService($api))->updateLicense($this->model(false), ['license' => 'test-key']);

        $this->assertSame(['The license key has not been activated on this machine'], $result['errors']);
        $this->assertSame([], $result['updated']);
    }

    public function test_activation_must_validate_on_this_machine_before_reporting_success(): void
    {
        $api = Mockery::mock(KeygenAPIService::class);
        $api->shouldReceive('validateLicenseKey')->once()->andReturn($this->response());
        $api->shouldReceive('activateMachine')->once()->andReturn(['data' => ['id' => 'test-machine']]);
        $api->shouldReceive('isLicenseValid')->once()->with('test-key')->andReturnFalse();

        $result = (new ProFeaturesService($api))->updateLicense($this->model(false), ['license' => 'test-key']);

        $this->assertNotEmpty($result['errors']);
        $this->assertSame([], $result['updated']);
    }

    /** @dataProvider activationCodes */
    public function test_unactivated_states_activate_and_clear_cached_validation(string $code): void
    {
        $api = Mockery::mock(KeygenAPIService::class);
        $api->shouldReceive('validateLicenseKey')->once()->andReturn($this->response($code));
        $api->shouldReceive('activateMachine')->once()->with('test-key', 'test-license-id')->andReturn(['data' => ['id' => 'test-machine']]);
        $api->shouldReceive('isLicenseValid')->once()->with('test-key')->andReturnTrue();

        $result = (new ProFeaturesService($api))->updateLicense($this->model(true), ['license' => 'test-key']);

        $this->assertSame([], $result['errors']);
        $this->assertSame(['License updated'], $result['updated']);
    }

    public static function activationCodes(): array
    {
        return [['NO_MACHINE'], ['NO_MACHINES'], ['FINGERPRINT_SCOPE_MISMATCH']];
    }

    public function test_unlimited_license_is_not_treated_as_a_single_machine_license(): void
    {
        $api = Mockery::mock(KeygenAPIService::class);
        $api->shouldReceive('validateLicenseKey')->once()->andReturn($this->response(count: 3, limit: null));
        $api->shouldReceive('activateMachine')->once()->andReturn(['data' => ['id' => 'test-machine']]);
        $api->shouldReceive('isLicenseValid')->once()->andReturnTrue();

        $result = (new ProFeaturesService($api))->updateLicense($this->model(true), ['license' => 'test-key']);

        $this->assertSame([], $result['errors']);
    }

    public function test_already_valid_license_does_not_activate_again(): void
    {
        $api = Mockery::mock(KeygenAPIService::class);
        $api->shouldReceive('validateLicenseKey')->once()->andReturn($this->response('VALID', 2));
        $api->shouldNotReceive('activateMachine');

        $result = (new ProFeaturesService($api))->updateLicense($this->model(true), ['license' => 'test-key']);

        $this->assertSame([], $result['errors']);
    }
}
