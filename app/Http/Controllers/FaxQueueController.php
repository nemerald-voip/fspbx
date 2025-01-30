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
        // logger($request->all());
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
                    // 'current_page' => route('extension-statistics.index'),
                    // 'export' => route('cdrs.export'),
                ]

            ]
        );

        $statuses = ['all' => 'Show All', 'sent' => 'Sent', 'waiting' => 'Waiting', 'failed' => 'Failed', 'sending' => 'Sending'];
        $scopes = ['global', 'local'];
        $selectedStatus = $request->get('status');
        $searchString = $request->get('search');
        $selectedScope = $request->get('scope', 'local');
        $searchPeriod = $request->get('period');
        $period = [
            Carbon::now()->startOfDay()->subDays(30),
            Carbon::now()->endOfDay()
        ];

        if (preg_match('/^(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)\s-\s(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)$/', $searchPeriod)) {
            $e = explode("-", $searchPeriod);
            $period[0] = Carbon::createFromFormat('m/d/y h:i A', trim($e[0]));
            $period[1] = Carbon::createFromFormat('m/d/y h:i A', trim($e[1]));
        }

        // Get local Time Zone
        $timeZone = get_local_time_zone(Session::get('domain_uuid'));
        $domainUuid = Session::get('domain_uuid');
        $faxQueues = FaxQueues::query();
        if (in_array($selectedScope, $scopes) && $selectedScope == 'local') {
            $faxQueues
                ->where('domain_uuid', $domainUuid);
        } else {
            $faxQueues
                ->join('v_domains', 'v_domains.domain_uuid', '=', 'v_fax_queue.domain_uuid');
        }
        if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
            $faxQueues
                ->where('fax_status', $selectedStatus);
        }
        if ($searchString) {
            $faxQueues->where(function ($query) use ($searchString) {
                $query
                    ->orWhereLike('fax_email_address', strtolower($searchString));
                try {
                    $phoneNumberUtil = PhoneNumberUtil::getInstance();
                    $phoneNumberObject = $phoneNumberUtil->parse($searchString, 'US');
                    if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                        $query->orWhereLike('fax_caller_id_number', $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164));
                        $query->orWhereLike('fax_number', $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164));
                    } else {
                        $query->orWhereLike('fax_caller_id_number', str_replace("-", "",  $searchString));
                        $query->orWhereLike('fax_number', str_replace("-", "",  $searchString));
                    }
                } catch (NumberParseException $e) {
                    $query->orWhereLike('fax_caller_id_number', str_replace("-", "",  $searchString));
                    $query->orWhereLike('fax_number', str_replace("-", "",  $searchString));
                }
            });
        }
        $faxQueues->whereBetween('fax_date', $period);
        $faxQueues = $faxQueues->orderBy('fax_date', 'desc')->paginate(10)->onEachSide(1);

        foreach ($faxQueues as $i => $faxQueue) {
            $faxQueues[$i]['fax_date'] = Carbon::parse($faxQueue['fax_date'])->setTimezone($timeZone);
            if (!empty($faxQueue['fax_notify_date'])) {
                $faxQueues[$i]['fax_notify_date'] = Carbon::parse($faxQueue['fax_notify_date'])->setTimezone($timeZone);
            }
            if (!empty($faxQueue['fax_retry_date'])) {
                $faxQueues[$i]['fax_retry_date'] = Carbon::parse($faxQueue['fax_retry_date'])->setTimezone($timeZone);
            }
        }

        $data = array();
        $data['faxQueues'] = $faxQueues;
        $data['statuses'] = $statuses;
        $data['selectedStatus'] = $selectedStatus;
        $data['selectedScope'] = $selectedScope;
        $data['searchString'] = $searchString;
        $data['searchPeriodStart'] = $period[0]->format('m/d/y h:i A');
        $data['searchPeriodEnd'] = $period[1]->format('m/d/y h:i A');
        $data['searchPeriod'] = implode(" - ", [$data['searchPeriodStart'], $data['searchPeriodEnd']]);
        $data['national_phone_number_format'] = PhoneNumberFormat::NATIONAL;

        unset($statuses, $faxQueues, $faxQueue, $domainUuid, $timeZone, $selectedStatus, $searchString, $selectedScope);

        $permissions['delete'] = userCheckPermission('fax_queue_delete');
        $permissions['view'] = userCheckPermission('fax_queue_view');

        return view('layouts.faxqueue.list')
            ->with($data)
            ->with('permissions', $permissions);
    }

    public function getData($paginate = 50)
    {
        $params['filterData'] = request()->filterData;
        $params['domain_uuid'] = session('domain_uuid');
        if (session('domains')) {
            $params['domains'] = session('domains')->pluck('domain_uuid');
        }
        $params['searchable'] = $this->searchable;
        $params['page'] = request()->get('page', 1); // Get the current page, default to 1

        if (!empty(request('filterData.dateRange'))) {
            $startPeriod = Carbon::parse(request('filterData.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filterData.dateRange')[1])->setTimeZone('UTC');
        } else {
            $startPeriod = Carbon::now($this->getTimezone())->startOfDay()->setTimeZone('UTC');
            $endPeriod = Carbon::now($this->getTimezone())->endOfDay()->setTimeZone('UTC');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'fax_date'); // Default to 'fax_date'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending

        $params['filterData']['startPeriod'] = $startPeriod;
        $params['filterData']['endPeriod'] = $endPeriod;
        $params['filterData']['sortField'] = request()->get('sortField', 'start_epoch');
        $params['filterData']['sortOrder'] = request()->get('sortField', 'desc');

        $this->filters = [
            'startPeriod' => $startPeriod,
            'endPeriod' => $endPeriod,
            'direction' => request('filterData.direction') ?? null,
            'search' => request('filterData.search') ?? null,
        ];

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);

        $data->select(
            'fax_queue_uuid',
            'fax_date',
            'fax_caller_id_number',
            'fax_email_address',
            'fax_retry_date',
            'fax_notify_date',
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

        if (!Cache::has(auth()->user()->user_uuid . '_' . session('domain_uuid') . '_timeZone')) {
            $timezone = get_local_time_zone(session('domain_uuid'));
            Cache::put(auth()->user()->user_uuid . session('domain_uuid') .  '_timeZone', $timezone, 600);
        } else {
            $timezone = Cache::get(auth()->user()->user_uuid . '_' . session('domain_uuid') . '_timeZone');
        }
        return $timezone;
    }

    public function updateStatus(FaxQueues $faxQueue, $status = null)
    {
        $faxQueue->update([
            'fax_status' => $status,
            'fax_retry_count' => 0,
            'fax_retry_date' => null
        ]);

        return redirect()->back();
    }
}
