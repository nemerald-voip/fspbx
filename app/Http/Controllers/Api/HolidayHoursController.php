<?php

namespace App\Http\Controllers\Api;

use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\EmergencyCall;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Process;
use App\Http\Requests\StoreEmergencyCallRequest;
use App\Http\Requests\UpdateEmergencyCallRequest;
use App\Models\BusinessHourException;

class HolidayHoursController extends Controller
{
    public function index(Request $request)
    {
        logger(request()->all());
        logger('here');
        return;
        $domain_uuid = session('domain_uuid');

        $calls = BusinessHourException::where('domain_uuid', $domain_uuid)
            ->get();

        return response()->json($calls);
    }


    public function store(StoreEmergencyCallRequest $request)
    {
        $domain_uuid = session('domain_uuid');
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $call = EmergencyCall::create([
                'domain_uuid'       => $domain_uuid,
                'emergency_number'  => $validated['emergency_number'],
                'description'       => $validated['description'] ?? null,
            ]);

            if (!empty($validated['members'])) {
                foreach ($validated['members'] as $member) {
                    $call->members()->create([
                        'domain_uuid'     => $domain_uuid,
                        'extension_uuid'  => $member['extension_uuid'],
                    ]);
                }
            }

            // Save Emails
            if (!empty($validated['emails'])) {
                foreach ($validated['emails'] as $email) {
                    $call->emails()->create([
                        'email' => $email,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('EmergencyCall store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving.']]
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

            $call = null;
            $routes = [];
            if (request()->has('item_uuid')) {
                $call = EmergencyCall::with('members', 'emails')
                    ->where('domain_uuid', $domain_uuid)
                    ->where('uuid', request('item_uuid'))
                    ->first();

                // Define the update route
                $updateRoute = route('emergency-calls.update', $call);
            }

            $extensions = Extensions::where('domain_uuid', $domain_uuid)
                ->select('extension_uuid', 'extension', 'effective_caller_id_name')
                ->orderBy('extension')
                ->get()
                ->map(function ($ext) {
                    return [
                        'value' => $ext->extension_uuid,
                        'name' => $ext->name_formatted,
                    ];
                });

            $routes = [
                'update_route' => $updateRoute ?? null,
            ];

            return response()->json([
                'item' => $call,
                'extensions' => $extensions,
                'routes' => $routes,
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
