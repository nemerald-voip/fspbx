<?php

namespace Tests\Feature\Listeners;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogPasswordReset;
use App\Listeners\LogSuccessfulLogin;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserLogEmailTest extends TestCase
{
    use WithFaker;

    /**
     * Build an in-memory (unsaved) user so tests don't depend on the full
     * FusionPBX v_users schema being present.
     */
    private function makeUser(): User
    {
        $user = new User([
            'username'    => 'jdoe',
            'user_email'  => 'jdoe@example.com',
            'domain_uuid' => $this->faker->uuid(),
        ]);
        $user->user_uuid = $this->faker->uuid();

        return $user;
    }

    public function test_successful_login_persists_the_user_email(): void
    {
        $user = $this->makeUser();

        (new LogSuccessfulLogin())->handle(new Login('web', $user, false));

        $log = UserLog::where('user_uuid', $user->user_uuid)->firstOrFail();
        $this->assertSame('jdoe@example.com', $log->email);
    }

    public function test_password_reset_persists_the_user_email(): void
    {
        $user = $this->makeUser();

        (new LogPasswordReset())->handle(new PasswordReset($user));

        $log = UserLog::where('user_uuid', $user->user_uuid)->firstOrFail();
        $this->assertSame('jdoe@example.com', $log->email);
    }

    public function test_failed_login_persists_the_resolved_users_email(): void
    {
        $user = $this->makeUser();

        (new LogFailedLogin())->handle(new Failed('web', $user, ['email' => 'jdoe@example.com', 'password' => 'wrong']));

        $log = UserLog::where('user_uuid', $user->user_uuid)->firstOrFail();
        $this->assertSame('jdoe@example.com', $log->email);
    }

    public function test_failed_login_with_no_matching_user_falls_back_to_the_submitted_email(): void
    {
        (new LogFailedLogin())->handle(new Failed('web', null, ['email' => 'nobody@example.com', 'password' => 'wrong']));

        $log = UserLog::whereNull('user_uuid')
            ->where('username', 'nobody@example.com')
            ->latest('insert_date')
            ->firstOrFail();
        $this->assertSame('nobody@example.com', $log->email);
    }

    public function test_email_survives_after_the_underlying_user_is_deleted(): void
    {
        $user = $this->makeUser();

        (new LogSuccessfulLogin())->handle(new Login('web', $user, false));

        // The user was never actually persisted in this isolated test schema,
        // so `$log->user` is already null here — exactly the state a real
        // deleted user leaves behind. The log must still carry its own email.
        $log = UserLog::where('user_uuid', $user->user_uuid)->firstOrFail();
        $this->assertNull($log->user);
        $this->assertSame('jdoe@example.com', $log->email);
    }
}
