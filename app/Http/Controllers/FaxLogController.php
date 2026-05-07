<?php

namespace App\Http\Controllers;

use App\Models\Faxes;
use App\Models\FaxLogs; 
use App\Models\OutboundFax;
use App\Jobs\SendFaxJob;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FaxLogController extends Controller
{
    protected $viewName = 'FaxLog';

    public function index()
    {
        $fax_uuid = request()->route('fax');

        // permission: match your old blade permission intent
        // change if your permission key is different
        if (! userCheckPermission('fax_log_view')) {
            return redirect('/');
        }

        $domain_uuid = session('domain_uuid');
        $tz = get_local_time_zone($domain_uuid);
        $fax = Faxes::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('fax_uuid', $fax_uuid)
            ->first(['fax_uuid', 'fax_name', 'fax_extension']);

        $faxLabel = $fax
            ? trim(implode(' - ', array_filter([$fax->fax_extension, $fax->fax_name])))
            : null;

        $startPeriod = Carbon::now($tz)->startOfDay()->setTimezone('UTC');
        $endPeriod   = Carbon::now($tz)->endOfDay()->setTimezone('UTC');

        return Inertia::render($this->viewName, [
            'fax_uuid' => $fax_uuid,
            'fax_label' => $faxLabel,
            'startPeriod' => fn () => $startPeriod,
            'endPeriod'   => fn () => $endPeriod,
            'timezone'    => fn () => $tz,

            'routes' => [
                'faxes_index' => route('faxes.index'),
                'select_all'  => route('fax-logs.select.all'),
                'bulk_delete' => route('fax-logs.bulk.delete'),
                'data_route'  => route('fax-logs.data'),
                'retry'       => route('fax-logs.retry', ['faxLog' => ':faxLog']),
            ],

            'permissions' => [
                'delete' => userCheckPermission('fax_log_delete'),
                'retry'  => userCheckPermission('fax_send'),
            ],
        ]);
    }

    public function getData()
    {
        $params = request()->all();
        $params['paginate'] = 50;

        $domain_uuid = session('domain_uuid');
        $params['domain_uuid'] = $domain_uuid;

        // Convert dateRange -> startPeriod/endPeriod (epoch)
        if (! empty(data_get($params, 'filter.dateRange'))) {
            $startPeriod = Carbon::parse(data_get($params, 'filter.dateRange.0'))->setTimezone('UTC');
            $endPeriod   = Carbon::parse(data_get($params, 'filter.dateRange.1'))->setTimezone('UTC');

            $params['filter']['startPeriod'] = $startPeriod->getTimestamp();
            $params['filter']['endPeriod']   = $endPeriod->getTimestamp();

            unset($params['filter']['dateRange']);
        }

        $qb = QueryBuilder::for(FaxLogs::class, request()->merge($params))
            ->select([
                'fax_log_uuid',
                'domain_uuid',
                'fax_uuid',
                'fax_success',
                'fax_result_code',
                'fax_result_text',
                'fax_file',
                DB::raw('fax_file as fax_file_path'),
                'source',
                'destination',
                'fax_local_station_id',
                'fax_ecm_used',
                'fax_bad_rows',
                'fax_transfer_rate',
                'fax_duration',
                'fax_uri',
                'fax_date',
                'fax_epoch',
                'fax_document_transferred_pages',
                'fax_document_total_pages',
                'outbound_fax_uuid',
                'outbound_fax_attempt_uuid',
            ])
            ->with([
                'fax' => function ($q) {
                    $q->select([
                        'fax_uuid',
                        'fax_caller_id_number',
                    ]);
                },
                'faxFile' => function ($q) {
                    $q->select([
                        'fax_file_uuid',
                        'fax_uuid', 
                        'fax_caller_id_number',
                        'fax_destination',
                        'domain_uuid',
                        'fax_mode',
                    ]);
                },
                'outboundFax' => function ($q) {
                    $q->select([
                        'outbound_fax_uuid',
                        'status',
                        'total_pages',
                        'retry_count',
                        'retry_limit',
                        'response',
                    ]);
                },
            ])
            ->where('domain_uuid', $domain_uuid)
            ->allowedFilters([
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('fax_epoch', '>=', (int) $value);
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('fax_epoch', '<=', (int) $value);
                }),
                AllowedFilter::callback('fax_uuid', function ($query, $value) {
                    if (!empty($value)) {
                        $query->where('fax_uuid', $value);
                    }
                }),

                // status: "all" | "success" | "failed"
                AllowedFilter::callback('status', function ($query, $value) {
                    if ($value === 'success') {
                        $query->where('fax_success', 1);
                    } elseif ($value === 'failed') {
                        $query->where('fax_success', 0);
                    }
                }),

                AllowedFilter::callback('search', function ($query, $value) {
                    $value = trim((string) $value);
                    if ($value === '') return;

                    $query->where(function ($q) use ($value) {
                        $q->where('fax_result_text', 'ilike', "%{$value}%")
                          ->orWhere('fax_result_code', 'ilike', "%{$value}%")
                          ->orWhere('fax_uri', 'ilike', "%{$value}%")
                          ->orWhere('fax_local_station_id', 'ilike', "%{$value}%")
                          ->orWhere('source', 'ilike', "%{$value}%")
                          ->orWhere('destination', 'ilike', "%{$value}%")
                          ->orWhere('fax_file', 'ilike', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['fax_epoch'])
            ->defaultSort('-fax_epoch');

        return $qb->paginate($params['paginate']);
    }

    public function selectAll()
    {
        try {
            $params = request()->all();
            $domain_uuid = session('domain_uuid');
            $params['domain_uuid'] = $domain_uuid;

            if (! empty(data_get($params, 'filter.dateRange'))) {
                $startTs = Carbon::parse(data_get($params, 'filter.dateRange.0'))->setTimezone('UTC')->getTimestamp();
                $endTs   = Carbon::parse(data_get($params, 'filter.dateRange.1'))->setTimezone('UTC')->getTimestamp();

                $params['filter']['startPeriod'] = $startTs;
                $params['filter']['endPeriod']   = $endTs;

                unset($params['filter']['dateRange']);
            }

            $ids = QueryBuilder::for(FaxLogs::class, request()->merge($params))
                ->select(['fax_log_uuid', 'domain_uuid', 'fax_epoch', 'fax_success'])
                ->where('domain_uuid', $domain_uuid)
                ->allowedFilters([
                    AllowedFilter::callback('startPeriod', fn ($q, $v) => $q->where('fax_epoch', '>=', (int) $v)),
                    AllowedFilter::callback('endPeriod',   fn ($q, $v) => $q->where('fax_epoch', '<=', (int) $v)),
                    AllowedFilter::callback('fax_uuid', function ($q, $v) {
                        if (!empty($v)) {
                            $q->where('fax_uuid', $v);
                        }
                    }),
                    AllowedFilter::callback('status', function ($q, $v) {
                        if ($v === 'success') $q->where('fax_success', 1);
                        if ($v === 'failed')  $q->where('fax_success', 0);
                    }),
                    AllowedFilter::callback('search', function ($q, $value) {
                        $value = trim((string) $value);
                        if ($value === '') return;
                        $q->where(function ($qq) use ($value) {
                            $qq->where('fax_result_text', 'ilike', "%{$value}%")
                               ->orWhere('fax_result_code', 'ilike', "%{$value}%")
                               ->orWhere('fax_uri', 'ilike', "%{$value}%")
                               ->orWhere('fax_local_station_id', 'ilike', "%{$value}%")
                               ->orWhere('source', 'ilike', "%{$value}%")
                               ->orWhere('destination', 'ilike', "%{$value}%")
                               ->orWhere('fax_file', 'ilike', "%{$value}%");
                        });
                    }),
                ])
                ->allowedSorts(['fax_epoch'])
                ->defaultSort('-fax_epoch')
                ->pluck('fax_log_uuid');

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $ids,
            ], 200);
        } catch (\Throwable $e) {
            logger('FaxLogController@selectAll error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'errors' => ['server' => ['Failed to select all items']]
            ], 500);
        }
    }

    public function bulkDelete()
    {
        if (! userCheckPermission('fax_log_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        request()->validate([
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => ['uuid'],
        ]);

        $domainUuid = session('domain_uuid');
        $uuids = request()->input('items', []);

        try {
            DB::beginTransaction();

            $records = FaxLogs::query()
                ->where('domain_uuid', $domainUuid)
                ->whereIn('fax_log_uuid', $uuids)
                ->select('fax_log_uuid', 'fax_file')
                ->get();

            $failed = [];

            foreach ($records as $r) {
                $tiffPath = (string) $r->fax_file;

                // Try to delete files if present (best-effort, but STRICT rollback if delete fails on existing file)
                if ($tiffPath !== '') {
                    $dir  = rtrim(pathinfo($tiffPath, PATHINFO_DIRNAME), DIRECTORY_SEPARATOR);
                    $base = pathinfo($tiffPath, PATHINFO_FILENAME);

                    $candidates = array_values(array_unique(array_filter([
                        $tiffPath,
                        $dir . DIRECTORY_SEPARATOR . $base . '.pdf',
                        $dir . DIRECTORY_SEPARATOR . $base . '.tif',
                        $dir . DIRECTORY_SEPARATOR . $base . '.tiff',
                    ])));

                    foreach ($candidates as $path) {
                        try {
                            if (File::exists($path)) {
                                $ok = File::delete($path);
                                if (! $ok && File::exists($path)) {
                                    $failed[] = [
                                        'fax_log_uuid' => $r->fax_log_uuid,
                                        'path' => $path,
                                        'reason' => 'delete returned false',
                                    ];
                                }
                            }
                        } catch (\Throwable $e) {
                            $failed[] = [
                                'fax_log_uuid' => $r->fax_log_uuid,
                                'path' => $path,
                                'reason' => $e->getMessage(),
                            ];
                        }
                    }
                }

                // Delete DB row (we’ll rollback if any file deletes failed)
                $r->delete();
            }

            if (! empty($failed)) {
                DB::rollBack();

                return response()->json([
                    'messages' => ['error' => ['Some fax log files could not be deleted, so no records were removed.']],
                    'failed' => $failed,
                ], 422);
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected fax log(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('FaxLogController@bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected fax log(s).']]
            ], 500);
        }
    }

    public function retryOutbound(string $faxLog)
    {
        if (! userCheckPermission('fax_send')) {
            return response()->json([
                'errors' => ['retry' => ['Access denied.']]
            ], 403);
        }

        $domainUuid = session('domain_uuid');

        $log = FaxLogs::query()
            ->with('outboundFax')
            ->where('domain_uuid', $domainUuid)
            ->where('fax_log_uuid', $faxLog)
            ->first();

        if (!$log || !$log->outbound_fax_uuid || !$log->outboundFax) {
            return response()->json([
                'errors' => ['retry' => ['Only outbound fax log rows can be retried.']]
            ], 422);
        }

        if ((string) $log->fax_success === '1') {
            return response()->json([
                'errors' => ['retry' => ['Successful fax attempts do not need to be retried.']]
            ], 422);
        }

        if ($log->outboundFax->status !== 'failed') {
            return response()->json([
                'errors' => ['retry' => ['Only failed outbound faxes can be retried.']]
            ], 422);
        }

        $updated = OutboundFax::query()
            ->where('domain_uuid', $domainUuid)
            ->where('outbound_fax_uuid', $log->outbound_fax_uuid)
            ->where('status', 'failed')
            ->update([
                'status'               => 'waiting',
                'retry_count'          => 0,
                'retry_at'             => now(),
                'call_uuid'            => null,
                'current_attempt_uuid' => (string) Str::uuid(),
                'notify_sent_at'       => null,
                'response'             => 'Manual retry requested from fax log ' . $log->fax_log_uuid,
            ]);

        if ($updated === 0) {
            return response()->json([
                'errors' => ['retry' => ['The outbound fax could not be queued for retry.']]
            ], 409);
        }

        SendFaxJob::dispatch($log->outbound_fax_uuid);

        return response()->json([
            'messages' => ['success' => ['Outbound fax queued for retry.']]
        ]);
    }
}
