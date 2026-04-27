<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\V1\DeletedResponseData;
use App\Data\Api\V1\UserData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Models\Domain;
use App\Models\Groups;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    private const ADMIN_GROUP_NAME = 'admin';

    /**
     * List users
     *
     * Returns users belonging to the specified domain.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `user_view` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `user_uuid` from the previous response.
     *
     * @group Users
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results (1-200). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this user UUID (cursor). Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/users",
     *   "has_more": false,
     *   "data": [
     *     {
     *       "user_uuid": "9c6c2a5e-1ab1-4a0e-8d7f-cb8b2a4d111e",
     *       "object": "user",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "username": "ada_lovelace",
     *       "user_email": "ada.lovelace@example.com",
     *       "first_name": "Ada",
     *       "last_name": "Lovelace",
     *       "name_formatted": "Ada Lovelace",
     *       "user_enabled": true,
     *       "is_domain_admin": true,
     *       "language": "en-us",
     *       "time_zone": "Europe/London",
     *       "created_at": "2026-04-08 12:34:56"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid starting_after UUID" {"error":{"type":"invalid_request_error","message":"Invalid starting_after UUID.","code":"invalid_request","param":"starting_after"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(Request $request, string $domain_uuid)
    {
        $this->requireAuth($request);
        $this->validateDomainUuid($domain_uuid);
        $this->loadDomainOrFail($domain_uuid);

        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min(200, $limit));

        $startingAfter = (string) $request->input('starting_after', '');

        $query = QueryBuilder::for(User::class)
            ->where('domain_uuid', $domain_uuid)
            ->with(['user_adv_fields', 'user_groups', 'settings'])
            ->defaultSort('user_uuid')
            ->reorder('user_uuid')
            ->limit($limit + 1);

        if ($startingAfter !== '') {
            if (! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
            }
            $query->where('user_uuid', '>', $startingAfter);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $rows->map(fn (User $u) => UserData::fromModel($u));

        return response()->json([
            'object'   => 'list',
            'url'      => "/api/v1/domains/{$domain_uuid}/users",
            'has_more' => $hasMore,
            'data'     => $data,
        ], 200);
    }

    /**
     * Retrieve a user
     *
     * Returns a single user from the specified domain.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `user_view` permission.
     *
     * @group Users
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam user_uuid string required The user UUID. Example: 9c6c2a5e-1ab1-4a0e-8d7f-cb8b2a4d111e
     *
     * @response 200 scenario="Success" {
     *   "user_uuid": "9c6c2a5e-1ab1-4a0e-8d7f-cb8b2a4d111e",
     *   "object": "user",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "username": "ada_lovelace",
     *   "user_email": "ada.lovelace@example.com",
     *   "first_name": "Ada",
     *   "last_name": "Lovelace",
     *   "name_formatted": "Ada Lovelace",
     *   "user_enabled": true,
     *   "is_domain_admin": true,
     *   "language": "en-us",
     *   "time_zone": "Europe/London",
     *   "created_at": "2026-04-08 12:34:56"
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid user UUID" {"error":{"type":"invalid_request_error","message":"Invalid user UUID.","code":"invalid_request","param":"user_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="User not found" {"error":{"type":"invalid_request_error","message":"User not found.","code":"resource_missing","param":"user_uuid"}}
     */
    public function show(Request $request, string $domain_uuid, string $user_uuid)
    {
        $this->requireAuth($request);
        $this->validateDomainUuid($domain_uuid);
        $this->validateUserUuid($user_uuid);
        $this->loadDomainOrFail($domain_uuid);

        $user = $this->loadUserOrFail($domain_uuid, $user_uuid);

        return response()->json(UserData::fromModel($user)->toArray(), 200);
    }

    /**
     * Create a user
     *
     * Creates a new user in the specified domain. Optionally promotes the user
     * to a domain administrator by adding them to the global `admin` group with
     * a `v_user_groups` row scoped to this domain.
     *
     * @group Users
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @response 201 scenario="Created" {
     *   "user_uuid": "9c6c2a5e-1ab1-4a0e-8d7f-cb8b2a4d111e",
     *   "object": "user",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "username": "ada_lovelace",
     *   "user_email": "ada.lovelace@example.com",
     *   "first_name": "Ada",
     *   "last_name": "Lovelace",
     *   "name_formatted": "Ada Lovelace",
     *   "user_enabled": true,
     *   "is_domain_admin": true,
     *   "language": "en-us",
     *   "time_zone": "Europe/London",
     *   "created_at": "2026-04-08 12:34:56"
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 422 scenario="Validation error" {"error":{"type":"invalid_request_error","message":"The given data was invalid.","code":"invalid_request","param":null,"details":{"user_email":["A user with this email already exists."]}}}
     */
    public function store(StoreUserRequest $request, string $domain_uuid)
    {
        $this->requireAuth($request);
        $this->validateDomainUuid($domain_uuid);
        $this->loadDomainOrFail($domain_uuid);

        $validated = $request->validated();

        try {
            /** @var User $user */
            $user = DB::transaction(function () use ($validated, $domain_uuid) {
                $user = User::create([
                    'username'     => (string) $validated['username'],
                    'user_email'   => (string) $validated['user_email'],
                    'password'     => Hash::make($validated['password']),
                    'domain_uuid'  => $domain_uuid,
                    'user_enabled' => $this->toTextBool($validated['user_enabled'] ?? true),
                ]);

                $user->user_adv_fields()->create([
                    'first_name' => $validated['first_name'],
                    'last_name'  => $validated['last_name'],
                ]);

                foreach (['language', 'time_zone'] as $field) {
                    if (! array_key_exists($field, $validated)) {
                        continue;
                    }
                    $user->settings()->create([
                        'domain_uuid'              => $domain_uuid,
                        'user_setting_category'    => 'domain',
                        'user_setting_subcategory' => $field,
                        'user_setting_name'        => $field === 'language' ? 'code' : 'name',
                        'user_setting_value'       => $validated[$field],
                        'user_setting_enabled'     => true,
                    ]);
                }

                if (! empty($validated['is_domain_admin'])) {
                    $this->assignAdminGroup($user, $domain_uuid);
                }

                return $user;
            });

            $user = $this->loadUserOrFail($domain_uuid, (string) $user->user_uuid);

            return response()
                ->json(UserData::fromModel($user)->toArray(), 201)
                ->header('Location', "/api/v1/domains/{$domain_uuid}/users/{$user->user_uuid}");
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger('API User store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    /**
     * Update a user
     *
     * Updates an existing user. All fields are optional; omitted fields are
     * left unchanged. Toggling `is_domain_admin` adds or removes the user's
     * `admin` group membership for this domain.
     *
     * @group Users
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam user_uuid string required The user UUID. Example: 9c6c2a5e-1ab1-4a0e-8d7f-cb8b2a4d111e
     *
     * @response 200 scenario="Success" {
     *   "user_uuid": "9c6c2a5e-1ab1-4a0e-8d7f-cb8b2a4d111e",
     *   "object": "user",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "username": "ada_lovelace",
     *   "user_email": "ada.lovelace@example.com",
     *   "first_name": "Ada",
     *   "last_name": "Lovelace",
     *   "name_formatted": "Ada Lovelace",
     *   "user_enabled": true,
     *   "is_domain_admin": false,
     *   "language": "en-us",
     *   "time_zone": "Europe/London",
     *   "created_at": "2026-04-08 12:34:56"
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid user UUID" {"error":{"type":"invalid_request_error","message":"Invalid user UUID.","code":"invalid_request","param":"user_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="User not found" {"error":{"type":"invalid_request_error","message":"User not found.","code":"resource_missing","param":"user_uuid"}}
     * @response 422 scenario="Validation error" {"error":{"type":"invalid_request_error","message":"The given data was invalid.","code":"invalid_request","param":null,"details":{"user_email":["A user with this email already exists."]}}}
     */
    public function update(UpdateUserRequest $request, string $domain_uuid, string $user_uuid)
    {
        $this->requireAuth($request);
        $this->validateDomainUuid($domain_uuid);
        $this->validateUserUuid($user_uuid);
        $this->loadDomainOrFail($domain_uuid);

        $user = $this->loadUserOrFail($domain_uuid, $user_uuid);
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $user, $domain_uuid) {
                // Direct user fields
                $userUpdates = [];
                foreach (['username', 'user_email'] as $f) {
                    if (array_key_exists($f, $validated)) {
                        $userUpdates[$f] = $validated[$f];
                    }
                }
                if (array_key_exists('user_enabled', $validated)) {
                    $userUpdates['user_enabled'] = $this->toTextBool($validated['user_enabled']);
                }
                if (array_key_exists('password', $validated)) {
                    $userUpdates['password'] = Hash::make($validated['password']);
                }
                if (! empty($userUpdates)) {
                    $user->update($userUpdates);
                }

                // Names live on user_adv_fields
                if (array_key_exists('first_name', $validated) || array_key_exists('last_name', $validated)) {
                    $advUpdates = [];
                    if (array_key_exists('first_name', $validated)) $advUpdates['first_name'] = $validated['first_name'];
                    if (array_key_exists('last_name', $validated))  $advUpdates['last_name']  = $validated['last_name'];
                    $user->user_adv_fields()->updateOrCreate(
                        ['user_uuid' => $user->user_uuid],
                        $advUpdates
                    );
                }

                // Settings (language / time_zone)
                foreach (['language', 'time_zone'] as $field) {
                    if (! array_key_exists($field, $validated)) {
                        continue;
                    }
                    $user->settings()->updateOrCreate(
                        [
                            'domain_uuid'              => $user->domain_uuid,
                            'user_setting_category'    => 'domain',
                            'user_setting_subcategory' => $field,
                        ],
                        [
                            'user_setting_name'    => $field === 'language' ? 'code' : 'name',
                            'user_setting_value'   => $validated[$field],
                            'user_setting_enabled' => true,
                        ]
                    );
                }

                // Domain admin toggle
                if (array_key_exists('is_domain_admin', $validated)) {
                    if ($validated['is_domain_admin']) {
                        $this->assignAdminGroup($user, $domain_uuid);
                    } else {
                        $this->removeAdminGroup($user, $domain_uuid);
                    }
                }
            });

            $user = $this->loadUserOrFail($domain_uuid, $user_uuid);

            return response()->json(UserData::fromModel($user)->toArray(), 200);
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger('API User update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    /**
     * Delete a user
     *
     * Permanently deletes a user, including their adv fields, settings, group
     * memberships, and any cross-domain permission rows. This is irreversible.
     *
     * @group Users
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam user_uuid string required The user UUID. Example: 9c6c2a5e-1ab1-4a0e-8d7f-cb8b2a4d111e
     *
     * @response 200 scenario="Success" {"object":"user","uuid":"9c6c2a5e-1ab1-4a0e-8d7f-cb8b2a4d111e","deleted":true}
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid user UUID" {"error":{"type":"invalid_request_error","message":"Invalid user UUID.","code":"invalid_request","param":"user_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="User not found" {"error":{"type":"invalid_request_error","message":"User not found.","code":"resource_missing","param":"user_uuid"}}
     */
    public function destroy(Request $request, string $domain_uuid, string $user_uuid)
    {
        $this->requireAuth($request);
        $this->validateDomainUuid($domain_uuid);
        $this->validateUserUuid($user_uuid);
        $this->loadDomainOrFail($domain_uuid);

        $user = $this->loadUserOrFail($domain_uuid, $user_uuid);

        try {
            DB::transaction(function () use ($user) {
                $user->user_groups()->delete();
                $user->user_adv_fields()->delete();
                $user->settings()->delete();
                $user->domain_permissions()->delete();
                $user->domain_group_permissions()->delete();
                $user->delete();
            });

            $payload = DeletedResponseData::from([
                'uuid'    => (string) $user_uuid,
                'object'  => 'user',
                'deleted' => true,
            ]);

            return response()->json($payload->toArray(), 200);
        } catch (\Throwable $e) {
            logger('API User delete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    // -----------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------

    private function requireAuth(Request $request): void
    {
        if (! $request->user()) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }
    }

    private function validateDomainUuid(string $domain_uuid): void
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }
    }

    private function validateUserUuid(string $user_uuid): void
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $user_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid user UUID.', 'invalid_request', 'user_uuid');
        }
    }

    private function loadDomainOrFail(string $domain_uuid): Domain
    {
        $domain = Domain::query()->where('domain_uuid', $domain_uuid)->first(['domain_uuid', 'domain_name']);
        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }
        return $domain;
    }

    private function loadUserOrFail(string $domain_uuid, string $user_uuid): User
    {
        $user = User::query()
            ->with(['user_adv_fields', 'user_groups', 'settings'])
            ->where('domain_uuid', $domain_uuid)
            ->where('user_uuid', $user_uuid)
            ->first();

        if (! $user) {
            throw new ApiException(404, 'invalid_request_error', 'User not found.', 'resource_missing', 'user_uuid');
        }
        return $user;
    }

    private function assignAdminGroup(User $user, string $domain_uuid): void
    {
        // Already an admin in this domain? noop.
        $exists = $user->user_groups()
            ->where('group_name', self::ADMIN_GROUP_NAME)
            ->where('domain_uuid', $domain_uuid)
            ->exists();
        if ($exists) {
            return;
        }

        $group = Groups::query()
            ->where('group_name', self::ADMIN_GROUP_NAME)
            ->whereNull('domain_uuid')
            ->first();

        if (! $group) {
            // Should never happen on a properly seeded install.
            throw new ApiException(
                500,
                'api_error',
                'Admin group is not configured on this server.',
                'internal_error'
            );
        }

        $user->user_groups()->create([
            'group_uuid'  => $group->group_uuid,
            'group_name'  => self::ADMIN_GROUP_NAME,
            'domain_uuid' => $domain_uuid,
        ]);
    }

    private function removeAdminGroup(User $user, string $domain_uuid): void
    {
        $user->user_groups()
            ->where('group_name', self::ADMIN_GROUP_NAME)
            ->where('domain_uuid', $domain_uuid)
            ->delete();
    }

    private function toTextBool($value): string
    {
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value);
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }
}
