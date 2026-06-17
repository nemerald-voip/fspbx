<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduledAnnouncementEventRequest;
use App\Http\Requests\StoreScheduledAnnouncementExceptionRequest;
use App\Http\Requests\StoreScheduledAnnouncementScheduleRequest;
use App\Http\Requests\UpdateScheduledAnnouncementEventRequest;
use App\Http\Requests\UpdateScheduledAnnouncementExceptionRequest;
use App\Http\Requests\UpdateScheduledAnnouncementScheduleRequest;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\ScheduledAnnouncementEvent;
use App\Models\ScheduledAnnouncementException;
use App\Models\ScheduledAnnouncementRun;
use App\Models\ScheduledAnnouncementSchedule;
use App\Services\ScheduledAnnouncements\ScheduledAnnouncementService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledAnnouncementController extends Controller
{
    public function index(): Response|\Illuminate\Http\RedirectResponse
    {
        if (! userCheckPermission('scheduled_announcements_list_view')) {
            return redirect('/');
        }

        return Inertia::render('ScheduledAnnouncements', [
            'timezone' => get_local_time_zone(session('domain_uuid')),
            'timezones' => getGroupedTimezones(),
            'permissions' => $this->permissions(),
            'routes' => [
                'current_page' => route('scheduled-announcements.index'),
                'data_route' => route('scheduled-announcements.data'),
                'schedule_store' => route('scheduled-announcements.schedules.store'),
                'schedule_update' => route('scheduled-announcements.schedules.update', ['schedule' => '__UUID__']),
                'schedule_destroy' => route('scheduled-announcements.schedules.destroy', ['schedule' => '__UUID__']),
                'event_store' => route('scheduled-announcements.events.store'),
                'event_update' => route('scheduled-announcements.events.update', ['event' => '__UUID__']),
                'event_destroy' => route('scheduled-announcements.events.destroy', ['event' => '__UUID__']),
                'event_run' => route('scheduled-announcements.events.run', ['event' => '__UUID__']),
                'exception_store' => route('scheduled-announcements.exceptions.store'),
                'exception_update' => route('scheduled-announcements.exceptions.update', ['exception' => '__UUID__']),
                'exception_destroy' => route('scheduled-announcements.exceptions.destroy', ['exception' => '__UUID__']),
                'greeting_route' => route('greetings.greetings'),
                'serve_greeting_route' => route('greeting.file.serve', ['file_name' => ':file_name']),
                'update_greeting_route' => route('greetings.file.update'),
                'delete_greeting_route' => route('greetings.file.delete'),
                'upload_greeting_route' => route('greetings.file.upload'),
                'text_to_speech_route' => route('greetings.textToSpeech'),
                'apply_greeting_route' => route('greeting.file.apply'),
            ],
        ]);
    }

    public function data(): JsonResponse
    {
        $this->authorizePermission('scheduled_announcements_list_view');
        $domainUuid = session('domain_uuid');
        $voiceOptions = $this->voiceOptions();

        return response()->json([
            'schedules' => ScheduledAnnouncementSchedule::where('domain_uuid', $domainUuid)
                ->with([
                    'events' => fn ($query) => $query->orderBy('sort_order')->orderBy('time_of_day'),
                    'exceptions' => fn ($query) => $query->orderBy('exception_date'),
                ])
                ->orderBy('name')
                ->get(),
            'events' => ScheduledAnnouncementEvent::where('domain_uuid', $domainUuid)
                ->with('schedule')
                ->orderBy('time_of_day')
                ->get(),
            'exceptions' => ScheduledAnnouncementException::where('domain_uuid', $domainUuid)
                ->orderByDesc('exception_date')
                ->get(),
            'runs' => ScheduledAnnouncementRun::where('domain_uuid', $domainUuid)
                ->with('event.schedule')
                ->orderByDesc('scheduled_for')
                ->limit(100)
                ->get(),
            'extensions' => Extensions::where('domain_uuid', $domainUuid)
                ->where('enabled', 'true')
                ->orderBy('extension')
                ->get(['extension_uuid', 'extension', 'effective_caller_id_name']),
            'recordings' => $this->recordingOptions($domainUuid),
            'voices' => $voiceOptions['voices'],
            'default_voice' => $voiceOptions['default_voice'],
            'speeds' => $voiceOptions['speeds'],
            'timezones' => getGroupedTimezones(),
            'phone_call_instructions' => [
                'Dial <strong>*732</strong> from your phone.',
                'Enter any extension number when prompted and press <strong>#</strong>.',
                'Follow the prompts to record your greeting.',
            ],
            'sample_message' => 'This is a scheduled announcement.',
        ]);
    }

    public function storeSchedule(StoreScheduledAnnouncementScheduleRequest $request, ScheduledAnnouncementService $service): JsonResponse
    {
        $schedule = $service->saveSchedule($request->validatedData());

        return response()->json(['messages' => ['server' => ['Schedule saved.']], 'schedule' => $schedule]);
    }

    public function updateSchedule(UpdateScheduledAnnouncementScheduleRequest $request, ScheduledAnnouncementSchedule $schedule, ScheduledAnnouncementService $service): JsonResponse
    {
        $this->assertDomain($schedule);
        $schedule = $service->saveSchedule($request->validatedData(), $schedule);

        return response()->json(['messages' => ['server' => ['Schedule updated.']], 'schedule' => $schedule]);
    }

    public function destroySchedule(ScheduledAnnouncementSchedule $schedule): JsonResponse
    {
        $this->authorizePermission('scheduled_announcements_delete');
        $this->assertDomain($schedule);
        ScheduledAnnouncementEvent::where('scheduled_announcement_schedule_uuid', $schedule->scheduled_announcement_schedule_uuid)->delete();
        ScheduledAnnouncementException::where('scheduled_announcement_schedule_uuid', $schedule->scheduled_announcement_schedule_uuid)->delete();
        $schedule->delete();

        return response()->json(['messages' => ['server' => ['Schedule deleted.']]]);
    }

    public function storeEvent(StoreScheduledAnnouncementEventRequest $request, ScheduledAnnouncementService $service): JsonResponse
    {
        $event = $service->saveEvent($request->validatedData());

        return response()->json(['messages' => ['server' => ['Announcement time saved.']], 'event' => $event]);
    }

    public function updateEvent(UpdateScheduledAnnouncementEventRequest $request, ScheduledAnnouncementEvent $event, ScheduledAnnouncementService $service): JsonResponse
    {
        $this->assertDomain($event);
        $event = $service->saveEvent($request->validatedData(), $event);

        return response()->json(['messages' => ['server' => ['Announcement time updated.']], 'event' => $event]);
    }

    public function destroyEvent(ScheduledAnnouncementEvent $event): JsonResponse
    {
        $this->authorizePermission('scheduled_announcements_delete');
        $this->assertDomain($event);
        $event->delete();

        return response()->json(['messages' => ['server' => ['Announcement time deleted.']]]);
    }

    public function runEvent(ScheduledAnnouncementEvent $event, ScheduledAnnouncementService $service): JsonResponse
    {
        $this->authorizePermission('scheduled_announcements_execute');
        $this->assertDomain($event);
        $run = $service->runNow($event);

        return response()->json(['messages' => ['server' => ['Run requested.']], 'run' => $run]);
    }

    public function storeException(StoreScheduledAnnouncementExceptionRequest $request, ScheduledAnnouncementService $service): JsonResponse
    {
        $exception = $service->saveException($request->validatedData());

        return response()->json(['messages' => ['server' => ['Exclusion saved.']], 'exception' => $exception]);
    }

    public function updateException(UpdateScheduledAnnouncementExceptionRequest $request, ScheduledAnnouncementException $exception, ScheduledAnnouncementService $service): JsonResponse
    {
        $this->assertDomain($exception);
        $exception = $service->saveException($request->validatedData(), $exception);

        return response()->json(['messages' => ['server' => ['Exclusion updated.']], 'exception' => $exception]);
    }

    public function destroyException(ScheduledAnnouncementException $exception): JsonResponse
    {
        $this->authorizePermission('scheduled_announcements_delete');
        $this->assertDomain($exception);
        $exception->delete();

        return response()->json(['messages' => ['server' => ['Exclusion deleted.']]]);
    }

    private function recordingOptions(string $domainUuid): array
    {
        return Recordings::where('domain_uuid', $domainUuid)
            ->orderBy('recording_name')
            ->get()
            ->map(fn (Recordings $recording) => [
                'value' => (string) $recording->recording_filename,
                'label' => $recording->recording_name,
                'description' => html_entity_decode(
                    $recording->recording_description ?? '',
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                ),
            ])
            ->values()
            ->all();
    }

    private function voiceOptions(): array
    {
        try {
            $openAiService = app(\App\Services\OpenAIService::class);

            return [
                'voices' => $openAiService->getVoices(),
                'default_voice' => $openAiService->getDefaultVoice(),
                'speeds' => $openAiService->getSpeeds(),
            ];
        } catch (\Throwable) {
            return [
                'voices' => [],
                'default_voice' => null,
                'speeds' => [],
            ];
        }
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(userCheckPermission($permission), 403);
    }

    private function assertDomain(object $model): void
    {
        abort_unless(($model->domain_uuid ?? null) === session('domain_uuid'), 404);
    }

    private function permissions(): array
    {
        return [
            'view' => userCheckPermission('scheduled_announcements_list_view'),
            'create' => userCheckPermission('scheduled_announcements_create'),
            'update' => userCheckPermission('scheduled_announcements_update'),
            'delete' => userCheckPermission('scheduled_announcements_delete'),
            'execute' => userCheckPermission('scheduled_announcements_execute'),
        ];
    }
}
