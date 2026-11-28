<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckFusionPBXLogin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetApplicationLocale;
use App\Jobs\EmailLoginChallengeCode;
use App\Models\User;
use App\Services\LdapUserAuthenticator;
use App\Services\UserService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MyAccountAuthenticationTest extends TestCase
{
    private const EMAIL = 'member@example.test';
    private const PASSWORD = 'Original-pass-123!';
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'account_test',
            'database.connections.account_test' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'cache.default' => 'array', 'session.driver' => 'array', 'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
        $this->withoutMiddleware([CheckFusionPBXLogin::class, HandleInertiaRequests::class, SetApplicationLocale::class]);
        Event::fake([Login::class, Failed::class]);
        Bus::fake();
        $this->mock(UncompromisedVerifier::class)->shouldReceive('verify')->andReturn(true);
        foreach ([
            'v_users' => ['user_uuid', 'user_email', 'password', 'user_enabled', 'domain_uuid', 'add_date'],
            'v_user_settings' => ['user_uuid'],
            'users_adv_fields' => ['id', 'user_uuid', 'first_name', 'last_name', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes', 'two_factor_cookies'],
            'v_default_settings' => ['default_setting_category', 'default_setting_subcategory', 'default_setting_name', 'default_setting_enabled', 'default_setting_value'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $schema) use ($columns) {
                foreach ($columns as $column) {
                    $schema->text($column)->nullable();
                }
            });
        }
        $this->user = User::create(['user_email' => self::EMAIL, 'password' => Hash::make(self::PASSWORD),
            'domain_uuid' => 'home-account', 'user_enabled' => 'true']);
    }

    private function login()
    {
        return $this->postJson('/login', ['user_email' => self::EMAIL, 'password' => self::PASSWORD]);
    }

    private function enable(): string
    {
        $this->actingAs($this->user);
        $this->postJson('/user/confirm-password', ['password' => self::PASSWORD])->assertSuccessful();
        $this->postJson('/user/two-factor-authentication')->assertSuccessful();
        $secret = $this->getJson('/user/two-factor-secret-key')->assertOk()->json('secretKey');
        $this->getJson('/user/two-factor-qr-code')->assertOk()->assertJsonStructure(['svg']);
        $otp = new Google2FA;
        $this->postJson('/user/confirmed-two-factor-authentication', [
            'code' => $otp->oathTotp($secret, $otp->getTimestamp() - 1),
        ])->assertSuccessful();
        return $secret;
    }

    public function test_authenticator_first_with_email_or_single_use_recovery_and_intended_redirect(): void
    {
        $secret = $this->enable();
        $codes = $this->getJson('/user/two-factor-recovery-codes')->assertOk()->json();
        $this->assertCount(8, $codes);
        $this->assertNotSame($secret, $this->user->fresh()->two_factor_secret);
        $this->post('/logout');
        Cache::put('settings.email_challenge_enabled', false);
        $this->login()->assertJsonPath('two_factor', true);
        Bus::assertNothingDispatched();
        $this->assertGuest();
        $this->withSession(['url.intended' => '/my-account'])->post('/two-factor-challenge', [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect('/my-account')->assertSessionMissing('login');
        $this->assertAuthenticatedAs($this->user);

        $this->post('/logout');
        $this->login()->assertJsonPath('two_factor', true);
        $this->postJson('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSuccessful();
        $this->post('/logout');
        $this->login();
        $this->postJson('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertJsonValidationErrors('recovery_code');
        $this->put('/email-challenge')->assertRedirect('/email-challenge');
        $code = Bus::dispatched(EmailLoginChallengeCode::class)->last()->attributes['code'];
        $this->postJson('/email-challenge', ['code' => $code, 'remember' => true])->assertSuccessful()
            ->assertCookieMissing('__TWO_FACTOR_EMAIL')->assertSessionMissing('login');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_pending_setup_preserves_email_policy_and_password_confirmation(): void
    {
        $this->actingAs($this->user);
        $this->postJson('/user/two-factor-authentication')->assertStatus(423);
        $this->postJson('/user/confirm-password', ['password' => self::PASSWORD])->assertSuccessful();
        $this->postJson('/user/two-factor-authentication')->assertSuccessful();
        $this->postJson('/user/confirmed-two-factor-authentication', ['code' => 'wrong'])->assertJsonValidationErrors('code');
        $this->assertFalse($this->user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->post('/logout');
        Cache::put('settings.email_challenge_enabled', true);
        $this->login()->assertJsonPath('email_challenge', true);
        $this->get('/email-challenge', ['X-Inertia' => 'true'])->assertOk();
        $first = Bus::dispatched(EmailLoginChallengeCode::class)->last()->attributes['code'];
        $this->put('/email-challenge')->assertRedirect();
        $second = Bus::dispatched(EmailLoginChallengeCode::class)->last()->attributes['code'];
        $this->assertNotSame($first, $second);
        $this->postJson('/email-challenge', ['code' => $first])->assertJsonValidationErrors('code');
        $this->postJson('/email-challenge', ['code' => $second, 'remember' => true])->assertSuccessful()->assertCookie('__TWO_FACTOR_EMAIL');
        $this->post('/logout');
        $token = json_decode($this->user->fresh()->two_factor_cookies, true)[0];
        $this->withCookie('__TWO_FACTOR_EMAIL', $token)->post('/login', [
            'user_email' => self::EMAIL, 'password' => self::PASSWORD,
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_disabled_user_wrong_password_and_changed_identity_cannot_complete_challenges(): void
    {
        Cache::put('settings.email_challenge_enabled', true);
        $this->postJson('/login', ['user_email' => self::EMAIL, 'password' => 'incorrect'])->assertJsonValidationErrors('user_email');
        Bus::assertNothingDispatched();
        $this->login()->assertJsonPath('email_challenge', true);
        $this->user->update(['user_email' => 'new@example.test']);
        $this->get('/email-challenge')->assertRedirect('/login')->assertSessionMissing('login');
        $this->user->update(['user_email' => self::EMAIL, 'user_enabled' => 'false']);
        $this->login()->assertJsonValidationErrors('user_email');
        $this->assertGuest();
    }

    public function test_recovery_regeneration_stale_redemption_and_disabling(): void
    {
        $this->enable();
        $old = $this->user->fresh();
        $code = $old->recoveryCodes()[0];
        $this->postJson('/user/two-factor-recovery-codes')->assertSuccessful();
        try {
            $old->replaceRecoveryCode($code);
            $this->fail('A stale recovery-code reader must not restore the old codes.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('recovery_code', $error->errors());
        }
        $this->deleteJson('/user/two-factor-authentication')->assertSuccessful();
        $this->assertNull($this->user->fresh()->two_factor_secret);
        $this->assertNull($this->user->fresh()->two_factor_recovery_codes);
        $this->post('/logout');
        $this->login()->assertJsonPath('two_factor', false);
    }

    public function test_profile_and_password_use_signed_in_user_across_selected_accounts(): void
    {
        $this->actingAs($this->user)->withSession(['domain_uuid' => 'another-account']);
        $this->putJson('/user/profile-information', ['section' => 'name', 'first_name' => 'Sam', 'last_name' => 'Lee',
            'user_uuid' => 'another-user'])->assertSuccessful();
        $this->assertSame('Sam Lee', $this->user->fresh()->name_formatted);
        DB::table('v_users')->insert(['user_uuid' => 'other', 'user_email' => 'Taken@Example.Test']);
        $data = ['section' => 'email', 'current_password' => self::PASSWORD];
        $this->putJson('/user/profile-information', $data + ['user_email' => 'taken@example.test'])->assertJsonValidationErrors('user_email');
        $this->putJson('/user/profile-information', $data + ['user_email' => 'New@Example.Test'])->assertSuccessful();
        $this->putJson('/user/password', ['current_password' => self::PASSWORD, 'password' => 'Updated-password1!',
            'password_confirmation' => 'Updated-password1!'])->assertSuccessful();
        $this->post('/logout');
        $this->login()->assertJsonValidationErrors('user_email');
        $this->postJson('/login', ['user_email' => 'new@example.test', 'password' => 'Updated-password1!'])->assertSuccessful();
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_directory_ownership_and_directory_password_confirmation(): void
    {
        $this->actingAs($this->user);
        $this->mock(UserService::class)->shouldReceive('directoryManagement')->andReturn(['managed' => true, 'email_managed' => true]);
        $this->putJson('/user/password', [])->assertForbidden();
        $this->putJson('/user/profile-information', ['section' => 'name'])->assertForbidden();
        $this->putJson('/user/profile-information', ['section' => 'email'])->assertForbidden();
        $this->mock(LdapUserAuthenticator::class)->shouldReceive('confirmPassword')->once()
            ->withArgs(fn ($user, $password) => $user->is($this->user) && $password === 'directory-password')->andReturn(true);
        $this->postJson('/user/confirm-password', ['password' => 'directory-password'])->assertSuccessful();
        $this->postJson('/user/two-factor-authentication')->assertSuccessful();
    }

    public function test_my_account_page_reports_authenticator_status_in_the_account_time_zone(): void
    {
        config(['app.name' => 'Example Telecom']);
        $this->mock(UserService::class)->shouldReceive('directoryManagement')->andReturn(['managed' => false, 'email_managed' => false]);
        Cache::put('_timeZone', 'America/New_York');
        Cache::put('_dateTimeFormats', ['date_format' => 'iso', 'time_format' => '24h']);
        $this->actingAs($this->user);
        $this->get('/my-account?section=two_factor', ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('component', 'MyAccount')
            ->assertJsonPath('props.initial_section', 'two_factor')
            ->assertJsonPath('props.authenticator', ['issuer' => 'Example Telecom', 'state' => 'off', 'confirmed_at' => null]);

        $this->postJson('/user/confirm-password', ['password' => self::PASSWORD])->assertSuccessful();
        $this->postJson('/user/two-factor-authentication')->assertSuccessful();
        $partial = ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'MyAccount', 'X-Inertia-Partial-Data' => 'authenticator'];
        $this->get('/my-account', $partial)->assertJsonPath('props.authenticator.state', 'pending');

        // Confirmed at 03:00 UTC is still the previous day in New York.
        $this->enable();
        $this->user->user_adv_fields()->update(['two_factor_confirmed_at' => Carbon::parse('2026-11-25 03:00:00', 'UTC')]);
        $this->user->refresh();
        $this->get('/my-account', $partial)->assertOk()
            ->assertJsonPath('props.authenticator', ['issuer' => 'Example Telecom', 'state' => 'enabled', 'confirmed_at' => '2026-11-24'])
            ->assertJsonMissingPath('props.profile');
    }

    public function test_email_verification_attempts_are_limited_and_expired_login_requires_a_password(): void
    {
        Cache::put('settings.email_challenge_enabled', true);
        $this->login()->assertJsonPath('email_challenge', true);
        $this->get('/email-challenge', ['X-Inertia' => 'true'])->assertOk();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/email-challenge', ['code' => '000000'])->assertJsonValidationErrors('code');
        }
        $this->postJson('/email-challenge', ['code' => '000000'])->assertTooManyRequests();
        $this->travel(11)->minutes();
        $this->get('/email-challenge')->assertRedirect('/login')->assertSessionMissing('login');
        $this->assertGuest();
    }

    public function test_enrolled_user_with_remembered_browser_always_starts_at_authenticator(): void
    {
        $this->enable();
        $this->user->user_adv_fields()->update(['two_factor_cookies' => json_encode(['known-device'])]);
        $this->post('/logout');
        Cache::put('settings.email_challenge_enabled', true);
        $this->withCookie('__TWO_FACTOR_EMAIL', 'known-device')->post('/login', [
            'user_email' => self::EMAIL, 'password' => self::PASSWORD, 'remember' => true,
        ])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        Bus::assertNothingDispatched();
    }
}
