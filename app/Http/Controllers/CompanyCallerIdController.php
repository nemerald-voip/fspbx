<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\CompanyCallerIdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyCallerIdController extends Controller
{
    public function update(Request $request, Domain $domain, CompanyCallerIdService $service): JsonResponse
    {
        abort_unless($domain->domain_uuid === session('domain_uuid'), 403);
        abort_unless(userCheckPermission('extension_edit'), 403);

        $input = $request->validate([
            'revision' => ['required', 'string', 'size:64'],
            'outbound_caller_id_number' => ['nullable', 'string', 'regex:/^\+?[0-9]{1,25}$/D'],
            'emergency_caller_id_number' => ['nullable', 'string', 'regex:/^\+?[0-9]{1,25}$/D'],
            'outbound_caller_id_name' => ['nullable', 'string', 'max:255', 'not_regex:/[$\x00-\x1f]/'],
            'emergency_caller_id_name' => ['nullable', 'string', 'max:255', 'not_regex:/[$\x00-\x1f]/'],
        ], [], [
            'outbound_caller_id_number' => __('External Caller ID Number'),
            'emergency_caller_id_number' => __('Emergency Caller ID Number'),
            'outbound_caller_id_name' => __('External Caller ID Name'),
            'emergency_caller_id_name' => __('Emergency Caller ID Name'),
        ]);

        $service->save($domain, $input);

        return response()->json([
            'company_caller_id' => $service->options($domain),
            'messages' => ['success' => [__('Company caller ID saved.')]],
        ]);
    }
}
