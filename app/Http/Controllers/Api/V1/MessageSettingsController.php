<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\V1\DeletedResponseData;
use App\Data\Api\V1\MessageSettingData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMessageSettingRequest;
use App\Http\Requests\Api\V1\UpdateMessageSettingRequest;
use App\Models\Domain;
use App\Models\MessageSetting;
use App\Services\Messaging\MessageParticipantService;
use App\Services\Messaging\MessageSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @group Message Settings
 * @authenticated
 */
class MessageSettingsController extends Controller
{
    public function __construct(
        private MessageSettingsService $settings,
        private MessageParticipantService $participants,
    ) {}

    /**
     * List message settings
     *
     * Lists messaging phone numbers in an account, including their provider,
     * allowed extensions, email recipient, description, and enabled status.
     * Search by phone number, provider, email, or description.
     *
     * When `has_more` is true, pass the last result's `sms_destination_uuid`
     * as `starting_after` to retrieve the next page.
     * Requires account access and `message_settings_list_view`.
     *
     * @urlParam domain_uuid string required The account UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Results per page (1-100). Defaults to 25. Example: 25
     * @queryParam starting_after string Return results after this message setting UUID. No-example
     * @queryParam search string Search phone number, provider, email, or description. Example: support
     * @queryParam carrier string Filter by message provider identifier. Example: voipms
     * @queryParam enabled boolean Filter by enabled status. Example: true
     * @response 200 {"object":"list","url":"/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/message-settings","has_more":false,"data":[{"sms_destination_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"message_setting","domain_uuid":"4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b","destination":"+12125550123","carrier":"voipms","allowed_extension_uuids":["d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b"],"email":"messages@example.com","description":"Customer support","enabled":true}]}
     * @response 400 {"error":{"type":"invalid_request_error","message":"The limit must not be greater than 100.","code":"invalid_parameter","param":"limit"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid"}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(Request $request, string $domain_uuid)
    {
        $this->domain($domain_uuid);
        if ($request->has('enabled') && $request->input('enabled') !== null && $request->input('enabled') !== '') {
            $value = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                $request->merge(['enabled' => $value]);
            }
        }
        $params = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'starting_after' => ['sometimes', 'uuid'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'carrier' => ['sometimes', 'string', Rule::in(array_keys(MessageSettingsService::carriers()))],
            'enabled' => ['sometimes', 'boolean'],
        ]);
        $limit = (int) ($params['limit'] ?? 25);
        $query = $this->settings->query($domain_uuid)->orderBy('sms_destination_uuid');
        if (isset($params['starting_after'])) {
            $query->where('sms_destination_uuid', '>', $params['starting_after']);
        }
        if (isset($params['carrier'])) {
            $query->where('carrier', $params['carrier']);
        }
        if (array_key_exists('enabled', $params)) {
            $query->where('enabled', $params['enabled'] ? 'true' : 'false');
        }
        if (isset($params['search']) && $params['search'] !== '') {
            $query->where(function ($query) use ($params) {
                $term = '%'.mb_strtolower($params['search']).'%';
                foreach (['destination', 'carrier', 'email', 'description'] as $column) {
                    $query->orWhereRaw('LOWER('.$column.') LIKE ?', [$term]);
                }
            });
        }
        $rows = $query->limit($limit + 1)->get();

        return response()->json([
            'object' => 'list',
            'url' => "/api/v1/domains/{$domain_uuid}/message-settings",
            'has_more' => $rows->count() > $limit,
            'data' => $rows->take($limit)->map(fn ($setting) => $this->payload($setting)->toArray())->values(),
        ]);
    }

    /**
     * Retrieve message settings
     *
     * Returns one messaging phone number's configuration. `allowed_extension_uuids`
     * contains the extensions permitted to share this number. Use the Extensions
     * API to retrieve their names and extension numbers.
     * Requires account access and `message_settings_list_view`.
     *
     * @urlParam domain_uuid string required The account UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam sms_destination_uuid string required The message setting UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @response 200 {"sms_destination_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"message_setting","domain_uuid":"4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b","destination":"+12125550123","carrier":"voipms","allowed_extension_uuids":["d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b"],"email":"messages@example.com","description":"Customer support","enabled":true}
     * @response 400 {"error":{"type":"invalid_request_error","message":"Invalid message setting UUID.","code":"invalid_request","param":"sms_destination_uuid"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid"}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"Message setting not found.","code":"resource_missing","param":"sms_destination_uuid"}}
     */
    public function show(string $domain_uuid, string $sms_destination_uuid)
    {
        $this->domain($domain_uuid);

        return response()->json($this->payload($this->findSetting($domain_uuid, $sms_destination_uuid))->toArray());
    }

    /**
     * Create message settings
     *
     * Configures a messaging phone number for an account. Supply the phone number,
     * its message provider, and any extensions that should share it. National numbers
     * are automatically converted to E.164 using the account country or the country
     * in Default Settings. If neither is configured, US is used. Numbers starting
     * with + use their supplied country code.
     * Allowed extensions can send and receive messages using the number and see
     * each other's replies. An empty or omitted extension list grants no extension access.
     *
     * The number is enabled by default. An optional email address receives incoming
     * messages. This endpoint saves the configuration in FS PBX; it does not purchase
     * a number or configure your provider's account.
     * Requires account access and `message_settings_manage`.
     *
     * @bodyParam allowed_extension_uuids string[] Optional extension UUIDs from this account. Defaults to []; on update, omit to preserve access. Example: ["d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b"]
     * @urlParam domain_uuid string required The account UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @response 201 {"sms_destination_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"message_setting","domain_uuid":"4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b","destination":"+12125550123","carrier":"voipms","allowed_extension_uuids":["d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b"],"email":"messages@example.com","description":"Customer support","enabled":true}
     * @response 400 {"error":{"type":"invalid_request_error","message":"Enter a valid phone number for the configured country, or include + and the country code.","code":"invalid_parameter","param":"destination"}}
     * @response 400 scenario="Invalid JSON" {"error":{"type":"invalid_request_error","message":"Invalid JSON request body. Remove trailing commas and check the JSON syntax.","code":"invalid_json","param":null}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"message_settings_manage"}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function store(StoreMessageSettingRequest $request, string $domain_uuid)
    {
        $setting = $this->settings->create($domain_uuid, $request->validated());

        return response()->json($this->payload($setting)->toArray(), 201)
            ->header('Location', "/api/v1/domains/{$domain_uuid}/message-settings/{$setting->sms_destination_uuid}");
    }

    /**
     * Update message settings
     *
     * Changes a messaging phone number's configuration. All fields are optional;
     * omitted fields retain their current values. When supplied, `allowed_extension_uuids`
     * replaces the entire access list. Send [] to remove all extension access.
     * Send null to clear the email recipient, description, or provider.
     * Requires account access and `message_settings_manage`.
     *
     * @bodyParam destination string Optional messaging phone number, automatically converted to E.164 using the account country or the country in Default Settings. If neither is configured, US is used. Numbers starting with + use their supplied country code. Omit to preserve the current number; cannot be null or empty. Example: +12125550123
     * @bodyParam allowed_extension_uuids string[] Optional extension UUIDs from this account. Replaces the full access list; omit to preserve access or send [] to remove all extension access. Example: ["d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b"]
     * @urlParam domain_uuid string required The account UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam sms_destination_uuid string required The message setting UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @response 200 {"sms_destination_uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"message_setting","domain_uuid":"4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b","destination":"+12125550123","carrier":"voipms","allowed_extension_uuids":[],"email":null,"description":"Customer support","enabled":true}
     * @response 400 {"error":{"type":"invalid_request_error","message":"The selected allowed_extension_uuids.0 is invalid.","code":"invalid_parameter","param":"allowed_extension_uuids.0"}}
     * @response 400 scenario="Invalid JSON" {"error":{"type":"invalid_request_error","message":"Invalid JSON request body. Remove trailing commas and check the JSON syntax.","code":"invalid_json","param":null}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"message_settings_manage"}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"Message setting not found.","code":"resource_missing","param":"sms_destination_uuid"}}
     */
    public function update(UpdateMessageSettingRequest $request, string $domain_uuid, string $sms_destination_uuid)
    {
        $setting = $this->settings->update($this->findSetting($domain_uuid, $sms_destination_uuid), $request->validated());

        return response()->json($this->payload($setting)->toArray());
    }

    /**
     * Delete message settings
     *
     * Removes a messaging phone number's configuration and its extension access
     * list from the account. Existing message history is retained. This does not
     * cancel the phone number with your provider.
     * Requires account access and `message_settings_manage`.
     *
     * @urlParam domain_uuid string required The account UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam sms_destination_uuid string required The message setting UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @response 200 {"uuid":"c9a76140-0ca4-4ea3-95af-7e12c2ff0df5","object":"message_setting","deleted":true}
     * @response 400 {"error":{"type":"invalid_request_error","message":"Invalid message setting UUID.","code":"invalid_request","param":"sms_destination_uuid"}}
     * @response 401 {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"message_settings_manage"}}
     * @response 404 {"error":{"type":"invalid_request_error","message":"Message setting not found.","code":"resource_missing","param":"sms_destination_uuid"}}
     */
    public function destroy(string $domain_uuid, string $sms_destination_uuid)
    {
        $this->domain($domain_uuid);
        $setting = $this->findSetting($domain_uuid, $sms_destination_uuid);
        $this->settings->delete([$domain_uuid], [$setting->sms_destination_uuid]);

        return response()->json((new DeletedResponseData($setting->sms_destination_uuid, 'message_setting', true))->toArray());
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

    private function findSetting(string $domainUuid, string $uuid): MessageSetting
    {
        if (! Str::isUuid($uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid message setting UUID.', 'invalid_request', 'sms_destination_uuid');
        }
        $setting = $this->settings->query($domainUuid)->whereKey($uuid)->first();
        if (! $setting) {
            throw new ApiException(404, 'invalid_request_error', 'Message setting not found.', 'resource_missing', 'sms_destination_uuid');
        }

        return $setting;
    }

    private function payload(MessageSetting $setting): MessageSettingData
    {
        return new MessageSettingData(
            sms_destination_uuid: $setting->sms_destination_uuid,
            object: 'message_setting',
            domain_uuid: $setting->domain_uuid,
            destination: $setting->destination,
            carrier: $setting->carrier,
            allowed_extension_uuids: $this->participants->assigned($setting)->pluck('extension_uuid')->all(),
            email: $setting->email,
            description: $setting->description,
            enabled: filter_var($setting->enabled, FILTER_VALIDATE_BOOLEAN),
        );
    }
}
