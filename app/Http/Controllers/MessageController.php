<?php

namespace App\Http\Controllers;

use App\Factories\MessageProviderFactory;
use App\Jobs\SendSmsNotificationToSlack;
use App\Mail\SmsToEmail;
use App\Models\Contact;
use App\Models\ContactPhone;
use App\Models\DomainSettings;
use App\Models\Extensions;
use App\Models\Messages;
use App\Models\MessageSetting;
use App\Models\Organization;
use App\Models\SmsDestinations;
use App\Services\Messaging\Outbound\CreateOutboundMessageService;
use App\Services\Messaging\Outbound\Data\CreateOutboundMessageData;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Services\Messaging\RetryMessageService;
use App\Services\Messaging\MessageParticipantService;
use App\Services\Messaging\MessageGroupService;
use App\Models\MessageGroup;
use Inertia\Inertia;
use libphonenumber\PhoneNumberFormat;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MessageController extends Controller
{

    public $model;
    protected $viewName = 'Messages';

    public function __construct()
    {
        $this->model = new Messages();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!userCheckPermission("messages_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'domainUuid' => session('domain_uuid'),
                'currentUserUuid' => auth()->id(),
                'routes' => [
                    'roomsIndex'   => route('messages.rooms'),
                    'prepareConversation' => route('messages.prepare-conversation'),
                    'roomMessages' => route('messages.room.messages', ['roomId' => ':roomId']),
                    'sendMessage'  => route('messages.send'),
                    'markRead'  => route('messages.mark-read'),
                    'hideConversation' => route('messages.hide-conversation'),
                    'deleteConversation' => route('messages.delete-conversation'),
                    'data_route'   => route('messages.data'),
                    'contactStore' => route('contacts.store'),
                    'contactShow'  => route('contacts.show', ['phoneNumber' => ':phoneNumber']),
                    'contactDestroy' => route('contacts.destroy', ['contact' => ':contact']),
                    'organizationsIndex' => route('organizations.index'),
                    'organizationsStore' => route('organizations.store'),
                ],
                'permissions' => function () {
                    return $this->getUserPermissions();
                },
            ]
        );
    }

    public function getData()
    {
        try {
            $extension_uuid = auth()->user()->extension_uuid;
            $domain_uuid = $this->currentDomainUuid();

            // 1. Build Base Extension Query
            $extQuery = Extensions::where('domain_uuid', $domain_uuid)
                ->orderBy('extension');

            // 2. SECURITY: If they don't have 'view_as' permission, ONLY fetch their own extension
            if (!userCheckPermission('messages_view_as')) {
                $extQuery->where('extension_uuid', $extension_uuid);
            }

            // 3. Execute Query
            $extensions = $extQuery->get([
                'extension_uuid',
                'extension',
                'effective_caller_id_name',
            ]);
            $extensionOptions = $extensions->map(function ($ext) use ($extension_uuid, $domain_uuid) {
                $myDids = app(MessageParticipantService::class)->routes($domain_uuid, $ext->extension_uuid);

                $isMe = $ext->extension_uuid === $extension_uuid;

                return [
                    'value' => $ext->extension_uuid,
                    'name' => $isMe ? "{$ext->name_formatted} (Me)" : $ext->name_formatted,
                    'is_me' => $isMe,

                    // Map the DIDs found in memory
                    'dids' => $myDids->map(function ($did) {
                        return [
                            'number' => $did->destination,
                            'label' => $did->description ?? null
                        ];
                    })->values()->all() // Ensure clean array
                ];
            })
                ->sortByDesc('is_me')
                ->values();

            return [
                'extension_uuid' => $extension_uuid,
                'extensions' => $extensionOptions,
            ];
        } catch (\Exception $e) {
            logger('MessagesController@getData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'errors' => ['server' => ['Failed to get data']]], 500);
        }
    }


    public function rooms(Request $request)
    {
        $domainUuid = $this->currentDomainUuid();
        $targetExtensionUuid = $this->authorizedExtension($request->input('extension_uuid'));
        $limit = min((int) $request->input('limit', 50), 200);
        $search = trim((string) $request->input('q', ''));

        // 1. Build Base Query
        // Since data is E.164, we can simply switch columns based on direction
        $base = Messages::query()
            ->selectRaw("
            message_uuid,
            message,
            media,
            created_at,
            extension_uuid,
            -- LOCAL: The number BELONGING to this system
            CASE WHEN direction = 'in' THEN destination ELSE source END AS local_number,
            -- REMOTE: The customer's number
            COALESCE(message_group_uuid::text, CASE WHEN direction = 'in' THEN source ELSE destination END) AS remote_number
        ")
            ->where('domain_uuid', $domainUuid);

        app(\App\Services\Messaging\MessageConversationVisibility::class)->visible($base, auth()->id());

        // Filter by Extension
        $numbers = app(MessageParticipantService::class)->numbers($domainUuid, $targetExtensionUuid);
        $base->where(fn ($q) => $q->where(fn ($in) => $in->where('direction', 'in')->whereIn('destination', $numbers))
            ->orWhere(fn ($out) => $out->where('direction', 'out')->whereIn('source', $numbers)));

        // Search Logic (Simple string match)
        if ($search !== '') {
            $base->where(function ($w) use ($search) {
                $w->where('source', 'ilike', "%{$search}%")
                    ->orWhere('destination', 'ilike', "%{$search}%")
                    ->orWhere('message', 'ilike', "%{$search}%");
                $w->orWhereHas('group', fn ($group) => $group->whereRaw('recipients::text ilike ?', ["%{$search}%"]));
            });
        }

        // 2. Group by the Unique Pair (Local + Remote)
        // Postgres DISTINCT ON works perfectly here
        $latestRooms = DB::query()
            ->fromSub($base, 't')
            ->selectRaw("DISTINCT ON (local_number, remote_number)
            local_number,
            remote_number,
            message_uuid,
            message,
            media,
            created_at
        ")
            ->orderBy('local_number')
            ->orderBy('remote_number')
            ->orderBy('created_at', 'desc');
        $rows = DB::query()->fromSub($latestRooms, 'latest_rooms')
            ->orderByDesc('created_at')->limit(max(1, $limit))->get();

        $groups = MessageGroup::where('domain_uuid', $domainUuid)
            ->whereIn('message_group_uuid', $rows->pluck('remote_number')->filter(fn ($key) => \Illuminate\Support\Str::isUuid($key)))
            ->get()->keyBy('message_group_uuid');
        $remoteNumbers = $rows->pluck('remote_number')->merge($groups->pluck('recipients')->flatten())->unique();

        $directory = $this->contactDirectory($domainUuid, $remoteNumbers);

        $pairs = $rows->map(function ($r) {
            return [
                'to' => $r->local_number,   // Me
                'from' => $r->remote_number // Customer
            ];
        });

        // 2. Fetch Unread Counts in Bulk
        // Personal unread counts exclude incoming messages already answered by the team.

        // Efficiently build a query for these specific pairs
        $visibleUnread = app(\App\Services\Messaging\MessageConversationVisibility::class)->visible(
            app(\App\Services\Messaging\MessageReadService::class)->unread($domainUuid, auth()->id()), auth()->id()
        );
        $unreadCounts = (clone $visibleUnread)
            ->selectRaw('destination, COALESCE(message_group_uuid::text, source) as conversation_key, count(*) as count')
            ->where('direction', 'in')
            ->where('domain_uuid', $this->currentDomainUuid())
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $pair) {
                    $q->orWhere(function ($sub) use ($pair) {
                        $sub->where('destination', $pair['to'])
                            ->whereRaw('COALESCE(message_group_uuid::text, source) = ?', [$pair['from']]);
                    });
                }
            })
            ->groupByRaw('destination, COALESCE(message_group_uuid::text, source)')
            ->get();

        // 3. Map Counts Keyed by "MyDID_CustomerDID"
        $countMap = [];
        foreach ($unreadCounts as $u) {
            $key = "{$u->destination}_{$u->conversation_key}";
            $countMap[$key] = $u->count;
        }

        // 4. Merge into Response
        $rooms = $rows->map(function ($r) use ($countMap, $directory, $groups) {
            $id = "{$r->local_number}_{$r->remote_number}";
            $displayName = $directory[$r->remote_number] ?? $this->formatPhoneNumber($r->remote_number);
            $group = $groups->get($r->remote_number);
            $recipients = $group ? $group->recipients : [$r->remote_number];
            if ($group) $displayName = implode(', ', array_map(fn ($number) => $directory[$number] ?? $this->formatPhoneNumber($number), $recipients));

            // Tell Carbon this raw string is UTC, then format it to an ISO string
            $timestamp = Carbon::parse($r->created_at, 'UTC')->toIsoString();

            $lastMessageText = (string) $r->message;
            if (trim($lastMessageText) === '' && !empty($r->media) && $r->media !== '[]' && $r->media !== 'null') {
                $lastMessageText = '📷 Image';
            }

            return [
                'id' => $id,
                'name' => $displayName,
                'my_number' => $r->local_number,
                'message_group_uuid' => $group?->message_group_uuid,
                'recipients' => $recipients,
                'recipient_contacts' => collect($recipients)->map(fn ($number) => [
                    'number' => $number,
                    'name' => $directory[$number] ?? null,
                ])->values()->all(),
                'avatar' => null,
                'unread' => $countMap[$id] ?? 0,
                'lastMessage' => $lastMessageText,
                'timestamp' => $timestamp,
            ];
        })->sortByDesc('timestamp')->values();

        $unreadTotal = $visibleUnread->whereIn('destination', $numbers)->count();
        return response()->json(['rooms' => $rooms, 'unread_total' => $unreadTotal]);
    }

    // Optional helper if you don't have it yet
    private function formatPhoneNumber($number)
    {
        return $number; // Add formatting logic here if desired
    }

    /**
     * Return the existing account contact name for each supplied phone number.
     * No contact records are created or changed while displaying messages.
     */
    private function contactDirectory(string $domainUuid, iterable $numbers): array
    {
        $numbers = collect($numbers)->filter()->unique()->values();
        if ($numbers->isEmpty()) {
            return [];
        }

        $phones = ContactPhone::whereIn('phone_number', $numbers)
            ->whereHasMorph('phoneable', [Contact::class, Organization::class], function ($query) use ($domainUuid) {
                $query->where('domain_uuid', $domainUuid);
            })
            ->with('phoneable')
            ->get();

        $directory = [];
        foreach ($phones as $phone) {
            $owner = $phone->phoneable;
            if (! $owner) {
                continue;
            }

            $name = $owner instanceof Contact ? $owner->full_name : $owner->name;
            if (trim((string) $name) !== '') {
                $directory[$phone->phone_number] = $name;
            }
        }

        return $directory;
    }

    // --- FETCH CONTACT FOR SIDE PANEL ---
    public function getContact(Request $request, $phoneNumber)
    {
        // Find the phone record
        $phone = ContactPhone::where('phone_number', $phoneNumber)->first();

        if (!$phone || !$phone->phoneable) {
            return response()->json(['contact' => null]);
        }

        $contact = $phone->phoneable;

        // Load related data for the form
        $contact->load(['emails', 'addresses', 'organization', 'phones']);

        // Flatten data for the VueForm (optional, but helps with mapping)
        $data = $contact->toArray();

        // Extract specific fields for the form if needed
        $data['phone_number'] = $phoneNumber; // The specific number we clicked on

        // Grab values from related tables to populate form fields
        $data['email'] = $contact->emails->where('label', 'work')->first()->email_address ?? null;
        $data['website'] = $contact->organization->website ?? null; // Example
        $data['address'] = $contact->addresses->first()->street ?? null; // Simplified

        // Map organization name string
        $data['organization'] = $contact->organization->name ?? null;

        return response()->json(['contact' => $data]);
    }

    // --- NEW: STORE CONTACT FROM SIDE PANEL ---
    public function storeContact(Request $request)
    {
        $data = $request->validate([
            'phone_number' => 'required|string',
            'first_name'   => 'nullable|string',
            'last_name'    => 'nullable|string',
            'email'        => 'nullable|email',
            'website'      => 'nullable|string',
            'organization' => 'nullable|string', // String input from form
            'department'   => 'nullable|string',
            'address'      => 'nullable|string',
            'notes'        => 'nullable|string',
            'mobile_number' => 'nullable|string',
            'fax_number'   => 'nullable|string',
        ]);

        $domainUuid = $this->currentDomainUuid();

        DB::beginTransaction();
        try {
            // 1. Handle Organization
            $orgId = null;
            if (!empty($data['organization'])) {
                $org = Organization::firstOrCreate(
                    ['domain_uuid' => $domainUuid, 'name' => $data['organization']],
                    ['website' => $data['website']]
                );
                $orgId = $org->organization_uuid;
            }

            // 2. Find or Create Contact via Phone Number linkage
            // Logic: Does this phone number already exist?
            $existingPhone = ContactPhone::where('phone_number', $data['phone_number'])->first();

            if ($existingPhone && $existingPhone->phoneable_type === Contact::class) {
                $contact = $existingPhone->phoneable;
                // Update existing
                $contact->update([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'organization_uuid' => $orgId,
                    'department' => $data['department'],
                    'notes' => $data['notes'],
                ]);
            } else {
                // Create New Contact
                $contact = Contact::create([
                    'domain_uuid' => $domainUuid,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'organization_uuid' => $orgId,
                    'department' => $data['department'],
                    'notes' => $data['notes'],
                ]);

                // Create the Phone Link
                $contact->phones()->create([
                    'phone_number' => $data['phone_number'],
                    'label' => 'work' // Default for the chat ID
                ]);
            }

            // 3. Handle Email (Update/Create 'work' email)
            if (!empty($data['email'])) {
                $contact->emails()->updateOrCreate(
                    ['label' => 'work'],
                    ['email_address' => $data['email']]
                );
            }

            // 4. Handle Address (Update/Create 'main' address)
            if (!empty($data['address'])) {
                $contact->addresses()->updateOrCreate(
                    ['label' => 'main'],
                    ['street' => $data['address'], 'domain_uuid' => $domainUuid]
                );
            }

            // 5. Handle Extra Phones (Mobile/Fax)
            if (!empty($data['mobile_number'])) {
                $contact->phones()->updateOrCreate(
                    ['label' => 'mobile'],
                    ['phone_number' => $data['mobile_number']]
                );
            }
            if (!empty($data['fax_number'])) {
                $contact->phones()->updateOrCreate(
                    ['label' => 'fax'],
                    ['phone_number' => $data['fax_number']]
                );
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);
            return response()->json(['message' => 'Failed to save contact'], 500);
        }
    }

    /**
     * Fetch messages for a specific room (Conversation between Extension and External Number)
     */
    public function roomMessages(Request $request, $roomId)
    {
        // 1. Parse Composite ID: "Local_Remote"
        // e.g. "+15551234567_+16469998888"
        $parts = explode('_', $roomId);

        if (count($parts) !== 2) {
            return response()->json(['messages' => []]);
        }

        $local = $parts[0];
        $remote = $parts[1];

        $domainUuid = $this->currentDomainUuid();
        $this->authorizeNumber($local);
        // $targetExtension = ...

        // 2. Query Exact Matches
        $query = app(MessageGroupService::class)->conversation(Messages::where('domain_uuid', $domainUuid), $local, $remote);

        app(\App\Services\Messaging\MessageConversationVisibility::class)->history($query, auth()->id());

        // 3. Pagination & Format (Same as before)
        $rows = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('page.size', 50));

        $directory = $this->contactDirectory(
            $domainUuid,
            collect($rows->items())
                ->filter(fn (Messages $message) => ! in_array(strtolower($message->direction), ['out', 'outbound', 'outgoing']))
                ->pluck('source')
        );

        $messages = collect($rows->items())->map(function ($r) use ($directory) {
            $isOutbound = in_array(strtolower($r->direction), ['out', 'outbound', 'outgoing']);
            return [
                'id' => $r->message_uuid,
                'sender_name' => $isOutbound
                    ? app(MessageParticipantService::class)->sender($r)
                    : ($directory[$r->source] ?? $this->formatPhoneNumber($r->source)),
                'text' => $r->message,
                'role' => $isOutbound ? 'user' : 'ai',
                'timestamp' => $r->created_at->toIsoString(),
                'send_error' => $isOutbound && $r->status === 'failed'
                    ? data_get($r->delivery_meta, 'outbound.provider.error', __('Message could not be sent.')) : null,
                'media' => collect($r->media ?? [])->map(fn ($media, $index) => array_replace($media, [
                    'access_path' => $media['access_path'] ?? route('messages.media.show', [
                        'message_uuid' => $r->message_uuid, 'index' => $index,
                        'file_name' => $media['stored_name'] ?? ('file_'.$index),
                    ], false),
                ]))->all(),
            ];
        });

        return response()->json([
            'messages' => $messages,
            'pagination' => [
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ]
        ]);
    }

    public function hideConversation(Request $request)
    {
        return $this->setConversationVisibility($request, false);
    }

    public function deleteConversation(Request $request)
    {
        return $this->setConversationVisibility($request, true);
    }

    private function setConversationVisibility(Request $request, bool $permanent)
    {
        abort_unless(userCheckPermission('messages_delete'), 403);
        $request->validate(['roomId' => 'required|string|max:100']);
        $parts = explode('_', $request->roomId);
        abort_unless(count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '', 422);
        $this->authorizeNumber($parts[0]);
        $action = $permanent ? 'deleteForUser' : 'hide';
        app(\App\Services\Messaging\MessageConversationVisibility::class)->{$action}(
            $this->currentDomainUuid(), auth()->id(), $parts[0], $parts[1]
        );
        try {
            foreach (app(MessageParticipantService::class)->members($this->currentDomainUuid(), $parts[0]) as $member) {
                broadcast(new \App\Events\ConversationUpdated([
                    'roomId' => $request->roomId, 'deleted_for_user_uuid' => auth()->id(),
                ], $member->extension_uuid));
            }
        } catch (\Throwable $e) {
            logger()->warning('Unable to broadcast personal conversation removal.', ['error' => $e->getMessage()]);
        }
        return response()->json(['success' => true]);
    }

    public function markRead(Request $request)
    {
        $request->validate(['roomId' => 'required|string', 'message_uuids' => 'required|array|max:500', 'message_uuids.*' => 'required|uuid']);

        // Parse Composite ID
        $parts = explode('_', $request->roomId);
        if (count($parts) !== 2) return response()->json([], 400);

        $myDid = $parts[0];
        $customerDid = $parts[1];
        $this->authorizeNumber($myDid);

        app(\App\Services\Messaging\MessageReadService::class)->markRead(
            $this->currentDomainUuid(), auth()->id(), $myDid, $customerDid, $request->message_uuids,
            $this->authorizedExtension($request->input('extension_uuid'))
        );

        return response()->json(['success' => true]);
    }



    private function currentDomainUuid(): string
    {
        $domain = session('domain_uuid');
        if (!$domain) {
            throw new \Exception('domain_uuid not found in session');
        }
        return (string) $domain;
    }

    private function authorizedExtension(?string $extensionUuid): string
    {
        $extensionUuid = $extensionUuid ?: auth()->user()->extension_uuid;
        abort_unless(userCheckPermission('messages_view') && $extensionUuid
            && ($extensionUuid === auth()->user()->extension_uuid || userCheckPermission('messages_view_as')), 403);
        abort_unless(Extensions::where('domain_uuid', $this->currentDomainUuid())
            ->where('extension_uuid', $extensionUuid)->exists(), 403);
        return $extensionUuid;
    }

    private function authorizeNumber(string $number): void
    {
        $extension = $this->authorizedExtension(request('extension_uuid'));
        $participants = app(MessageParticipantService::class);
        abort_unless(in_array($participants->normalize($this->currentDomainUuid(), $number),
            $participants->numbers($this->currentDomainUuid(), $extension), true), 403);
    }

    public function prepareConversation(Request $request, MessageGroupService $groups)
    {
        $data = $request->validate([
            'source' => ['required', 'string'], 'extension_uuid' => ['required', 'uuid'],
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['required', 'string', 'max:40'],
        ]);
        $extensionUuid = $this->authorizedExtension($data['extension_uuid']);
        $domain = $this->currentDomainUuid();
        $local = app(MessageParticipantService::class)->normalize($domain, $data['source']);
        $this->authorizeNumber($local);
        $recipients = $groups->recipients($data['recipients'], $local, get_domain_setting('country', $domain) ?? 'US');
        if (!$recipients) throw \Illuminate\Validation\ValidationException::withMessages(['recipients' => [__('Enter at least one other phone number.')]]);
        $group = null;
        if (count($recipients) > 1) {
            $extension = Extensions::where('domain_uuid', $domain)->findOrFail($extensionUuid);
            $config = $this->getPhoneNumberSmsConfig($local, $extension->extension, $domain);
            if ($config->carrier !== 'sinch') throw \Illuminate\Validation\ValidationException::withMessages([
                'recipients' => [__('Group messaging is available through Inteliquent in FS PBX.')],
            ]);
            $group = $groups->findOrCreate($domain, $local, $recipients);
        }
        return response()->json(['room' => [
            'id' => $local.'_'.($group?->message_group_uuid ?? $recipients[0]),
            'name' => implode(', ', $recipients), 'recipients' => $recipients,
            'message_group_uuid' => $group?->message_group_uuid, 'my_number' => $local,
            'unread' => 0, 'lastMessage' => __('Draft'), 'draft' => true,
        ]]);
    }

    public function send(Request $request, CreateOutboundMessageService $outbound)
    {
        try {
            $data = $request->validate([
                'source' => ['required', 'string'],
                'destination' => ['required_without:message_group_uuid', 'nullable', 'string'],
                'message_group_uuid' => ['nullable', 'uuid'],
                'message' => ['nullable', 'string'],
                'extension_uuid' => ['required', 'string'],
                'media' => ['sometimes', 'array'],
                'media.*' => ['file'],
            ]);

            $extension = Extensions::where('domain_uuid', $this->currentDomainUuid())
                ->findOrFail($this->authorizedExtension($data['extension_uuid']));
            $domainUuid = session('domain_uuid');

            $countryCode = get_domain_setting('country', $domainUuid) ?? 'US';

            $normalizedSource = formatPhoneNumber($data['source'], $countryCode, PhoneNumberFormat::E164);
            $group = !empty($data['message_group_uuid']) ? MessageGroup::where('domain_uuid', $domainUuid)
                ->where('local_number', $normalizedSource)->findOrFail($data['message_group_uuid']) : null;
            $normalizedDestination = $group ? $group->recipients[0]
                : formatPhoneNumber($data['destination'], $countryCode, PhoneNumberFormat::E164);

            $smsConfig = $this->getPhoneNumberSmsConfig(
                $normalizedSource,
                $extension->extension,
                $domainUuid
            );

            $message = $outbound->create(CreateOutboundMessageData::from([
                'domainUuid' => $domainUuid,
                'extensionUuid' => $extension->extension_uuid,
                'source' => $normalizedSource,
                'destination' => $normalizedDestination,
                'messageGroupUuid' => $group?->message_group_uuid,
                'message' => $data['message'] ?? '',
                'origin' => 'portal',
                'carrier' => $smsConfig->carrier,
                'mediaFiles' => $request->file('media', []),
                'meta' => [
                    'requested_by_user_uuid' => session('user_uuid'),
                ],
            ]));

            return response()->json([
                'success' => true,
                'message' => $message,
                'room_id' => $message->roomId(),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]],
            ], 500);
        }
    }


    public function retry(RetryMessageService $retryService)
    {
        try {
            $items = $this->model::whereIn($this->model->getKeyName(), request('items', []))->get();

            messaging_webhook_debug('MessageController retry() called', [
                'requested_ids' => request('items', []),
                'found_count' => $items->count(),
            ]);

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['No messages selected']]
                ], 422);
            }

            $retryService->retryMany($items);

            return response()->json([
                'messages' => ['success' => ['Selected message(s) scheduled for retry']]
            ], 201);
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }


    private function getPhoneNumberSmsConfig($sourceNumber, $extensionNumber, $domainUuid)
    {
        $extensionUuid = Extensions::where('domain_uuid', $domainUuid)->where('extension', $extensionNumber)->value('extension_uuid');
        $participants = app(MessageParticipantService::class);
        $phoneNumberSmsConfig = $participants->routes($domainUuid, $extensionUuid)
            ->first(fn ($r) => $participants->normalize($domainUuid, $r->destination) === $sourceNumber);

        if (!$phoneNumberSmsConfig) {
            throw new \Exception(
                "SMS configuration not found for source {$sourceNumber} on extension {$extensionNumber}"
            );
        }

        return $phoneNumberSmsConfig;
    }

    private function updateMessageStatus($message, $response)
    {
        if (isset($response['result']) && !empty($response['result'])) {
            if (isset($response['result']['messageid'])) {
                $message->status = 'success';
                $message->reference_id = $response['result']['messageid'];
            } else {
                $message->status = 'failed';
                $errorDetail = json_encode($response['result']);
                SendSmsNotificationToSlack::dispatch("*Commio Inbound SMS Failed*.From: " . $message->source . " To: " . $message->extension . "\nRingotel API Error: No message ID received. Details: " . $errorDetail)->onQueue('messages');
            }
        } else {
            $message->status = 'failed';
            $errorDetail = isset($response['error']) ? json_encode($response['error']) : 'Unknown error';
            SendSmsNotificationToSlack::dispatch("*Commio Inbound SMS Failed*.From: " . $message->source . " To: " . $message->extension . "\nRingotel API Failure: " . $errorDetail)->onQueue('messages');
        }
        $message->save();
    }

    public function logs()
    {
        $params = request()->all();
        $params['paginate'] = 50;

        $domainUuid = session('domain_uuid');
        $params['domain_uuid'] = $domainUuid;

        $startPeriod = null;
        $endPeriod = null;

        if (!empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimezone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimezone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod;
        $params['filter']['endPeriod'] = $endPeriod;

        unset($params['filter']['dateRange']);

        $query = QueryBuilder::for(
            Messages::query()->where('domain_uuid', $domainUuid),
            request()->merge($params)
        )
            ->select([
                'message_uuid',
                'extension_uuid',
                'domain_uuid',
                'source',
                'destination',
                'message',
                'direction',
                'type',
                'status',
                'reference_id',
                'media',
                'delivery_meta',
                'read_at',
                'created_at',
            ])
            ->allowedFilters([
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    if ($value) {
                        $query->where('created_at', '>=', $value);
                    }
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    if ($value) {
                        $query->where('created_at', '<=', $value);
                    }
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('source', 'ilike', "%{$value}%")
                            ->orWhere('destination', 'ilike', "%{$value}%")
                            ->orWhere('message', 'ilike', "%{$value}%")
                            ->orWhere('reference_id', 'ilike', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at'])
            ->defaultSort('-created_at');

        if ($params['paginate']) {
            return $query
                ->paginate($params['paginate'])
                ->through(fn($row) => $this->transformLogRow($row));
        }

        return $query
            ->cursor()
            ->map(fn($row) => $this->transformLogRow($row));
    }

    protected function transformLogRow(Messages $row): array
    {
        $media = is_array($row->media) ? $row->media : [];
        $deliveryMeta = is_array($row->delivery_meta) ? $row->delivery_meta : [];

        $providerName = data_get($deliveryMeta, 'provider.name')
            ?? data_get($deliveryMeta, 'outbound.provider.name');

        $statusSummary = $this->buildStatusSummary($row, $deliveryMeta);

        return [
            ...$row->toArray(),
            'provider_name' => $providerName,
            'message_preview' => $this->buildMessagePreview($row->message, $media),
            'status_summary' => $statusSummary,
            'media_count' => count($media),
            'has_media' => !empty($media),
        ];
    }

    protected function buildMessagePreview(?string $message, array $media): string
    {
        $message = trim((string) $message);

        if ($message !== '') {
            return mb_strlen($message) > 70
                ? mb_substr($message, 0, 70) . '...'
                : $message;
        }

        $count = count($media);

        if ($count > 0) {
            $label = $count === 1 ? 'attachment' : 'attachments';
            $firstName = $media[0]['original_name'] ?? null;

            return $firstName
                ? "📎 {$count} {$label} ({$firstName})"
                : "📎 {$count} {$label}";
        }

        return '—';
    }

    protected function buildStatusSummary(Messages $row, array $deliveryMeta): string
    {
        $parts = [];

        $providerName = data_get($deliveryMeta, 'provider.name')
            ?? data_get($deliveryMeta, 'outbound.provider.name');

        $providerStatus = data_get($deliveryMeta, 'provider.status')
            ?? data_get($deliveryMeta, 'outbound.provider.status')
            ?? $row->status;

        if ($providerStatus) {
            $parts[] = ($providerName ? "{$providerName}: " : '') . $providerStatus;
        }

        if ($ringotel = data_get($deliveryMeta, 'ringotel.status')) {
            $parts[] = "ringotel: {$ringotel}";
        }

        if ($email = data_get($deliveryMeta, 'email.status')) {
            $parts[] = "email: {$email}";
        }

        return implode(' • ', $parts);
    }

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['messages_view_as'] = userCheckPermission('messages_view_as');
        $permissions['messages_delete'] = userCheckPermission('messages_delete');

        return $permissions;
    }
}
