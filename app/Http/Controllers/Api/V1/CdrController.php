<?php

namespace App\Http\Controllers\Api\V1;

use Carbon\Carbon;
use App\Models\CDR;
use App\Models\Domain;
use App\Models\Extensions;
use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use App\Models\CallCenterQueues;
use Illuminate\Support\Facades\Session;
use App\Services\Auth\PermissionService;
use App\Services\CdrDataService;
use App\Services\CallRecordingUrlService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CallTranscriptionController;

/**
 * Token-safe V1 CDR controller.
 * All domain context is resolved from the {domain_uuid} route parameter.
 */
class CdrController extends Controller
{
    public function __construct(
        private CdrDataService $cdrDataService,
        private PermissionService $permissionService
    ) {}

    /**
    * Get call detail records with filtering and pagination
    * Route path:
    * - GET /api/v1/domains/{domain_uuid}/call-detail-records/data
     */
    public function getData(Request $request, string $domain_uuid)
    {
        $this->assertValidDomain($request, $domain_uuid);
        $this->seedCdrPermissionsInSession($request->user(), $domain_uuid);

        $params = $request->all();

        foreach (['filter.search', 'filter.searchTerm', 'filterData.search'] as $searchKey) {
            $raw = data_get($params, $searchKey);
            if ($raw === null || $raw === '') {
                continue;
            }

            $search = trim((string) $raw);
            if (preg_match('/[A-Za-z]/', $search)) {
                break;
            }

            $digits = preg_replace('/\D+/', '', $search);
            if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                $digits = substr($digits, 1);
            }

            data_set($params, $searchKey, $digits);
            break;
        }

        $params['paginate'] = (int) $request->input('paginate', 50);
        $params['domain_uuid'] = $domain_uuid;

        [$startPeriod, $endPeriod] = $this->resolveDatePeriod($request, $domain_uuid);

        $params['filter']['startPeriod'] = $startPeriod->getTimestamp();
        $params['filter']['endPeriod'] = $endPeriod->getTimestamp();

        unset(
            $params['filter']['entityType'],
            $params['filter']['dateRange'],
            $params['filter']['start_date'],
            $params['filter']['end_date'],
        );

        $cdrs = $this->cdrDataService->getData($params);

        return response()->json($cdrs);
    }

    /**
    * Get available entities (extensions and contact center queues)
    *
    * Route path:
    * - GET /api/v1/domains/{domain_uuid}/call-detail-records/entities
     */
    public function getEntities(Request $request, string $domain_uuid)
    {
        $this->assertValidDomain($request, $domain_uuid);

        $extensions = Extensions::where('domain_uuid', $domain_uuid)
            ->select('extension_uuid', 'extension', 'effective_caller_id_name')
            ->orderBy('extension', 'asc')
            ->get();

        $queues = CallCenterQueues::where('domain_uuid', $domain_uuid)
            ->select('call_center_queue_uuid', 'queue_extension', 'queue_name')
            ->orderBy('queue_extension', 'asc')
            ->get();

        $entities = [
            [
                'groupLabel' => 'Extensions',
                'groupOptions' => $extensions->map(function ($extension) {
                    return [
                        'value' => $extension->extension_uuid,
                        'label' => $extension->name_formatted,
                        'destination' => $extension->extension,
                        'type' => 'extension',
                    ];
                })->toArray(),
            ],
            [
                'groupLabel' => 'Contact Centers',
                'groupOptions' => $queues->map(function ($queue) {
                    return [
                        'value' => $queue->call_center_queue_uuid,
                        'label' => $queue->queue_name,
                        'destination' => $queue->queue_extension,
                        'type' => 'queue',
                    ];
                })->toArray(),
            ],
        ];

        return response()->json($entities);
    }

    /**
    * Get item options for a specific CDR
    * Route path:
    * - POST /api/v1/domains/{domain_uuid}/call-detail-records/item-options
     */
    public function getItemOptions(Request $request, string $domain_uuid)
    {
        $this->assertValidDomain($request, $domain_uuid);
        $this->assertItemBelongsToDomain($request, $domain_uuid);

        // Delegate to the session-based controller now that domain/item are validated
        return app(\App\Http\Controllers\CdrsController::class)->getItemOptions();
    }

    /**
    * Get recording options and URLs for a CDR
    * Route path:
    * - GET /api/v1/domains/{domain_uuid}/call-detail-records/recording-options
     */
    public function getRecordingOptions(Request $request, string $domain_uuid, CallRecordingUrlService $urlService)
    {
        $this->assertValidDomain($request, $domain_uuid);
        $this->assertItemBelongsToDomain($request, $domain_uuid);

        return app(\App\Http\Controllers\CdrsController::class)->getRecordingOptions($urlService);
    }

    /**
    * Transcribe a call recording
    * Route path:
    * - POST /api/v1/domains/{domain_uuid}/call-detail-records/recordings/transcribe
     */
    public function transcribe(Request $request, string $domain_uuid, CallTranscriptionController $controller)
    {
        $this->assertValidDomain($request, $domain_uuid);
        $request->merge(['domain_uuid' => $domain_uuid]);

        return $controller->transcribe($request);
    }

    /**
     * Get or generate a summary of a call transcription :
     * Retrieves or generates an AI summary of a call's transcription.
     * If a summary already exists, it is returned immediately.
     * Otherwise, a summary is generated asynchronously.
    * Route path:
    * - POST /api/v1/domains/{domain_uuid}/call-detail-records/recordings/summarize
     */
    public function summarize(Request $request, string $domain_uuid, CallTranscriptionController $controller)
    {
        $this->assertValidDomain($request, $domain_uuid);

        return $controller->summarize($request);
    }

    private function assertValidDomain(Request $request, string $domain_uuid): void
    {
        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (!Domain::query()->where('domain_uuid', $domain_uuid)->exists()) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        if (!$request->user()) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }
    }

    private function assertItemBelongsToDomain(Request $request, string $domain_uuid): void
    {
        $itemUuid = (string) $request->input('item_uuid', '');
        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $itemUuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid item UUID.', 'invalid_request', 'item_uuid');
        }

        $item = CDR::query()->select(['xml_cdr_uuid', 'domain_uuid'])->find($itemUuid);
        if (!$item || (string) $item->domain_uuid !== (string) $domain_uuid) {
            throw new ApiException(404, 'invalid_request_error', 'CDR record not found for this domain.', 'resource_missing', 'item_uuid');
        }
    }

    private function seedCdrPermissionsInSession($user, string $domain_uuid): void
    {
        $permissions = [];

        if ($user && $this->permissionService->userHasPermission($user, 'xml_cdr_domain', $domain_uuid)) {
            $permissions[] = (object) ['permission_name' => 'xml_cdr_domain'];
        }

        Session::put('permissions', $permissions);
    }

    private function resolveDatePeriod(Request $request, string $domain_uuid): array
    {
        $timezone = get_local_time_zone($domain_uuid) ?: 'UTC';

        $rawStart = $request->input('start_date', $request->input('filter.start_date'));
        $rawEnd = $request->input('end_date', $request->input('filter.end_date'));

        if ($rawStart || $rawEnd) {
            if ($rawStart && !$rawEnd) {
                $rawEnd = $rawStart;
            }

            if ($rawEnd && !$rawStart) {
                $rawStart = $rawEnd;
            }

            $startPeriod = $this->parseDateBoundary((string) $rawStart, $timezone, true, 'start_date');
            $endPeriod = $this->parseDateBoundary((string) $rawEnd, $timezone, false, 'end_date');

            if ($startPeriod->gt($endPeriod)) {
                throw new ApiException(400, 'invalid_request_error', 'start_date must be before or equal to end_date.', 'invalid_request', 'start_date');
            }

            return [$startPeriod->setTimeZone('UTC'), $endPeriod->setTimeZone('UTC')];
        }

        $dateRange = $request->input('filter.dateRange');
        if (is_array($dateRange) && count($dateRange) === 2 && $dateRange[0] && $dateRange[1]) {
            $startPeriod = $this->parseDateBoundary((string) $dateRange[0], $timezone, true, 'filter.dateRange');
            $endPeriod = $this->parseDateBoundary((string) $dateRange[1], $timezone, false, 'filter.dateRange');

            if ($startPeriod->gt($endPeriod)) {
                throw new ApiException(400, 'invalid_request_error', 'The date range start must be before or equal to the end date.', 'invalid_request', 'filter.dateRange');
            }

            return [$startPeriod->setTimeZone('UTC'), $endPeriod->setTimeZone('UTC')];
        }

        return [
            Carbon::now($timezone)->startOfDay()->setTimeZone('UTC'),
            Carbon::now($timezone)->endOfDay()->setTimeZone('UTC'),
        ];
    }

    private function parseDateBoundary(string $value, string $timezone, bool $isStart, string $field): Carbon
    {
        $trimmed = trim($value);

        try {
            $date = Carbon::parse($trimmed, $timezone);
        } catch (\Throwable $e) {
            throw new ApiException(400, 'invalid_request_error', "Invalid {$field} value. Use YYYY-MM-DD or an ISO-8601 date/time.", 'invalid_request', $field);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            $date = $isStart ? $date->startOfDay() : $date->endOfDay();
        }

        return $date;
    }
}
