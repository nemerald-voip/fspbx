<?php

namespace App\Http\Controllers\Api;

use App\Models\EmergencyCall;
use Illuminate\Support\Facades\DB;
use App\Models\BusinessHourHoliday;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Process;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreHolidayHourRequest;
use App\Http\Requests\UpdateHolidayHourRequest;
use App\Http\Resources\BusinessHourHolidayResource;

class HolidayHoursController extends Controller
{
    public $model;

    public function __construct()
    {
        $this->model = new BusinessHourHoliday();
    }

    public function index()
    {

        $holidays = BusinessHourHoliday::with('target')
            ->where('business_hour_uuid', request('uuid'))
            ->orderBy('created_at', 'desc')
            ->get();

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
                'messages' => ['success' => ['Holiday created']],
                'data'     => $holiday,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('BusinessHourHoliday store error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'messages' => ['error' => ['Could not save holiday.']],
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
                'business_hour_uuid' => $data['business_hour_uuid'],
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
                'messages' => ['success' => ['Holiday updated']],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('Holiday update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Could not update holiday.']],
            ], 500);
        }
    }

    public function getItemOptions()
    {
        try {
            $item = null;
            if (request()->has('item_uuid')) {
                $item = $this->model::where('uuid', request('item_uuid'))
                    ->first();

                // Define the update route
                $updateRoute = route('holiday-hours.update', $item);
            } else {
                // Create a new model if item_uuid is not provided
                $item = $this->model;
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
                'errors' => ['server' => ['Failed to fetch item details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function bulkDelete()
    {

        try {
            DB::beginTransaction();

            // Delete all holidays whose 'uuid' is in the provided array
            BusinessHourHoliday::whereIn('uuid', request('items'))->delete();

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected holiday(s) deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
    
            logger('BusinessHourHoliday bulkDelete error: ' . $e->getMessage() . 
                   " at " . $e->getFile() . ":" . $e->getLine());
    
            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected holiday(s).']]
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
                'messages' => ['error' => ['An error occurred while checking service status.']]
            ], 500);
        }
    }
}
