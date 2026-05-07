<?php

namespace App\Http\Controllers;

use App\Models\ConferenceRoom;
use App\Models\Conferences;
use App\Services\FreeswitchEslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;

class ActiveConferenceController extends Controller
{
    protected int $perPage = 50;

    protected array $searchable = [
        'name',
        'extension',
        'participant_pin',
        'member_count',
        'identifier',
    ];

    protected array $allowedSortFields = [
        'name',
        'extension',
        'participant_pin',
        'member_count',
    ];

    public function index()
    {
        if (! userCheckPermission('conference_active_view')) {
            return redirect('/');
        }

        return Inertia::render('ActiveConferences', [
            'routes' => [
                'current_page' => route('active-conferences.index'),
                'data_route' => route('active-conferences.data'),
                'conference_centers' => route('conference-centers.index'),
                'conference_rooms' => route('conference-rooms.index'),
                'interactive' => url('/active-conferences/:uuid/interactive'),
            ],
            'permissions' => [
                'interactive_view' => userCheckPermission('conference_interactive_view'),
            ],
        ]);
    }

    public function interactive(string $conference)
    {
        if (! userCheckPermission('conference_interactive_view') || ! $this->validConferenceIdentifier($conference)) {
            return redirect('/');
        }

        return Inertia::render('ActiveConferenceInteractive', [
            'conference' => $conference,
            'display_name' => $this->conferenceDisplayName($conference),
            'routes' => [
                'current_page' => route('active-conferences.interactive', ['conference' => $conference]),
                'data_route' => route('active-conferences.interactive.data', ['conference' => $conference]),
                'action' => route('active-conferences.interactive.action', ['conference' => $conference]),
                'active_conferences' => route('active-conferences.index'),
            ],
            'permissions' => $this->interactivePermissions(),
        ]);
    }

    public function getData(Request $request, FreeswitchEslService $eslService): JsonResponse
    {
        if (! userCheckPermission('conference_active_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->activeConferences($request, $eslService);

        return response()->json($this->paginateCollection($items, $this->perPage));
    }

    public function getInteractiveData(string $conference, FreeswitchEslService $eslService): JsonResponse
    {
        if (! userCheckPermission('conference_interactive_view') || ! $this->validConferenceIdentifier($conference)) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $data = $this->interactiveConference($conference, $eslService);

        if (! $data) {
            return response()->json([
                'conference' => [
                    'identifier' => $conference,
                    'name' => $this->conferenceDisplayName($conference),
                    'member_count' => 0,
                    'locked' => false,
                    'recording' => false,
                    'mute_all' => true,
                ],
                'members' => [],
            ]);
        }

        return response()->json($data);
    }

    public function executeInteractiveAction(string $conference, Request $request, FreeswitchEslService $eslService): JsonResponse
    {
        if (! userCheckPermission('conference_interactive_view') || ! $this->validConferenceIdentifier($conference)) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'string'],
            'id' => ['nullable', 'integer'],
            'uuid' => ['nullable', 'uuid'],
            'direction' => ['nullable', 'in:up,down'],
        ]);

        if (! $this->canExecuteInteractiveAction($validated['action'])) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $this->executeConferenceCommand($conference, $validated, $eslService);

            return response()->json([
                'messages' => ['success' => ['Request has been successfully processed.']],
            ]);
        } catch (\Throwable $e) {
            logger('ActiveConferenceController@executeInteractiveAction error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to process request.']],
            ], 500);
        }
    }

    private function activeConferences(Request $request, FreeswitchEslService $eslService): Collection
    {
        $xml = $eslService->executeCommand('conference xml_list');

        if (! $xml || ! isset($xml->conference)) {
            return collect();
        }

        $items = collect();
        $domainName = session('domain_name');

        foreach ($xml->conference as $conference) {
            $fullName = (string) ($conference['name'] ?? '');
            [$identifier, $conferenceDomain] = array_pad(explode('@', $fullName, 2), 2, null);

            if ($conferenceDomain !== $domainName) {
                continue;
            }

            $items->push([
                'identifier' => $identifier,
                'full_name' => $fullName,
                'name' => $identifier,
                'extension' => $identifier,
                'participant_pin' => null,
                'member_count' => (int) ($conference['member-count'] ?? 0),
                'type' => $this->isUuid($identifier) ? 'conference_room' : 'conference',
            ]);
        }

        $items = $this->hydrateConferenceMetadata($items);
        $items = $this->filterItems($items, trim((string) $request->input('filter.search', '')));
        $items = $this->sortItems($items, (string) $request->input('sort', 'name'));

        return $items->values();
    }

    private function hydrateConferenceMetadata(Collection $items): Collection
    {
        $roomUuids = $items
            ->where('type', 'conference_room')
            ->pluck('identifier')
            ->filter()
            ->values();

        $conferenceExtensions = $items
            ->where('type', 'conference')
            ->pluck('identifier')
            ->filter(fn ($identifier) => is_numeric($identifier))
            ->values();

        $rooms = $roomUuids->isEmpty()
            ? collect()
            : QueryBuilder::for(ConferenceRoom::class, new Request())
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('conference_room_uuid', $roomUuids)
                ->with(['conferenceCenter:conference_center_uuid,conference_center_extension'])
                ->get([
                    'conference_room_uuid',
                    'conference_center_uuid',
                    'conference_room_name',
                    'participant_pin',
                ])
                ->keyBy('conference_room_uuid');

        $conferences = $conferenceExtensions->isEmpty()
            ? collect()
            : QueryBuilder::for(Conferences::class, new Request())
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('conference_extension', $conferenceExtensions)
                ->get([
                    'conference_extension',
                    'conference_name',
                    'conference_pin_number',
                ])
                ->keyBy('conference_extension');

        return $items->map(function (array $item) use ($rooms, $conferences) {
            if ($item['type'] === 'conference_room') {
                $room = $rooms->get($item['identifier']);

                if ($room) {
                    $item['name'] = $room->conference_room_name;
                    $item['extension'] = $room->conferenceCenter?->conference_center_extension;
                    $item['participant_pin'] = $room->participant_pin;
                }
            } else {
                $conference = $conferences->get($item['identifier']);

                if ($conference) {
                    $item['name'] = $conference->conference_name;
                    $item['extension'] = $conference->conference_extension;
                    $item['participant_pin'] = $conference->conference_pin_number;
                }
            }

            return $item;
        });
    }

    private function interactiveConference(string $conference, FreeswitchEslService $eslService): ?array
    {
        $conferenceName = $this->conferenceName($conference);
        $xml = $eslService->executeCommand("conference '{$conferenceName}' xml_list");

        if (! $xml || ! isset($xml->conference)) {
            return null;
        }

        $conferenceXml = $xml->conference;
        $members = collect();
        $muteAll = true;

        foreach ($conferenceXml->members->member ?? [] as $member) {
            $canSpeak = (string) ($member->flags->can_speak ?? 'false') === 'true';
            $isModerator = (string) ($member->flags->is_moderator ?? 'false') === 'true';

            if (! $isModerator && $canSpeak) {
                $muteAll = false;
            }

            $uuid = (string) ($member->uuid ?? '');
            $handRaised = $uuid !== ''
                ? trim((string) $eslService->executeCommand("uuid_getvar {$uuid} hand_raised", false)) === 'true'
                : false;

            $members->push([
                'id' => (int) ($member->id ?? 0),
                'uuid' => $uuid,
                'caller_id_name' => urldecode((string) ($member->caller_id_name ?? '')),
                'caller_id_number' => urldecode((string) ($member->caller_id_number ?? '')),
                'join_time' => (int) ($member->join_time ?? 0),
                'last_talking' => (int) ($member->last_talking ?? 0),
                'can_hear' => (string) ($member->flags->can_hear ?? 'false') === 'true',
                'can_speak' => $canSpeak,
                'talking' => (string) ($member->flags->talking ?? 'false') === 'true',
                'has_video' => (string) ($member->flags->has_video ?? 'false') === 'true',
                'has_floor' => (string) ($member->flags->has_floor ?? 'false') === 'true',
                'hand_raised' => $handRaised,
                'is_moderator' => $isModerator,
            ]);
        }

        $eslService->disconnect();

        return [
            'conference' => [
                'identifier' => $conference,
                'full_name' => $conferenceName,
                'name' => $this->conferenceDisplayName($conference),
                'session_uuid' => (string) ($conferenceXml['uuid'] ?? ''),
                'member_count' => (int) ($conferenceXml['member-count'] ?? 0),
                'locked' => (string) ($conferenceXml['locked'] ?? 'false') === 'true',
                'recording' => (string) ($conferenceXml['recording'] ?? 'false') === 'true',
                'mute_all' => $muteAll,
            ],
            'members' => $members->values(),
        ];
    }

    private function executeConferenceCommand(string $conference, array $validated, FreeswitchEslService $eslService): void
    {
        $conferenceName = $this->conferenceName($conference);
        $action = $validated['action'];
        $id = $validated['id'] ?? null;
        $uuid = $validated['uuid'] ?? null;
        $direction = $validated['direction'] ?? null;

        if ($action === 'kick_all') {
            $this->endConference($conferenceName, $eslService);
            return;
        }

        $data = str_replace('_non_moderator', ' non_moderator', $action);
        $command = "conference {$conferenceName} {$data}";

        if ($id) {
            $command .= " {$id}";
        }

        if (in_array($action, ['energy', 'volume_in', 'volume_out'], true)) {
            $response = (string) $eslService->executeCommand($command, false);
            $currentValue = (int) (explode('=', $response)[1] ?? 0);
            $step = $action === 'energy' ? 100 : 1;
            $nextValue = $direction === 'up' ? $currentValue + $step : $currentValue - $step;
            $eslService->executeCommand("{$command} {$nextValue}");
            return;
        }

        if ($action === 'kick' && $uuid) {
            $eslService->executeCommand("uuid_kill {$uuid}");
            return;
        }

        $eslService->executeCommand($command, false);

        if (in_array($action, ['mute', 'unmute', 'mute_non_moderator', 'unmute_non_moderator'], true) && $uuid) {
            $eslService->executeCommand("uuid_setvar {$uuid} hand_raised false", false);
        }

        $eslService->disconnect();
    }

    private function endConference(string $conferenceName, FreeswitchEslService $eslService): void
    {
        $xml = $eslService->executeCommand("conference '{$conferenceName}' xml_list", false);

        if (! $xml || ! isset($xml->conference->members->member)) {
            $eslService->disconnect();
            return;
        }

        foreach ($xml->conference->members->member as $member) {
            $uuid = (string) ($member->uuid ?? '');

            if ($this->isUuid($uuid)) {
                $eslService->executeCommand("uuid_kill {$uuid}", false);
            }
        }

        $eslService->disconnect();
    }

    private function filterItems(Collection $items, string $search): Collection
    {
        if ($search === '') {
            return $items;
        }

        return $items->filter(function (array $item) use ($search) {
            foreach ($this->searchable as $field) {
                if (stripos((string) ($item[$field] ?? ''), $search) !== false) {
                    return true;
                }
            }

            return false;
        });
    }

    private function sortItems(Collection $items, string $sort): Collection
    {
        $descending = str_starts_with($sort, '-');
        $field = ltrim($sort, '-');

        if (! in_array($field, $this->allowedSortFields, true)) {
            $field = 'name';
        }

        return $descending
            ? $items->sortByDesc($field, SORT_NATURAL | SORT_FLAG_CASE)
            : $items->sortBy($field, SORT_NATURAL | SORT_FLAG_CASE);
    }

    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage() ?: 1;

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page
        );

        $paginator->setPath(url()->current());
        $paginator->appends(request()->query());

        return $paginator;
    }

    private function isUuid(?string $value): bool
    {
        return is_string($value)
            && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value) === 1;
    }

    private function validConferenceIdentifier(?string $value): bool
    {
        return $this->isUuid($value) || (is_string($value) && ctype_digit($value));
    }

    private function conferenceName(string $conference): string
    {
        return $conference . '@' . session('domain_name');
    }

    private function conferenceDisplayName(string $conference): string
    {
        if ($this->isUuid($conference)) {
            $room = QueryBuilder::for(ConferenceRoom::class, new Request())
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($conference)
                ->first(['conference_room_uuid', 'conference_room_name']);

            return $room?->conference_room_name ?: $conference;
        }

        $legacyConference = QueryBuilder::for(Conferences::class, new Request())
            ->where('domain_uuid', session('domain_uuid'))
            ->where('conference_extension', $conference)
            ->first(['conference_name', 'conference_extension']);

        return $legacyConference?->conference_name ?: $conference;
    }

    private function interactivePermissions(): array
    {
        return [
            'lock' => userCheckPermission('conference_interactive_lock'),
            'kick' => userCheckPermission('conference_interactive_kick'),
            'energy' => userCheckPermission('conference_interactive_energy'),
            'volume' => userCheckPermission('conference_interactive_volume'),
            'gain' => userCheckPermission('conference_interactive_gain'),
            'mute' => userCheckPermission('conference_interactive_mute'),
            'deaf' => userCheckPermission('conference_interactive_deaf'),
            'video' => userCheckPermission('conference_interactive_video'),
        ];
    }

    private function canExecuteInteractiveAction(string $action): bool
    {
        return match ($action) {
            'lock', 'unlock' => userCheckPermission('conference_interactive_lock'),
            'kick', 'kick_all' => userCheckPermission('conference_interactive_kick'),
            'energy' => userCheckPermission('conference_interactive_energy'),
            'volume_in' => userCheckPermission('conference_interactive_volume'),
            'volume_out' => userCheckPermission('conference_interactive_gain'),
            'mute', 'unmute', 'mute_non_moderator', 'unmute_non_moderator' => userCheckPermission('conference_interactive_mute'),
            'deaf', 'undeaf' => userCheckPermission('conference_interactive_deaf'),
            default => false,
        };
    }
}
