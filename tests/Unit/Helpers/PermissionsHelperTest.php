<?php

namespace Tests\Unit\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use stdClass;
use Tests\TestCase;

/**
 * Covers the permission helpers in app/helpers.php — specifically the
 * Sanctum-compatibility fix for `isSuperAdmin()` and the defensive guards
 * in `userCheckPermission()`.
 *
 * Historical bug (now fixed): `isSuperAdmin()` raised a TypeError
 * "foreach() argument must be of type array|object, null given" when called
 * from a stateless Sanctum API context (e.g. POST /api/v1/domains/{uuid}/extensions),
 * because Session::get('user.groups') returns null/false outside a web session.
 */
class PermissionsHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Session::flush();
        parent::tearDown();
    }

    private function makeGroup(string $name, int $level): stdClass
    {
        $g = new stdClass();
        $g->group_name = $name;
        $g->group_level = $level;
        return $g;
    }

    private function makeAuthUserWithGroups(array $groups): object
    {
        return new class($groups) {
            private array $groups;
            public function __construct(array $g) { $this->groups = $g; }
            public function groups() { return collect($this->groups); }
        };
    }

    // ------------------------------------------------------------------
    // isSuperAdmin() — Sanctum path (Auth::user() populated, Session empty)
    // ------------------------------------------------------------------

    public function test_is_super_admin_returns_true_for_superadmin_via_auth_user_sanctum(): void
    {
        $user = $this->makeAuthUserWithGroups([$this->makeGroup('superadmin', 80)]);
        Auth::shouldReceive('user')->andReturn($user);

        $this->assertTrue(isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_regular_user_via_auth_user_sanctum(): void
    {
        $user = $this->makeAuthUserWithGroups([$this->makeGroup('user', 10)]);
        Auth::shouldReceive('user')->andReturn($user);

        $this->assertFalse(isSuperAdmin());
    }

    public function test_is_super_admin_rejects_superadmin_group_with_level_below_80(): void
    {
        // group_name matches but level too low — must not grant superadmin.
        $user = $this->makeAuthUserWithGroups([$this->makeGroup('superadmin', 79)]);
        Auth::shouldReceive('user')->andReturn($user);

        $this->assertFalse(isSuperAdmin());
    }

    // ------------------------------------------------------------------
    // isSuperAdmin() — legacy web-session fallback
    // ------------------------------------------------------------------

    public function test_is_super_admin_returns_true_via_session_groups_legacy_web_session(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        Session::put('user.groups', [$this->makeGroup('superadmin', 80)]);

        $this->assertTrue(isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_when_session_groups_match_no_superadmin(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        Session::put('user.groups', [$this->makeGroup('user', 10)]);

        $this->assertFalse(isSuperAdmin());
    }

    // ------------------------------------------------------------------
    // isSuperAdmin() — robustness against malformed inputs (the historical 500 cause)
    // ------------------------------------------------------------------

    public function test_is_super_admin_does_not_throw_when_session_is_empty_and_no_auth(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        // Session::get('user.groups') will return null — must not throw TypeError.

        $this->assertFalse(isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_when_session_groups_is_scalar_not_iterable(): void
    {
        // The historical 500 cause: Session::get returned a scalar (e.g. `false`)
        // and `foreach (... as $group)` raised TypeError.
        Auth::shouldReceive('user')->andReturn(null);
        Session::put('user.groups', false);

        $this->assertFalse(isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_when_session_groups_contains_non_object_items(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        Session::put('user.groups', ['scalar_string', 42, null]);

        $this->assertFalse(isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_when_session_groups_objects_lack_expected_properties(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        $malformed = new stdClass();
        $malformed->some_other_property = 'foo';
        Session::put('user.groups', [$malformed]);

        $this->assertFalse(isSuperAdmin());
    }

    // ------------------------------------------------------------------
    // isSuperAdmin($user) — explicit user argument (ExtensionObserver pattern)
    // ------------------------------------------------------------------

    public function test_is_super_admin_accepts_explicit_user_argument_used_by_observers(): void
    {
        // app/Observers/ExtensionObserver.php calls `isSuperadmin($user)` (lowercase
        // alias resolved by PHP's case-insensitive function names).
        $user = $this->makeAuthUserWithGroups([$this->makeGroup('superadmin', 80)]);
        Auth::shouldReceive('user')->andReturn(null);  // ensure fallback is bypassed

        $this->assertTrue(isSuperAdmin($user));
    }

    public function test_is_super_admin_explicit_user_argument_takes_precedence_over_auth_user(): void
    {
        $regularExplicit = $this->makeAuthUserWithGroups([$this->makeGroup('user', 10)]);
        $superadminAuth = $this->makeAuthUserWithGroups([$this->makeGroup('superadmin', 80)]);
        Auth::shouldReceive('user')->andReturn($superadminAuth);

        // Explicit $user wins over Auth::user(). In this scenario the function
        // must inspect the *explicit* (regular) user, not the authenticated one.
        $this->assertFalse(isSuperAdmin($regularExplicit));
    }

    // ------------------------------------------------------------------
    // userCheckPermission() — same defensive guard against scalar Session values
    // ------------------------------------------------------------------

    public function test_user_check_permission_returns_false_when_session_is_scalar_not_iterable(): void
    {
        Session::put('permissions', 'corrupted_scalar');

        $this->assertFalse(userCheckPermission('extension_add'));
    }

    public function test_user_check_permission_returns_true_when_permission_present(): void
    {
        $perm = new stdClass();
        $perm->permission_name = 'extension_add';
        Session::put('permissions', [$perm]);

        $this->assertTrue(userCheckPermission('extension_add'));
    }

    public function test_user_check_permission_returns_false_when_permission_absent(): void
    {
        $perm = new stdClass();
        $perm->permission_name = 'extension_view';
        Session::put('permissions', [$perm]);

        $this->assertFalse(userCheckPermission('extension_add'));
    }

    public function test_user_check_permission_does_not_throw_when_session_unset(): void
    {
        // Default Session::get('permissions', false) returns false — must coerce
        // to "no permissions" cleanly, not crash on foreach.
        $this->assertFalse(userCheckPermission('extension_add'));
    }
}
