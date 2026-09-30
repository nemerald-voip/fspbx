<?php

namespace Tests\Unit;

use App\Http\Controllers\BusinessHoursController;
use App\Http\Controllers\VirtualReceptionistController;
use App\Models\BasicDialerCampaign;
use App\Services\BasicDialerService;
use App\Services\BasicQueueService;
use App\Services\CallFlowService;
use App\Services\CallRoutingOptionsService;
use App\Services\DynamicRouteService;
use App\Services\RingGroupService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedRoutingDestinationTest extends TestCase
{
    /** @dataProvider destinations */
    public function test_consumers_use_the_same_action_for_every_selectable_destination(
        string $type, ?string $model, ?string $field, ?string $value, string $application, string $data
    ): void {
        session(['domain_name' => 'example.test', 'domain_uuid' => RoutingDestinations::DOMAIN]);
        $expected = ['action' => $application, 'data' => $data];

        $phone = buildDestinationAction(['type' => $type, 'extension' => $value], 'example.test');
        $this->assertSame($application, $phone['destination_app']);
        $this->assertSame($data, $phone['destination_data']);
        $savedTarget = (new CallRoutingOptionsService)->actionForTarget($type, RoutingDestinations::target($model, $field, $value));
        $this->assertSame($phone, $savedTarget);

        foreach ([\App\Http\Requests\Api\V1\StorePhoneNumberRequest::class, \App\Http\Requests\Api\V1\UpdatePhoneNumberRequest::class] as $requestClass) {
            $payload = ['routing_options' => [['type' => $type, 'extension' => $value]]];
            $request = new $requestClass($payload);
            $rules = array_intersect_key($request->rules(), array_flip(['routing_options.*.type', 'routing_options.*.extension']));
            $validator = \Illuminate\Support\Facades\Validator::make($payload, $rules);
            $this->assertTrue($validator->passes(), $validator->errors()->toJson());
        }
        foreach ([\App\Http\Requests\Api\V1\StoreRingGroupRequest::class, \App\Http\Requests\Api\V1\UpdateRingGroupRequest::class] as $requestClass) {
            $payload = ['timeout_action' => $type, 'timeout_target' => $value];
            $request = new $requestClass($payload);
            $rules = array_intersect_key($request->rules(), array_flip(['timeout_action', 'timeout_target']));
            $validator = \Illuminate\Support\Facades\Validator::make($payload, $rules);
            $this->assertTrue($validator->passes(), $validator->errors()->toJson());
        }

        $ringGroup = (new RingGroupService)->buildUpdateData(['timeout_action' => $type, 'timeout_target' => $value], 'example.test');
        $this->assertSame($application, $ringGroup['ring_group_timeout_app']);
        $this->assertSame($data, $ringGroup['ring_group_timeout_data']);

        $callFlow = (new CallFlowService)->buildSaveData(['call_flow_action' => $type, 'call_flow_target' => $value], null, 'example.test');
        $this->assertSame($application, $callFlow['call_flow_app']);
        $this->assertSame($type === 'hangup' ? 'NORMAL_CLEARING' : $data, $callFlow['call_flow_data']);

        $this->assertSame($expected, $this->invoke(BusinessHoursController::class, 'buildExitDestinationAction', [['failback_action' => $type, 'failback_target' => $value]]));
        $this->assertSame($expected, $this->invoke(VirtualReceptionistController::class, 'buildExitDestinationAction', [['exit_action' => $type, 'exit_target' => $value]]));
        $this->assertSame(trim($application.' '.$data), $this->invoke(VirtualReceptionistController::class, 'buildKeyDestinationAction', [['action' => $type, 'extension' => $value, 'target' => $value]]));
        $this->assertSame($application.':'.$data, $this->invoke(BasicQueueService::class, 'buildQueueTimeoutAction', [['timeout_action' => $type, 'timeout_target' => $value], 'example.test']));

        $campaign = new BasicDialerCampaign;
        $campaign->forceFill(['destination_type' => $type, 'destination_target' => $value]);
        $this->assertSame($application === 'transfer' ? $data : '&'.$application.'('.$data.')', $this->invoke(BasicDialerService::class, 'answeredApplication', [$campaign, 'example.test']));

        if ($type !== 'dynamic_routes') {
            $this->assertSame(['application' => $application, 'data' => $data], $this->invoke(DynamicRouteService::class, 'destinationAction', [$type, $value, 'example.test']));
        }

        // The optional module must not be needed by any main-repo consumer.
        $moduleRequest = 'Modules\\ContactCenter\\Http\\Requests\\UpdateSettingsRequest';
        if (class_exists($moduleRequest)) {
            $request = new $moduleRequest(['timeout_action' => $type, 'timeout_target' => $value]);
            $this->assertSame($application.':'.$data, (new \ReflectionMethod($request, 'normalizeVueTimeoutAction'))->invoke($request));
        }
    }

    public static function destinations(): array
    {
        $cases = [];
        foreach (RoutingDestinations::cases() as $type => $case) $cases[$type] = [$type, ...$case];

        return $cases;
    }

    /** @dataProvider invalidTargets */
    public function test_invalid_saved_targets_are_rejected(string $problem): void
    {
        $target = RoutingDestinations::target(\App\Models\DynamicRoute::class, 'extension', '9504');
        $type = 'dynamic_routes';
        if ($problem === 'missing') $target = null;
        if ($problem === 'blank') $target->extension = '';
        if ($problem === 'account') $target->domain_uuid = RoutingDestinations::TARGET;
        if ($problem === 'type') $type = 'ai_agents';
        if ($problem === 'unsupported') $type = 'unknown';

        $this->expectException(ValidationException::class);
        (new CallRoutingOptionsService(RoutingDestinations::DOMAIN))->actionForTarget($type, $target, 'example.test');
    }

    public static function invalidTargets(): array
    {
        return [['missing'], ['blank'], ['account'], ['type'], ['unsupported']];
    }

    public function test_dynamic_routes_keep_their_no_chaining_restriction(): void
    {
        $this->assertEqualsCanonicalizing(array_diff(CallRoutingOptionsService::destinationTypes(), ['dynamic_routes']), DynamicRouteService::destinationTypes());
        $this->expectException(ValidationException::class);
        $this->invoke(DynamicRouteService::class, 'destinationAction', ['dynamic_routes', '9504', 'example.test']);
    }

    public function test_forwarding_preserves_external_numbers_and_voicemail_prefixes(): void
    {
        $this->assertSame('+15555550100', CallRoutingOptionsService::forwardingTarget('external', '100', '+15555550100'));
        $this->assertSame('*99100', CallRoutingOptionsService::forwardingTarget('voicemails', '100'));
        $this->assertSame('9504', CallRoutingOptionsService::forwardingTarget('dynamic_routes', '9504'));
        $this->assertNull(CallRoutingOptionsService::forwardingTarget('recordings', 'greeting.wav'));
    }

    public function test_missing_saved_target_fails_before_the_dialplan_is_written(): void
    {
        session(['domain_name' => 'example.test', 'domain_uuid' => RoutingDestinations::DOMAIN]);
        $businessHour = new \App\Models\BusinessHour([
            'domain_uuid' => RoutingDestinations::DOMAIN,
            'dialplan_uuid' => RoutingDestinations::TARGET,
            'name' => 'Check Holidays', 'extension' => '9200', 'timezone' => 'America/Los_Angeles',
            'after_hours_action' => 'dynamic_routes',
        ]);
        $businessHour->setRelation('periods', collect());
        $businessHour->setRelation('holidays', collect());
        $businessHour->setRelation('after_hours_target', null);

        $compiler = app('blade.compiler');
        $pathProperty = new \ReflectionProperty(\Illuminate\View\Compilers\Compiler::class, 'cachePath');
        $originalPath = $pathProperty->getValue($compiler);
        $compiledPath = sys_get_temp_dir().'/fspbx-invalid-routing-test-'.getmypid();
        \Illuminate\Support\Facades\File::ensureDirectoryExists($compiledPath);
        $pathProperty->setValue($compiler, $compiledPath);
        try {
            $this->expectException(ValidationException::class);
            $this->invoke(BusinessHoursController::class, 'generateDialPlanXML', [$businessHour]);
        } finally {
            $pathProperty->setValue($compiler, $originalPath);
            \Illuminate\Support\Facades\File::deleteDirectory($compiledPath);
        }
    }

    private function invoke(string $class, string $method, array $args)
    {
        $object = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

        return (new \ReflectionMethod($class, $method))->invokeArgs($object, $args);
    }
}
