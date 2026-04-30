<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDialplanRequest;
use App\Http\Requests\StoreOutboundRouteRequest;
use App\Http\Requests\UpdateDialplanRequest;
use App\Models\Bridge;
use App\Models\Dialplans;
use App\Models\Domain;
use App\Models\Gateways;
use App\Services\DialplanService;
use App\Services\FreeswitchEslService;
use App\Services\OutboundRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DialplanController extends Controller
{
    protected int $perPage = 50;

    private const INBOUND_ROUTES_APP_UUID = 'c03b422e-13a8-bd1b-e42b-b6b9b4d27ce4';

    private const OUTBOUND_ROUTES_APP_UUID = '8c914ec3-9fc0-8ab5-4cda-6c9288bdc9a3';

    private const DANGEROUS_APPLICATIONS = [
        'system',
        'bgsystem',
        'spawn',
        'bg_spawn',
        'spawn_stream',
    ];

    private const FALLBACK_APPLICATION_OPTIONS = [
        'answer',
        'bridge',
        'export',
        'hangup',
        'lua',
        'park',
        'playback',
        'record_session',
        'redirect',
        'set',
        'sleep',
        'transfer',
        'voicemail',
    ];

    public function index()
    {
        if (!userCheckPermission('dialplan_view')) {
            return redirect('/');
        }

        return Inertia::render('Dialplans', [
            'routes' => [
                'current_page' => route('dialplans.index'),
                'data_route' => route('dialplans.data'),
                'select_all' => route('dialplans.select.all'),
                'bulk_delete' => route('dialplans.bulk.delete'),
                'bulk_copy' => route('dialplans.bulk.copy'),
                'bulk_toggle' => route('dialplans.bulk.toggle'),
                'store' => route('dialplans.store'),
                'item_options' => route('dialplans.item.options'),
                'outbound_route_options' => route('dialplans.outbound-routes.options'),
                'outbound_route_store' => route('dialplans.outbound-routes.store'),
            ],
            'permissions' => [
                'create' => userCheckPermission('dialplan_add'),
                'create_outbound_route' => userCheckPermission('outbound_route_add'),
                'update' => userCheckPermission('dialplan_edit'),
                'destroy' => userCheckPermission('dialplan_delete'),
                'view_global' => userCheckPermission('dialplan_all'),
                'domain' => userCheckPermission('dialplan_domain'),
                'context' => userCheckPermission('dialplan_context'),
                'outbound_route_pin_numbers' => userCheckPermission('outbound_route_pin_numbers'),
            ],
        ]);
    }

    public function store(StoreDialplanRequest $request, DialplanService $service): JsonResponse
    {
        try {
            $dialplan = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Dialplan created successfully.']],
                'dialplan_uuid' => $dialplan->dialplan_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('DialplanController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create dialplan.']],
            ], 500);
        }
    }

    public function update(UpdateDialplanRequest $request, Dialplans $dialplan, DialplanService $service): JsonResponse
    {
        if (!$this->canModifyDialplan($dialplan)) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $dialplan = $service->save($request->validated(), $dialplan);

            return response()->json([
                'messages' => ['success' => ['Dialplan updated successfully.']],
                'dialplan_uuid' => $dialplan->dialplan_uuid,
            ]);
        } catch (\Throwable $e) {
            logger('DialplanController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update dialplan.']],
            ], 500);
        }
    }

    public function getOutboundRouteOptions(): JsonResponse
    {
        if (!userCheckPermission('outbound_route_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return response()->json([
            'gateway_options' => $this->outboundRouteGatewayOptions(),
            'pattern_options' => $this->outboundRoutePatternOptions(),
            'domain_options' => $this->domainOptions(),
            'context_options' => $this->contextOptions(),
            'permissions' => [
                'pin_numbers' => userCheckPermission('outbound_route_pin_numbers'),
            ],
            'defaults' => [
                'domain_uuid' => '',
                'dialplan_context' => 'global',
                'dialplan_order' => '100',
                'dialplan_enabled' => 'true',
                'pin_numbers_enabled' => 'false',
            ],
            'routes' => [
                'store_route' => route('dialplans.outbound-routes.store'),
            ],
        ]);
    }

    public function storeOutboundRoute(StoreOutboundRouteRequest $request, OutboundRouteService $service): JsonResponse
    {
        try {
            $result = $service->create($request->validated());

            return response()->json([
                'messages' => ['success' => ["Created {$result['count']} outbound route dialplan(s)."]],
                'dialplan_uuids' => $result['dialplan_uuids'],
            ], 201);
        } catch (\Throwable $e) {
            logger('DialplanController@storeOutboundRoute error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create outbound route.']],
            ], 500);
        }
    }

    public function getData(Request $request)
    {
        if (!userCheckPermission('dialplan_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $currentDomain = session('domain_uuid');

        $items = QueryBuilder::for(Dialplans::class)
            ->select([
                'dialplan_uuid',
                'domain_uuid',
                'app_uuid',
                'dialplan_name',
                'dialplan_number',
                'dialplan_context',
                'dialplan_continue',
                'dialplan_order',
                'dialplan_enabled',
                'dialplan_description',
            ])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->withCount('dialplan_details')
            ->whereNotNull('dialplan_enabled')
            ->whereRaw("trim(dialplan_enabled) <> ''")
            ->tap(fn ($query) => $this->applyCategoryFilter($query, $request))
            ->when(!userCheckPermission('dialplan_all') || !$request->boolean('filter.showGlobal'), function ($query) use ($currentDomain) {
                $query->where(function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain);

                    if (userCheckPermission('dialplan_domain')) {
                        $query->orWhereNull('domain_uuid');
                    }
                });
            })
            ->allowedFilters($this->allowedFilters())
            ->allowedSorts([
                'dialplan_name',
                'dialplan_number',
                'dialplan_context',
                'dialplan_order',
                'dialplan_enabled',
                'dialplan_description',
            ])
            ->defaultSort('dialplan_order', 'dialplan_name')
            ->paginate($this->perPage);

        $items->getCollection()->each(function (Dialplans $dialplan) {
            $enabled = $dialplan->getRawOriginal('dialplan_enabled') ?: 'false';
            $dialplan->setAttribute('dialplan_enabled_raw', $enabled);
            $dialplan->setAttribute('enabled_label', $enabled === 'true' ? 'Enabled' : 'Disabled');
        });

        return $items;
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && !userCheckPermission('dialplan_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (!$itemUuid && !userCheckPermission('dialplan_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = Dialplans::query()
                ->with(['dialplan_details' => function ($query) {
                    $query->orderBy('dialplan_detail_group')->orderBy('dialplan_detail_order');
                }])
                ->whereKey($itemUuid)
                ->firstOrFail();

            if (!$this->canViewDialplan($item)) {
                return response()->json([
                    'messages' => ['error' => ['Access denied.']],
                ], 403);
            }

            $details = $item->dialplan_details
                ->map(fn ($detail) => [
                    'dialplan_detail_uuid' => $detail->dialplan_detail_uuid,
                    'dialplan_detail_tag' => $detail->getRawOriginal('dialplan_detail_tag'),
                    'dialplan_detail_type' => $detail->getRawOriginal('dialplan_detail_type'),
                    'dialplan_detail_data' => $detail->getRawOriginal('dialplan_detail_data'),
                    'dialplan_detail_break' => $detail->getRawOriginal('dialplan_detail_break'),
                    'dialplan_detail_inline' => $detail->getRawOriginal('dialplan_detail_inline'),
                    'dialplan_detail_group' => $detail->getRawOriginal('dialplan_detail_group'),
                    'dialplan_detail_order' => $detail->getRawOriginal('dialplan_detail_order'),
                    'dialplan_detail_enabled' => $this->booleanString($detail->getRawOriginal('dialplan_detail_enabled') ?? 'true'),
                ])
                ->values()
                ->all();

            $itemPayload = $item->toArray();
            $itemPayload['domain_uuid'] = $item->domain_uuid ?: '';
            $itemPayload['dialplan_enabled'] = $this->booleanString($item->getRawOriginal('dialplan_enabled') ?? 'true');
            $itemPayload['dialplan_continue'] = $item->getRawOriginal('dialplan_continue') ?: 'false';
            $itemPayload['dialplan_destination'] = $item->getRawOriginal('dialplan_destination') ?: 'false';
            $itemPayload['dialplan_details'] = $details;
        } else {
            $itemPayload = [
                'dialplan_uuid' => null,
                'domain_uuid' => '',
                'hostname' => null,
                'dialplan_name' => null,
                'dialplan_number' => null,
                'dialplan_destination' => 'false',
                'dialplan_context' => session('domain_name'),
                'dialplan_continue' => 'false',
                'dialplan_order' => '200',
                'dialplan_enabled' => 'true',
                'dialplan_description' => null,
                'dialplan_details' => $this->defaultDetails(),
            ];
        }

        return response()->json([
            'item' => $itemPayload,
            'domain_options' => $this->domainOptions(),
            'context_options' => $this->contextOptions(),
            'application_options' => $this->applicationOptions(),
            'condition_options' => $this->conditionOptions(),
            'routes' => [
                'store_route' => route('dialplans.store'),
                'update_route' => $itemUuid ? route('dialplans.update', ['dialplan' => $item->dialplan_uuid]) : null,
            ],
        ]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (!userCheckPermission('dialplan_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->dialplanSelectionQuery($request)
            ->pluck('dialplan_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching dialplans selected.']],
        ]);
    }

    public function bulkDelete(Request $request, DialplanService $service): JsonResponse
    {
        if (!userCheckPermission('dialplan_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $dialplans = $this->selectedDialplans($request, true);

        if ($dialplans->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No dialplans selected.']],
            ], 422);
        }

        try {
            $service->delete($dialplans);

            return response()->json([
                'messages' => ['success' => ["Deleted {$dialplans->count()} dialplan(s)."]],
            ]);
        } catch (\Throwable $e) {
            logger('DialplanController@bulkDelete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected dialplans.']],
            ], 500);
        }
    }

    public function bulkCopy(Request $request, DialplanService $service): JsonResponse
    {
        if (!userCheckPermission('dialplan_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $dialplans = $this->selectedDialplans($request, true);

        if ($dialplans->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No dialplans selected.']],
            ], 422);
        }

        try {
            $dialplans->each(fn (Dialplans $dialplan) => $service->copy($dialplan));

            return response()->json([
                'messages' => ['success' => ["Copied {$dialplans->count()} dialplan(s)."]],
            ], 201);
        } catch (\Throwable $e) {
            logger('DialplanController@bulkCopy error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while copying the selected dialplans.']],
            ], 500);
        }
    }

    public function bulkToggle(Request $request, DialplanService $service): JsonResponse
    {
        if (!userCheckPermission('dialplan_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $dialplans = $this->selectedDialplans($request, true);

        if ($dialplans->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No dialplans selected.']],
            ], 422);
        }

        try {
            $service->toggle($dialplans);

            return response()->json([
                'messages' => ['success' => ['Dialplan enabled state toggled.']],
            ]);
        } catch (\Throwable $e) {
            logger('DialplanController@bulkToggle error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while toggling the selected dialplans.']],
            ], 500);
        }
    }

    private function dialplanSelectionQuery(Request $request)
    {
        $currentDomain = session('domain_uuid');

        return QueryBuilder::for(Dialplans::class)
            ->select(['dialplan_uuid'])
            ->whereNotNull('dialplan_enabled')
            ->whereRaw("trim(dialplan_enabled) <> ''")
            ->tap(fn ($query) => $this->applyCategoryFilter($query, $request))
            ->when(!userCheckPermission('dialplan_all') || !$request->boolean('filter.showGlobal'), function ($query) use ($currentDomain) {
                $query->where(function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain);

                    if (userCheckPermission('dialplan_domain')) {
                        $query->orWhereNull('domain_uuid');
                    }
                });
            })
            ->allowedFilters($this->allowedFilters())
            ->defaultSort('dialplan_order', 'dialplan_name');
    }

    private function selectedDialplans(Request $request, bool $allowGlobal = false)
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['required', 'uuid'],
        ]);

        return Dialplans::query()
            ->whereIn('dialplan_uuid', array_values(array_unique($validated['items'])))
            ->when(!$allowGlobal || !userCheckPermission('dialplan_all'), function ($query) {
                $query->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'));

                    if (userCheckPermission('dialplan_domain')) {
                        $query->orWhereNull('domain_uuid');
                    }
                });
            })
            ->get();
    }

    private function allowedFilters(): array
    {
        return [
            AllowedFilter::callback('search', function ($query, $value) {
                $needle = trim((string) $value);

                if ($needle === '') {
                    return;
                }

                $query->where(function ($query) use ($needle) {
                    $query->where('dialplan_uuid', 'ilike', "%{$needle}%")
                        ->orWhere('dialplan_name', 'ilike', "%{$needle}%")
                        ->orWhere('dialplan_number', 'ilike', "%{$needle}%")
                        ->orWhere('dialplan_context', 'ilike', "%{$needle}%")
                        ->orWhere('dialplan_description', 'ilike', "%{$needle}%");
                });
            }),
            AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            AllowedFilter::callback('showApplicationDialplans', function ($query, $value) {}),
            AllowedFilter::callback('category', function ($query, $value) {}),
        ];
    }

    private function applyCategoryFilter($query, Request $request): void
    {
        $category = $request->input('filter.category');

        if ($category === 'inbound') {
            $query->where(function ($query) {
                $query->where('app_uuid', self::INBOUND_ROUTES_APP_UUID)
                    ->orWhere('dialplan_context', 'public');
            });

            return;
        }

        if ($category === 'outbound') {
            $query->where('app_uuid', self::OUTBOUND_ROUTES_APP_UUID);

            return;
        }

        $query->where('dialplan_context', '<>', 'public')
            ->where(function ($query) {
                $query->whereNull('app_uuid')
                    ->orWhere('app_uuid', '<>', self::INBOUND_ROUTES_APP_UUID);
            });
    }

    private function canViewDialplan(Dialplans $dialplan): bool
    {
        if (userCheckPermission('dialplan_all')) {
            return true;
        }

        if (userCheckPermission('dialplan_domain') && blank($dialplan->domain_uuid)) {
            return true;
        }

        return $dialplan->domain_uuid === session('domain_uuid');
    }

    private function canModifyDialplan(Dialplans $dialplan): bool
    {
        return $this->canViewDialplan($dialplan);
    }

    private function domainOptions(): array
    {
        if (!userCheckPermission('dialplan_domain')) {
            return [];
        }

        return collect([['value' => '', 'label' => 'Global']])
            ->merge(
                Domain::query()
                    ->orderBy('domain_name')
                    ->get(['domain_uuid', 'domain_name'])
                    ->map(fn (Domain $domain) => [
                        'value' => $domain->domain_uuid,
                        'label' => $domain->domain_name,
                    ])
            )
            ->values()
            ->all();
    }

    private function contextOptions(): array
    {
        return collect([
            session('domain_name'),
            'global',
        ])
            ->unique()
            ->filter()
            ->map(fn ($context) => ['value' => $context, 'label' => $context])
            ->values()
            ->all();
    }

    private function conditionOptions(): array
    {
        return collect([
            'ani',
            'ani2',
            'caller_id_name',
            'caller_id_number',
            'chan_name',
            'context',
            'destination_number',
            'dialplan',
            'hour',
            'mday',
            'minute',
            'minute-of-day',
            'mon',
            'network_addr',
            'rdnis',
            'source',
            'time-of-day',
            'username',
            'uuid',
            'wday',
            'week',
            'year',
            '${call_direction}',
            '${number_alias}',
            '${sip_contact_host}',
            '${sip_from_host}',
            '${sip_from_user}',
            '${sip_to_user}',
            '${toll_allow}',
        ])
            ->map(fn ($value) => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
    }

    private function applicationOptions(): array
    {
        $applications = Cache::get('dialplans:freeswitch_application_options');

        if (blank($applications)) {
            $applications = $this->freeswitchApplications();

            if (!blank($applications)) {
                Cache::put('dialplans:freeswitch_application_options', $applications, now()->addMinutes(10));
            }
        }

        return collect($applications ?: self::FALLBACK_APPLICATION_OPTIONS)
            ->map(fn ($value) => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
    }

    private function outboundRouteGatewayOptions(): array
    {
        $currentDomain = session('domain_uuid');

        $gateways = Gateways::query()
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->where('enabled', 'true')
            ->when(!userCheckPermission('outbound_route_any_gateway'), function ($query) use ($currentDomain) {
                $query->where('domain_uuid', $currentDomain);
            })
            ->orderByRaw('domain_uuid = ? desc', [$currentDomain])
            ->orderBy('gateway')
            ->get(['gateway_uuid', 'domain_uuid', 'gateway']);

        $options = $gateways
            ->groupBy(fn (Gateways $gateway) => $gateway->domain?->domain_description ?: $gateway->domain?->domain_name ?: 'Global')
            ->map(fn ($items, $label) => [
                'label' => $label,
                'items' => $items->map(fn (Gateways $gateway) => [
                    'value' => $gateway->gateway_uuid . ':' . $gateway->gateway,
                    'label' => $gateway->gateway,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        if (userCheckPermission('bridge_view')) {
            $bridges = Bridge::query()
                ->where('bridge_enabled', 'true')
                ->where('domain_uuid', $currentDomain)
                ->orderBy('bridge_name')
                ->get(['bridge_name', 'bridge_destination']);

            if ($bridges->isNotEmpty()) {
                $options[] = [
                    'label' => 'Bridges',
                    'items' => $bridges->map(fn (Bridge $bridge) => [
                        'value' => 'bridge:' . $bridge->bridge_destination,
                        'label' => $bridge->bridge_name,
                    ])->values()->all(),
                ];
            }
        }

        $options[] = [
            'label' => 'Advanced',
            'items' => [
                ['value' => 'enum', 'label' => 'ENUM'],
                ['value' => 'freetdm', 'label' => 'FreeTDM'],
                ['value' => 'transfer:', 'label' => 'Transfer'],
                ['value' => 'xmpp', 'label' => 'XMPP'],
            ],
        ];

        return $options;
    }

    private function outboundRoutePatternOptions(): array
    {
        return [
            ['value' => '^(\\d{7})$', 'label' => '7 digit local'],
            ['value' => '^(\\d{10})$', 'label' => '10 digit'],
            ['value' => '^\\+?(\\d{11})$', 'label' => '11 digit'],
            ['value' => '^(?:\\+?1)?([2-9]\\d{2}[2-9]\\d{2}\\d{4})$', 'label' => 'North America'],
            ['value' => '^9999(?:\\+?1)?([2-9]\\d{2}[2-9]\\d{2}\\d{4})$', 'label' => 'FS PBX Fax North America (Prefix 9999)'],
            ['value' => '^\\+([1-9]\\d{7,14})$', 'label' => 'E.164 international'],
            ['value' => '^011([1-9]\\d{7,14})$', 'label' => '011 international'],
            ['value' => '^00([1-9]\\d{7,14})$', 'label' => '00 international'],
            ['value' => '^([1-9]\\d{7,14})$', 'label' => 'International digits'],
            ['value' => '^(311)$', 'label' => '311'],
            ['value' => '^(411)$', 'label' => '411'],
            ['value' => '^(933|911)\\.?$', 'label' => '911 / 933'],
            ['value' => '^(988)$', 'label' => '988'],
            ['value' => '^(?:\\+1|1)?(8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$', 'label' => 'Toll free'],
        ];
    }

    private function freeswitchApplications(): array
    {
        $service = null;

        try {
            $service = new FreeswitchEslService();

            if (!$service->isConnected()) {
                return [];
            }

            $applications = $this->applicationNamesFromJsonResponse(
                $service->executeCommand('show application as json', false)
            );

            if (blank($applications)) {
                $applications = $this->applicationNamesFromTextResponse(
                    $service->executeCommand('show application')
                );
            }

            return $this->formatApplicationNames($applications);
        } catch (\Throwable $e) {
            logger('DialplanController@freeswitchApplications error: ' . $e->getMessage());

            return [];
        } finally {
            $service?->disconnect();
        }
    }

    private function applicationNamesFromJsonResponse($response): array
    {
        if (!is_array($response) || empty($response['rows']) || !is_array($response['rows'])) {
            return [];
        }

        return collect($response['rows'])
            ->pluck('name')
            ->filter(fn ($application) => $this->shouldIncludeFreeswitchApplication((string) $application))
            ->values()
            ->all();
    }

    private function applicationNamesFromTextResponse($response): array
    {
        if (!is_string($response) || blank($response)) {
            return [];
        }

        $section = explode("\n\n", trim($response))[0] ?? '';
        $applications = [];

        foreach (preg_split('/\r\n|\r|\n/', $section) as $line) {
            $columns = str_getcsv(trim($line));

            if (count($columns) < 2) {
                continue;
            }

            $application = trim((string) ($columns[0] ?? ''));

            if ($this->shouldIncludeFreeswitchApplication($application)) {
                $applications[] = $application;
            }
        }

        return $applications;
    }

    private function formatApplicationNames(array $applications): array
    {
        return collect($applications)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function shouldIncludeFreeswitchApplication(string $application): bool
    {
        return filled($application)
            && $application !== 'name'
            && !in_array($application, self::DANGEROUS_APPLICATIONS, true)
            && !str_contains($application, '[');
    }

    private function booleanString($value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    private function defaultDetails(): array
    {
        return [
            [
                'dialplan_detail_tag' => 'condition',
                'dialplan_detail_type' => 'destination_number',
                'dialplan_detail_data' => null,
                'dialplan_detail_break' => null,
                'dialplan_detail_inline' => null,
                'dialplan_detail_group' => 0,
                'dialplan_detail_order' => 10,
                'dialplan_detail_enabled' => 'true',
            ],
            [
                'dialplan_detail_tag' => 'action',
                'dialplan_detail_type' => 'transfer',
                'dialplan_detail_data' => null,
                'dialplan_detail_break' => null,
                'dialplan_detail_inline' => null,
                'dialplan_detail_group' => 0,
                'dialplan_detail_order' => 20,
                'dialplan_detail_enabled' => 'true',
            ],
        ];
    }
}
