<?php

namespace App\Services\ScheduledAnnouncements;

use App\Models\Extensions;
use App\Models\ScheduledAnnouncementEvent;
use App\Models\ScheduledAnnouncementException;
use App\Models\ScheduledAnnouncementRun;
use App\Models\ScheduledAnnouncementSchedule;
use App\Services\FreeswitchEslService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ScheduledAnnouncementService
{
    private const PLAYBACK_LEAD_IN_MS = 500;

    public function __construct(
        private AuthoritativeDnsActiveNodeGuard $activeNodeGuard,
    ) {
    }

    public function processDueEvents(): void
    {
        ScheduledAnnouncementSchedule::query()
            ->where('enabled', true)
            ->with([
                'domain',
                'events' => fn ($query) => $query->with([
                    'schedule.domain',
                ]),
                'exceptions',
            ])
            ->chunkById(50, function ($schedules) {
                foreach ($schedules as $schedule) {
                    $this->processSchedule($schedule);
                }
            }, 'scheduled_announcement_schedule_uuid', 'scheduled_announcement_schedule_uuid');
    }

    public function runNow(ScheduledAnnouncementEvent $event): ScheduledAnnouncementRun
    {
        $event->loadMissing('schedule.domain');

        $run = ScheduledAnnouncementRun::create([
            'domain_uuid' => $event->domain_uuid,
            'scheduled_announcement_schedule_uuid' => $event->scheduled_announcement_schedule_uuid,
            'scheduled_announcement_event_uuid' => $event->scheduled_announcement_event_uuid,
            'recording_filename' => $event->schedule?->recording_filename,
            'occurrence_key' => 'manual:' . (string) Str::uuid(),
            'scheduled_for' => now(),
            'claimed_at' => now(),
            'claimed_by_hostname' => gethostname() ?: php_uname('n'),
            'status' => 'claimed',
            'manual' => true,
        ]);

        return $this->executeRun($run, $event);
    }

    public function saveSchedule(array $data, ?ScheduledAnnouncementSchedule $schedule = null): ScheduledAnnouncementSchedule
    {
        $events = $data['events'] ?? [];
        $exceptions = $data['exceptions'] ?? [];
        unset($data['events'], $data['exceptions']);

        return DB::transaction(function () use ($data, $events, $exceptions, $schedule) {
            $schedule ??= new ScheduledAnnouncementSchedule();
            $schedule->fill($data);
            $schedule->save();

            ScheduledAnnouncementEvent::where('scheduled_announcement_schedule_uuid', $schedule->scheduled_announcement_schedule_uuid)->delete();
            foreach (array_values($events) as $index => $event) {
                ScheduledAnnouncementEvent::create([
                    'domain_uuid' => $schedule->domain_uuid,
                    'scheduled_announcement_schedule_uuid' => $schedule->scheduled_announcement_schedule_uuid,
                    'time_of_day' => $event['time_of_day'],
                    'weekdays' => array_values(array_unique(array_map('intval', $event['weekdays'] ?? []))),
                    'sort_order' => $index + 1,
                ]);
            }

            ScheduledAnnouncementException::where('scheduled_announcement_schedule_uuid', $schedule->scheduled_announcement_schedule_uuid)->delete();
            foreach (array_values($exceptions) as $exception) {
                ScheduledAnnouncementException::create([
                    'domain_uuid' => $schedule->domain_uuid,
                    'scheduled_announcement_schedule_uuid' => $schedule->scheduled_announcement_schedule_uuid,
                    'exception_date' => $exception['exception_date'],
                    'comment' => $exception['comment'] ?? null,
                ]);
            }

            return $schedule->load(['events', 'exceptions']);
        });
    }

    public function saveEvent(array $data, ?ScheduledAnnouncementEvent $event = null): ScheduledAnnouncementEvent
    {
        $event ??= new ScheduledAnnouncementEvent();
        $event->fill($data);
        $event->save();

        return $event;
    }

    public function saveException(array $data, ?ScheduledAnnouncementException $exception = null): ScheduledAnnouncementException
    {
        $exception ??= new ScheduledAnnouncementException();
        $exception->fill($data);
        $exception->save();

        return $exception;
    }

    private function processSchedule(ScheduledAnnouncementSchedule $schedule): void
    {
        $timezone = $this->scheduleTimezone($schedule);
        $now = now();
        $localToday = $now->copy()->timezone($timezone)->toDateString();

        $exception = $this->exceptionForDate($schedule, $localToday);
        if ($exception || ! $this->scheduleCanRunToday($schedule, $localToday)) {
            return;
        }

        $this->processEventsForDate($schedule, $localToday, $timezone, $now);
    }

    private function scheduleCanRunToday(ScheduledAnnouncementSchedule $schedule, string $localDate): bool
    {
        if ($schedule->starts_on && $localDate < $schedule->starts_on->toDateString()) {
            return false;
        }

        if ($schedule->ends_on && $localDate > $schedule->ends_on->toDateString()) {
            return false;
        }

        return true;
    }

    private function processEventsForDate(ScheduledAnnouncementSchedule $schedule, string $localDate, string $timezone, Carbon $now): void
    {
        foreach ($schedule->events as $event) {
            $scheduledFor = Carbon::parse($localDate . ' ' . $event->time_of_day, $timezone)->utc();
            $occurrenceKey = $event->scheduled_announcement_event_uuid . ':' . $scheduledFor->format('YmdHis');

            if (! $this->eventRunsToday($event, $scheduledFor, $timezone)) {
                continue;
            }

            if ($now->lt($scheduledFor)) {
                continue;
            }

            if ($now->gt($scheduledFor->copy()->addSeconds($this->fireWindowSeconds()))) {
                if ($now->lte($scheduledFor->copy()->addMinutes(10))) {
                    $this->createTerminalRun($event, $occurrenceKey, $scheduledFor, 'missed', 'Announcement was discovered outside the fire window.');
                }
                continue;
            }

            $this->claimAndExecute($event, $occurrenceKey, $scheduledFor);
        }
    }

    private function exceptionForDate(ScheduledAnnouncementSchedule $schedule, string $localDate): ?ScheduledAnnouncementException
    {
        return $schedule->exceptions
            ->first(fn ($item) => $item->exception_date->toDateString() === $localDate);
    }

    private function eventRunsToday(ScheduledAnnouncementEvent $event, Carbon $scheduledFor, string $timezone): bool
    {
        $weekdays = $event->weekdays ?: [];
        if (empty($weekdays)) {
            return true;
        }

        $weekday = (int) $scheduledFor->copy()->timezone($timezone)->isoWeekday();

        return in_array($weekday, array_map('intval', $weekdays), true);
    }

    private function claimAndExecute(ScheduledAnnouncementEvent $event, string $occurrenceKey, Carbon $scheduledFor): ?ScheduledAnnouncementRun
    {
        $activeStatus = $this->activeNodeGuard->canExecute();

        if (! $activeStatus['active']) {
            return $this->createTerminalRun(
                $event,
                $occurrenceKey,
                $scheduledFor,
                $activeStatus['status'] === 'standby' ? 'skipped_standby' : 'skipped_active_unknown',
                $activeStatus['reason'],
                $activeStatus
            );
        }

        try {
            $run = ScheduledAnnouncementRun::create([
                'domain_uuid' => $event->domain_uuid,
                'scheduled_announcement_schedule_uuid' => $event->scheduled_announcement_schedule_uuid,
                'scheduled_announcement_event_uuid' => $event->scheduled_announcement_event_uuid,
                'recording_filename' => $event->schedule?->recording_filename,
                'occurrence_key' => $occurrenceKey,
                'scheduled_for' => $scheduledFor,
                'claimed_at' => now(),
                'claimed_by_hostname' => gethostname() ?: php_uname('n'),
                'dns_answers' => $activeStatus,
                'status' => 'claimed',
            ]);
        } catch (QueryException) {
            return null;
        }

        return $this->executeRun($run, $event);
    }

    private function executeRun(ScheduledAnnouncementRun $run, ScheduledAnnouncementEvent $event): ScheduledAnnouncementRun
    {
        $freeswitchEslService = app(FreeswitchEslService::class);
        $activeStatus = $this->activeNodeGuard->canExecute($freeswitchEslService);

        if (! $activeStatus['active']) {
            $run->update([
                'status' => $activeStatus['status'] === 'standby' ? 'skipped_standby' : 'skipped_active_unknown',
                'dns_answers' => $activeStatus,
                'error_text' => $activeStatus['reason'],
            ]);

            return $run->refresh();
        }

        $event->loadMissing('schedule.domain');

        $media = $this->mediaPlaybackTarget($event);
        if ($media === null) {
            $run->update([
                'status' => 'failed',
                'dns_answers' => $activeStatus,
                'error_text' => 'Announcement recording is not playable.',
            ]);

            return $run->refresh();
        }

        $commands = [];
        $responses = [];
        $errors = [];
        $domainName = $event->schedule?->domain?->domain_name;
        $extensions = $event->schedule ? $this->scheduleExtensions($event->schedule) : collect();
        $busyDestinations = [];

        if ($event->schedule && $this->busyExtensionBehavior($event->schedule) === 'skip' && ! empty($domainName)) {
            $busyDestinations = $this->busyDestinations($freeswitchEslService, $extensions, $domainName);

            if ($busyDestinations === null) {
                $run->update([
                    'status' => 'skipped_busy_unknown',
                    'dns_answers' => $activeStatus,
                    'error_text' => 'Could not determine busy extensions.',
                ]);

                return $run->refresh();
            }
        }

        foreach ($extensions as $extension) {
            if (! $extension || empty($extension->extension) || empty($domainName)) {
                continue;
            }

            $destination = $this->extensionDestination($extension, $domainName);

            if (isset($busyDestinations[$destination])) {
                $responses[] = "Skipped busy extension {$destination}.";
                continue;
            }

            $command = $this->playbackCommand($event, $extension, $domainName, $media);
            $commands[] = $command;

            try {
                $response = $freeswitchEslService->executeCommand($command, false);
                $responses[] = $response;

                if ($response === null || str_starts_with(trim((string) $response), '-ERR')) {
                    $errors[] = trim((string) $response) ?: 'FreeSWITCH returned no response.';
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $status = empty($commands) ? 'failed' : (empty($errors) ? 'executed' : 'failed');
        $errorText = empty($commands) ? 'No selected extensions were available for this schedule.' : implode("\n", $errors);

        if (empty($commands) && ! empty($busyDestinations)) {
            $status = 'skipped_busy';
            $errorText = 'All selected extensions were busy.';
        }

        $run->update([
            'executed_at' => $status === 'executed' ? now() : null,
            'executed_by_hostname' => gethostname() ?: php_uname('n'),
            'status' => $status,
            'dns_answers' => $activeStatus,
            'esl_command' => implode("\n", $commands),
            'esl_response' => implode("\n", array_map(fn ($response) => (string) $response, $responses)),
            'error_text' => $errorText ?: null,
        ]);

        return $run->refresh();
    }

    private function createTerminalRun(
        ScheduledAnnouncementEvent $event,
        string $occurrenceKey,
        Carbon $scheduledFor,
        string $status,
        string $errorText,
        ?array $dnsAnswers = null
    ): ?ScheduledAnnouncementRun {
        try {
            return ScheduledAnnouncementRun::create([
                'domain_uuid' => $event->domain_uuid,
                'scheduled_announcement_schedule_uuid' => $event->scheduled_announcement_schedule_uuid,
                'scheduled_announcement_event_uuid' => $event->scheduled_announcement_event_uuid,
                'recording_filename' => $event->schedule?->recording_filename,
                'occurrence_key' => $occurrenceKey,
                'scheduled_for' => $scheduledFor,
                'claimed_by_hostname' => gethostname() ?: php_uname('n'),
                'status' => $status,
                'dns_answers' => $dnsAnswers,
                'error_text' => $errorText,
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    private function playbackCommand(ScheduledAnnouncementEvent $event, Extensions $extension, string $domainName, string $media): string
    {
        $destination = $this->extensionDestination($extension, $domainName);
        $callerName = $this->sanitizeVariableValue($event->schedule?->name ?: 'Announcement');

        return "bgapi originate {origination_caller_id_name='{$callerName}',origination_caller_id_number=announcement,sip_auto_answer=true,originate_timeout=20}user/{$destination} &playback({$media})";
    }

    private function mediaPlaybackTarget(ScheduledAnnouncementEvent $event): ?string
    {
        $domainName = $event->schedule?->domain?->domain_name;
        $fileName = $this->sanitizeMediaTarget((string) $event->schedule?->recording_filename);

        if (blank($domainName) || blank($fileName)) {
            return null;
        }

        $relativePath = trim($domainName, '/') . '/' . ltrim($fileName, '/');

        if (! Storage::disk('recordings')->exists($relativePath)) {
            return null;
        }

        $path = $this->sanitizeMediaTarget(Storage::disk('recordings')->path($relativePath));

        return 'file_string://silence_stream://' . self::PLAYBACK_LEAD_IN_MS . '!' . $path;
    }

    private function scheduleExtensions(ScheduledAnnouncementSchedule $schedule)
    {
        $extensionUuids = array_values(array_filter(array_map('strval', $schedule->extension_uuids ?? [])));

        if (empty($extensionUuids)) {
            return collect();
        }

        return Extensions::query()
            ->where('domain_uuid', $schedule->domain_uuid)
            ->where('enabled', 'true')
            ->whereIn('extension_uuid', $extensionUuids)
            ->orderBy('extension')
            ->get();
    }

    private function busyExtensionBehavior(ScheduledAnnouncementSchedule $schedule): string
    {
        return in_array($schedule->busy_extension_behavior, ['skip', 'force'], true)
            ? $schedule->busy_extension_behavior
            : 'skip';
    }

    private function busyDestinations(FreeswitchEslService $freeswitchEslService, $extensions, string $domainName): ?array
    {
        $result = $freeswitchEslService->executeCommand('show channels as json', false);

        if (! is_array($result) || str_starts_with(trim((string) ($result['error'] ?? '')), '-ERR')) {
            return null;
        }

        $rows = $result['rows'] ?? [];
        if (! is_array($rows)) {
            return null;
        }

        $busyDestinations = [];
        foreach ($extensions as $extension) {
            if (! $extension || empty($extension->extension)) {
                continue;
            }

            $destination = $this->extensionDestination($extension, $domainName);
            foreach ($rows as $row) {
                if (is_array($row) && str_contains((string) json_encode($row), $destination)) {
                    $busyDestinations[$destination] = true;
                    break;
                }
            }
        }

        return $busyDestinations;
    }

    private function extensionDestination(Extensions $extension, string $domainName): string
    {
        return $this->sanitizeEndpointPart((string) $extension->extension) . '@' . $this->sanitizeEndpointPart($domainName);
    }

    private function sanitizeEndpointPart(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '', $value) ?: '';
    }

    private function sanitizeVariableValue(string $value): string
    {
        return str_replace(["\n", "\r", "'", '\\'], ' ', Str::limit($value, 80, ''));
    }

    private function sanitizeMediaTarget(string $value): string
    {
        return str_replace(["\n", "\r"], '', $value);
    }

    private function scheduleTimezone(ScheduledAnnouncementSchedule $schedule): string
    {
        $timezone = $schedule->timezone ?: get_local_time_zone($schedule->domain_uuid);

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : config('app.timezone', 'UTC');
    }

    private function fireWindowSeconds(): int
    {
        $value = DB::table('v_default_settings')
            ->where('default_setting_category', 'scheduled_jobs')
            ->where('default_setting_subcategory', 'scheduled_announcements_fire_window_seconds')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        return max(1, (int) ($value ?: 15));
    }
}
