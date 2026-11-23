<?php

namespace Tests\Unit;

use App\Http\Requests\StoreAssemblyAiConfigRequest;
use App\Services\CallTranscription\TranscriptionProviderRegistry;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\TestCase;

class AssemblyAiLanguageSettingsTest extends TestCase
{
    private Container $previousContainer;
    private $previousFacadeApplication;
    private ValidationFactory $validation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $app = new Container();
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstance(HttpFactory::class);
        $app->instance('config', new Repository(['services' => ['assemblyai' => [
            'api_key' => 'test-key',
            'base_url' => 'https://assemblyai.example',
            'webhook_header_name' => 'X-Test-Webhook',
            'webhook_header_value' => 'test-secret',
        ]]]));
        $app->instance('url', new class {
            public function route($name, $parameters = [], $absolute = true): string
            {
                return 'https://pbx.example/webhook/assemblyai';
            }
        });
        $translator = new Translator(new ArrayLoader(), 'en-us');
        $app->instance('translator', $translator);
        $this->validation = new ValidationFactory($translator, $app);
        $app->instance(HttpFactory::class, new HttpFactory());
        Http::preventStrayRequests();
        Http::fake(['https://assemblyai.example/v2/transcript' => Http::response([
            'id' => 'test-transcript', 'status' => 'queued',
        ])]);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Facade::clearResolvedInstance(HttpFactory::class);
        parent::tearDown();
    }

    /** @dataProvider enabledDetectionValues */
    public function test_rejects_fixed_language_with_automatic_detection($enabled): void
    {
        $validator = $this->validator(['language_code' => 'en_us', 'language_detection' => $enabled]);

        $this->assertFalse($validator->passes());
        $this->assertSame([
            'language_code' => ['Clear Language Code when automatic language detection is enabled.'],
        ], $validator->errors()->toArray());
        Http::assertNothingSent();
    }

    public static function enabledDetectionValues(): array
    {
        return [[true], ['true'], [1], ['1']];
    }

    /** @dataProvider validLanguageSettings */
    public function test_accepts_each_language_mode_independently(array $settings): void
    {
        $validator = $this->validator($settings);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public static function validLanguageSettings(): array
    {
        return [
            'fixed language' => [['language_code' => 'en_us', 'language_detection' => false]],
            'fixed language with string boolean' => [['language_code' => 'fr', 'language_detection' => 'false']],
            'fixed language without detection option' => [['language_code' => 'en_us']],
            'detection with blank code' => [['language_code' => '', 'language_detection' => true]],
            'detection with null code' => [['language_code' => null, 'language_detection' => true]],
            'detection without code' => [['language_detection' => true]],
        ];
    }

    public function test_previously_saved_conflict_sends_only_the_fixed_language(): void
    {
        $payload = $this->transcribe([
            'language_code' => 'en_us',
            'language_detection' => true,
            'language_confidence_threshold' => 0.8,
            'language_detection_options' => ['expected_languages' => ['en', 'fr']],
            'punctuate' => false,
        ]);

        $this->assertSame('en_us', $payload['language_code']);
        $this->assertArrayNotHasKey('language_detection', $payload);
        $this->assertArrayNotHasKey('language_confidence_threshold', $payload);
        $this->assertArrayNotHasKey('language_detection_options', $payload);
        $this->assertFalse($payload['punctuate']);
        $this->assertSame('https://audio.example/call.wav', $payload['audio_url']);
        $this->assertSame('https://pbx.example/webhook/assemblyai', $payload['webhook_url']);
        $this->assertSame('X-Test-Webhook', $payload['webhook_auth_header_name']);
        $this->assertSame('test-secret', $payload['webhook_auth_header_value']);
    }

    public function test_automatic_detection_preserves_its_options(): void
    {
        $options = [
            'language_detection' => true,
            'language_confidence_threshold' => 0.8,
            'language_detection_options' => ['expected_languages' => ['en', 'fr']],
        ];
        $payload = $this->transcribe($options + ['language_code' => null]);

        $this->assertArrayNotHasKey('language_code', $payload);
        foreach ($options as $key => $value) {
            $this->assertSame($value, $payload[$key]);
        }
    }

    public function test_per_call_fixed_language_overrides_saved_detection(): void
    {
        $payload = $this->transcribe([
            'language_detection' => true,
            'language_confidence_threshold' => 0.8,
        ], ['language_code' => 'fr']);

        $this->assertSame('fr', $payload['language_code']);
        $this->assertArrayNotHasKey('language_detection', $payload);
        $this->assertArrayNotHasKey('language_confidence_threshold', $payload);
    }

    public function test_per_call_detection_can_clear_the_saved_fixed_language(): void
    {
        $payload = $this->transcribe([
            'language_code' => 'en_us',
            'language_detection' => false,
        ], ['language_code' => null, 'language_detection' => true]);

        $this->assertArrayNotHasKey('language_code', $payload);
        $this->assertTrue($payload['language_detection']);
    }

    private function validator(array $input): Validator
    {
        $request = StoreAssemblyAiConfigRequest::create('/test', 'POST', $input);
        $request->prepareForValidation();
        $validator = $this->validation->make(
            $request->all(), $request->rules(), $request->messages(), $request->attributes()
        );
        $request->withValidator($validator);

        return $validator;
    }

    private function transcribe(array $options, array $overrides = []): array
    {
        $provider = (new TranscriptionProviderRegistry())->make('assemblyai', ['config' => $options]);
        $this->assertSame(['id' => 'test-transcript', 'status' => 'queued'],
            $provider->transcribe('https://audio.example/call.wav', $overrides));
        Http::assertSentCount(1);
        $payload = Http::recorded()->first()[0]->data();
        $this->assertSame($payload, $provider->payload);

        return $payload;
    }
}
