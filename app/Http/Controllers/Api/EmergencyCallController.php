<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmergencyCall;
use Illuminate\Http\Request;

class EmergencyCallController extends Controller
{
    public function index(Request $request)
    {
        $domain_uuid = session('domain_uuid');

        $calls = EmergencyCall::with('members')
            ->where('domain_uuid', $domain_uuid)
            ->get();

        return response()->json($calls);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'emergency_number' => 'required|string|max:20',
            'prompt' => 'nullable|string',
            'description' => 'nullable|string',
            'members' => 'array',
            'members.*.extension_uuid' => 'required|uuid',
        ]);

        $data['domain_uuid'] = $request->user()->domain_uuid ?? session('domain_uuid');

        $call = EmergencyCall::create($data);

        foreach ($data['members'] ?? [] as $member) {
            $call->members()->create([
                'domain_uuid' => $data['domain_uuid'],
                'extension_uuid' => $member['extension_uuid'],
            ]);
        }

        return response()->json($call->load('members'), 201);
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
}
