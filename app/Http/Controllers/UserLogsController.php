<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\FaxQueues;
use App\Models\UserLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

class UserLogsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'UserLogs';
    protected $searchable = ['remote_address', 'username', 'user.user_email'];

    public function __construct()
    {
        $this->model = new UserLog();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("user_log_view")) {
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
                    return get_local_time_zone(session('domain_uuid'));
                },
                'routes' => [
                    'current_page' => route('user-logs.index'),
                    'select_all' => route('user-logs.select.all'),
                ]

            ]
        );

    }

    public function getData($paginate = 50)
    {
        if (!empty(request('filterData.dateRange'))) {
            $startPeriod = Carbon::parse(request('filterData.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filterData.dateRange')[1])->setTimeZone('UTC');
        } else {
            $domain_uuid = session('domain_uuid');
            $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfDay()->setTimeZone('UTC');
            $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))->endOfDay()->setTimeZone('UTC');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'timestamp'); // Default to 'timestamp'
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

        logger($data);

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

        $data->with('user');

        $data->select(
            'user_log_uuid',
            'domain_uuid',
            'timestamp',
            'user_uuid',
            'type',
            'result',
            'remote_address',
            'user_agent',
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
        $query->where('timestamp', '>=', $value->toIso8601String());
    }

    protected function filterEndPeriod($query, $value)
    {
        $query->where('timestamp', '<=', $value->toIso8601String());
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
