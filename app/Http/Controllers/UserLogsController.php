<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\FaxQueues;
use App\Models\UserLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;

class UserLogsController extends Controller
{

    public $model;
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
    public function index(Request $request)
    {
        if (! userCheckPermission('user_log_view')) return redirect('/');

        $timezone = get_local_time_zone(session('domain_uuid'));
        return Inertia::render($this->viewName, [
            'startPeriod' => Carbon::now($timezone)->startOfDay()->utc()->toIso8601String(),
            'endPeriod' => Carbon::now($timezone)->endOfDay()->utc()->toIso8601String(),
            'timezone' => $timezone,
            'pagination' => [
                'per_page' => fspbx_pagination_per_page($request),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'data_route' => route('user-logs.data'),
                'select_all' => route('user-logs.select.all'),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        abort_unless(userCheckPermission('user_log_view'), 403);

        return $this->builder($this->requestFilters($request), (string) $request->input('sort', '-timestamp'))
            ->paginate(fspbx_pagination_per_page($request));
    }

    private function requestFilters(Request $request): array
    {
        $request->validate([
            'filter.dateRange' => ['nullable', 'array', 'size:2'],
            'filter.dateRange.0' => ['required_with:filter.dateRange', 'date'],
            'filter.dateRange.1' => ['required_with:filter.dateRange', 'date', 'after_or_equal:filter.dateRange.0'],
            'filter.search' => ['nullable', 'string'],
        ]);
        $range = $request->input('filter.dateRange');
        $timezone = get_local_time_zone(session('domain_uuid'));

        return [
            'startPeriod' => $range ? Carbon::parse($range[0])->utc() : Carbon::now($timezone)->startOfDay()->utc(),
            'endPeriod' => $range ? Carbon::parse($range[1])->utc() : Carbon::now($timezone)->endOfDay()->utc(),
            'search' => $request->input('filter.search'),
            'showGlobal' => $request->boolean('filter.showGlobal'),
        ];
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [], string $sort = '-timestamp')
    {
        $data =  $this->model::query();
        if (isset($filters['showGlobal']) && $filters['showGlobal']) {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter devices by those domains
            $domainUuids = collect(Session::get('domains', []))->pluck('domain_uuid');
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
            'username',
            'email',
            'type',
            'result',
            'remote_address',
            'user_agent',
        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if ($value === null || $value === '') continue;
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $field = ltrim($sort, '-');
        if (! in_array($field, ['timestamp', 'username', 'email', 'type', 'result', 'remote_address'], true)) {
            $sort = '-timestamp';
            $field = 'timestamp';
        }
        $data->orderBy($field, str_starts_with($sort, '-') ? 'desc' : 'asc');

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
    public function selectAll(Request $request)
    {
        abort_unless(userCheckPermission('user_log_view'), 403);

        return response()->json([
            'messages' => ['success' => [__('All items selected')]],
            'items' => $this->builder($this->requestFilters($request))->pluck('user_log_uuid'),
        ]);
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
