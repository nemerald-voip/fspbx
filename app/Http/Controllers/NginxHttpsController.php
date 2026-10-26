<?php

namespace App\Http\Controllers;

use App\Services\NginxHttpsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NginxHttpsController extends Controller
{
    public function __construct(private readonly NginxHttpsService $service) {}

    public function show(): JsonResponse
    {
        if (! isSuperAdmin()) {
            return $this->denied();
        }

        return response()->json($this->service->status());
    }

    public function update(Request $request): JsonResponse
    {
        if (! isSuperAdmin()) {
            return $this->denied();
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'allow_self_signed' => ['sometimes', 'boolean'],
        ]);

        try {
            $status = $this->service->apply((bool) $data['enabled'], (bool) ($data['allow_self_signed'] ?? false));
        } catch (RuntimeException $exception) {
            logger('NginxHttpsController@update: '.$exception->getMessage());

            return response()->json(['errors' => ['https' => [$exception->getMessage()]]], 422);
        }

        return response()->json([
            ...$status,
            'messages' => ['success' => [$status['enabled']
                ? __('HTTPS hardening is on.')
                : __('HTTPS hardening is off.')]],
        ]);
    }

    private function denied(): JsonResponse
    {
        return response()->json(['errors' => ['authorization' => [__('Access denied.')]]], 403);
    }
}
