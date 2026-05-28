<?php

namespace App\Http\Controllers;

use App\Models\AiReceptionist;
use App\Models\AiReceptionistTool;
use App\Rules\UniqueExtension;
use App\Services\AiReceptionistService;
use App\Services\CallRoutingOptionsService;
use App\Services\OpenAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AiReceptionistController extends Controller
{
    protected int $perPage = 50;

    private const OPENAI_VOICES = [
        'alloy',
        'ash',
        'ballad',
        'cedar',
        'coral',
        'echo',
        'marin',
        'sage',
        'shimmer',
        'verse',
    ];

    public function index()
    {
        if (! userCheckPermission('ai_receptionist_view')) {
            return redirect('/');
        }

        return Inertia::render('AIReceptionists', [
            'routes' => [
                'current_page' => route('ai-receptionists.index'),
                'data' => route('ai-receptionists.data'),
                'store' => route('ai-receptionists.store'),
                'item_options' => route('ai-receptionists.item.options'),
                'select_all' => route('ai-receptionists.select.all'),
                'bulk_delete' => route('ai-receptionists.bulk.delete'),
                'tool_store' => route('ai-receptionists.tools.store'),
                'voice_preview' => route('ai-receptionists.voice.preview'),
                'get_routing_options' => route('routing.options'),
            ],
            'permissions' => [
                'create' => userCheckPermission('ai_receptionist_add'),
                'update' => userCheckPermission('ai_receptionist_edit'),
                'destroy' => userCheckPermission('ai_receptionist_delete'),
                'view_all' => userCheckPermission('ai_receptionist_all'),
                'manage_tools' => userCheckPermission('ai_receptionist_tools'),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('ai_receptionist_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return $this->scopedReceptionists($request)
            ->select([
                'domain_uuid',
                'ai_receptionist_uuid',
                'dialplan_uuid',
                'name',
                'extension',
                'description',
            ])
            ->with([
                'domain:domain_uuid,domain_name,domain_description',
            ])
            ->allowedSorts(['name', 'extension', 'description'])
            ->defaultSort('extension')
            ->paginate($this->perPage)
            ->appends($request->query());
    }

    public function store(Request $request, AiReceptionistService $service): JsonResponse
    {
        if (! userCheckPermission('ai_receptionist_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $receptionist = $service->saveReceptionist($this->validatedReceptionist($request));

        return response()->json([
            'messages' => ['success' => ['AI receptionist created.']],
            'ai_receptionist_uuid' => $receptionist->ai_receptionist_uuid,
        ], 201);
    }

    public function update(Request $request, AiReceptionist $ai_receptionist, AiReceptionistService $service): JsonResponse
    {
        if (! userCheckPermission('ai_receptionist_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if ($ai_receptionist->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $service->saveReceptionist($this->validatedReceptionist($request, $ai_receptionist), $ai_receptionist);

        return response()->json([
            'messages' => ['success' => ['AI receptionist updated.']],
        ]);
    }

    public function getItemOptions(Request $request, AiReceptionistService $service): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('ai_receptionist_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('ai_receptionist_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $item = $itemUuid
            ? AiReceptionist::query()
                ->with('routes')
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail()
            : new AiReceptionist();

        if ($itemUuid) {
            $item->append([
                'fallback_target_uuid',
                'fallback_target_name',
                'fallback_target_extension',
            ]);
        } else {
            $item->extension = $item->generateUniqueSequenceNumber();
            $item->transcript_enabled = true;
            $item->tool_access_enabled = true;
            $item->max_duration_seconds = 900;
            $item->user_silence_checkin_seconds = 15;
            $item->user_idle_timeout_seconds = 60;
            $item->allow_interruptions = true;
            $item->min_interruption_duration = 0.5;
        }

        return response()->json([
            'item' => $item,
            'routing_types' => (new CallRoutingOptionsService)->routingTypes,
            'route_routing_types' => (new CallRoutingOptionsService)->forwardingTypes,
            'tools' => $this->toolOptions($itemUuid),
            'routes' => [
                'store_route' => route('ai-receptionists.store'),
                'update_route' => $itemUuid ? route('ai-receptionists.update', ['ai_receptionist' => $item->ai_receptionist_uuid]) : null,
                'tool_store_route' => route('ai-receptionists.tools.store'),
                'voice_preview_route' => route('ai-receptionists.voice.preview'),
                'get_routing_options' => route('routing.options'),
            ],
        ]);
    }

    public function previewVoice(Request $request, OpenAIService $openAIService)
    {
        if (! userCheckPermission('ai_receptionist_add') && ! userCheckPermission('ai_receptionist_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validated = $request->validate([
            'voice' => ['required', 'string', Rule::in(self::OPENAI_VOICES)],
        ]);

        $audio = $openAIService->textToSpeech(
            'gpt-4o-mini-tts-2025-12-15',
            'Hi, this is your AI receptionist voice preview. How can I help you today?',
            $validated['voice'],
            'mp3',
            '1.0'
        );

        return response($audio, 200, [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('ai_receptionist_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->scopedReceptionists($request)
                ->defaultSort('extension')
                ->pluck('ai_receptionist_uuid'),
            'messages' => ['success' => ['All matching AI receptionists selected.']],
        ]);
    }

    public function bulkDelete(Request $request, AiReceptionistService $service): JsonResponse
    {
        if (! userCheckPermission('ai_receptionist_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json(['messages' => ['error' => ['No AI receptionists selected.']]], 422);
        }

        $items = AiReceptionist::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('ai_receptionist_uuid', $uuids)
            ->get();

        $deleted = $service->deleteReceptionists($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} AI receptionist(s)."]],
        ]);
    }

    public function storeTool(Request $request, AiReceptionistService $service): JsonResponse
    {
        if (! userCheckPermission('ai_receptionist_tools')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validated = $request->validate([
            'ai_receptionist_uuid' => ['nullable', 'uuid', Rule::exists('ai_receptionists', 'ai_receptionist_uuid')->where('domain_uuid', session('domain_uuid'))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1024'],
            'method' => ['required', 'in:GET,POST,PUT,PATCH,DELETE'],
            'url' => ['required', 'url', 'max:2048'],
            'headers' => ['nullable', 'array'],
            'request_schema' => ['nullable', 'array'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:60'],
            'enabled' => ['required', 'boolean'],
        ]);

        $tool = $service->saveTool($validated);

        return response()->json([
            'messages' => ['success' => ['Tool saved.']],
            'tool_uuid' => $tool->tool_uuid,
        ], 201);
    }

    private function validatedReceptionist(Request $request, ?AiReceptionist $receptionist = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'extension' => [
                'required',
                'string',
                'max:32',
                Rule::unique('ai_receptionists', 'extension')
                    ->where('domain_uuid', session('domain_uuid'))
                    ->ignore($receptionist?->ai_receptionist_uuid, 'ai_receptionist_uuid'),
                new UniqueExtension($receptionist?->ai_receptionist_uuid),
            ],
            'openai_voice' => ['nullable', 'string', 'max:64'],
            'system_prompt' => ['nullable', 'string'],
            'initial_message' => ['nullable', 'string'],
            'fallback_type' => ['nullable', 'string', 'max:64'],
            'fallback_target' => ['nullable', 'string', 'max:255'],
            'fallback_label' => ['nullable', 'string', 'max:255'],
            'max_duration_seconds' => ['nullable', 'integer', 'min:30', 'max:7200'],
            'user_silence_checkin_seconds' => ['required', 'integer', 'min:5', 'max:600'],
            'user_idle_timeout_seconds' => ['required', 'integer', 'min:10', 'max:3600', 'gt:user_silence_checkin_seconds'],
            'allow_interruptions' => ['required', 'boolean'],
            'min_interruption_duration' => ['required', 'numeric', 'min:0', 'max:10'],
            'transcript_enabled' => ['required', 'boolean'],
            'tool_access_enabled' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:1024'],
            'routes' => ['nullable', 'array'],
            'routes.*.route_uuid' => ['nullable', 'uuid'],
            'routes.*.name' => ['nullable', 'string', 'max:255'],
            'routes.*.match_phrases' => ['nullable'],
            'routes.*.action_type' => ['nullable', Rule::in(['transfer', 'email'])],
            'routes.*.transfer_type' => ['nullable', Rule::in(['warm', 'cold'])],
            'routes.*.destination_type' => ['nullable', 'string', 'max:64'],
            'routes.*.destination_target' => ['nullable', 'string', 'max:255'],
            'routes.*.destination_label' => ['nullable', 'string', 'max:255'],
            'routes.*.email_to' => ['nullable', 'string', 'max:1024'],
            'routes.*.email_subject' => ['nullable', 'string', 'max:255'],
            'routes.*.email_instructions' => ['nullable', 'string'],
            'routes.*.notify_on_failed_warm_transfer' => ['nullable', 'boolean'],
            'routes.*.failed_transfer_email_to' => ['nullable', 'string', 'max:1024'],
            'routes.*.enabled' => ['nullable', 'boolean'],
            'routes.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($validated['routes'] ?? [] as $index => $route) {
            $name = trim((string) ($route['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            if (($route['action_type'] ?? null) === 'email' && blank($route['email_to'] ?? null)) {
                throw ValidationException::withMessages([
                    "routes.{$index}.email_to" => 'Email routes need an email address.',
                ]);
            }

            if (($route['action_type'] ?? null) === 'transfer') {
                if (blank($route['destination_type'] ?? null) || blank($route['destination_target'] ?? null)) {
                    throw ValidationException::withMessages([
                        "routes.{$index}.destination_target" => 'Transfer routes need a destination.',
                    ]);
                }

                if (($route['transfer_type'] ?? null) === 'warm'
                    && ! in_array($route['destination_type'] ?? null, ['extensions', 'external'], true)) {
                    throw ValidationException::withMessages([
                        "routes.{$index}.destination_type" => 'Warm transfer supports only Extension and External Number.',
                    ]);
                }

                if (($route['transfer_type'] ?? null) === 'warm'
                    && blank($route['failed_transfer_email_to'] ?? null)) {
                    throw ValidationException::withMessages([
                        "routes.{$index}.failed_transfer_email_to" => 'Warm transfer routes need a fallback email address.',
                    ]);
                }
            }
        }

        return $validated;
    }

    private function scopedReceptionists(Request $request): QueryBuilder
    {
        return QueryBuilder::for(AiReceptionist::class)
            ->when(! userCheckPermission('ai_receptionist_all') || ! $request->boolean('filter.showGlobal'), function ($query) {
                $query->where('domain_uuid', session('domain_uuid'));
            })
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);
                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('name', 'ilike', "%{$needle}%")
                            ->orWhere('extension', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            ]);
    }

    private function toolOptions(?string $receptionistUuid): array
    {
        return AiReceptionistTool::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where(function ($query) use ($receptionistUuid) {
                $query->whereNull('ai_receptionist_uuid');
                if ($receptionistUuid) {
                    $query->orWhere('ai_receptionist_uuid', $receptionistUuid);
                }
            })
            ->orderBy('name')
            ->get(['tool_uuid', 'name', 'description', 'method', 'url', 'enabled'])
            ->toArray();
    }

    private function validatedUuids(Request $request): array
    {
        return $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ])['items'] ?? [];
    }
}
