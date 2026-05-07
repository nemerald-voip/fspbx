<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConferenceRoomRequest;
use App\Http\Requests\UpdateConferenceRoomRequest;
use App\Models\ConferenceCenter;
use App\Models\ConferenceRoom;
use App\Services\ConferenceRoomService;
use App\Services\FreeswitchEslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ConferenceRoomController extends Controller
{
    private const TOGGLE_FIELDS = [
        'record',
        'wait_mod',
        'announce_name',
        'announce_count',
        'announce_recording',
        'mute',
        'sounds',
        'enabled',
    ];

    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('conference_room_view')) {
            return redirect('/');
        }

        return Inertia::render('ConferenceRooms', [
            'routes' => [
                'current_page' => route('conference-rooms.index'),
                'data_route' => route('conference-rooms.data'),
                'select_all' => route('conference-rooms.select.all'),
                'bulk_delete' => route('conference-rooms.bulk.delete'),
                'bulk_toggle' => route('conference-rooms.bulk.toggle'),
                'store' => route('conference-rooms.store'),
                'item_options' => route('conference-rooms.item.options'),
                'centers' => route('conference-centers.index'),
                'conference_profiles' => route('conference-profiles.index'),
                'active_conferences' => route('active-conferences.index'),
                'interactive' => url('/active-conferences/:uuid/interactive'),
                'cdr' => '/app/conference_cdr/conference_cdr.php?id=:uuid',
                'sessions' => '/app/conference_centers/conference_sessions.php?id=:uuid',
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function store(StoreConferenceRoomRequest $request, ConferenceRoomService $service): JsonResponse
    {
        try {
            $conferenceRoom = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Conference room created successfully.']],
                'conference_room_uuid' => $conferenceRoom->conference_room_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('ConferenceRoomController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create conference room.']],
            ], 500);
        }
    }

    public function update(UpdateConferenceRoomRequest $request, ConferenceRoom $conference_room, ConferenceRoomService $service): JsonResponse
    {
        if ($conference_room->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $conference_room);

            return response()->json([
                'messages' => ['success' => ['Conference room updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceRoomController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update conference room.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request, ConferenceRoomService $service): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('conference_room_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('conference_room_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $conferenceCenters = QueryBuilder::for(ConferenceCenter::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->defaultSort('conference_center_name')
            ->get(['conference_center_uuid', 'conference_center_name', 'conference_center_extension', 'conference_center_pin_length']);

        if ($itemUuid) {
            $item = QueryBuilder::for(ConferenceRoom::class)
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail();
        } else {
            $centerUuid = $conferenceCenters->first()?->conference_center_uuid;
            $item = new ConferenceRoom();
            $item->conference_center_uuid = $centerUuid;
            $item->profile = 'default';
            $item->record = 'false';
            $item->moderator_pin = $service->generatePin($centerUuid);
            $item->participant_pin = $service->generatePin($centerUuid);
            $item->max_members = 0;
            $item->wait_mod = 'true';
            $item->moderator_endconf = 'false';
            $item->announce_name = 'true';
            $item->announce_recording = 'true';
            $item->announce_count = 'true';
            $item->mute = 'false';
            $item->sounds = 'false';
            $item->enabled = 'true';
        }

        return response()->json([
            'item' => $item,
            'conference_centers' => $conferenceCenters->map(fn ($center) => [
                'value' => $center->conference_center_uuid,
                'label' => trim($center->conference_center_name . ' (' . $center->conference_center_extension . ')'),
                'pin_length' => $center->conference_center_pin_length,
            ]),
            'profiles' => $this->conferenceProfiles(),
            'permissions' => $this->permissions(),
            'routes' => [
                'store_route' => route('conference-rooms.store'),
                'update_route' => $itemUuid ? route('conference-rooms.update', ['conference_room' => $item->conference_room_uuid]) : null,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('conference_room_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $memberCounts = $this->activeMemberCounts();

        return $this->scopedConferenceRooms()
            ->select([
                'domain_uuid',
                'conference_room_uuid',
                'conference_center_uuid',
                'conference_room_name',
                'moderator_pin',
                'participant_pin',
                'record',
                'wait_mod',
                'announce_name',
                'announce_count',
                'announce_recording',
                'mute',
                'sounds',
                'enabled',
                'description',
                'profile',
                'max_members',
            ])
            ->with(['conferenceCenter:conference_center_uuid,conference_center_name,conference_center_extension'])
            ->allowedSorts([
                'conference_room_name',
                'moderator_pin',
                'participant_pin',
                'record',
                'wait_mod',
                'announce_name',
                'announce_count',
                'announce_recording',
                'mute',
                'sounds',
                'enabled',
                'description',
            ])
            ->defaultSort('description')
            ->paginate($this->perPage)
            ->through(function (ConferenceRoom $room) use ($memberCounts) {
                $room->member_count = $memberCounts[$room->conference_room_uuid] ?? 0;
                return $room;
            });
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_room_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedConferenceRooms()
            ->select(['conference_room_uuid'])
            ->defaultSort('description')
            ->pluck('conference_room_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching conference rooms selected.']],
        ]);
    }

    public function bulkDelete(Request $request, ConferenceRoomService $service): JsonResponse
    {
        if (! userCheckPermission('conference_room_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference rooms selected.']],
            ], 422);
        }

        $items = QueryBuilder::for(ConferenceRoom::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('conference_room_uuid', $uuids)
            ->get();

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} conference room(s)."]],
        ]);
    }

    public function bulkToggle(Request $request, ConferenceRoomService $service): JsonResponse
    {
        if (! userCheckPermission('conference_room_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $field = $request->input('field');
        if (! in_array($field, self::TOGGLE_FIELDS, true)) {
            return response()->json([
                'messages' => ['error' => ['Invalid toggle field.']],
            ], 422);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json([
                'messages' => ['error' => ['No conference rooms selected.']],
            ], 422);
        }

        $items = QueryBuilder::for(ConferenceRoom::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('conference_room_uuid', $uuids)
            ->get();

        $service->toggle($items, $field);

        return response()->json([
            'messages' => ['success' => ['Conference room setting toggled.']],
        ]);
    }

    private function scopedConferenceRooms(): QueryBuilder
    {
        return QueryBuilder::for(ConferenceRoom::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('conference_room_name', 'ilike', "%{$needle}%")
                            ->orWhere('moderator_pin', 'ilike', "%{$needle}%")
                            ->orWhere('participant_pin', 'ilike', "%{$needle}%")
                            ->orWhere('account_code', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%");
                    });
                }),
            ]);
    }

    private function activeMemberCounts(): array
    {
        if (! extension_loaded('esl')) {
            return [];
        }

        try {
            $xml = app(FreeswitchEslService::class)->executeCommand('conference xml_list');
        } catch (\Throwable) {
            return [];
        }

        if (! $xml || ! isset($xml->conference)) {
            return [];
        }

        $counts = [];
        foreach ($xml->conference as $conference) {
            $attributes = $conference->attributes();
            $name = (string) ($attributes['name'] ?? '');
            $domain = session('domain_name');

            if (! str_ends_with($name, '@' . $domain)) {
                continue;
            }

            $roomUuid = substr($name, 0, -1 * (strlen($domain) + 1));
            $counts[$roomUuid] = (int) ($attributes['member-count'] ?? 0);
        }

        return $counts;
    }

    private function conferenceProfiles(): array
    {
        return DB::table('v_conference_profiles')
            ->where('profile_enabled', 'true')
            ->where('profile_name', '<>', 'sla')
            ->orderBy('profile_name')
            ->pluck('profile_name')
            ->map(fn ($profile) => ['value' => $profile, 'label' => $profile])
            ->all();
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('conference_room_add'),
            'update' => userCheckPermission('conference_room_edit'),
            'destroy' => userCheckPermission('conference_room_delete'),
            'enabled' => userCheckPermission('conference_room_enabled'),
            'profile' => userCheckPermission('conference_room_profile'),
            'profile_view' => userCheckPermission('conference_profile_view'),
            'record' => userCheckPermission('conference_room_record'),
            'max_members' => userCheckPermission('conference_room_max_members'),
            'wait_mod' => userCheckPermission('conference_room_wait_mod'),
            'moderator_endconf' => userCheckPermission('conference_room_moderator_endconf'),
            'announce_name' => userCheckPermission('conference_room_announce_name'),
            'announce_count' => userCheckPermission('conference_room_announce_count'),
            'announce_recording' => userCheckPermission('conference_room_announce_recording'),
            'mute' => userCheckPermission('conference_room_mute'),
            'sounds' => userCheckPermission('conference_room_sounds'),
            'email_address' => userCheckPermission('conference_room_email_address'),
            'account_code' => userCheckPermission('conference_room_account_code'),
            'interactive_view' => userCheckPermission('conference_interactive_view'),
            'active_view' => userCheckPermission('conference_active_view'),
            'cdr_view' => userCheckPermission('conference_cdr_view'),
            'session_view' => userCheckPermission('conference_session_view'),
            'view_all' => userCheckPermission('conference_room_view_all'),
        ];
    }

    private function validatedUuids(Request $request): array
    {
        return collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();
    }
}
