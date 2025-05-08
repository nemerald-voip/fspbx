<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\EmergencyCall;
use Illuminate\Support\Facades\DB;
use App\Models\BusinessHourHoliday;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Process;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreHolidayHourRequest;
use App\Http\Requests\StoreEmergencyCallRequest;
use App\Http\Requests\UpdateEmergencyCallRequest;

class HolidayHoursController extends Controller
{
    public $model;

    public function __construct()
    {
        $this->model = new BusinessHourHoliday();
    }

    public function index()
    {

        $holidays = BusinessHourHoliday::where('business_hour_uuid', request('uuid'))
            ->get();

        return response()->json($holidays);
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

        logger($data);

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
                'messages' => ['success' => ['Holiday exception created']],
                'data'     => $holiday,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('BusinessHourHoliday store error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'messages' => ['error' => ['Could not save holiday exception.']],
            ], 500);
        }
    }


    public function update(UpdateEmergencyCallRequest $request, string $id)
    {
        $domain_uuid = session('domain_uuid');
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $call = EmergencyCall::with('members')
                ->where('domain_uuid', $domain_uuid)
                ->findOrFail($id);

            $call->update([
                'emergency_number' => $validated['emergency_number'],
                'description' => $validated['description'] ?? null,
            ]);


            // Delete old members and emails
            $call->members()->delete();
            $call->emails()->delete();

            if (!empty($validated['members'])) {
                foreach ($validated['members'] as $member) {
                    $call->members()->create([
                        'domain_uuid' => $domain_uuid,
                        'extension_uuid' => $member['extension_uuid'],
                    ]);
                }
            }

            // Save new emails
            if (!empty($validated['emails'])) {
                foreach ($validated['emails'] as $email) {
                    $call->emails()->create([
                        'email' => $email,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Item updated']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('EmergencyCall update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating.']]
            ], 500);
        }
    }

    public function getItemOptions()
    {
        try {
            $domain_uuid = session('domain_uuid');

            $item = null;
            if (request()->has('item_uuid')) {
                $item = $this->model::where('domain_uuid', $domain_uuid)
                    ->where('uuid', request('item_uuid'))
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

            $domain_uuid = auth()->user()->domain_uuid ?? session('domain_uuid');

            $items = EmergencyCall::where('domain_uuid', $domain_uuid)
                ->whereIn('uuid', request('items'))
                ->get();

            foreach ($items as $item) {
                // 💥 Delete related members and emails first
                $item->members()->delete();
                $item->emails()->delete();

                // Delete the parent EmergencyCall
                $item->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected item(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('EmergencyCall bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected item(s).']]
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
