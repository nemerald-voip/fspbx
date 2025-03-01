<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\FaxQueues;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use libphonenumber\PhoneNumberUtil;
use Illuminate\Support\Facades\Cache;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Session;
use libphonenumber\NumberParseException;

class FaxQueueController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'FaxQueue';
    protected $searchable = ['fax_caller_id_number'];

    public function __construct()
    {
        $this->model = new FaxQueues();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("fax_queue_all")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'startPeriod' => function () {
                    return $this->filters['startPeriod'];
                },
                'endPeriod' => function () {
                    return $this->filters['endPeriod'];
                },
                'timezone' => function () {
                    return $this->getTimezone();
                },
                'routes' => [
                    'current_page' => route('faxqueue.index'),
                    'retry' => route('faxqueue.retry'),
                    'select_all' => route('faxqueue.select.all'),
                ]

            ]
        );

        $statuses = ['all' => 'Show All', 'sent' => 'Sent', 'waiting' => 'Waiting', 'failed' => 'Failed', 'sending' => 'Sending'];


    }

    public function getData($paginate = 50)
    {
        if (!empty(request('filterData.dateRange'))) {
            $startPeriod = Carbon::parse(request('filterData.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filterData.dateRange')[1])->setTimeZone('UTC');
        } else {
            $startPeriod = Carbon::now($this->getTimezone())->startOfDay()->setTimeZone('UTC');
            $endPeriod = Carbon::now($this->getTimezone())->endOfDay()->setTimeZone('UTC');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'fax_date'); // Default to 'fax_date'
        $this->sortOrder = request()->get('sortOrder', 'desc'); // Default to descending

        $this->filters = [
            'startPeriod' => $startPeriod,
            'endPeriod' => $endPeriod,
            // 'direction' => request('filterData.direction') ?? null,
            'search' => request('filterData.search') ?? null,
        ];

        // Check if showGlobal parameter is present and not empty
        if (!empty(request('filterData.showGlobal'))) {
            $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
        } else {
            $this->filters['showGlobal'] = null;
        }

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        // logger($data);

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        if (isset($filters['showGlobal']) && $filters['showGlobal']) {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter devices by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter devices by the session's domain_uuid
            $domainUuid = Session::get('domain_uuid');
            $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        }

        $data->select(
            'fax_queue_uuid',
            'domain_uuid',
            'fax_date',
            'fax_number',
            'fax_caller_id_number',
            'fax_email_address',
            'fax_retry_date',
            'fax_retry_count',
            'fax_notify_date',
            'fax_status',
        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (strpos($field, '.') !== false) {
                    // Nested field (e.g., 'extension.name_formatted')
                    [$relation, $nestedField] = explode('.', $field, 2);

                    $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                        $query->where($nestedField, 'ilike', '%' . $value . '%');
                    });
                } else {
                    // Direct field
                    $query->orWhere($field, 'ilike', '%' . $value . '%');
                }
            }
        });
    }

    protected function filterStartPeriod($query, $value)
    {
        $query->where('fax_date', '>=', $value->toIso8601String());
    }

    protected function filterEndPeriod($query, $value)
    {
        $query->where('fax_date', '<=', $value->toIso8601String());
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function destroy($id)
    {
        $faxQueue = FaxQueues::findOrFail($id);

        if (isset($faxQueue)) {
            $deleted = $faxQueue->delete();
            if ($deleted) {
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected entries have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected entries'
                    ]
                ]);
            }
        }
    }

    protected function getTimezone()
    {
        $domainUuid = session('domain_uuid');
        $cacheKey = "{$domainUuid}_timeZone";
    
        return Cache::remember($cacheKey, 600, function () use ($domainUuid) {
            return get_local_time_zone($domainUuid);
        });
    }

    public function retry()
    {    
        $items = request('items', []);
    
        if (empty($items)) {
            return response()->json([
                'status' => 400,
                'error' => ['message' => 'No fax queue items provided.']
            ], 400);
        }
    
        // Retrieve and update the selected fax queue records
        $updated = FaxQueues::whereIn('fax_queue_uuid', $items)->update([
            'fax_status' => 'waiting',
            'fax_retry_count' => 0,
            'fax_retry_date' => null,
            'fax_notify_date' => null,
            'fax_notify_sent' => false,
        ]);
    
        if ($updated) {
            return response()->json([
                'status' => 200,
                'success' => ['message' => 'Selected faxes have been reset for retry.']
            ]);
        } else {
            return response()->json([
                'status' => 500,
                'error' => ['message' => 'Failed to update the selected faxes.']
            ], 500);
        }
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectAll()
    {
        try {
            if (request()->get('showGlobal')) {
                $uuids = $this->model::get($this->model->getKeyName())->pluck($this->model->getKeyName());
            } else {
                $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                    ->get($this->model->getKeyName())->pluck($this->model->getKeyName());
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
    

    public function getStatusOptions()
    {
        return [
            [
                'name' => 'Answered',
                'value' => 'answered'
            ],
            [
                'name' => 'No Answer',
                'value' => 'no_answer'
            ],
            [
                'name' => 'Cancelled',
                'value' => 'cancelled'
            ],
            [
                'name' => 'Voicemail',
                'value' => 'voicemail'
            ],
            [
                'name' => 'Missed Call',
                'value' => 'missed call'
            ],
            [
                'name' => 'Abandoned',
                'value' => 'abandoned'
            ],
        ];
    }
}
