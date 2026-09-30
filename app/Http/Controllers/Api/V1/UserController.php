<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\V1\DeletedResponseData;
use App\Data\Api\V1\UserData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Models\Domain;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * @group Users
 * @authenticated
 */
class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    /**
     * List users
     *
     * Lists FS PBX users in the specified account. Use this endpoint to browse user
     * profiles or find a user to retrieve, update, or delete. Each result includes
     * the user's UUID, name, email, enabled status, assigned extension, roles,
     * and whether the user is directory-managed. Use Retrieve a user for the
     * time zone, managed accounts, account groups, and locations.
     *
     * Search by name or email, or filter by enabled status. Results are paginated;
     * when `has_more` is true, pass the last result's `user_uuid` as `starting_after`
     * to fetch the next page.
     *
     * Requires access to the domain in the URL and `user_view`. Passwords, API tokens,
     * and two-factor secrets are never returned.
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Results per page (1-100). Defaults to 25. Example: 25
     * @queryParam starting_after string Return users after this user UUID. No-example
     * @queryParam search string Search email, first name, or last name. Example: Jane
     * @queryParam user_enabled boolean Filter by enabled status. Example: true
     * @response 200 {"object":"list","url":"/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/users","has_more":false,"data":[{"user_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"user","domain_uuid":"4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b","user_email":"jane.smith@example.com","first_name":"Jane","last_name":"Smith","user_enabled":true,"extension_uuid":null,"groups":[],"directory_managed":false}]}
     * @response 400 {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid"}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(Request $request, string $domain_uuid)
    {
        $this->domain($domain_uuid);
        if ($request->has('user_enabled')) {
            $value = $request->input('user_enabled');
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null && $value !== '' && $normalized !== null) {
                $request->merge(['user_enabled' => $normalized]);
            }
        }
        $params = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'starting_after' => ['sometimes', 'uuid'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_enabled' => ['sometimes', 'boolean'],
        ]);
        $limit = (int) ($params['limit'] ?? 25);
        $query = $this->readQuery($domain_uuid, includeDetails: false)->orderBy('user_uuid');
        if (isset($params['starting_after'])) {
            $query->where('user_uuid', '>', $params['starting_after']);
        }
        if (array_key_exists('user_enabled', $params)) {
            $query->where('user_enabled', $params['user_enabled'] ? 'true' : 'false');
        }
        if (! empty($params['search'])) {
            $query->searchIdentity($params['search']);
        }
        $rows = $query->limit($limit + 1)->get();

        return response()->json([
            'object' => 'list',
            'url' => "/api/v1/domains/{$domain_uuid}/users",
            'has_more' => $rows->count() > $limit,
            'data' => $rows->take($limit)->map(fn (User $user) => $this->payload($user, includeDetails: false)->toArray())->values(),
        ]);
    }

    /**
     * Retrieve a user
     *
     * Returns one FS PBX user's profile and access assignments in the specified
     * account. Use this endpoint to inspect the user's current details before
     * making changes, including their name, email, enabled status, time zone,
     * assigned extension, roles, managed accounts, and locations.
     *
     * `groups` contains role UUIDs; `accounts` and `account_groups` identify the
     * accounts and account groups the user is assigned to manage. `locations`
     * contains assigned location UUIDs, and `directory_managed` indicates whether
     * the user is linked to a connected directory.
     *
     * Requires domain access and `user_view`. The user must belong to the domain
     * in the URL. Passwords, API tokens, and two-factor secrets are never returned.
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam user_uuid string required The user UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @response 200 {"user_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"user","domain_uuid":"4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b","user_email":"jane.smith@example.com","first_name":"Jane","last_name":"Smith","user_enabled":true,"extension_uuid":null,"time_zone":null,"groups":[],"accounts":[],"account_groups":[],"locations":[],"directory_managed":false}
     * @response 400 {"error":{"type":"invalid_request_error","message":"Invalid user UUID.","code":"invalid_request","param":"user_uuid"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid"}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"User not found.","code":"resource_missing","param":"user_uuid"}}
     */
    public function show(string $domain_uuid, string $user_uuid)
    {
        $this->domain($domain_uuid);

        return response()->json($this->payload($this->findUser($domain_uuid, $user_uuid))->toArray());
    }

    /**
     * Create a user
     *
     * Creates a local FS PBX user in the specified account. Use this endpoint to
     * add a staff member or administrator who will sign in to the FS PBX web
     * interface. Returns the created user's profile and `user_uuid`, which can
     * be used to retrieve, update, or delete the user.
     *
     * Only `first_name` and `user_email` are required. You can also assign an
     * existing extension and roles to configure the user's access. No role is
     * assigned automatically. If `groups` is supplied, it must contain at least
     * one existing role UUID. The domain in the URL owns the new user. Password
     * setup is a separate step: call Send a password reset email for the new
     * `user_uuid` to let the user choose their password. Creating the user does
     * not send an email or set or return a password.
     *
     * If `time_zone` is omitted or null, it defaults to the account's local timezone,
     * falling back to the system timezone setting or UTC.
     *
     * Requires domain access and `user_add`. Role assignments require
     * `user_group_edit` and cannot exceed the caller's role level. Only superadmins
     * may assign the superadmin role. Disabled creation requires `user_status`.
     * Account assignments require their corresponding management permissions.
     * The domain's user limit is enforced.
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @bodyParam groups string[] Role UUIDs. May be omitted; when supplied, must contain at least one existing role UUID. Assigning roles requires user_group_edit. Example: ["6fdb722a-3f2f-430d-ab05-46a75de8c587"]
     * @bodyParam accounts string[] Optional managed account UUIDs. Requires user_update_managed_accounts and access to each account. Example: []
     * @bodyParam account_groups string[] Optional managed account-group UUIDs. Requires user_update_managed_account_groups and authority over each group. Example: []
     * @bodyParam locations string[] Optional location UUIDs from this domain. Example: []
     * @response 201 {"user_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"user","domain_uuid":"4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b","user_email":"jane.smith@example.com","first_name":"Jane","last_name":"Smith","user_enabled":true,"extension_uuid":null,"time_zone":"America/New_York","groups":[],"accounts":[],"account_groups":[],"locations":[],"directory_managed":false}
     * @response 400 {"error":{"type":"invalid_request_error","message":"The first name field is required.","code":"invalid_parameter","param":"first_name"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"type":"invalid_request_error","message":"One or more selected groups are not allowed.","code":"forbidden","param":null}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function store(StoreUserRequest $request, string $domain_uuid)
    {
        $user = $this->users->create($request->user(), $domain_uuid, $request->validated());

        return response()->json($this->payload($this->findUser($domain_uuid, $user->user_uuid))->toArray(), 201)
            ->header('Location', "/api/v1/domains/{$domain_uuid}/users/{$user->user_uuid}");
    }

    /**
     * Update a user
     *
     * Updates an existing FS PBX user in the specified account. Use this endpoint
     * to change profile details, enable or disable the user, or revise their
     * extension, roles, managed accounts, and location assignments. Returns the
     * updated user profile.
     *
     * This is a partial update: all fields are optional, and omitted fields and
     * assignments are preserved. Send `extension_uuid: null` to unassign the
     * extension. If `groups` is supplied, it must contain at least one existing
     * role UUID; an empty array is rejected.
     *
     * Directory-managed identity and status cannot be changed here. Directory-managed
     * email and extension assignments are protected, and directory-managed roles
     * are preserved. Local roles may still be changed with the appropriate permission.
     *
     * Requires domain access and `user_edit`. Changes to roles, status, and managed
     * accounts require the same additional permissions as creation. Non-superadmins
     * cannot modify superadmins or users above their role level.
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam user_uuid string required The user UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @bodyParam first_name string Optional first name. Cannot be empty when supplied. Example: Jane
     * @bodyParam user_email string Optional email address, unique across all accounts. Cannot be empty when supplied. Example: jane.smith@example.com
     * @bodyParam groups string[] Role UUIDs. Omit to preserve; when supplied, must contain at least one existing role UUID. Changes require user_group_edit. Directory-managed roles are preserved. Example: ["6fdb722a-3f2f-430d-ab05-46a75de8c587"]
     * @bodyParam accounts string[] Optional managed account UUIDs. Omit to preserve; send [] to clear. Changes require user_update_managed_accounts and access to each account. Example: []
     * @bodyParam account_groups string[] Optional managed account-group UUIDs. Omit to preserve; send [] to clear. Changes require user_update_managed_account_groups and authority over each group. Example: []
     * @bodyParam locations string[] Optional location UUIDs from this domain. Omit to preserve; send [] to clear. Example: []
     * @response 200 {"user_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"user","domain_uuid":"4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b","user_email":"jane.smith@example.com","first_name":"Jane","last_name":"Smith","user_enabled":true,"extension_uuid":null,"time_zone":null,"groups":[],"accounts":[],"account_groups":[],"locations":[],"directory_managed":false}
     * @response 400 {"error":{"type":"invalid_request_error","message":"This field is managed by the connected directory.","code":"invalid_parameter","param":"first_name"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"type":"invalid_request_error","message":"You are not allowed to manage this user.","code":"forbidden","param":null}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"User not found.","code":"resource_missing","param":"user_uuid"}}
     */
    public function update(UpdateUserRequest $request, string $domain_uuid, string $user_uuid)
    {
        $user = $this->findUser($domain_uuid, $user_uuid);
        $user = $this->users->update($request->user(), $domain_uuid, $user, $request->validated());

        return response()->json($this->payload($this->findUser($domain_uuid, $user->user_uuid))->toArray());
    }

    /**
     * Send a password reset email
     *
     * Emails a link that lets an existing local FS PBX user choose a new password.
     * Use this after creating a user to set up their first password, or to help
     * an existing user regain access. Returns confirmation that the email was sent.
     *
     * No request body is needed. The email goes to the user's saved `user_email`
     * and uses the existing password-reset email template. Passwords, reset tokens,
     * and reset URLs are never returned. The current password stays valid until
     * the user completes the reset; sending the email does not enable a disabled user.
     *
     * Links are single-use and expire according to the password-reset configuration
     * (60 minutes by default). Repeat requests for the same recipient are throttled
     * according to that configuration (60 seconds by default), returning HTTP 429.
     *
     * Requires domain access and `user_edit`. The user must belong to the domain
     * in the URL. Non-superadmins cannot request resets for superadmins or users
     * above their role level. Directory-managed users must reset their passwords
     * through the connected directory.
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam user_uuid string required The user UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @response 200 {"object":"password_reset","user_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","sent":true}
     * @response 400 {"error":{"type":"invalid_request_error","message":"This user is managed by an external directory. Reset the password in the connected directory.","code":"invalid_parameter","param":"user_uuid"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"type":"invalid_request_error","message":"You are not allowed to manage this user.","code":"forbidden","param":null}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"User not found.","code":"resource_missing","param":"user_uuid"}}
     * @response 429 {"error":{"type":"rate_limit_error","message":"Please wait before requesting another password reset email.","code":"password_reset_throttled","param":null}}
     * @response 503 {"error":{"type":"api_error","message":"Unable to send the password reset email. Please try again later.","code":"mail_delivery_failed","param":null}}
     */
    public function sendPasswordReset(Request $request, string $domain_uuid, string $user_uuid)
    {
        $this->domain($domain_uuid);
        $user = $this->findUser($domain_uuid, $user_uuid);

        try {
            $status = $this->users->sendPasswordResetLink($request->user(), $domain_uuid, $user);
        } catch (TransportExceptionInterface $e) {
            report($e);

            throw new ApiException(503, 'api_error', 'Unable to send the password reset email. Please try again later.', 'mail_delivery_failed');
        }

        return match ($status) {
            Password::RESET_LINK_SENT => response()->json([
                'object' => 'password_reset', 'user_uuid' => $user->user_uuid, 'sent' => true,
            ]),
            Password::RESET_THROTTLED => throw new ApiException(429, 'rate_limit_error', 'Please wait before requesting another password reset email.', 'password_reset_throttled'),
            Password::INVALID_USER => throw new ApiException(404, 'invalid_request_error', 'User not found.', 'resource_missing', 'user_uuid'),
            default => throw new ApiException(500, 'api_error', 'Unable to send the password reset email. Please try again later.', 'mail_delivery_failed'),
        };
    }

    /**
     * Delete a user
     *
     * Permanently removes a local FS PBX user from the specified account to revoke
     * their application access. Deletes the user's profile, settings, assignments,
     * API tokens, and sessions. Returns the deleted user's UUID and a `deleted: true`
     * confirmation.
     *
     * Requires domain access and `user_delete`. Non-superadmins cannot delete
     * superadmins or users above their role level. Directory-managed users must
     * be disabled or removed in their connected directory instead.
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam user_uuid string required The user UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @response 200 {"uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"user","deleted":true}
     * @response 400 {"error":{"type":"invalid_request_error","message":"Directory-managed users cannot be deleted here. Disable or remove them in the connected directory.","code":"invalid_parameter","param":"user_uuid"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"type":"invalid_request_error","message":"You are not allowed to manage this user.","code":"forbidden","param":null}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"User not found.","code":"resource_missing","param":"user_uuid"}}
     */
    public function destroy(Request $request, string $domain_uuid, string $user_uuid)
    {
        $this->domain($domain_uuid);
        $user = $this->findUser($domain_uuid, $user_uuid);
        $this->users->deleteMany($request->user(), $domain_uuid, [$user->user_uuid]);

        return response()->json((new DeletedResponseData($user->user_uuid, 'user', true))->toArray());
    }

    private function domain(string $uuid): void
    {
        if (! Str::isUuid($uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }
        if (! Domain::whereKey($uuid)->exists()) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }
    }

    private function findUser(string $domainUuid, string $uuid): User
    {
        if (! Str::isUuid($uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid user UUID.', 'invalid_request', 'user_uuid');
        }
        $user = $this->readQuery($domainUuid)->whereKey($uuid)->first();
        if (! $user) {
            throw new ApiException(404, 'invalid_request_error', 'User not found.', 'resource_missing', 'user_uuid');
        }

        return $user;
    }

    private function readQuery(string $domainUuid, bool $includeDetails = true): Builder
    {
        $columns = ['user_uuid', 'domain_uuid', 'user_email', 'user_enabled'];
        if (Schema::hasColumn('v_users', 'extension_uuid')) {
            $columns[] = 'extension_uuid';
        }
        // User eagerly loads settings by default; summaries only need names and roles.
        $query = $this->users->query($domainUuid)->select($columns)->without('settings')->with([
            'user_adv_fields:id,user_uuid,first_name,last_name', 'user_groups:user_uuid,group_uuid',
        ]);
        if ($includeDetails) {
            $query->with(['settings', 'domain_permissions', 'domain_group_permissions', 'locations']);
        }
        if (Schema::hasTable('ldap_directory_users')) {
            $query->withExists(['ldapDirectoryUsers as directory_managed' => fn ($query) => $query
                ->whereColumn('ldap_directory_users.domain_uuid', 'v_users.domain_uuid')]);
        }

        return $query;
    }

    private function payload(User $user, bool $includeDetails = true): UserData
    {
        $data = new UserData(
            user_uuid: $user->user_uuid,
            object: 'user',
            domain_uuid: $user->domain_uuid,
            user_email: $user->user_email,
            first_name: $user->first_name,
            last_name: $user->last_name,
            user_enabled: filter_var($user->user_enabled, FILTER_VALIDATE_BOOLEAN),
            extension_uuid: $user->extension_uuid,
            groups: $user->user_groups->pluck('group_uuid')->unique()->sort()->values()->all(),
            directory_managed: (bool) $user->directory_managed,
        );

        if ($includeDetails) {
            $data->time_zone = $user->time_zone;
            $data->accounts = $user->domain_permissions->pluck('domain_uuid')->sort()->values()->all();
            $data->account_groups = $user->domain_group_permissions->pluck('domain_group_uuid')->sort()->values()->all();
            $data->locations = $user->locations->pluck('location_uuid')->sort()->values()->all();
        }

        return $data;
    }
}
