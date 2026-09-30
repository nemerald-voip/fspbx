<?php

namespace Tests\Unit;

use App\Exceptions\Handler;
use App\Http\Controllers\Auth\EmailChallengeController;
use App\Http\Controllers\CsrfTokenController;
use App\Http\Middleware\CheckUserEnabled;
use App\Http\Middleware\SetApplicationLocale;
use App\Jobs\EmailLoginChallengeCode;
use App\Models\User;
use App\Services\LdapUserAuthenticator;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\Compiler;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Http\Requests\LoginRequest;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Http\Responses\FailedPasswordResetResponse;
use Laravel\Fortify\Http\Responses\LockoutResponse;
use Laravel\Fortify\Http\Responses\PasswordResetResponse;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\LoginRateLimiter;
use Mockery;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class AuthenticationLocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'auth_localization_test',
            'database.connections.auth_localization_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'cache.default' => 'array',
            'session.driver' => 'array',
            'app.debug' => false,
        ]);
        DB::purge('auth_localization_test');
        app()->instance(CacheRateLimiter::class, new CacheRateLimiter(app('cache')->store('array')));
        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_subcategory');
            $table->string('default_setting_value');
            $table->string('default_setting_enabled')->default('true');
        });
        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_uuid')->nullable();
            $table->string('domain_setting_subcategory');
            $table->string('domain_setting_value');
            $table->string('domain_setting_enabled')->default('true');
        });

        $compiledPath = sys_get_temp_dir().'/fspbx-auth-localization-views';
        File::ensureDirectoryExists($compiledPath);
        config(['view.compiled' => $compiledPath]);
        (new ReflectionProperty(Compiler::class, 'cachePath'))->setValue(app('blade.compiler'), $compiledPath);
        app('session')->start();
        Bus::fake();
        Mail::fake();
        Event::fake();
        Log::spy();
        Http::preventStrayRequests();
        $this->mock(UncompromisedVerifier::class)->shouldReceive('verify')->andReturn(true)->byDefault();
    }

    protected function tearDown(): void
    {
        DB::disconnect('auth_localization_test');
        parent::tearDown();
    }

    public static function locales(): array
    {
        return array_map(fn ($locale) => [$locale], ['en-us', 'ru', 'fr', 'es-419', 'pt-br']);
    }

    /** @dataProvider locales */
    public function test_framework_validation_and_password_policy_messages(string $locale): void
    {
        app()->setLocale($locale);
        $messages = $this->groupMessages($locale, 'validation');

        $login = Validator::make([], (new LoginRequest)->rules());
        $this->assertSame(
            str_replace(':attribute', $messages['attributes']['user_email'], $messages['required']),
            $login->errors()->first('user_email')
        );
        $this->assertSame(
            str_replace(':attribute', $messages['attributes']['password'], $messages['required']),
            $login->errors()->first('password')
        );

        $cases = [
            [['user_email' => 'invalid'], ['user_email' => 'email'], 'user_email', 'email', []],
            [['password' => []], ['password' => 'string'], 'password', 'string', []],
            [['password' => 'a', 'password_confirmation' => 'b'], ['password' => 'confirmed'], 'password', 'confirmed', []],
            [['password' => 'Ab1!'], ['password' => [Password::defaults()]], 'password', 'min.string', [':min' => '10']],
            [['password' => '123456789!'], ['password' => [Password::defaults()]], 'password', 'password.letters', []],
            [['password' => 'abcdefgh1!'], ['password' => [Password::defaults()]], 'password', 'password.mixed', []],
            [['password' => 'Abcdefghi!'], ['password' => [Password::defaults()]], 'password', 'password.numbers', []],
            [['password' => 'Abcdefghi1'], ['password' => [Password::defaults()]], 'password', 'password.symbols', []],
            [['code' => '1234567'], ['code' => 'max:6'], 'code', 'max.string', [':max' => '6']],
        ];
        foreach ($cases as [$input, $rules, $attribute, $key, $replacements]) {
            $validator = Validator::make($input, $rules);
            $this->assertTrue($validator->fails());
            $expected = \Illuminate\Support\Arr::get($messages, $key);
            $this->assertNotNull($expected, "$locale: $key");
            $expected = strtr($expected, [':attribute' => $messages['attributes'][$attribute]] + $replacements);
            $this->assertContains($expected, $validator->errors()->get($attribute), "$locale: $key");
        }

        $this->mock(UncompromisedVerifier::class)->shouldReceive('verify')->once()->andReturn(false);
        $validator = Validator::make(['password' => 'ValidPassword123!'], ['password' => [Password::defaults()]]);
        $this->assertSame($messages['password.uncompromised'], $validator->errors()->first('password'));
    }

    /** @dataProvider locales */
    public function test_fortify_failure_and_password_reset_responses(string $locale): void
    {
        app()->setLocale($locale);
        $auth = $this->groupMessages($locale, 'auth');
        $passwords = $this->groupMessages($locale, 'passwords');
        $request = $this->request(['user_email' => 'test@example.test', 'password' => 'wrong']);
        $this->mock(LdapUserAuthenticator::class)->shouldReceive('authenticate')->once()->andReturn(null);
        $this->assertValidationMessage(
            fn () => app(AttemptToAuthenticate::class)->handle($request, fn () => $this->fail('Unexpected login')),
            'user_email', $auth['failed']
        );

        $limiter = Mockery::mock(LoginRateLimiter::class);
        $limiter->shouldReceive('availableIn')->once()->andReturn(30);
        $this->assertValidationMessage(
            fn () => (new LockoutResponse($limiter))->toResponse($request),
            'user_email', str_replace(':seconds', '30', $auth['throttle']), 429
        );
        foreach (['token', 'user', 'throttled'] as $key) {
            $response = $key === 'token'
                ? new FailedPasswordResetResponse("passwords.$key")
                : new FailedPasswordResetLinkRequestResponse("passwords.$key");
            $this->assertValidationMessage(fn () => $response->toResponse($request), 'email', $passwords[$key]);
        }
        foreach (['sent' => SuccessfulPasswordResetLinkRequestResponse::class, 'reset' => PasswordResetResponse::class] as $key => $class) {
            $response = (new $class("passwords.$key"))->toResponse($request);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($passwords[$key], $response->getData(true)['message']);
        }
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
    }

    /** @dataProvider locales */
    public function test_email_challenge_errors_and_resend(string $locale): void
    {
        app()->setLocale($locale);
        $catalog = json_decode(file_get_contents(lang_path("$locale.json")), true);
        session(['code' => '123456', 'code_expiration' => now()->addMinutes(10)]);
        $controller = new EmailChallengeController;
        $this->assertValidationMessage(
            fn () => $controller->store($this->challengeRequest('654321')),
            'code', $catalog['Supplied authentication code is invalid.']
        );
        session(['code_expiration' => now()->subMinute()]);
        $this->assertValidationMessage(
            fn () => $controller->store($this->challengeRequest('123456')),
            'code', $catalog['The code has expired.']
        );

        $user = new User;
        $user->user_email = 'test@example.test';
        $user->domain_uuid = 'test-domain';
        $user->setRelation('user_adv_fields', null);
        $request = Mockery::mock(TwoFactorLoginRequest::class)->makePartial();
        $request->shouldReceive('hasChallengedUser')->once()->andReturn(true);
        $request->shouldReceive('challengedUser')->once()->andReturn($user);
        Inertia::flushShared();
        $response = $controller->update($request)->toResponse($this->request([], ['X-Inertia' => 'true']));
        $this->assertSame($catalog['Verification code has been resent'], $response->getData(true)['props']['status']);
        $this->assertTrue(session('code_expiration')->isFuture());
        Bus::assertDispatched(EmailLoginChallengeCode::class);
        Mail::assertNothingSent();
    }

    /** @dataProvider locales */
    public function test_early_web_errors_keep_status_headers_and_resolve_guest_language(string $locale): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_subcategory' => 'language', 'default_setting_value' => $locale,
        ]);
        $request = $this->request([], ['Accept' => 'text/html']);
        $catalog = json_decode(file_get_contents(lang_path("$locale.json")), true);
        foreach ([419 => new TokenMismatchException, 429 => new TooManyRequestsHttpException(30)] as $status => $exception) {
            app()->setLocale('en-us');
            $response = app(Handler::class)->render($request, $exception);
            $this->assertSame($status, $response->getStatusCode());
            $key = $status === 419 ? 'Page Expired' : 'Too Many Requests';
            $this->assertStringContainsString(e($catalog[$key]), $response->getContent());
            $this->assertSame($locale, app()->getLocale());
            if ($status === 429) {
                $this->assertSame('30', $response->headers->get('Retry-After'));
            }
        }
    }

    public function test_locale_selection_and_fallback_remain_unchanged(): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_subcategory' => 'language', 'default_setting_value' => 'fr',
        ]);
        $request = $this->request();
        $middleware = new SetApplicationLocale;
        $resolve = fn () => $middleware->handle($request, fn () => app()->getLocale());
        $this->assertSame('fr', $resolve());
        session(['domain.language.code' => 'ru']);
        $this->assertSame('ru', $resolve());
        session(['domain.language.code' => 'unsupported']);
        $this->assertSame('en-us', $resolve());
        session(['domain.language.code' => 'de']);
        $this->assertSame('de', $resolve());
        $this->assertSame('Sign in to your account', __('Sign in to your account'));
        $this->assertSame('These credentials do not match our records.', __('auth.failed'));
    }

    public function test_suspension_message_uses_account_language_before_session_invalidation(): void
    {
        session(['domain.language.code' => 'ru']);
        app()->setLocale('en-us');
        $user = new User;
        $user->user_enabled = 'false';
        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn($user);
        Auth::shouldReceive('logout')->once();
        $response = (new CheckUserEnabled)->handle($this->request(), fn () => $this->fail('Suspended user continued'));
        $this->assertSame(route('login'), $response->getTargetUrl());
        $this->assertSame(
            __('Your account has been suspended, please contact your system administrator.', [], 'ru'),
            session('error')
        );
        $this->assertNull(session('domain.language.code'));
    }

    public function test_csrf_refresh_response_retains_token_and_message_bag(): void
    {
        app()->setLocale('fr');
        $request = $this->request();
        $before = $request->session()->token();
        $response = (new CsrfTokenController)->store($request);
        $payload = $response->getData(true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame($before, $payload['token']);
        $this->assertSame(['success' => [__('Token refreshed')]], $payload['messages']);
    }

    public function test_csrf_refresh_failure_retains_error_bag(): void
    {
        app()->setLocale('pt-br');
        $session = Mockery::mock(\Illuminate\Session\Store::class);
        $session->shouldReceive('regenerateToken')->once()->andThrow(new \RuntimeException('Test session failure'));
        $request = $this->request();
        $request->setLaravelSession($session);
        $response = (new CsrfTokenController)->store($request);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'errors' => ['server' => [__('Failed to refresh token')]],
        ], $response->getData(true));
    }

    public function test_stateless_rate_limit_response_does_not_adopt_global_language(): void
    {
        DB::table('v_default_settings')->insert([
            'default_setting_subcategory' => 'language', 'default_setting_value' => 'ru',
        ]);
        app()->setLocale('en-us');
        $request = Request::create('/login');
        $request->headers->set('Accept', 'application/json');
        app()->instance('request', $request);
        $response = app(Handler::class)->render($request, new TooManyRequestsHttpException(30, 'Too Many Attempts.'));
        $this->assertSame('en-us', app()->getLocale());
        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('30', $response->headers->get('Retry-After'));
        $this->assertSame('Too Many Attempts.', $response->getData(true)['message']);
    }

    private function groupMessages(string $locale, string $group): array
    {
        $messages = require lang_path("en-us/$group.php");
        $catalog = json_decode(file_get_contents(lang_path("$locale.json")), true);
        array_walk_recursive($messages, function (&$message) use ($catalog) {
            $this->assertArrayHasKey($message, $catalog);
            $this->assertNotSame('', $catalog[$message]);
            $message = $catalog[$message];
        });

        return $messages;
    }

    private function request(array $input = [], array $headers = []): Request
    {
        $request = Request::create('/login', 'POST', $input);
        $request->headers->set('Accept', 'application/json');
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);

        return $request;
    }

    private function challengeRequest(string $code): TwoFactorLoginRequest
    {
        return TwoFactorLoginRequest::createFrom($this->request(['code' => $code]));
    }

    private function assertValidationMessage(callable $action, string $field, string $message, int $status = 422): void
    {
        try {
            $action();
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertSame($status, $exception->status);
            $this->assertContains($message, $exception->errors()[$field]);
        }
    }
}
