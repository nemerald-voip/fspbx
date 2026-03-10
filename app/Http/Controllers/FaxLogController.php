<?php

namespace App\Http\Controllers;

use App\Models\FaxLogs; 
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FaxLogController extends Controller
{
    protected $viewName = 'FaxLog';

    public function index()
    {
        // permission: match your old blade permission intent
        // change if your permission key is different
        if (! userCheckPermission('fax_log_view')) {
            return redirect('/');
        }

        $domain_uuid = session('domain_uuid');
        $tz = get_local_time_zone($domain_uuid);

        $startPeriod = Carbon::now($tz)->startOfDay()->setTimezone('UTC');
        $endPeriod   = Carbon::now($tz)->endOfDay()->setTimezone('UTC');

        return Inertia::render($this->viewName, [
            'startPeriod' => fn () => $startPeriod,
            'endPeriod'   => fn () => $endPeriod,
            'timezone'    => fn () => $tz,

            'routes' => [
                'select_all'  => route('fax-logs.select.all'),
                'bulk_delete' => route('fax-logs.bulk.delete'),
                'data_route'  => route('fax-logs.data'),
            ],

            'permissions' => [
                'delete' => userCheckPermission('fax_log_delete'),
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
                'fax_ecm_used',
                'fax_local_station_id',
                'fax_bad_rows',
                'fax_transfer_rate',
                'fax_retry_attempts',
                'fax_retry_limit',
                'fax_retry_sleep',
                'fax_uri',
                'fax_duration',
                'fax_date',
                'fax_epoch',
                'fax_document_transferred_pages',
                'fax_document_total_pages',
            ])
            ->where('domain_uuid', $domain_uuid)
            ->allowedFilters([
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('fax_epoch', '>=', (int) $value);
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('fax_epoch', '<=', (int) $value);
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
}