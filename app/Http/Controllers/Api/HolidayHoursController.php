<?php

namespace App\Http\Controllers\Api;

use App\Models\BusinessHour;
use Illuminate\Support\Facades\DB;
use App\Models\BusinessHourHoliday;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Process;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreHolidayHourRequest;
use App\Http\Requests\UpdateHolidayHourRequest;
use App\Http\Requests\IndexHolidayHourRequest;
use App\Http\Requests\HolidayHourItemOptionsRequest;
use App\Http\Requests\BulkDeleteHolidayHourRequest;
use App\Http\Controllers\BusinessHoursController;
use App\Http\Resources\BusinessHourHolidayResource;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class HolidayHoursController extends Controller
{
    public $model;

    public function __construct()
    {
        $this->model = new BusinessHourHoliday();
        $this->middleware(function ($request, $next) {
            $domain = session('domain_uuid');
            abort_unless(is_string($domain) && Str::isUuid($domain), 403);
            $action = match ($request->route()->getActionMethod()) {
                'index' => 'list_view',
                'store' => 'create',
                'update' => 'update',
                'bulkDelete' => 'delete',
                'getItemOptions' => $request->filled('item_uuid') ? 'update' : 'create',
                default => null,
            };
            abort_unless($action && userCheckPermission('business_hours_holidays_'.$action), 403);

            return $next($request);
        });
    }

    public function index(IndexHolidayHourRequest $request)
    {
        $businessHour = BusinessHour::where('domain_uuid', session('domain_uuid'))
            ->findOrFail($request->validated('uuid'));

        $holidays = $businessHour->holidays()
            ->orderBy('created_at', 'desc')
            ->get();
        $holidays->each(fn ($holiday) => $holiday->setRelation('businessHour', $businessHour));

        return BusinessHourHolidayResource::collection($holidays);
    }


    public function store(StoreHolidayHourRequest $request)
    {
        $data = $request->validated();

        // Determine the true Eloquent model class for this action
        $callRoutingService = new CallRoutingOptionsService;
        $action     = $data['action'];
        $targetId   = $data['target']['value'] ?? null;
        $targetType = $action
            ? $callRoutingService->mapActionToModel($action)
            : null;

        DB::beginTransaction();

        try {
            $holiday = BusinessHourHoliday::create([
                'business_hour_uuid'  => $data['business_hour_uuid'],
                'holiday_type'        => $data['holiday_type'],
                'description'         => $data['description'] ?? null,
                'start_date'          => $data['start_date'] ?? null,
                'end_date'            => $data['end_date'] ?? null,
                'start_time'          => $data['start_time'] ?? null,
                'end_time'            => $data['end_time']   ?? null,
                'mon'                 => $data['mon']        ?? null,
                'wday'                => $data['wday']       ?? null,
                'mweek'               => $data['mweek']      ?? null,
                'week'               => $data['week']      ?? null,
                'mday'                => $data['mday']       ?? null,

                'action'              => $action,
                'target_type'         => $targetType,
                'target_id'           => $targetId,
            ]);

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('Holiday created')]],
                'data'     => $holiday,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }

            logger('BusinessHourHoliday store error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'messages' => ['error' => [__('Could not save holiday.')]],
            ], 500);
        }
    }


    public function update(UpdateHolidayHourRequest $request, BusinessHourHoliday $holiday_hour)
    {
        $data = $request->validated();

        // Map action to the correct model class
        $callRoutingService = new CallRoutingOptionsService;
        $action     = $data['action'];
        $targetId   = $data['target']['value'] ?? null;
        $targetType = $action
            ? $callRoutingService->mapActionToModel($action)
            : null;

        try {
            DB::beginTransaction();

            $holiday_hour->update([
                'holiday_type'       => $data['holiday_type'],
                'description'        => $data['description'] ?? null,
                'start_date'         => $data['start_date']   ?? null,
                'end_date'           => $data['end_date']     ?? null,
                'start_time'         => $data['start_time']   ?? null,
                'end_time'           => $data['end_time']     ?? null,
                'mon'                => $data['mon']          ?? null,
                'wday'               => $data['wday']         ?? null,
                'mweek'              => $data['mweek']        ?? null,
                'week'               => $data['week']         ?? null,
                'mday'               => $data['mday']         ?? null,
                'action'             => $action,
                'target_type'        => $targetType,
                'target_id'          => $targetId,
            ]);

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('Holiday updated')]],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof ValidationException) {
                throw $e;
            }
            logger('Holiday update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Could not update holiday.')]],
            ], 500);
        }
    }

    public function getItemOptions(HolidayHourItemOptionsRequest $request)
    {
        $item = $this->model;
        if ($request->filled('item_uuid')) {
            $item = BusinessHourHoliday::findOrFail($request->validated('item_uuid'));
            abort_unless($item->businessHour()->value('domain_uuid') === session('domain_uuid'), 404);
        }

        try {
            if ($item->exists) {
                // Define the update route
                $updateRoute = route('holiday-hours.update', $item);
            } else {
                $storeRoute  = route('holiday-hours.store');
            }

            $routingOptionsService = new CallRoutingOptionsService;
            $routingTypes = $routingOptionsService->routingTypes;

            $routes = [
                'store_route' => $storeRoute ?? null,
                'update_route' => $updateRoute ?? null,
                'get_routing_options' => route('routing.options'),
            ];

            return response()->json([
                'item' => $item,
                'routes' => $routes,
                'routing_types' => $routingTypes,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to fetch item details')]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function bulkDelete(BulkDeleteHolidayHourRequest $request)
    {
        $uuids = $request->validated('items');

        try {
            DB::beginTransaction();

            $selection = BusinessHourHoliday::whereHas('businessHour', fn ($query) => $query->where('domain_uuid', session('domain_uuid')))
                ->whereKey($uuids);
            $holidays = (clone $selection)->with('businessHour')->get();
            abort_unless($holidays->count() === count($uuids), 404);

            // Bulk deletion skips observers; rebuild each authorized parent once below.
            $selection->delete();

            foreach ($holidays->pluck('businessHour')->unique('uuid')->sortBy('uuid') as $parent) {
                app(BusinessHoursController::class)->generateDialPlanXML($parent);
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('Selected holiday(s) deleted successfully.')]]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface) {
                throw $e;
            }

            logger('BusinessHourHoliday bulkDelete error: ' . $e->getMessage() .
                " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('An error occurred while deleting the selected holiday(s).')]]
            ], 500);
        }
    }


    function checkServiceStatus(string $processName = 'esl:listen-emergency')
    {
        try {
            $result = Process::run('ps aux | grep "' . $processName . '" | grep -v "grep"');

            $output = $result->output();

            $isRunning = !empty(trim($output));

            return response()->json([
                'status' => $isRunning,
                'raw' => $output,
            ]);
        } catch (\Throwable $e) {
            logger('checkServiceStatus error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('An error occurred while checking service status.')]]
            ], 500);
        }
    }
}
