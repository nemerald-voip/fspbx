<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSwitchVariableRequest;
use App\Http\Requests\Settings\BulkSettingsActionRequest;
use App\Models\SwitchVariable;
use App\Services\SwitchVariableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SwitchVariableController extends Controller
{
    public function __construct(private readonly SwitchVariableService $variables)
    {
    }

    public function index(): Response|\Illuminate\Http\RedirectResponse
    {
        if (! userCheckPermission('var_view')) {
            return redirect('/');
        }

        return Inertia::render('SwitchVariables', [
            'routes' => [
                'current_page' => route('switch-variables.index'),
                'data_route' => route('switch-variables.data'),
                'store' => route('switch-variables.store'),
                'update' => route('switch-variables.update', ['switch_variable' => '__VARIABLE__']),
                'item_options' => route('switch-variables.item.options'),
                'bulk_copy' => route('switch-variables.bulk.copy'),
                'bulk_delete' => route('switch-variables.bulk.delete'),
                'bulk_toggle' => route('switch-variables.bulk.toggle'),
                'sync' => route('switch-variables.sync'),
            ],
            'permissions' => [
                'create' => userCheckPermission('var_add'),
                'update' => userCheckPermission('var_edit'),
                'destroy' => userCheckPermission('var_delete'),
            ],
            'options' => [
                'categories' => $this->variables->categories(),
                'commands' => SwitchVariableService::COMMAND_OPTIONS,
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (! userCheckPermission('var_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $perPage = min(max((int) $request->input('per_page', 50), 1), 5000);

        return response()->json($this->variables->variables(
            $request->input('filter', []),
            $request->input('sort'),
            (int) $request->input('page', 1),
            $perPage
        ));
    }

    public function itemOptions(Request $request): JsonResponse
    {
        $uuid = $request->input('itemUuid');

        if ($uuid && ! userCheckPermission('var_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $uuid && ! userCheckPermission('var_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'item' => $this->variables->variableItem($uuid),
            'commands' => SwitchVariableService::COMMAND_OPTIONS,
            'categories' => $this->variables->categories(),
        ]);
    }

    public function store(SaveSwitchVariableRequest $request): JsonResponse
    {
        if (! userCheckPermission('var_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $this->variables->saveVariable($request->validated());

        return response()->json(['messages' => ['success' => ['Variable created.']]], 201);
    }

    public function update(SaveSwitchVariableRequest $request, SwitchVariable $switchVariable): JsonResponse
    {
        if (! userCheckPermission('var_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $this->variables->saveVariable($request->validated(), $switchVariable);

        return response()->json(['messages' => ['success' => ['Variable updated.']]]);
    }

    public function bulkToggle(BulkSettingsActionRequest $request): JsonResponse
    {
        if (! userCheckPermission('var_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $count = $this->variables->toggle($request->validated('items'));

        return response()->json(['messages' => ['success' => ["Toggled {$count} variable(s)."]]]);
    }

    public function bulkCopy(BulkSettingsActionRequest $request): JsonResponse
    {
        if (! userCheckPermission('var_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $count = $this->variables->copy($request->validated('items'));

        return response()->json(['messages' => ['success' => ["Copied {$count} variable(s)."]]]);
    }

    public function bulkDelete(BulkSettingsActionRequest $request): JsonResponse
    {
        if (! userCheckPermission('var_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $count = $this->variables->delete($request->validated('items'));

        return response()->json(['messages' => ['success' => ["Deleted {$count} variable(s)."]]]);
    }

    public function sync(): JsonResponse
    {
        if (! userCheckPermission('var_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $this->variables->syncVarsXml()) {
            return response()->json([
                'messages' => ['error' => ['Unable to write vars.xml. Check the switch conf directory setting.']],
            ], 422);
        }

        return response()->json(['messages' => ['success' => ['vars.xml updated.']]]);
    }
}
