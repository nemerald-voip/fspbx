<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFlagRequest;
use App\Http\Requests\UpdateFlagRequest;
use App\Models\Extensions;
use App\Models\Flag;
use App\Models\FlagRule;
use App\Services\FlagService;
use App\Services\FlagStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class FlagsController extends Controller
{
    public function __construct(private FlagService $flags, private FlagStateService $state)
    {
    }

    public function index(Request $request)
    {
        if (!userCheckPermission('flags_list_view')) {
            return redirect('/');
        }

        return Inertia::render('Flags', [
            'routes' => [
                'data_route' => route('flags.data'),
                'item_options' => route('flags.item.options'),
                'select_all' => route('flags.select.all'),
                'bulk_delete' => route('flags.bulk.delete'),
                'bulk_toggle' => route('flags.bulk.toggle'),
            ],
            'permissions' => [
                'create' => userCheckPermission('flags_create'),
                'update' => userCheckPermission('flags_update'),
                'destroy' => userCheckPermission('flags_delete'),
                'toggle' => userCheckPermission('flags_toggle'),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        abort_unless(userCheckPermission('flags_list_view'), 403);

        $items = $this->query()
            ->with('rules')
            ->allowedSorts(['name', 'flag_number', 'type', 'enabled', 'description'])
            ->defaultSort('name')
            ->paginate(fspbx_pagination_per_page($request));

        $items->getCollection()->transform(function (Flag $flag) {
            return [
                'uuid' => $flag->uuid,
                'name' => $flag->name,
                'flag_number' => $flag->flag_number,
                'blf_value' => 'flag' . $flag->flag_number,
                'type' => $flag->type,
                'active' => (bool) $flag->active,
                'is_active' => $this->state->isActive($flag),
                'blf_enabled' => (bool) $flag->blf_enabled,
                'enabled' => (bool) $flag->enabled,
                'schedule_summary' => $flag->isManual() ? null : $this->scheduleSummary($flag),
                'description' => $flag->description,
            ];
        });

        return $items;
    }

    public function selectAll(Request $request): JsonResponse
    {
        abort_unless(userCheckPermission('flags_list_view'), 403);

        return response()->json([
            'items' => $this->query()->pluck('uuid'),
            'messages' => ['success' => [__('All matching flags selected.')]],
        ]);
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('item_uuid');

        if (!userCheckPermission($itemUuid ? 'flags_update' : 'flags_create')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        if ($itemUuid) {
            $flag = Flag::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->with(['rules', 'allowedExtensions'])
                ->findOrFail($itemUuid);

            $item = $this->flags->formData($flag);
            $item['usages'] = $flag->usages();
        } else {
            $item = [
                'uuid' => null,
                'flag_number' => (new Flag(['domain_uuid' => session('domain_uuid')]))->generateUniqueFlagNumber(),
                'type' => Flag::TYPE_MANUAL,
                'timezone' => get_local_time_zone(session('domain_uuid')),
                'active' => false,
                'blf_enabled' => true,
                'enabled' => true,
                'allowed_extensions' => [],
                'weekly_rules' => [],
                'date_rules' => [],
                'usages' => [],
            ];
        }

        $extensions = Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('extension')
            ->get(['extension_uuid', 'extension', 'effective_caller_id_name'])
            ->map(fn ($extension) => [
                'value' => $extension->extension_uuid,
                'label' => $extension->name_formatted,
            ]);

        return response()->json([
            'item' => $item,
            'extension_options' => $extensions,
            'timezones' => getGroupedTimezones(),
            'routes' => [
                'store_route' => route('flags.store'),
                'update_route' => $itemUuid ? route('flags.update', ['flag' => $itemUuid]) : null,
            ],
        ]);
    }

    public function store(StoreFlagRequest $request): JsonResponse
    {
        try {
            $flag = $this->flags->save($request->validated(), null, session('domain_uuid'));
        } catch (\Throwable $e) {
            logger('FlagsController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json(['messages' => ['error' => [__('Failed to create flag.')]]], 500);
        }

        $this->flags->notifyBlf($flag, session('domain_name'));

        return response()->json([
            'messages' => ['success' => [__('Flag created successfully.')]],
            'uuid' => $flag->uuid,
        ], 201);
    }

    public function update(UpdateFlagRequest $request, string $flag): JsonResponse
    {
        $flag = Flag::query()->where('domain_uuid', session('domain_uuid'))->findOrFail($flag);
        $previousNumber = $flag->flag_number;

        try {
            $flag = $this->flags->save($request->validated(), $flag, session('domain_uuid'));
        } catch (\Throwable $e) {
            logger('FlagsController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json(['messages' => ['error' => [__('Failed to update flag.')]]], 500);
        }

        $this->flags->notifyBlf($flag, session('domain_name'), $previousNumber);

        return response()->json([
            'messages' => ['success' => [__('Flag updated successfully.')]],
        ]);
    }

    public function bulkToggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'in:active,enabled'],
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ]);

        $permission = $validated['field'] === 'active' ? 'flags_toggle' : 'flags_update';
        if (!userCheckPermission($permission)) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $flags = Flag::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('uuid', $validated['items'])
            ->when($validated['field'] === 'active', fn ($query) => $query->where('type', Flag::TYPE_MANUAL))
            ->get();

        if ($flags->isEmpty()) {
            return response()->json([
                'messages' => ['error' => [__('Only manual flags can be switched on or off. Scheduled flags follow their schedule.')]],
            ], 422);
        }

        foreach ($flags as $flag) {
            $flag->{$validated['field']} = !$flag->{$validated['field']};
            $flag->save();
            $this->flags->notifyBlf($flag, session('domain_name'));
        }

        return response()->json([
            'messages' => ['success' => [__('Flag updated successfully.')]],
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (!userCheckPermission('flags_delete')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ]);

        $flags = Flag::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('uuid', $validated['items'])
            ->get();

        $inUse = [];
        foreach ($flags as $flag) {
            foreach ($flag->usages() as $usage) {
                $inUse[] = __(':flag is used by :usage', ['flag' => $flag->name, 'usage' => $usage]);
            }
        }

        if ($inUse) {
            // Notification.vue shows the first message of each key, so give every usage its own key.
            $messages = ['error' => [__('Remove these flags from their ring groups and virtual receptionists before deleting them.')]];
            foreach ($inUse as $index => $message) {
                $messages['error_' . ($index + 1)] = [$message];
            }

            return response()->json(['messages' => $messages], 422);
        }

        DB::transaction(function () use ($flags) {
            foreach ($flags as $flag) {
                $flag->delete();
            }
        });

        foreach ($flags as $flag) {
            $this->flags->clearBlf($flag->flag_number, session('domain_name'));
        }

        return response()->json([
            'messages' => ['success' => [__('Deleted :count flag(s).', ['count' => $flags->count()])]],
        ]);
    }

    private function query(): QueryBuilder
    {
        return QueryBuilder::for(Flag::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);
                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('name', 'ilike', "%{$needle}%")
                            ->orWhere('flag_number', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%");
                    });
                }),
            ]);
    }

    private function scheduleSummary(Flag $flag): string
    {
        $dayNames = [1 => __('Sun'), 2 => __('Mon'), 3 => __('Tue'), 4 => __('Wed'), 5 => __('Thu'), 6 => __('Fri'), 7 => __('Sat')];

        return $flag->rules->map(function (FlagRule $rule) use ($dayNames) {
            $from = substr((string) $rule->start_time, 0, 5);
            $to = substr((string) $rule->end_time, 0, 5);

            if ($rule->rule_type === FlagRule::TYPE_WEEKLY) {
                $days = collect(FlagStateService::parseWeekdays($rule->weekdays))
                    ->map(fn ($day) => $dayNames[$day])
                    ->implode(', ');

                return "{$days} {$from}–{$to}";
            }

            $start = trim(substr((string) $rule->start_date, 0, 10) . ' ' . $from);
            $end = trim(substr((string) $rule->end_date, 0, 10) . ' ' . $to);

            return "{$start} → {$end}";
        })->implode('; ');
    }
}
