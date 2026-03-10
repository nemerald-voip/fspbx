<?php

namespace App\Http\Controllers;

use App\Models\FaxFiles;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FaxSentController extends Controller
{

    protected $viewName = 'FaxSent';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $fax_uuid = request()->route('fax');

        // Check permissions
        if (!userCheckPermission("fax_sent_view")) {
            return redirect('/');
        }

        $domain_uuid = session('domain_uuid');
        $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfDay()->setTimeZone('UTC');
        $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))->endOfDay()->setTimeZone('UTC');

        return Inertia::render(
            $this->viewName,
            [
                'fax_uuid' => $fax_uuid,
                'startPeriod' => function () use ($startPeriod) {
                    return $startPeriod;
                },
                'endPeriod' => function () use ($endPeriod) {
                    return $endPeriod;
                },
                'timezone' => function () use ($domain_uuid) {
                    return get_local_time_zone($domain_uuid);
                },
                'routes' => [
                    'select_all' => route('fax-sent.select.all'),
                    'bulk_delete' => route('fax-sent.bulk.delete'),
                    'data_route' => route('fax-sent.data'),
                    'download' => route('fax-sent.fax.download', ['file' => ':file']),
                ],
                'permissions' => [
                    'delete' => userCheckPermission('fax_sent_delete'),
                ],

            ]
        );
    }

    public function getData()
    {
        $params = request()->all();
        $params['paginate'] = 50;
        $domain_uuid = session('domain_uuid');
        $params['domain_uuid'] = $domain_uuid;

        if (!empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod->getTimestamp();
        $params['filter']['endPeriod'] = $endPeriod->getTimestamp();

        unset(
            $params['filter']['dateRange'],
        );

        $data = QueryBuilder::for(FaxFiles::class, request()->merge($params))
            ->select([
                'fax_file_uuid',
                'domain_uuid',
                'fax_uuid',
                'fax_caller_id_name',
                'fax_caller_id_number',
                'fax_epoch',
                'fax_date',

            ])
            ->where('fax_mode', 'tx')
            ->with(['fax' => function ($query) {
                $query->select('fax_uuid', 'fax_caller_id_number');
            }])
            ->allowedFilters([
                AllowedFilter::callback('fax_uuid', function ($query, $value) {
                    $query->where('fax_uuid', $value);
                }),
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('fax_epoch', '>=', $value);
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('fax_epoch', '<=', $value);
                }),

                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('fax_caller_id_number', 'ilike', "%{$value}%")
                            ->orWhere('fax_caller_id_name', 'ilike', "%{$value}%")
                            ->orWhere('fax_destination', 'ilike', "%{$value}%");
                    });
                }),
            ])
            // Sorting
            ->allowedSorts(['fax_epoch']) // add more if needed
            ->defaultSort('-fax_epoch');

        if ($params['paginate']) {
            $data = $data->paginate($params['paginate']);
        } else {
            $data = $data->cursor();
        }

        return $data;
    }


    /**
     * Get all items
     *
     */
    public function selectAll()
    {
        try {
            $params = request()->all();

            $domain_uuid = session('domain_uuid');
            $params['domain_uuid'] = $domain_uuid;

            if (!empty(data_get($params, 'filter.dateRange'))) {
                $startTs = Carbon::parse(data_get($params, 'filter.dateRange.0'))
                    ->getTimestamp();

                $endTs = Carbon::parse(data_get($params, 'filter.dateRange.1'))
                    ->getTimestamp();

                $params['filter']['startPeriod'] = $startTs;
                $params['filter']['endPeriod']   = $endTs;

                unset($params['filter']['dateRange']);
            }

            $data = QueryBuilder::for(FaxFiles::class, request()->merge($params))
                ->select([
                    'fax_file_uuid',
                    'fax_uuid',

                ])
                ->where('fax_mode', 'tx')
                ->with(['fax' => function ($query) {
                    $query->select('fax_uuid', 'fax_caller_id_number');
                }])
                ->allowedFilters([
                    AllowedFilter::exact('fax_uuid'),
                    AllowedFilter::callback('startPeriod', function ($query, $value) {
                        $query->where('fax_epoch', '>=', $value);
                    }),
                    AllowedFilter::callback('endPeriod', function ($query, $value) {
                        $query->where('fax_epoch', '<=', $value);
                    }),

                    AllowedFilter::callback('search', function ($query, $value) {
                        $query->where(function ($q) use ($value) {
                            $q->where('fax_caller_id_number', 'ilike', "%{$value}%")
                                ->orWhere('fax_caller_id_name', 'ilike', "%{$value}%")
                                ->orWhere('fax_destination', 'ilike', "%{$value}%");
                        });
                    }),
                ])
                // Sorting
                ->allowedSorts(['fax_epoch']) // add more if needed
                ->defaultSort('-fax_epoch')
                ->pluck('fax_file_uuid');

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $data,
            ], 200);
        } catch (\Exception $e) {
            logger('FaxSentController@selectAll error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    public function download($file)
    {
        try {
            $file = FaxFiles::where('fax_file_uuid', $file)
                ->with(['fax' => function ($query) {
                    $query->select('fax_uuid', 'fax_caller_id_number');
                }])
                ->with([
                    'fax:fax_uuid,fax_extension,fax_caller_id_number',
                    'domain:domain_uuid,domain_name',
                ])
                ->firstOrFail();

            if (session('domain_uuid') && $file->domain_uuid !== session('domain_uuid')) {
                return response()->json([
                    'success' => false,
                    'errors' => ['auth' => ['You are not authorized to access this file.']],
                ], 403);
            }

            // Build relative path, keeping original filename base
            $original   = basename($file->fax_file_path);                 // e.g. "+1213...-17-17-03.tif"
            $baseName   = pathinfo($original, PATHINFO_FILENAME);


            // Build relative path on the "fax" disk
            $relative = "{$file->domain->domain_name}/{$file->fax->fax_extension}/sent/{$baseName}.pdf";

            if (!Storage::disk('fax')->exists($relative)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['file' => ['File not found.']],
                ], 404);
            }

            $downloadName = "{$baseName}.pdf";

            return Storage::disk('fax')->download($relative, $downloadName, [
                'Content-Type'  => 'application/pdf',
                'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'errors' => ['file' => ['File not found.']],
            ], 404);
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to download the file.']],
            ], 500);
        }
    }


    /**
     * Remove the specified fax files from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete()
    {
        if (! userCheckPermission('fax_sent_delete')) {
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

            $records = FaxFiles::query()
                ->where('domain_uuid', $domainUuid)
                ->whereIn('fax_file_uuid', $uuids)
                ->select('fax_file_uuid', 'fax_file_path', 'fax_file_type')
                ->get();

            $failed = [];

            foreach ($records as $r) {
                $tiffPath = (string) $r->fax_file_path;

                if ($tiffPath === '') {
                    $failed[] = [
                        'fax_file_uuid' => $r->fax_file_uuid,
                        'reason' => 'fax_file_path is empty',
                    ];
                    continue;
                }

                // Build sibling paths in same directory, same base name
                $dir  = rtrim(pathinfo($tiffPath, PATHINFO_DIRNAME), DIRECTORY_SEPARATOR);
                $base = pathinfo($tiffPath, PATHINFO_FILENAME);

                // Always attempt to delete both the TIFF (whatever ext is in the path) and the PDF
                $candidates = array_values(array_unique(array_filter([
                    $tiffPath,
                    $dir . DIRECTORY_SEPARATOR . $base . '.pdf',

                    // Optional extra safety (uncomment if you sometimes store .tiff instead of .tif)
                    $dir . DIRECTORY_SEPARATOR . $base . '.tif',
                    $dir . DIRECTORY_SEPARATOR . $base . '.tiff',
                ])));

                // Attempt deletes (ignore missing, but track failures to delete existing files)
                foreach ($candidates as $path) {
                    try {
                        if (File::exists($path)) {
                            $ok = File::delete($path);
                            if (! $ok && File::exists($path)) {
                                $failed[] = [
                                    'fax_file_uuid' => $r->fax_file_uuid,
                                    'path' => $path,
                                    'reason' => 'delete returned false',
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        $failed[] = [
                            'fax_file_uuid' => $r->fax_file_uuid,
                            'path' => $path,
                            'reason' => $e->getMessage(),
                        ];
                    }
                }

                // Now safe to delete DB record
                $r->delete();
            }

            // Strict: if any failures, rollback everything so DB/files stay consistent
            if (! empty($failed)) {
                DB::rollBack();

                return response()->json([
                    'messages' => ['error' => ['Some fax files could not be deleted, so no records were removed.']],
                    'failed' => $failed,
                ], 422);
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected fax file(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('FaxSentController@bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected fax file(s).']]
            ], 500);
        }
    }
}
