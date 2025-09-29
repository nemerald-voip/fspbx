<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\FaxFiles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FaxInboxController extends Controller
{

    protected $viewName = 'FaxInbox';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $fax_uuid = request()->route('fax');

        // Check permissions
        if (!userCheckPermission("fax_inbox_view")) {
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
                    // 'current_page' => route('fax-inbox.index'),
                    'select_all' => route('fax-inbox.select.all'),
                    'bulk_delete' => route('fax-inbox.bulk.delete'),
                    'data_route' => route('fax-inbox.data'),
                    'download' => route('fax-inbox.fax.download', ['file' => ':file']),
                ],
                'permissions' => [
                    'delete' => userCheckPermission('fax_inbox_delete'),
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
            ->where('fax_mode', 'rx')
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

        // logger($data);

        return $data;
    }


    /**
     * Get all items
     *
     * @return JsonResponse
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
                ->where('fax_mode', 'rx')
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
            logger('FaxInboxComputer@selectAll error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
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
            $relative = "{$file->domain->domain_name}/{$file->fax->fax_extension}/inbox/{$baseName}.pdf";

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
        // Permission gate — change to your real permission key if needed
        if (! userCheckPermission('fax_inbox_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        request()->validate([
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => ['uuid'], // if your uuids are strings; otherwise adjust
        ]);

        try {
            DB::beginTransaction();

            $domainUuid = session('domain_uuid');
            $uuids = request()->input('items', []);

            /** @var Collection<\App\Models\FaxFiles> $files */
            $files = FaxFiles::query()
                ->where('domain_uuid', $domainUuid)           // domain scope
                ->whereIn('fax_file_uuid', $uuids)
                ->with([
                    'fax:fax_uuid,fax_extension',
                    'domain:domain_uuid,domain_name',
                ])
                ->select('fax_file_uuid', 'domain_uuid', 'fax_uuid', 'fax_file_path')
                ->get();

            // Delete physical files first (best-effort), then DB records
            foreach ($files as $f) {
                $original   = basename($f->fax_file_path);                 // "+1213...-17-17-03.tif"
                $base       = pathinfo($original, PATHINFO_FILENAME);
                $ext        = strtolower(pathinfo($original, PATHINFO_EXTENSION)); // tif/tiff/...
                $domainName = $f->domain?->domain_name;
                $extension  = $f->fax?->fax_extension;

                if ($domainName && $extension) {
                    // Main stored file path (inbox)
                    $relativeMain = "{$domainName}/{$extension}/inbox/{$base}.{$ext}";

                    // If you sometimes also keep a converted PDF or thumbnail, delete them too:
                    $candidatePaths = [
                        $relativeMain,
                        "{$domainName}/{$extension}/inbox/{$base}.pdf",
                        // "{$domainName}/{$extension}/inbox/{$base}.png",
                        // "{$domainName}/{$extension}/inbox/{$base}.jpg",
                    ];

                    // Best-effort delete; ignore missing
                    Storage::disk('fax')->delete(array_filter($candidatePaths));
                }

                // Finally delete the DB record
                $f->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected fax file(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected fax file(s).']]
            ], 500);
        }
    }
}
