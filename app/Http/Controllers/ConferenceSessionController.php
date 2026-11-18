<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use App\Models\ConferenceRoom;
use App\Models\ConferenceSession;
use App\Models\ConferenceSessionDetail;
use App\Services\ConferenceSessionRecordingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ConferenceSessionController extends Controller
{
    public function index(Request $request, ConferenceRoom $conference_room)
    {
        $this->authorizeRoom($conference_room);

        return $this->page($request, $conference_room);
    }

    public function show(Request $request, ConferenceRoom $conference_room, ConferenceSession $conference_session)
    {
        $this->authorizeSession($conference_room, $conference_session);

        return $this->page($request, $conference_room, $conference_session);
    }

    public function data(Request $request, ConferenceRoom $conference_room)
    {
        $this->authorizeRoom($conference_room);
        $request->validate(['filter.profile' => ['nullable', 'string', 'max:255']]);

        return QueryBuilder::for(ConferenceSession::query()
            ->where('domain_uuid', $conference_room->domain_uuid)
            ->where('meeting_uuid', $conference_room->conference_room_uuid), $request)
            ->allowedFilters([AllowedFilter::partial('profile')])
            ->allowedSorts(['start_epoch', 'end_epoch', 'profile'])
            ->defaultSort('-start_epoch')
            ->orderBy('conference_session_uuid')
            ->paginate(fspbx_pagination_per_page($request))
            ->appends($request->query())
            ->through(fn (ConferenceSession $session) => $this->sessionData($conference_room, $session));
    }

    public function participants(Request $request, ConferenceRoom $conference_room, ConferenceSession $conference_session)
    {
        $this->authorizeSession($conference_room, $conference_session);
        $request->validate(['filter.search' => ['nullable', 'string', 'max:255']]);

        return QueryBuilder::for(ConferenceSessionDetail::query()
            ->where('domain_uuid', $conference_room->domain_uuid)
            ->where('conference_session_uuid', $conference_session->conference_session_uuid), $request)
            ->allowedFilters([AllowedFilter::callback('search', function ($query, $value) {
                $needle = '%' . strtolower((string) $value) . '%';
                $query->where(function ($query) use ($needle) {
                    $query->whereRaw('lower(caller_id_name) like ?', [$needle])
                        ->orWhereRaw('lower(caller_id_number) like ?', [$needle]);
                });
            })])
            ->allowedSorts(['caller_id_name', 'caller_id_number', 'moderator', 'network_addr', 'start_epoch', 'end_epoch'])
            ->defaultSort('start_epoch')
            ->orderBy('conference_session_detail_uuid')
            ->paginate(fspbx_pagination_per_page($request))
            ->appends($request->query())
            ->through(fn (ConferenceSessionDetail $detail) => [
                'conference_session_detail_uuid' => $detail->conference_session_detail_uuid,
                'caller_id_name' => $detail->caller_id_name,
                'caller_id_number' => $detail->caller_id_number,
                'moderator' => $detail->moderator === 'true',
                'network_addr' => $detail->network_addr,
                'start_epoch' => $detail->start_epoch,
                'end_epoch' => $detail->end_epoch,
                'call_details_url' => $this->canViewCallDetails() && Str::isUuid($detail->uuid ?? '')
                    ? route('conference-sessions.call-details', [$conference_room, $conference_session, $detail])
                    : null,
            ]);
    }

    public function callDetails(Request $request, ConferenceRoom $conference_room, ConferenceSession $conference_session, ConferenceSessionDetail $conference_session_detail)
    {
        $this->authorizeSession($conference_room, $conference_session);
        abort_unless($this->canViewCallDetails(), 403);
        abort_unless($conference_session_detail->domain_uuid === $conference_room->domain_uuid
            && $conference_session_detail->conference_session_uuid === $conference_session->conference_session_uuid, 404);
        abort_unless(Str::isUuid($conference_session_detail->uuid ?? ''), 404);

        $cdr = CDR::query()->where('domain_uuid', $conference_room->domain_uuid)
            ->whereKey($conference_session_detail->uuid);
        if (userCheckPermission('xml_cdr_view_self_records') && !userCheckPermission('xml_cdr_view_all_records')) {
            $extensionUuid = $request->user()?->extension_uuid;
            abort_unless(filled($extensionUuid), 403);
            $cdr->where('extension_uuid', $extensionUuid);
        }
        $cdr->firstOrFail();

        $request->merge(['item_uuid' => $conference_session_detail->uuid]);

        return app(CdrsController::class)->getItemOptions();
    }

    public function bulkDelete(Request $request, ConferenceRoom $conference_room)
    {
        $this->authorizeRoom($conference_room);
        abort_unless(userCheckPermission('conference_session_delete'), 403);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ]);

        DB::transaction(function () use ($conference_room, $validated) {
            $sessions = ConferenceSession::query()
                ->where('domain_uuid', $conference_room->domain_uuid)
                ->where('meeting_uuid', $conference_room->conference_room_uuid)
                ->whereIn('conference_session_uuid', $validated['items'])
                ->lockForUpdate()->get();
            abort_unless($sessions->count() === count($validated['items']), 404);

            ConferenceSessionDetail::query()->where('domain_uuid', $conference_room->domain_uuid)
                ->whereIn('conference_session_uuid', $sessions->modelKeys())->delete();
            ConferenceSession::query()->where('domain_uuid', $conference_room->domain_uuid)
                ->where('meeting_uuid', $conference_room->conference_room_uuid)
                ->whereIn('conference_session_uuid', $sessions->modelKeys())->delete();
        });

        // Match the legacy history deletion: recordings on disk are retained.
        return response()->json(['messages' => ['success' => [__('Conference sessions deleted.')]]]);
    }

    public function stream(ConferenceRoom $conference_room, ConferenceSession $conference_session, ConferenceSessionRecordingService $recordings)
    {
        $this->authorizeSession($conference_room, $conference_session);
        abort_unless(userCheckPermission('conference_session_play'), 403);

        return $this->recordingResponse($conference_session, $recordings, false);
    }

    public function download(ConferenceRoom $conference_room, ConferenceSession $conference_session, ConferenceSessionRecordingService $recordings)
    {
        $this->authorizeSession($conference_room, $conference_session);
        abort_unless($this->canDownload(), 403);

        return $this->recordingResponse($conference_session, $recordings, true);
    }

    private function recordingResponse(ConferenceSession $session, ConferenceSessionRecordingService $recordings, bool $download)
    {
        $path = $recordings->path($session);
        abort_if($path === null, 404);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            default => 'audio/wav',
        };

        return response()->download($path, basename($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $download ? 'attachment' : 'inline');
    }

    private function page(Request $request, ConferenceRoom $room, ?ConferenceSession $session = null)
    {
        return Inertia::render('ConferenceSessions', [
            'room' => $room->only('conference_room_uuid', 'conference_room_name', 'description'),
            'conference_session' => $session ? $this->sessionData($room, $session) : null,
            'timezone' => get_local_time_zone($room->domain_uuid),
            'pagination' => [
                'per_page' => fspbx_pagination_per_page($request),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'permissions' => [
                'destroy' => userCheckPermission('conference_session_delete'),
                'rooms' => userCheckPermission('conference_room_view'),
            ],
            'routes' => [
                'rooms' => route('conference-rooms.index'),
                'sessions' => route('conference-sessions.index', $room),
                'data' => $session ? route('conference-sessions.participants', [$room, $session]) : route('conference-sessions.data', $room),
                'bulk_delete' => route('conference-sessions.bulk-delete', $room),
            ],
        ]);
    }

    private function sessionData(ConferenceRoom $room, ConferenceSession $session): array
    {
        $play = userCheckPermission('conference_session_play');
        $download = $this->canDownload();
        $hasRecording = ($play || $download) && app(ConferenceSessionRecordingService::class)->path($session) !== null;

        return [
            'conference_session_uuid' => $session->conference_session_uuid,
            'start_epoch' => $session->start_epoch,
            'end_epoch' => $session->end_epoch,
            'profile' => $session->profile,
            'details_url' => route('conference-sessions.show', [$room, $session]),
            'stream_url' => $play && $hasRecording ? route('conference-sessions.stream', [$room, $session]) : null,
            'download_url' => $download && $hasRecording ? route('conference-sessions.download', [$room, $session]) : null,
        ];
    }

    private function authorizeRoom(ConferenceRoom $room): void
    {
        abort_unless(userCheckPermission('conference_session_view'), 403);
        abort_unless(Str::isUuid(session('domain_uuid') ?? ''), 403);
        abort_unless($room->domain_uuid === session('domain_uuid'), 404);
    }

    private function authorizeSession(ConferenceRoom $room, ConferenceSession $session): void
    {
        $this->authorizeRoom($room);
        abort_unless($session->domain_uuid === $room->domain_uuid
            && $session->meeting_uuid === $room->conference_room_uuid, 404);
    }

    private function canDownload(): bool
    {
        return userCheckPermission('conference_session_play') || userCheckPermission('call_recording_download');
    }

    private function canViewCallDetails(): bool
    {
        return userCheckPermission('conference_session_details') && userCheckPermission('xml_cdr_details');
    }
}
