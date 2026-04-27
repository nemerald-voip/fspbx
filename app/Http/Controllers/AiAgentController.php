<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\AiAgent;
use App\Models\AiAgentKbDocument;
use App\Models\Dialplans;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\CallRoutingOptionsService;
use App\Services\ElevenLabsConvaiService;
use App\Services\Tts\ElevenLabsTtsService;
use App\Http\Requests\StoreAiAgentRequest;
use App\Http\Requests\UpdateAiAgentRequest;
use App\Http\Requests\StoreAiAgentKbDocumentRequest;
use App\Traits\ChecksLimits;

class AiAgentController extends Controller
{
    use ChecksLimits;

    public $model;
    protected $viewName = 'AiAgents';

    public function __construct()
    {
        $this->model = new AiAgent();
    }

    public function index()
    {
        if (!userCheckPermission("ai_agent_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'routes' => [
                    'current_page' => route('ai-agents.index'),
                    'data_route' => route('ai-agents.data'),
                    'store' => route('ai-agents.store'),
                    'item_options' => route('ai-agents.item.options'),
                    'select_all' => route('ai-agents.select.all'),
                    'bulk_delete' => route('ai-agents.bulk.delete'),
                ],
                'permissions' => $this->getUserPermissions(),
            ]
        );
    }

    public function getData()
    {
        $perPage = 50;
        $currentDomain = session('domain_uuid');

        $items = QueryBuilder::for(AiAgent::class)
            ->where('domain_uuid', $currentDomain)
            ->select([
                'ai_agent_uuid',
                'agent_name',
                'agent_extension',
                'agent_enabled',
                'description',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('agent_name', 'ilike', "%{$value}%")
                            ->orWhere('agent_extension', 'ilike', "%{$value}%")
                            ->orWhere('description', 'ilike', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('agent_enabled'),
            ])
            ->allowedSorts(['agent_extension', 'agent_name'])
            ->defaultSort('agent_extension')
            ->paginate($perPage);

        return $items;
    }

    public function store(StoreAiAgentRequest $request)
    {
        $inputs = $request->validated();

        if ($resp = $this->enforceLimit('ai_agents', AiAgent::class, 'domain_uuid', 'ai_agent_limit_error')) {
            return $resp;
        }

        try {
            DB::beginTransaction();

            $convaiService = app(ElevenLabsConvaiService::class);

            // Create the agent in ElevenLabs
            $agentResponse = $convaiService->createAgent(
                $inputs['agent_name'],
                $inputs['system_prompt'] ?? null,
                $inputs['first_message'] ?? null,
                $inputs['voice_id'] ?? null,
                $inputs['language'] ?? 'en',
            );

            $elevenlabsAgentId = $agentResponse['agent_id'] ?? null;
            $elevenlabsPhoneNumberId = null;

            // Create SIP trunk phone number and assign agent
            if ($elevenlabsAgentId) {
                try {
                    $phoneResponse = $convaiService->createSipTrunkPhoneNumber(
                        'Voxra Agent: ' . $inputs['agent_name'],
                        $inputs['agent_extension'],
                        config('services.elevenlabs.sip_allowed_addresses', []),
                    );
                    $elevenlabsPhoneNumberId = $phoneResponse['phone_number_id'] ?? null;

                    if ($elevenlabsPhoneNumberId) {
                        $convaiService->assignAgentToPhoneNumber($elevenlabsPhoneNumberId, $elevenlabsAgentId);
                    }
                } catch (\Exception $e) {
                    logger('ElevenLabs SIP phone number setup warning: ' . $e->getMessage());
                }
            }

            $instance = $this->model;
            $instance->fill([
                'domain_uuid' => session('domain_uuid'),
                'dialplan_uuid' => Str::uuid(),
                'agent_name' => $inputs['agent_name'],
                'agent_extension' => $inputs['agent_extension'],
                'elevenlabs_agent_id' => $elevenlabsAgentId,
                'elevenlabs_phone_number_id' => $elevenlabsPhoneNumberId,
                'system_prompt' => $inputs['system_prompt'] ?? null,
                'first_message' => $inputs['first_message'] ?? null,
                'voice_id' => $inputs['voice_id'] ?? null,
                'language' => $inputs['language'] ?? 'en',
                'agent_enabled' => $inputs['agent_enabled'],
                'description' => $inputs['description'] ?? null,
                'insert_date' => date('Y-m-d H:i:s'),
                'insert_user' => session('user_uuid'),
            ]);

            $instance->save();

            $this->generateDialPlanXML($instance);

            DB::commit();

            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            return response()->json([
                'item_uuid' => $instance->ai_agent_uuid,
                'messages' => ['success' => ['AI agent created successfully.']]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to create the AI agent. ' . $e->getMessage()]]
            ], 500);
        }
    }

    public function update(UpdateAiAgentRequest $request)
    {
        $inputs = $request->validated();

        try {
            DB::beginTransaction();

            $instance = $this->model::where('ai_agent_uuid', $inputs['ai_agent_uuid'])->firstOrFail();

            // Update ElevenLabs agent if relevant fields changed
            if ($instance->elevenlabs_agent_id) {
                $convaiService = app(ElevenLabsConvaiService::class);
                try {
                    $convaiService->updateAgent(
                        $instance->elevenlabs_agent_id,
                        $inputs['agent_name'],
                        $inputs['system_prompt'] ?? null,
                        $inputs['first_message'] ?? null,
                        $inputs['voice_id'] ?? null,
                        $inputs['language'] ?? 'en',
                    );
                } catch (\Exception $e) {
                    logger('ElevenLabs update agent warning: ' . $e->getMessage());
                }
            }

            $instance->fill([
                'agent_name' => $inputs['agent_name'],
                'agent_extension' => $inputs['agent_extension'],
                'system_prompt' => $inputs['system_prompt'] ?? null,
                'first_message' => $inputs['first_message'] ?? null,
                'voice_id' => $inputs['voice_id'] ?? null,
                'language' => $inputs['language'] ?? 'en',
                'agent_enabled' => $inputs['agent_enabled'],
                'description' => $inputs['description'] ?? null,
                'update_date' => date('Y-m-d H:i:s'),
                'update_user' => session('user_uuid'),
            ]);

            $instance->save();

            $this->generateDialPlanXML($instance);

            DB::commit();

            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            return response()->json([
                'messages' => ['success' => ['AI agent settings have been updated successfully.']]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update the AI agent settings. Please try again.']]
            ], 500);
        }
    }

    public function bulkDelete()
    {
        try {
            DB::beginTransaction();

            $items = $this->model::whereIn('ai_agent_uuid', request('items'))
                ->get(['ai_agent_uuid', 'dialplan_uuid', 'elevenlabs_agent_id', 'elevenlabs_phone_number_id']);

            $convaiService = null;
            try {
                $convaiService = app(ElevenLabsConvaiService::class);
            } catch (\Exception $e) {
                logger('ElevenLabs service unavailable during delete: ' . $e->getMessage());
            }

            foreach ($items as $item) {
                // Clean up KB documents (ElevenLabs + local files + DB rows)
                $kbDocs = AiAgentKbDocument::where('ai_agent_uuid', $item->ai_agent_uuid)->get();
                foreach ($kbDocs as $kbDoc) {
                    if ($convaiService && $kbDoc->elevenlabs_documentation_id) {
                        try {
                            $convaiService->deleteKbDocument($kbDoc->elevenlabs_documentation_id);
                        } catch (\Exception $e) {
                            logger('ElevenLabs KB cleanup warning: ' . $e->getMessage());
                        }
                    }
                    if ($kbDoc->file_path && Storage::disk('local')->exists($kbDoc->file_path)) {
                        Storage::disk('local')->delete($kbDoc->file_path);
                    }
                    $kbDoc->delete();
                }

                // Clean up ElevenLabs resources
                if ($convaiService) {
                    try {
                        if ($item->elevenlabs_phone_number_id) {
                            $convaiService->deletePhoneNumber($item->elevenlabs_phone_number_id);
                        }
                        if ($item->elevenlabs_agent_id) {
                            $convaiService->deleteAgent($item->elevenlabs_agent_id);
                        }
                    } catch (\Exception $e) {
                        logger('ElevenLabs cleanup warning: ' . $e->getMessage());
                    }
                }

                // Delete dialplan
                Dialplans::where('dialplan_uuid', $item->dialplan_uuid)->delete();

                // Clear cache
                $this->clearCache();

                // Delete the item
                $item->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['server' => ['All selected items have been deleted successfully.']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500);
        }
    }

    private function generateDialPlanXML(AiAgent $agent): void
    {
        $data = [
            'agent' => $agent,
            'dialplan_continue' => 'false',
        ];

        $xml = trim(view('layouts.xml.ai-agent-dial-plan-template', $data)->render());

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $dom->formatOutput = true;
        $xml = $dom->saveXML($dom->documentElement);

        $dialPlan = Dialplans::where('dialplan_uuid', $agent->dialplan_uuid)->first();

        if (!$dialPlan) {
            $newDialplanUuid = Str::uuid();

            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $newDialplanUuid;
            $dialPlan->app_uuid = 'b2c48e1a-7f3d-4a1e-9c5b-8d6e7f1a2b3c';
            $dialPlan->domain_uuid = session('domain_uuid');
            $dialPlan->dialplan_context = session('domain_name');
            $dialPlan->dialplan_name = $agent->agent_name;
            $dialPlan->dialplan_number = $agent->agent_extension;
            $dialPlan->dialplan_continue = $data['dialplan_continue'];
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 101;
            $dialPlan->dialplan_enabled = $agent->agent_enabled;
            $dialPlan->dialplan_description = $agent->description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = session('user_uuid');

            $agent->dialplan_uuid = $newDialplanUuid;
            $agent->save();
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $agent->agent_name;
            $dialPlan->dialplan_number = $agent->agent_extension;
            $dialPlan->dialplan_enabled = $agent->agent_enabled;
            $dialPlan->dialplan_description = $agent->description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = session('user_uuid');
        }

        $dialPlan->save();

        $this->clearCache();
    }

    private function clearCache(): void
    {
        FusionCache::clear("dialplan." . session('domain_name'));
    }

    public function getItemOptions(Request $request)
    {
        try {
            $domainUuid = $request->input('domain_uuid') ?? session('domain_uuid');
            $itemUuid = $request->input('item_uuid');

            $routes = [
                'store_route' => route('ai-agents.store'),
            ];

            $kbDocuments = [];

            $routingOptionsService = new CallRoutingOptionsService;
            $routingTypes = $routingOptionsService->routingTypes;

            // Get ElevenLabs voices
            $voices = [];
            try {
                $ttsService = app(ElevenLabsTtsService::class);
                $voices = $ttsService->getVoices();
            } catch (\Exception $e) {
                logger('Failed to fetch ElevenLabs voices: ' . $e->getMessage());
            }

            if ($itemUuid) {
                $agent = $this->model::where('domain_uuid', $domainUuid)
                    ->where('ai_agent_uuid', $itemUuid)
                    ->firstOrFail();

                $routes = array_merge($routes, [
                    'update_route'     => route('ai-agents.update', $agent),
                    'kb_store_route'   => route('ai-agents.kb.store', ['ai_agent' => $agent->ai_agent_uuid]),
                ]);

                $kbDocuments = $agent->kbDocuments()
                    ->orderBy('insert_date', 'desc')
                    ->get([
                        'kb_document_uuid',
                        'ai_agent_uuid',
                        'document_type',
                        'name',
                        'url',
                        'file_mime_type',
                        'file_size',
                        'sync_status',
                        'sync_error',
                    ])
                    ->map(function ($doc) {
                        return array_merge($doc->toArray(), [
                            'delete_route' => route('ai-agents.kb.delete', [
                                'ai_agent'    => $doc->ai_agent_uuid,
                                'kb_document' => $doc->kb_document_uuid,
                            ]),
                        ]);
                    });
            } else {
                if ($resp = $this->enforceLimit('ai_agents', AiAgent::class, 'domain_uuid', 'ai_agent_limit_error')) {
                    return $resp;
                }

                $agent = new AiAgent();
                $agent->ai_agent_uuid = '';
                $agent->agent_name = '';
                $agent->agent_extension = $agent->generateUniqueSequenceNumber();
                $agent->description = '';
                $agent->system_prompt = '';
                $agent->first_message = '';
                $agent->voice_id = null;
                $agent->language = 'en';
                $agent->agent_enabled = 'true';
            }

            $permissions = $this->getUserPermissions();

            $languages = [
                ['value' => 'en', 'label' => 'English'],
                ['value' => 'es', 'label' => 'Spanish'],
                ['value' => 'fr', 'label' => 'French'],
                ['value' => 'de', 'label' => 'German'],
                ['value' => 'it', 'label' => 'Italian'],
                ['value' => 'pt', 'label' => 'Portuguese'],
                ['value' => 'nl', 'label' => 'Dutch'],
                ['value' => 'ja', 'label' => 'Japanese'],
                ['value' => 'zh', 'label' => 'Chinese'],
                ['value' => 'ko', 'label' => 'Korean'],
                ['value' => 'pl', 'label' => 'Polish'],
                ['value' => 'ru', 'label' => 'Russian'],
                ['value' => 'sv', 'label' => 'Swedish'],
                ['value' => 'tr', 'label' => 'Turkish'],
                ['value' => 'uk', 'label' => 'Ukrainian'],
                ['value' => 'hi', 'label' => 'Hindi'],
                ['value' => 'ar', 'label' => 'Arabic'],
            ];

            return response()->json([
                'item' => $agent,
                'permissions' => $permissions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'voices' => $voices,
                'languages' => $languages,
                'kb_documents' => $kbDocuments,
            ]);
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details.']]
            ], 500);
        }
    }

    public function getUserPermissions()
    {
        return [
            'ai_agent_create' => userCheckPermission('ai_agent_add'),
            'ai_agent_update' => userCheckPermission('ai_agent_edit'),
            'ai_agent_destroy' => userCheckPermission('ai_agent_delete'),
            'is_superadmin' => isSuperAdmin(),
        ];
    }

    public function storeKbDocument(StoreAiAgentKbDocumentRequest $request, string $aiAgentUuid)
    {
        if (!userCheckPermission('ai_agent_edit')) {
            return response()->json(['errors' => ['server' => ['Permission denied.']]], 403);
        }

        $inputs = $request->validated();

        try {
            $agent = AiAgent::where('ai_agent_uuid', $aiAgentUuid)
                ->where('domain_uuid', session('domain_uuid'))
                ->firstOrFail();

            if (!$agent->elevenlabs_agent_id) {
                return response()->json([
                    'errors' => ['server' => ['This agent is not linked to ElevenLabs.']]
                ], 422);
            }

            $convaiService = app(ElevenLabsConvaiService::class);

            $kbDoc = new AiAgentKbDocument();
            $kbDoc->kb_document_uuid = (string) Str::uuid();
            $kbDoc->ai_agent_uuid    = $agent->ai_agent_uuid;
            $kbDoc->domain_uuid      = $agent->domain_uuid;
            $kbDoc->document_type    = $inputs['document_type'];
            $kbDoc->name             = $inputs['name'];
            $kbDoc->insert_date      = date('Y-m-d H:i:s');
            $kbDoc->insert_user      = session('user_uuid');

            $elevenlabsResponse = null;

            if ($inputs['document_type'] === 'file') {
                $uploaded = $request->file('file');
                $relativePath = 'ai_agent_kb/' . $agent->domain_uuid . '/' . $kbDoc->kb_document_uuid . '/' . $uploaded->getClientOriginalName();
                Storage::disk('local')->put($relativePath, file_get_contents($uploaded->getRealPath()));

                $kbDoc->file_path      = $relativePath;
                $kbDoc->file_mime_type = $uploaded->getClientMimeType();
                $kbDoc->file_size      = $uploaded->getSize();

                $elevenlabsResponse = $convaiService->uploadKbFile(
                    Storage::disk('local')->path($relativePath),
                    $inputs['name']
                );
            } elseif ($inputs['document_type'] === 'url') {
                $kbDoc->url = $inputs['url'];
                $elevenlabsResponse = $convaiService->addKbUrl($inputs['url'], $inputs['name']);
            } else { // text
                $kbDoc->text_content = $inputs['text'];
                $elevenlabsResponse = $convaiService->addKbText($inputs['text'], $inputs['name']);
            }

            $kbDoc->elevenlabs_documentation_id = $elevenlabsResponse['id']
                ?? $elevenlabsResponse['documentation_id']
                ?? null;
            $kbDoc->sync_status = $kbDoc->elevenlabs_documentation_id ? 'synced' : 'failed';
            $kbDoc->save();

            // Re-PATCH the agent with the full KB array
            $this->syncAgentKbList($agent, $convaiService);

            return response()->json([
                'kb_document' => array_merge($kbDoc->toArray(), [
                    'delete_route' => route('ai-agents.kb.delete', [
                        'ai_agent'    => $agent->ai_agent_uuid,
                        'kb_document' => $kbDoc->kb_document_uuid,
                    ]),
                ]),
                'messages' => ['success' => ['Knowledge base document added.']],
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'errors' => ['server' => ['Failed to add knowledge base document. ' . $e->getMessage()]]
            ], 500);
        }
    }

    public function deleteKbDocument(string $aiAgentUuid, string $kbDocumentUuid)
    {
        if (!userCheckPermission('ai_agent_edit')) {
            return response()->json(['errors' => ['server' => ['Permission denied.']]], 403);
        }

        try {
            $agent = AiAgent::where('ai_agent_uuid', $aiAgentUuid)
                ->where('domain_uuid', session('domain_uuid'))
                ->firstOrFail();

            $kbDoc = AiAgentKbDocument::where('kb_document_uuid', $kbDocumentUuid)
                ->where('ai_agent_uuid', $agent->ai_agent_uuid)
                ->firstOrFail();

            $convaiService = app(ElevenLabsConvaiService::class);

            if ($kbDoc->elevenlabs_documentation_id) {
                try {
                    $convaiService->deleteKbDocument($kbDoc->elevenlabs_documentation_id);
                } catch (\Exception $e) {
                    logger('ElevenLabs KB delete warning: ' . $e->getMessage());
                }
            }

            if ($kbDoc->file_path && Storage::disk('local')->exists($kbDoc->file_path)) {
                Storage::disk('local')->delete($kbDoc->file_path);
            }

            $kbDoc->delete();

            // Re-PATCH the agent with the remaining KB array
            $this->syncAgentKbList($agent, $convaiService);

            return response()->json([
                'messages' => ['success' => ['Knowledge base document removed.']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'errors' => ['server' => ['Failed to remove knowledge base document.']]
            ], 500);
        }
    }

    private function syncAgentKbList(AiAgent $agent, ElevenLabsConvaiService $convaiService): void
    {
        if (!$agent->elevenlabs_agent_id) {
            return;
        }

        $docs = AiAgentKbDocument::where('ai_agent_uuid', $agent->ai_agent_uuid)
            ->where('sync_status', 'synced')
            ->whereNotNull('elevenlabs_documentation_id')
            ->get(['document_type', 'elevenlabs_documentation_id', 'name'])
            ->map(fn($d) => [
                'type' => $d->document_type,
                'id'   => $d->elevenlabs_documentation_id,
                'name' => $d->name,
            ])
            ->toArray();

        try {
            $convaiService->setAgentKnowledgeBase($agent->elevenlabs_agent_id, $docs);
        } catch (\Exception $e) {
            logger('ElevenLabs setAgentKnowledgeBase warning: ' . $e->getMessage());
        }
    }

    public function selectAll()
    {
        try {
            $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                ->get($this->model->getKeyName())->pluck($this->model->getKeyName());

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500);
        }
    }
}
