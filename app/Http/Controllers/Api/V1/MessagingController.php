<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\MessageSetting;
use App\Models\SmsDestinations;
use App\Services\Auth\PermissionService;
use App\Services\Messaging\MessageGroupService;
use App\Services\Messaging\MessageParticipantService;
use App\Services\Messaging\MessagingWebhookSettings;
use App\Services\Messaging\Outbound\CreateOutboundMessageService;
use App\Services\Messaging\Outbound\Data\CreateOutboundMessageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MessagingController extends Controller
{
    public function __construct(
        private readonly MessageParticipantService $participants,
        private readonly MessageGroupService $groups,
        private readonly CreateOutboundMessageService $outbound,
        private readonly PermissionService $permissions,
        private readonly MessagingWebhookSettings $webhooks,
    ) {}

    public function indexNumbers(Request $request, string $domain_uuid): JsonResponse
    {
        $this->domain($domain_uuid);

        return response()->json([
            'object' => 'list',
            'data' => SmsDestinations::query()->where('domain_uuid', $domain_uuid)
                ->orderBy('destination')->get()->map(fn (SmsDestinations $number) => $this->numberPayload($number)),
        ]);
    }

    public function storeNumber(Request $request, string $domain_uuid): JsonResponse
    {
        $this->domain($domain_uuid);
        $data = $this->validatedNumber($request, $domain_uuid, false);

        $number = DB::transaction(function () use ($data, $domain_uuid) {
            $number = new MessageSetting();
            $number->sms_destination_uuid = (string) Str::uuid();
            $number->domain_uuid = $domain_uuid;
            $number->fill(collect($data)->except('allowed_extension_uuids')->all());
            $number->enabled = ($data['enabled'] ?? true) ? 'true' : 'false';
            $number->save();
            $this->participants->assign($number, $data['allowed_extension_uuids']);

            return $number->fresh();
        });

        return response()->json($this->numberPayload($number), 201);
    }

    public function showNumber(Request $request, string $domain_uuid, string $sms_destination_uuid): JsonResponse
    {
        return response()->json($this->numberPayload($this->number($domain_uuid, $sms_destination_uuid)));
    }

    public function updateNumber(Request $request, string $domain_uuid, string $sms_destination_uuid): JsonResponse
    {
        $number = $this->number($domain_uuid, $sms_destination_uuid);
        $data = $this->validatedNumber($request, $domain_uuid, true);

        DB::transaction(function () use ($number, $data) {
            $members = $data['allowed_extension_uuids'] ?? null;
            $number->fill(collect($data)->except('allowed_extension_uuids')->all());
            if (array_key_exists('enabled', $data)) {
                $number->enabled = $data['enabled'] ? 'true' : 'false';
            }
            $number->save();
            if ($members !== null) {
                $this->participants->assign($number, $members);
            }
        });

        return response()->json($this->numberPayload($number->fresh()));
    }

    public function destroyNumber(Request $request, string $domain_uuid, string $sms_destination_uuid): JsonResponse
    {
        $number = $this->number($domain_uuid, $sms_destination_uuid);
        DB::transaction(function () use ($number) {
            DB::table('sms_destination_members')->where('domain_uuid', $number->domain_uuid)
                ->where('sms_destination_uuid', $number->sms_destination_uuid)->delete();
            $number->delete();
        });

        return response()->json(['deleted' => true, 'sms_destination_uuid' => $sms_destination_uuid]);
    }

    public function members(Request $request, string $domain_uuid, string $sms_destination_uuid): JsonResponse
    {
        $number = $this->number($domain_uuid, $sms_destination_uuid);

        return response()->json([
            'sms_destination_uuid' => $number->sms_destination_uuid,
            'allowed_extension_uuids' => $this->participants->assigned($number)->pluck('extension_uuid')->values(),
        ]);
    }

    public function replaceMembers(Request $request, string $domain_uuid, string $sms_destination_uuid): JsonResponse
    {
        $number = $this->number($domain_uuid, $sms_destination_uuid);
        $data = $request->validate([
            'allowed_extension_uuids' => ['required', 'array', 'min:1'],
            'allowed_extension_uuids.*' => ['required', 'uuid'],
        ]);
        $this->participants->assign($number, $data['allowed_extension_uuids']);

        return $this->members($request, $domain_uuid, $sms_destination_uuid);
    }

    public function send(Request $request, string $domain_uuid): JsonResponse
    {
        $this->domain($domain_uuid);
        $data = $request->validate([
            'extension_uuid' => ['required', 'uuid'],
            'from' => ['required', 'string', 'max:40'],
            'to' => ['required', 'array', 'min:1', 'max:20'],
            'to.*' => ['required', 'string', 'max:40'],
            'text' => ['nullable', 'string', 'max:10000'],
            'media_urls' => ['nullable', 'array', 'max:10'],
            'media_urls.*' => ['required', 'url', 'max:2048'],
        ]);
        if (blank($data['text'] ?? null) && empty($data['media_urls'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['text' => [__('Enter a message or include media.')]]);
        }

        $extension = Extensions::without('advSettings')->where('domain_uuid', $domain_uuid)
            ->where('extension_uuid', $data['extension_uuid'])->first();
        if (! $extension || ! $this->mayUseExtension($request, $domain_uuid, $extension->extension_uuid)) {
            throw new ApiException(403, 'permission_error', 'You cannot send as this extension.', 'forbidden');
        }

        $source = $this->participants->normalize($domain_uuid, $data['from']);
        $route = $this->participants->routes($domain_uuid, $extension->extension_uuid)
            ->first(fn ($candidate) => $this->participants->normalize($domain_uuid, $candidate->destination) === $source);
        if (! $route) {
            throw new ApiException(422, 'invalid_request_error', 'The selected extension cannot use this SMS number.', 'invalid_sender', 'from');
        }

        $recipients = $this->groups->recipients($data['to'], $source, get_domain_setting('country', $domain_uuid) ?? 'US');
        if ($recipients === []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['to' => [__('Enter at least one recipient.')]]);
        }

        $group = null;
        if (count($recipients) > 1) {
            if ($route->carrier !== 'sinch') {
                throw \Illuminate\Validation\ValidationException::withMessages(['to' => [__('External group messaging is available through Inteliquent.')]]);
            }
            $group = $this->groups->findOrCreate($domain_uuid, $source, $recipients);
        }

        $message = $this->outbound->create(new CreateOutboundMessageData(
            domainUuid: $domain_uuid,
            extensionUuid: $extension->extension_uuid,
            source: $source,
            destination: $group ? $group->recipients[0] : $recipients[0],
            message: (string) ($data['text'] ?? ''),
            origin: 'api',
            carrier: $route->carrier,
            mediaRemoteUrls: $data['media_urls'] ?? [],
            meta: ['api_user_uuid' => $request->user()->user_uuid],
            messageGroupUuid: $group?->message_group_uuid,
        ));

        return response()->json([
            'message_uuid' => $message->message_uuid,
            'message_group_uuid' => $message->message_group_uuid,
            'status' => $message->status,
        ], 202);
    }

    public function settings(Request $request, string $domain_uuid): JsonResponse
    {
        $this->domain($domain_uuid);

        if ($request->isMethod('get')) {
            return response()->json(array_merge([
                'compress_outbound_photos' => app(\App\Services\Messaging\PhotoCompressionSettings::class)->enabled($domain_uuid),
            ], $this->webhooks->get($domain_uuid)));
        }

        $data = $request->validate([
            'webhook_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\//i'],
            'webhook_enabled' => ['sometimes', 'boolean'],
            'compress_outbound_photos' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('webhook_url', $data) || array_key_exists('webhook_enabled', $data)) {
            $current = $this->webhooks->get($domain_uuid);
            $this->webhooks->set(
                $domain_uuid,
                $data['webhook_url'] ?? $current['webhook_url'],
                $data['webhook_enabled'] ?? $current['webhook_enabled'],
            );
        }

        if (array_key_exists('compress_outbound_photos', $data)) {
            app(\App\Services\Messaging\PhotoCompressionSettings::class)->set($domain_uuid, $data['compress_outbound_photos']);
        }

        return response()->json(array_merge([
            'compress_outbound_photos' => app(\App\Services\Messaging\PhotoCompressionSettings::class)->enabled($domain_uuid),
        ], $this->webhooks->get($domain_uuid)));
    }

    private function number(string $domainUuid, string $uuid): SmsDestinations
    {
        $this->domain($domainUuid);
        return SmsDestinations::where('domain_uuid', $domainUuid)->where('sms_destination_uuid', $uuid)->firstOrFail();
    }

    private function domain(string $uuid): void
    {
        if (! Domain::where('domain_uuid', $uuid)->exists()) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }
    }

    private function mayUseExtension(Request $request, string $domainUuid, string $extensionUuid): bool
    {
        return $extensionUuid === $request->user()->extension_uuid
            || $this->permissions->userHasPermission($request->user(), 'messages_view_as', $domainUuid);
    }

    private function numberPayload(SmsDestinations $number): array
    {
        return [
            'sms_destination_uuid' => $number->sms_destination_uuid,
            'domain_uuid' => $number->domain_uuid,
            'destination' => $number->destination,
            'carrier' => $number->carrier,
            'enabled' => $number->enabled === 'true',
            'description' => $number->description,
            'email' => $number->email,
            'allowed_extension_uuids' => $this->participants->assigned($number)->pluck('extension_uuid')->values(),
            'external_group_mms_supported' => $number->carrier === 'sinch',
        ];
    }

    private function validatedNumber(Request $request, string $domainUuid, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $unique = Rule::unique('v_sms_destinations', 'destination')
            ->where(fn ($query) => $query->where('domain_uuid', $domainUuid));
        if ($partial && $request->route('sms_destination_uuid')) {
            $unique->ignore($request->route('sms_destination_uuid'), 'sms_destination_uuid');
        }
        return $request->validate([
            'destination' => [$required, 'string', 'max:40', $unique],
            'carrier' => [$required, 'string', Rule::in(['apidaze', 'bandwidth', 'bulkvs', 'clicksend', 'thinq', 'fibernetics', 'sinch', 'telnyx', 'twilio', 'voipms', 'voxutel'])],
            'enabled' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'allowed_extension_uuids' => [$partial ? 'sometimes' : 'required', 'array', 'min:1'],
            'allowed_extension_uuids.*' => ['required', 'uuid'],
        ]);
    }
}
