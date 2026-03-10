<?php

namespace App\Http\Controllers;

use App\Models\EmailQueue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmailQueueController extends Controller
{
    public $model;
    protected $viewName = 'EmailQueue';

    public function __construct()
    {
        $this->model = new EmailQueue();
    }

    public function index()
    {
        if (!userCheckPermission('email_queue_view')) {
            return redirect('/');
        }

        $domain_uuid = session('domain_uuid');

        $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))
            ->subDays(30)
            ->startOfDay()
            ->setTimeZone('UTC');

        $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))
            ->endOfDay()
            ->setTimeZone('UTC');

        return Inertia::render($this->viewName, [
            'startPeriod' => fn() => $startPeriod,
            'endPeriod' => fn() => $endPeriod,
            'timezone' => fn() => get_local_time_zone($domain_uuid),
            'statusOptions' => fn() => $this->getStatusOptions(),
            'routes' => [
                'current_page' => route('emailqueue.index'),
                'data_route' => route('emailqueue.data'),
                'select_all' => route('emailqueue.select.all'),
                'bulk_delete' => route('emailqueue.bulk.delete'),
                'update_status' => route('emailqueue.update-status'),
            ],
            'permissions' => fn() => $this->getUserPermissions(),
        ]);
    }

    public function getData()
    {
        $params = request()->all();
        $params['paginate'] = 50;
        $params['domain_uuid'] = session('domain_uuid');

        $showGlobal = filter_var(data_get($params, 'filter.showGlobal'), FILTER_VALIDATE_BOOLEAN);

        if (!empty(data_get($params, 'filter.dateRange'))) {
            $params['filter']['startPeriod'] = Carbon::parse(data_get($params, 'filter.dateRange.0'))->toIso8601String();
            $params['filter']['endPeriod'] = Carbon::parse(data_get($params, 'filter.dateRange.1'))->toIso8601String();
            unset($params['filter']['dateRange']);
        }

        unset($params['filter']['showGlobal']);

        $data = QueryBuilder::for(EmailQueue::class, request()->merge($params))
            ->with([
                'domain' => function ($query) {
                    $query->select('domain_uuid', 'domain_name', 'domain_description');
                }
            ])
            ->select([
                'email_queue_uuid',
                'domain_uuid',
                'hostname',
                'email_date',
                'email_from',
                'email_to',
                'email_subject',
                'email_status',
            ])
            ->when(!$showGlobal, function ($query) use ($params) {
                $query->where('domain_uuid', $params['domain_uuid']);
            })
            ->when($showGlobal, function ($query) {
                $domainUuids = collect(Session::get('domains', []))->pluck('domain_uuid');
                $query->whereIn('domain_uuid', $domainUuids);
            })
            ->allowedFilters([
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('email_date', '>=', $value);
                }),

                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('email_date', '<=', $value);
                }),

                AllowedFilter::callback('status', function ($query, $value) {
                    if (blank($value) || $value === 'all') {
                        return;
                    }

                    if ($value === 'blank') {
                        $query->where(function ($q) {
                            $q->whereNull('email_status')
                                ->orWhere('email_status', '');
                        });

                        return;
                    }

                    $query->where('email_status', $value);
                }),

                AllowedFilter::callback('search', function ($query, $value) {
                    if (blank($value)) {
                        return;
                    }

                    $query->where(function ($q) use ($value) {
                        $q->where('hostname', 'ILIKE', "%{$value}%")
                            ->orWhere('email_from', 'ILIKE', "%{$value}%")
                            ->orWhere('email_to', 'ILIKE', "%{$value}%")
                            ->orWhere('email_subject', 'ILIKE', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['email_date', 'email_from', 'email_to', 'email_status', 'hostname'])
            ->defaultSort('-email_date')
            ->paginate($params['paginate'])
            ->through(function ($item) {
                $timezone = get_local_time_zone(session('domain_uuid'));

                return [
                    'email_queue_uuid' => $item->email_queue_uuid,
                    'domain_uuid' => $item->domain_uuid,
                    'hostname' => $item->hostname,
                    'email_from' => $item->email_from,
                    'email_to' => $item->email_to,
                    'email_subject' => $item->email_subject
                        ? (@iconv_mime_decode($item->email_subject, 0, 'UTF-8') ?: $item->email_subject)
                        : null,
                    'email_status' => blank($item->email_status) ? 'blank' : $item->email_status,
                    'email_date' => $item->email_date,
                    'email_date_formatted' => $item->email_date
                        ? Carbon::parse($item->email_date)->setTimezone($timezone)->format('M j, Y g:i A')
                        : null,
                    'domain' => $item->domain ? [
                        'domain_uuid' => $item->domain->domain_uuid,
                        'domain_name' => $item->domain->domain_name,
                        'domain_description' => $item->domain->domain_description,
                    ] : null,
                ];
            });

        return response()->json($data);
    }

    public function updateStatus(Request $request)
    {
        try {
            $items = $request->get('items', []);
            $status = $request->get('status');

            if (empty($items)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['No email queue items were provided.']]
                ], 400);
            }

            EmailQueue::whereIn('email_queue_uuid', $items)
                ->when(!$request->boolean('showGlobal'), function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'));
                })
                ->update([
                    'email_status' => $status,
                ]);

            return response()->json([
                'messages' => ['server' => ['Status updated successfully.']],
            ], 200);
        } catch (\Exception $e) {
            logger('EmailQueueController@updateStatus error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while updating status.']]
            ], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $items = $request->get('items', []);

            if (empty($items)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['No email queue items were provided.']]
                ], 400);
            }

            EmailQueue::whereIn('email_queue_uuid', $items)
                ->when(!$request->boolean('showGlobal'), function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'));
                })
                ->delete();

            return response()->json([
                'messages' => ['server' => ['All selected items have been deleted successfully.']],
            ], 200);
        } catch (\Exception $e) {
            logger('EmailQueueController@bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500);
        }
    }

    public function selectAll()
    {
        try {
            $params = request()->all();
            $params['domain_uuid'] = session('domain_uuid');

            $showGlobal = filter_var(data_get($params, 'filter.showGlobal'), FILTER_VALIDATE_BOOLEAN);

            if (!empty(data_get($params, 'filter.dateRange'))) {
                $params['filter']['startPeriod'] = Carbon::parse(data_get($params, 'filter.dateRange.0'))->toIso8601String();
                $params['filter']['endPeriod'] = Carbon::parse(data_get($params, 'filter.dateRange.1'))->toIso8601String();
                unset($params['filter']['dateRange']);
            }

            unset($params['filter']['showGlobal']);

            $data = QueryBuilder::for(EmailQueue::class, request()->merge($params))
                ->select([
                    'email_queue_uuid',
                    'domain_uuid',
                    'hostname',
                    'email_date',
                    'email_from',
                    'email_to',
                    'email_subject',
                    'email_status',
                ])
                ->when(!$showGlobal, function ($query) use ($params) {
                    $query->where('domain_uuid', $params['domain_uuid']);
                })
                ->when($showGlobal, function ($query) {
                    $domainUuids = collect(Session::get('domains', []))->pluck('domain_uuid');
                    $query->whereIn('domain_uuid', $domainUuids);
                })
                ->allowedFilters([
                    AllowedFilter::callback('startPeriod', function ($query, $value) {
                        $query->where('email_date', '>=', $value);
                    }),

                    AllowedFilter::callback('endPeriod', function ($query, $value) {
                        $query->where('email_date', '<=', $value);
                    }),

                    AllowedFilter::callback('status', function ($query, $value) {
                        if (blank($value) || $value === 'all') {
                            return;
                        }

                        if ($value === 'blank') {
                            $query->where(function ($q) {
                                $q->whereNull('email_status')
                                    ->orWhere('email_status', '');
                            });

                            return;
                        }

                        $query->where('email_status', $value);
                    }),

                    AllowedFilter::callback('search', function ($query, $value) {
                        if (blank($value)) {
                            return;
                        }

                        $query->where(function ($q) use ($value) {
                            $q->where('hostname', 'ILIKE', "%{$value}%")
                                ->orWhere('email_from', 'ILIKE', "%{$value}%")
                                ->orWhere('email_to', 'ILIKE', "%{$value}%")
                                ->orWhere('email_subject', 'ILIKE', "%{$value}%");
                        });
                    }),
                ])
                ->pluck('email_queue_uuid');

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $data,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500);
        }
    }

    public function getStatusOptions()
    {
        return [
            ['name' => 'Show All', 'value' => 'all'],
            ['name' => 'Sent', 'value' => 'sent'],
            ['name' => 'Waiting', 'value' => 'waiting'],
            ['name' => 'Blank', 'value' => 'blank'],
        ];
    }

    public function getUserPermissions()
    {
        return [
            'email_queue_update' => userCheckPermission('email_queue_edit'),
            'email_queue_delete' => userCheckPermission('email_queue_delete'),
        ];
    }
}
