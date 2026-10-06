<?php

namespace App\Console\Commands;

use App\Jobs\SendS3UploadReport;
use App\Models\CDR;
use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use App\Services\Ha\ActiveNodeResolver;
use App\Services\S3RecordingArchiver;
use App\Services\S3StorageConfigService;
use App\Services\S3UploadServerSelector;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class UploadCallRecordingsToS3Storage extends Command
{
    protected $signature = 'fs:upload-call-recordings-to-s3-storage
        {--scheduled : Apply scheduled upload enablement and server selection}';

    protected $description = 'Upload archived call recordings to S3-compatible object storage';

    public function __construct(
        protected S3StorageConfigService $s3StorageConfigService,
        protected S3UploadServerSelector $selector,
        protected ActiveNodeResolver $activeNode,
        protected S3RecordingArchiver $archiver,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->uploadRecordings((bool) $this->option('scheduled'));

        return self::SUCCESS;
    }

    public function uploadRecordings(bool $scheduled = false)
    {
        // Preserve the explicit operator-run command's historical override.
        // Only the scheduler opts into automatic enablement/server selection.
        $decision = $scheduled ? $this->selector->resolve() : [
            'allowed' => true, 'coordinated' => false,
            'reason' => 'Manual run: bypassing scheduled enablement and server selection.',
        ];
        if (! $decision['allowed']) {
            $this->info('S3 upload skipped: '.$decision['reason']);
            return;
        }
        $this->info('S3 upload selection: '.$decision['reason']);
        $coordinated = $decision['coordinated'];
        $failed = [];
        $success = [];

        // Local receipts recover interrupted cleanup, including when no local
        // CDR pointers remain for the main pending-recordings query.
        foreach ($this->archiver->receipts() as $path => $receipt) {
            $settings = $this->s3StorageConfigService->getSettingsForDomain($receipt['domain_uuid']);
            if (! $settings || $this->isManagedQueueAudio(rtrim($receipt['record_path'], '/').'/'.$receipt['record_name'])) {
                continue;
            }
            try {
                if (! $this->runSelected(
                    $this->recordingKey($receipt['domain_uuid'], $receipt['record_path'], $receipt['record_name']),
                    fn ($authorize) => $this->archiver->cleanup($path, $receipt, $settings, $authorize),
                    $coordinated,
                    $scheduled,
                )) {
                    break;
                }
            } catch (Throwable $exception) {
                $failed[] = ['name' => $receipt['record_name'], 'msg' => $exception->getMessage()];
                report($exception);
            }
        }

        $recordingIds = $this->getCallRecordingIds($this->getUploadLimit());
        if (empty($recordingIds)) {
            $this->info('No recordings found for upload.');
        }
        $domainUuids = CDR::whereIn('xml_cdr_uuid', $recordingIds)->distinct()
            ->pluck('domain_uuid')->filter()->values()->all();
        $storageSettings = $this->s3StorageConfigService->getSettingsMapForDomains($domainUuids);
        $timeZones = $this->getTimeZonesByDomain($domainUuids);

        $this->processRecordingsInChunks($recordingIds, function ($rec) use (
            &$failed, &$success, &$coordinated, $storageSettings, $timeZones, $scheduled
        ) {
            $settings = $storageSettings['domains'][$rec->domain_uuid] ?? $storageSettings['default'];
            if (! $settings) {
                $failed[] = ['name' => $rec->record_name, 'msg' => 'No s3_storage settings found for account.'];
                return;
            }
            $source = rtrim($rec->record_path, '/').'/'.$rec->record_name;
            if ($this->isManagedQueueAudio($source)) {
                $failed[] = ['name' => $rec->record_name, 'msg' => 'Skipped: path is Contact Center hold audio, not a call recording.'];
                return;
            }

            try {
                $name = $rec->record_name;
                return $this->runSelected(
                    $this->recordingKey($rec->domain_uuid, $rec->record_path, $name),
                    function ($authorize, $write) use ($rec, $settings, $timeZones, $name, &$success) {
                        $key = $this->archiver->archive(
                            $rec, $settings, $timeZones['domains'][$rec->domain_uuid] ?? $timeZones['default'],
                            $authorize, $write,
                        );
                        if ($key !== null) {
                            $success[] = $name.' => '.$key;
                        }
                    },
                    $coordinated,
                    $scheduled,
                );
            } catch (Throwable $exception) {
                $failed[] = ['name' => $rec->record_name, 'msg' => $exception->getMessage()];
                report($exception);
            }
        });

        $email = DefaultSettings::where('default_setting_category', 's3_storage')
            ->where('default_setting_subcategory', 'upload_notification_email')
            ->where('default_setting_enabled', true)->value('default_setting_value');
        if ($email) {
            SendS3UploadReport::dispatch(compact('email', 'failed', 'success'))->onQueue('emails');
        }
    }

    /**
     * One bounded recording per claim, so handoff can drain between files.
     * Once coordinated, a running invocation must never downgrade to MAC mode.
     */
    protected function runSelected(string $key, Closure $work, bool &$coordinated, bool $scheduled = true): bool
    {
        $decision = $scheduled ? $this->selector->resolve() : ['allowed' => true, 'coordinated' => false];
        if (! $decision['allowed'] || ($coordinated && ! $decision['coordinated'])) {
            return false;
        }
        $coordinated = $decision['coordinated'];
        if ($coordinated && ! function_exists('pcntl_alarm')) {
            throw new RuntimeException('The pcntl extension is required for coordinated S3 upload deadlines.');
        }

        // Node-local protection also covers manual CLI runs in legacy mode.
        // The generic execution claim supplies the cross-node authority.
        $lock = Cache::lock('s3-recording:'.$key, 900);
        if (! $lock->get()) {
            return true;
        }
        $execution = null;
        $alarmInstalled = false;
        try {
            if ($coordinated) {
                $execution = $this->activeNode->claimExecution('s3_recording_upload', $key, 900);
                if (! $execution) {
                    return false;
                }
                $previousHandler = pcntl_signal_get_handler(SIGALRM);
                $previousAsync = pcntl_async_signals(true);
                pcntl_signal(SIGALRM, fn () => throw new RuntimeException('S3 recording exceeded its execution deadline.', 409));
                pcntl_alarm(840);
                $alarmInstalled = true;
            }

            $authorize = function () use ($execution, $scheduled) {
                if (! $scheduled) {
                    return;
                }
                if ($execution) {
                    $this->activeNode->assertExecution($execution);
                    return;
                }
                $current = $this->selector->resolve();
                if (! $current['allowed'] || $current['coordinated']) {
                    throw new RuntimeException('S3 upload server selection changed; retry on the selected server.', 409);
                }
            };
            $write = function (Closure $mutation) use ($execution, $authorize) {
                if ($execution) {
                    return $this->activeNode->withExecution($execution, $mutation);
                }
                return DB::transaction(function () use ($authorize, $mutation) {
                    $authorize();
                    $result = $mutation();
                    $authorize();
                    return $result;
                });
            };
            $work($authorize, $write);
            if ($execution) {
                $this->activeNode->finishExecution($execution);
            }

            return true;
        } catch (Throwable $exception) {
            if ($execution) {
                $this->activeNode->finishExecution($execution, 'failed', $exception->getMessage());
            }
            throw $exception;
        } finally {
            if ($alarmInstalled) {
                pcntl_alarm(0);
                pcntl_signal(SIGALRM, $previousHandler);
                pcntl_async_signals($previousAsync);
            }
            $lock->release();
        }
    }

    protected function recordingKey(?string $domain, string $path, string $name): string
    {
        return hash('sha256', json_encode([$domain, $path, $name]));
    }

    /**
     * Compiled queue audio shares the recordings disk, but is never archived.
     * Match by position to allow an account named "contact-center-audio".
     */
    protected function isManagedQueueAudio(string $file): bool
    {
        $root = rtrim(Storage::disk('recordings')->path(''), '/').'/';
        if (! str_starts_with($file, $root)) {
            return false;
        }
        $segments = explode('/', substr($file, strlen($root)));

        return ($segments[1] ?? null) === 'contact-center-audio';
    }

    protected function getTimeZonesByDomain(array $domainUuids)
    {
        $default = DefaultSettings::where('default_setting_category', 'domain')
            ->where('default_setting_subcategory', 'time_zone')
            ->where('default_setting_enabled', true)->value('default_setting_value') ?? 'UTC';
        $domains = DomainSettings::whereIn('domain_uuid', $domainUuids)
            ->where('domain_setting_subcategory', 'time_zone')
            ->where('domain_setting_enabled', true)
            ->pluck('domain_setting_value', 'domain_uuid')->filter()->all();

        return compact('default', 'domains');
    }

    protected function getUploadLimit(): int
    {
        $limit = (int) DefaultSettings::where('default_setting_category', 'scheduled_jobs')
            ->where('default_setting_subcategory', 's3_upload_limit')
            ->where('default_setting_enabled', true)->value('default_setting_value');

        return $limit > 0 ? min($limit, 20000) : 2000;
    }

    protected function getCallRecordingIds(int $limit): array
    {
        return CDR::query()->whereNotNull('record_name')->where('record_name', '<>', '')
            ->whereNotNull('record_path')->where('record_path', '<>', '')
            ->where('record_path', 'not like', '%S3%')->where('record_path', 'not like', '%NFS%')
            ->where('hangup_cause', '<>', 'LOSE_RACE')
            ->where('start_stamp', '<=', now()->subMinutes(360))
            ->orderBy('start_stamp', 'asc')->limit($limit)->pluck('xml_cdr_uuid')->all();
    }

    protected function processRecordingsInChunks(array $ids, callable $callback): void
    {
        foreach (array_chunk($ids, 200) as $idChunk) {
            $recordings = CDR::select([
                'xml_cdr_uuid', 'domain_uuid', 'domain_name', 'direction', 'caller_id_number',
                'caller_destination', 'start_stamp', 'record_path', 'record_name',
            ])->whereIn('xml_cdr_uuid', $idChunk)->orderBy('start_stamp', 'asc')->get();
            foreach ($recordings as $rec) {
                if ($callback($rec) === false) {
                    return;
                }
            }
        }
    }
}
