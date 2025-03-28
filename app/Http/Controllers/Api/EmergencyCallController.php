<?php

namespace App\Http\Controllers\Api;

use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\EmergencyCall;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmergencyCallRequest;

class EmergencyCallController extends Controller
{
    public function index(Request $request)
    {
        $domain_uuid = session('domain_uuid');

        $calls = EmergencyCall::with('members')
            ->where('domain_uuid', $domain_uuid)
            ->get();

        logger($calls);

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
    

    public function update(Request $request, $id)
    {
        $call = EmergencyCall::with('members')->findOrFail($id);

        $data = $request->validate([
            'emergency_number' => 'required|string|max:20',
            'prompt' => 'nullable|string',
            'description' => 'nullable|string',
            'members' => 'array',
            'members.*.extension_uuid' => 'required|uuid',
        ]);

        $call->update($data);

        // Sync members
        $call->members()->delete();

        foreach ($data['members'] ?? [] as $member) {
            $call->members()->create([
                'domain_uuid' => $call->domain_uuid,
                'extension_uuid' => $member['extension_uuid'],
            ]);
        }

        return response()->json($call->load('members'));
    }

    public function destroy($id)
    {
        $call = EmergencyCall::findOrFail($id);
        $call->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function getItemOptions(Request $request)
    {
        try {
            $domain_uuid = session('domain_uuid');

            $call = null;
            if ($request->has('item_uuid')) {
                $call = EmergencyCall::with('members')
                    ->where('domain_uuid', $domain_uuid)
                    ->where('id', $request->item_uuid)
                    ->first();
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

            return response()->json([
                'item' => $call,
                'extensions' => $extensions,
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
}
