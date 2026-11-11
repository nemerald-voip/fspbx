<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSwitchNetworkRequest;
use App\Services\SwitchNetworkService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;

class SwitchNetworkController extends Controller
{
    public function __construct(private readonly SwitchNetworkService $network)
    {
    }

    public function show(): JsonResponse
    {
        abort_unless(userCheckPermission('var_view') && userCheckPermission('var_add') && userCheckPermission('var_edit'), 403);
        return response()->json($this->network->state());
    }

    public function preview(SaveSwitchNetworkRequest $request): JsonResponse
    {
        return response()->json($this->network->preview($request->validated()));
    }

    public function update(SaveSwitchNetworkRequest $request): JsonResponse
    {
        try {
            return response()->json($this->network->apply($request->validated()));
        } catch (LockTimeoutException $exception) {
            return response()->json(['errors' => ['runtime' => [__('Network Setup is already applying changes. Wait and retry.')]]], 409);
        }
    }
}
