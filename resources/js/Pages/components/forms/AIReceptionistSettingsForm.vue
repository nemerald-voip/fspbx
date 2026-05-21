<template>
    <Skeleton v-if="isFormLoading" />

    <div v-show="!isFormLoading" class="flex flex-col xl:flex-row">
        <div class="basis-3/4">
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                :display-errors="false" :float-placeholders="false">
                <template #empty>
                    <div class="space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>
                            <HiddenElement name="domain_uuid" :meta="true" />

                            <StaticElement name="header" tag="h4" content="AI Receptionist Settings"
                                description="Configure LiveKit, the default voice engine, and model choices used by AI Receptionists." />

                            <ToggleElement name="enabled" text="Enable AI Receptionists" :true-value="true"
                                :false-value="false" :disabled="canEdit" @change="handleEnabledChange" />

                            <StaticElement name="global_header" tag="h4" content="LiveKit Connection"
                                description="LiveKit carries call media and provides bundled inference for the modular pipelines. OpenAI Realtime uses the system OPENAI_API_KEY."
                                :conditions="[showEnabledSystemFields]" />

                            <TextElement name="livekit_url" label="LiveKit URL" :floating="false" :disabled="canEdit"
                                :conditions="[showEnabledSystemFields]" @change="handleLiveKitUrlChange"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <StaticElement name="livekit_hosting_status" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12' },
                                StaticElement: { container: 'rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-600' }
                            }" :conditions="[showEnabledSystemFields]">
                                <template #default>
                                    <div class="space-y-1">
                                        <div class="font-medium text-gray-800">
                                            LiveKit hosting: {{ selectedLiveKitHosting.label }}
                                        </div>
                                        <div>{{ selectedLiveKitHosting.description }}</div>
                                    </div>
                                </template>
                            </StaticElement>

                            <TextElement name="livekit_api_key" label="LiveKit API Key" :floating="false"
                                :disabled="canEdit" :conditions="[showEnabledSystemFields]"
                                description="Required when FS PBX provides LiveKit credentials to a local or external worker."
                                :columns="{ lg: { wrapper: 6 } }" />

                            <TextElement name="livekit_api_secret" label="LiveKit API Secret" input-type="password"
                                :floating="false" :disabled="canEdit" :conditions="[showEnabledSystemFields]"
                                description="Required for local and external workers. Hosted LiveKit/Telnyx agents may receive this from their host."
                                :columns="{ lg: { wrapper: 6 } }" />

                            <StaticElement name="runtime_header" tag="h4" content="Agent Runtime"
                                description="Choose where the AI worker runs. This is separate from where LiveKit media is hosted."
                                :conditions="[showEnabledSystemFields]" />

                            <SelectElement name="agent_runtime" label="Agent Runtime" :native="false"
                                :items="agentRuntimeOptions" label-prop="label" value-prop="value" :search="true"
                                :disabled="canEdit" :strict="false" placeholder="Select agent runtime"
                                @change="handleAgentRuntimeChange" :conditions="[showEnabledSystemFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <StaticElement name="agent_runtime_help" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12' },
                                StaticElement: { container: 'rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-600' }
                            }" :conditions="[showEnabledSystemFields]">
                                <template #default>
                                    <div class="space-y-2">
                                        <div v-for="option in agentRuntimeOptions" :key="option.value">
                                            <div class="font-medium text-gray-800">{{ option.label }}</div>
                                            <div>{{ option.description }}</div>
                                        </div>
                                    </div>
                                </template>
                            </StaticElement>

                            <StaticElement v-if="isInheriting && selectedEnabled" name="inherited_notice" tag="div" :add-classes="{
                                StaticElement: { container: 'rounded-md border border-yellow-200 bg-yellow-50 p-3' }
                            }">
                                <template #default>
                                    <div class="flex items-start gap-3">
                                        <ExclamationTriangleIcon class="size-5 shrink-0 text-yellow-500" />
                                        <div class="text-sm text-yellow-900">
                                            <p class="font-medium">No custom options set. This account is using the
                                                system defaults.</p>
                                        </div>
                                    </div>
                                </template>
                            </StaticElement>

                            <SelectElement name="default_engine" label="Default Engine" :native="false"
                                :items="engineOptions" label-prop="label" value-prop="value" :search="true"
                                :disabled="canEdit" :strict="false" placeholder="Select default engine"
                                @change="handleEngineChange" :conditions="[showEnabledFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <StaticElement name="engine_help" tag="div" :add-classes="{
                                StaticElement: { container: 'rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-600' }
                            }" :conditions="[showEnabledFields]">
                                <template #default>
                                    <div class="space-y-2">
                                        <div v-for="option in engineOptions" :key="option.value">
                                            <div class="font-medium text-gray-800">{{ option.label }}</div>
                                            <div>{{ option.description }}</div>
                                        </div>
                                    </div>
                                </template>
                            </StaticElement>

                            <StaticElement name="pipeline_header" tag="h4" :content="pipelineHeader"
                                :description="pipelineDescription" :conditions="[showPipelineModelFields]" />

                            <SelectElement name="deepgram_model" label="Deepgram STT Model" :native="false"
                                :items="deepgramModelOptions" label-prop="label" value-prop="value" :search="true"
                                allow-absent :strict="false" placeholder="Select or enter a model" :floating="false"
                                :disabled="canEdit" :conditions="[showStandardFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <TextElement name="deepgram_language" label="Deepgram Language" placeholder="en-US"
                                :floating="false" :disabled="canEdit" :conditions="[showStandardFields]"
                                description="Use en for English or multi where supported by the selected model."
                                :columns="{ lg: { wrapper: 6 } }" />

                            <SelectElement name="assemblyai_model" label="AssemblyAI STT Model" :native="false"
                                :items="assemblyaiModelOptions" label-prop="label" value-prop="value" :search="true"
                                allow-absent :strict="false" placeholder="Select or enter a model" :floating="false"
                                :disabled="canEdit" :conditions="[showAssemblyAIFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <TextElement name="assemblyai_language" label="AssemblyAI Language" placeholder="en"
                                :floating="false" :disabled="canEdit" :conditions="[showAssemblyAIFields]"
                                description="Use en for English or multi where supported by the selected model."
                                :columns="{ lg: { wrapper: 6 } }" />

                            <SelectElement name="openai_model" label="OpenAI LLM Model" :native="false"
                                :items="openaiLlmModelOptions" label-prop="label" value-prop="value" :search="true"
                                allow-absent :strict="false" placeholder="Select or enter a model" :floating="false"
                                :disabled="canEdit" :conditions="[showPipelineModelFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <SelectElement name="elevenlabs_model" label="ElevenLabs TTS Model" :native="false"
                                :items="elevenlabsModelOptions" label-prop="label" value-prop="value" :search="true"
                                allow-absent :strict="false" placeholder="Select or enter a model" :floating="false"
                                :disabled="canEdit" :conditions="[showPipelineModelFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <SelectElement name="elevenlabs_voice_id" label="ElevenLabs Voice"
                                :items="elevenlabsVoiceOptions" label-prop="label" value-prop="value"
                                :search="true" :native="false" allow-absent :strict="false"
                                placeholder="Select or enter a voice ID" :floating="false" :disabled="canEdit"
                                :conditions="[showPipelineModelFields]"
                                description="Default ElevenLabs voices supported by LiveKit Inference."
                                :columns="{ lg: { wrapper: 6 } }" />

                            <TextElement name="elevenlabs_language" label="ElevenLabs Language" placeholder="en"
                                :floating="false" :disabled="canEdit" :conditions="[showPipelineModelFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <StaticElement name="openai_realtime_header" tag="h4" content="OpenAI Realtime"
                                description="Uses OpenAI Realtime directly through the system OPENAI_API_KEY. LiveKit still carries the call media."
                                :conditions="[showOpenAIRealtimeFields]" />

                            <SelectElement name="openai_realtime_model" label="OpenAI Realtime Model" :native="false"
                                :items="openaiRealtimeModelOptions" label-prop="label" value-prop="value" :search="true"
                                allow-absent :strict="false" placeholder="Select or enter a model" :floating="false"
                                :disabled="canEdit" :conditions="[showOpenAIRealtimeFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <SelectElement name="openai_voice" label="OpenAI Realtime Voice" :native="false"
                                :items="openaiVoiceOptions" label-prop="label" value-prop="value" :search="true"
                                allow-absent :strict="false" placeholder="Select or enter a voice" :floating="false"
                                :disabled="canEdit" :conditions="[showOpenAIRealtimeFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <StaticElement v-if="showExternalAgentNotice" name="external_agent_service" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12' },
                                StaticElement: { container: 'rounded-md border border-indigo-100 bg-indigo-50 p-4' }
                            }">
                                <template #default>
                                    <div class="space-y-2 text-sm text-indigo-900">
                                        <h4 class="font-semibold">Agent Service Managed Outside This Server</h4>
                                        <p>
                                            Selected runtime: {{ selectedAgentRuntimeLabel }}. FS PBX will keep the
                                            PBX API, routing policy, tools, and transfer control, but this page will not
                                            start or stop a local Supervisor service.
                                        </p>
                                    </div>
                                </template>
                            </StaticElement>

                            <StaticElement v-if="showLocalAgentControls" name="agent_service" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12' },
                                StaticElement: { container: 'rounded-md border border-gray-200 bg-white p-4' }
                            }">
                                <template #default>
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900">Agent Service</h4>
                                            <p class="mt-1 text-sm text-gray-600">
                                                Start the Python LiveKit worker after the settings above are saved.
                                            </p>
                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                                <span :class="serviceStatusBadgeClass">
                                                    {{ serviceStatusLabel }}
                                                </span>
                                                <button type="button"
                                                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                                                    :disabled="serviceLoading" @click="getAgentServiceStatus">
                                                    Refresh
                                                </button>
                                            </div>
                                            <ul v-if="agentServiceStatus?.readiness_errors?.length"
                                                class="mt-3 list-disc space-y-1 pl-5 text-sm text-amber-700">
                                                <li v-for="message in agentServiceStatus.readiness_errors" :key="message">
                                                    {{ message }}
                                                </li>
                                            </ul>
                                            <p v-if="agentServiceStatus?.raw"
                                                class="mt-3 break-words font-mono text-xs text-gray-500">
                                                {{ agentServiceStatus.raw }}
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap gap-2">
                                            <button type="button"
                                                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="serviceLoading || !agentServiceStatus?.ready"
                                                @click="submitAgentServiceAction('start')">
                                                <Spinner :show="isServiceActionLoading('start')" class="h-4 w-4" />
                                                <span>Start</span>
                                            </button>
                                            <button type="button"
                                                class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="serviceLoading || !agentServiceStatus?.ready"
                                                @click="submitAgentServiceAction('restart')">
                                                <Spinner :show="isServiceActionLoading('restart')" class="h-4 w-4 text-gray-700" />
                                                <span>Restart</span>
                                            </button>
                                            <button type="button"
                                                class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="serviceLoading"
                                                @click="submitAgentServiceAction('stop')">
                                                <Spinner :show="isServiceActionLoading('stop')" class="h-4 w-4 text-gray-700" />
                                                <span>Stop</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </StaticElement>

                            <StaticElement name="actions_row" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12 !mb-0' },
                                StaticElement: { container: 'mt-4' }
                            }">
                                <template #default>
                                    <div class="flex justify-start gap-3">
                                        <ButtonElement v-if="showOverrideBtn" name="overrideDefaults" :secondary="true"
                                            button-label="Override Defaults" @click="startOverride" />

                                        <ButtonElement v-if="showSaveBtn" name="save" button-label="Save"
                                            :submits="true" />

                                        <ButtonElement v-if="showRevertBtn" name="revertDefaults" :secondary="true"
                                            button-label="Revert to Defaults" @click="revertToDefaults" />

                                        <ButtonElement v-if="showCancelBtn" name="cancelOverride" :secondary="true"
                                            button-label="Cancel" @click="cancelOverride" />
                                    </div>
                                </template>
                            </StaticElement>
                        </FormElements>
                    </div>
                </template>
            </Vueform>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import Skeleton from "@generalComponents/Skeleton.vue";
import Spinner from "@generalComponents/Spinner.vue";
import { ExclamationTriangleIcon } from "@heroicons/vue/20/solid";

const props = defineProps({
    domain_uuid: String,
    routes: Object,
});

const emit = defineEmits(["error", "success"]);

const form$ = ref(null);
const settings = ref({});
const isFormLoading = ref(false);
const isOverride = ref(false);
const selectedEnabled = ref(false);
const selectedEngine = ref("standard_pipeline");
const selectedAgentRuntime = ref("local_worker");
const selectedLiveKitUrl = ref("");
const extraProviderConfig = ref({});
const agentServiceStatus = ref(null);
const serviceLoading = ref(false);
const serviceActionLoading = ref(null);

const engineOptions = [
    {
        value: "standard_pipeline",
        label: "Deepgram STT + OpenAI LLM + ElevenLabs TTS",
        description: "Recommended modular path. Deepgram STT, OpenAI LLM, and ElevenLabs TTS are provided by LiveKit Inference.",
    },
    {
        value: "openai_realtime",
        label: "OpenAI Realtime Speech-to-Speech",
        description: "Premium low-latency speech-to-speech path using OpenAI Realtime through the system OPENAI_API_KEY.",
    },
    {
        value: "assemblyai_agent",
        label: "AssemblyAI Realtime Agent",
        description: "AssemblyAI STT, OpenAI LLM, and ElevenLabs TTS are provided by LiveKit Inference.",
    },
];

const agentRuntimeOptions = [
    {
        value: "local_worker",
        label: "Local FS PBX Worker",
        description: "Run the Python LiveKit worker on this FS PBX server. This page can start, stop, and check the local Supervisor service.",
    },
    {
        value: "external_worker",
        label: "External Self-Hosted Worker",
        description: "Run the same Python worker on another VM or container. It connects back to this FS PBX API for PBX tools and transfers.",
    },
    {
        value: "livekit_cloud_agent",
        label: "LiveKit Cloud Hosted Agent",
        description: "Deploy the worker as a managed LiveKit Cloud agent. LiveKit hosts the worker and FS PBX remains the PBX policy boundary.",
    },
    {
        value: "telnyx_hosted_agent",
        label: "Telnyx Hosted Agent",
        description: "Deploy the worker on LiveKit on Telnyx. Telnyx hosts the worker and FS PBX remains the PBX policy boundary.",
    },
];

const deepgramModelOptions = [
    { value: "deepgram/flux-general", label: "Flux General (Recommended)" },
    { value: "deepgram/nova-3", label: "Nova 3" },
    { value: "deepgram/nova-3-medical", label: "Nova 3 Medical" },
    { value: "deepgram/nova-2", label: "Nova 2" },
    { value: "deepgram/nova-2-conversationalai", label: "Nova 2 Conversational AI" },
    { value: "deepgram/nova-2-medical", label: "Nova 2 Medical" },
    { value: "deepgram/nova-2-phonecall", label: "Nova 2 Phone Call" },
];

const openaiLlmModelOptions = [
    { value: "openai/gpt-4.1-mini", label: "GPT-4.1 mini (Recommended)" },
    { value: "openai/gpt-4o-mini", label: "GPT-4o mini" },
    { value: "openai/gpt-4.1-nano", label: "GPT-4.1 nano" },
    { value: "openai/gpt-4.1", label: "GPT-4.1" },
    { value: "openai/gpt-4o", label: "GPT-4o" },
    { value: "openai/gpt-5-nano", label: "GPT-5 nano" },
    { value: "openai/gpt-5-mini", label: "GPT-5 mini" },
    { value: "openai/gpt-5", label: "GPT-5" },
    { value: "openai/gpt-5.1-chat-latest", label: "GPT-5.1 Chat" },
    { value: "openai/gpt-5.2-chat-latest", label: "GPT-5.2 Chat" },
    { value: "openai/gpt-5.3-chat-latest", label: "GPT-5.3 Chat" },
    { value: "openai/gpt-5.4-mini", label: "GPT-5.4 mini" },
    { value: "openai/gpt-5.4", label: "GPT-5.4" },
];

const elevenlabsModelOptions = [
    { value: "elevenlabs/eleven_flash_v2_5", label: "Eleven Flash v2.5 (Recommended)" },
    { value: "elevenlabs/eleven_flash_v2", label: "Eleven Flash v2" },
    { value: "elevenlabs/eleven_multilingual_v2", label: "Eleven Multilingual v2" },
    { value: "elevenlabs/eleven_turbo_v2", label: "Eleven Turbo v2" },
    { value: "elevenlabs/eleven_turbo_v2_5", label: "Eleven Turbo v2.5" },
    { value: "elevenlabs/eleven_v3", label: "Eleven v3" },
];

const elevenlabsVoiceOptions = [
    { value: "XrExE9yKIg1WjnnlVkGX", label: "Matilda (Recommended)" },
    { value: "hpp4J3VqNfWAUOO0d1Us", label: "Bella" },
    { value: "CwhRBWXzGAHq8TQ4Fs17", label: "Roger" },
    { value: "EXAVITQu4vr4xnSDxMaL", label: "Sarah" },
    { value: "FGY2WhTYpPnrIDTdsKH5", label: "Laura" },
    { value: "IKne3meq5aSn9XLyUdCD", label: "Charlie" },
    { value: "JBFqnCBsd6RMkjVDRZzb", label: "George" },
    { value: "N2lVS1w4EtoT3dr4eOWO", label: "Callum" },
    { value: "SAz9YHcvj6GT2YYXdXww", label: "River" },
    { value: "SOYHLrjzK2X1ezoPC6cr", label: "Harry" },
    { value: "TX3LPaxmHKxFdv7VOQHJ", label: "Liam" },
    { value: "Xb7hH8MSUJpSbSDYk0k2", label: "Alice" },
    { value: "bIHbv24MWmeRgasZH58o", label: "Will" },
    { value: "cgSgspJ2msm6clMCkdW9", label: "Jessica" },
    { value: "cjVigY5qzO86Huf0OWal", label: "Eric" },
    { value: "iP95p4xoKVk53GoZ742B", label: "Chris" },
    { value: "nPczCjzI2devNBz1zQrb", label: "Brian" },
    { value: "onwK4e9ZLuTAKqWW03F9", label: "Daniel" },
    { value: "pFZP5JQG7iQjIQuC4Bku", label: "Lily" },
    { value: "pNInz6obpgDQGcFmaJgB", label: "Adam" },
    { value: "pqHfZKP75CvOlQylNhV4", label: "Bill" },
];

const openaiRealtimeModelOptions = [
    { value: "gpt-realtime", label: "GPT Realtime (Recommended)" },
    { value: "gpt-4o-realtime-preview", label: "GPT-4o Realtime Preview" },
    { value: "gpt-4o-mini-realtime-preview", label: "GPT-4o mini Realtime Preview" },
];

const openaiVoiceOptions = [
    { value: "marin", label: "Marin (Recommended)" },
    { value: "alloy", label: "Alloy" },
    { value: "ash", label: "Ash" },
    { value: "ballad", label: "Ballad" },
    { value: "coral", label: "Coral" },
    { value: "echo", label: "Echo" },
    { value: "sage", label: "Sage" },
    { value: "shimmer", label: "Shimmer" },
    { value: "verse", label: "Verse" },
];

const assemblyaiModelOptions = [
    { value: "assemblyai/u3-rt-pro", label: "Universal 3 Realtime Pro (Recommended)" },
    { value: "assemblyai/universal-streaming", label: "Universal Streaming English" },
    { value: "assemblyai/universal-streaming-multilingual", label: "Universal Streaming Multilingual" },
];

const isInheriting = computed(() => settings.value?.scope === "system" && !!settings.value?.domain_uuid);
const hasDomainOverride = computed(() => settings.value?.scope === "domain" && !!settings.value?.domain_uuid);
const showOverrideBtn = computed(() => isInheriting.value && !isOverride.value);
const showSaveBtn = computed(() => !props.domain_uuid || hasDomainOverride.value || isOverride.value);
const showRevertBtn = computed(() => hasDomainOverride.value);
const showCancelBtn = computed(() => isOverride.value && isInheriting.value);
const canEdit = computed(() => props.domain_uuid && !hasDomainOverride.value && !isOverride.value);
const pipelineHeader = computed(() => (
    selectedEngine.value === "assemblyai_agent"
        ? "AssemblyAI + OpenAI + ElevenLabs"
        : "Deepgram + OpenAI + ElevenLabs"
));
const pipelineDescription = computed(() => (
    selectedEngine.value === "assemblyai_agent"
        ? "AssemblyAI STT, OpenAI LLM, and ElevenLabs TTS are served by LiveKit Inference."
        : "Deepgram STT, OpenAI LLM, and ElevenLabs TTS are served by LiveKit Inference."
));
const serviceStatusLabel = computed(() => {
    const status = agentServiceStatus.value?.status;
    if (!status) return "Status unknown";
    return `Status: ${status.replace("_", " ")}`;
});
const serviceStatusBadgeClass = computed(() => {
    const status = agentServiceStatus.value?.status;
    const base = "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset";
    if (status === "running") return `${base} bg-emerald-50 text-emerald-700 ring-emerald-600/20`;
    if (status === "stopped") return `${base} bg-gray-50 text-gray-700 ring-gray-500/20`;
    if (status === "not_installed" || status === "fatal" || status === "backoff") {
        return `${base} bg-rose-50 text-rose-700 ring-rose-600/20`;
    }
    return `${base} bg-amber-50 text-amber-700 ring-amber-600/20`;
});
const selectedAgentRuntimeLabel = computed(() => (
    agentRuntimeOptions.find((option) => option.value === selectedAgentRuntime.value)?.label ?? "External agent"
));
const showLocalAgentControls = computed(() => (
    !props.domain_uuid && selectedEnabled.value && selectedAgentRuntime.value === "local_worker"
));
const showExternalAgentNotice = computed(() => (
    !props.domain_uuid && selectedEnabled.value && selectedAgentRuntime.value !== "local_worker"
));
const selectedLiveKitHosting = computed(() => detectLiveKitHosting(selectedLiveKitUrl.value));

onMounted(() => {
    getSettings();
});

function startOverride() {
    isOverride.value = true;
}

async function revertToDefaults() {
    if (!props.domain_uuid) return;

    await axios.delete(props.routes.ai_receptionist_settings_route, {
        data: { domain_uuid: props.domain_uuid },
    });

    isOverride.value = false;
    await getSettings();
}

function cancelOverride() {
    isOverride.value = false;
    updateForm(settings.value ?? {});
}

function handleEngineChange(value) {
    selectedEngine.value = value || "standard_pipeline";
}

function handleAgentRuntimeChange(value) {
    selectedAgentRuntime.value = value || "local_worker";

    if (selectedAgentRuntime.value === "local_worker") {
        getAgentServiceStatus();
    } else {
        agentServiceStatus.value = null;
    }
}

function handleLiveKitUrlChange(value) {
    selectedLiveKitUrl.value = value ?? "";
}

function handleEnabledChange(value) {
    selectedEnabled.value = !!value;
}

function showEnabledFields() {
    return selectedEnabled.value;
}

function showEnabledSystemFields() {
    return selectedEnabled.value && !props.domain_uuid;
}

function showStandardFields() {
    return selectedEnabled.value && selectedEngine.value === "standard_pipeline";
}

function showOpenAIRealtimeFields() {
    return selectedEnabled.value && selectedEngine.value === "openai_realtime";
}

function showAssemblyAIFields() {
    return selectedEnabled.value && selectedEngine.value === "assemblyai_agent";
}

function showPipelineModelFields() {
    return showStandardFields() || showAssemblyAIFields();
}

const getSettings = async () => {
    isFormLoading.value = true;
    try {
        const { data } = await axios.get(props.routes.ai_receptionist_settings_route, {
            params: { domain_uuid: props.domain_uuid ?? null },
        });

        settings.value = data ?? {};
        updateForm(settings.value);
        if (hasDomainOverride.value) isOverride.value = false;
        return data;
    } catch (err) {
        emit("error", err);
        settings.value = {};
        return {};
    } finally {
        isFormLoading.value = false;
    }
};

function updateForm(data) {
    const providerConfig = data.provider_config ?? {};
    selectedEnabled.value = data.enabled ?? false;
    selectedEngine.value = data.default_engine ?? "standard_pipeline";
    selectedAgentRuntime.value = data.agent_runtime ?? "local_worker";
    selectedLiveKitUrl.value = data.livekit_url ?? "";
    extraProviderConfig.value = stripKnownKeys(providerConfig, knownProviderConfigKeys);

    form$.value?.update({
        domain_uuid: props.domain_uuid ?? null,
        enabled: data.enabled ?? false,
        default_engine: data.default_engine ?? "standard_pipeline",
        agent_runtime: data.agent_runtime ?? "local_worker",
        livekit_url: data.livekit_url ?? null,
        livekit_api_key: data.livekit_api_key ?? null,
        livekit_api_secret: data.livekit_api_secret ?? null,
        deepgram_model: normalizeInferenceModel(providerConfig.deepgram_model, "deepgram", "deepgram/flux-general"),
        deepgram_language: providerConfig.deepgram_language ?? "en",
        openai_model: normalizeInferenceModel(providerConfig.openai_model, "openai", "openai/gpt-4.1-mini"),
        elevenlabs_model: normalizeInferenceModel(providerConfig.elevenlabs_model, "elevenlabs", "elevenlabs/eleven_flash_v2_5"),
        elevenlabs_voice_id: providerConfig.elevenlabs_voice_id ?? "XrExE9yKIg1WjnnlVkGX",
        elevenlabs_language: providerConfig.elevenlabs_language ?? "en",
        openai_realtime_model: providerConfig.openai_realtime_model ?? "gpt-realtime",
        openai_voice: providerConfig.openai_voice ?? "marin",
        assemblyai_model: normalizeInferenceModel(providerConfig.assemblyai_model, "assemblyai", "assemblyai/u3-rt-pro"),
        assemblyai_language: providerConfig.assemblyai_language ?? "en",
    });

    if (!props.domain_uuid && selectedEnabled.value && selectedAgentRuntime.value === "local_worker") {
        getAgentServiceStatus();
    } else {
        agentServiceStatus.value = null;
    }
}

const knownProviderConfigKeys = [
    "deepgram_model",
    "deepgram_language",
    "openai_model",
    "elevenlabs_model",
    "elevenlabs_voice_id",
    "elevenlabs_language",
    "openai_realtime_model",
    "openai_voice",
    "assemblyai_model",
    "assemblyai_language",
];

function normalizeInferenceModel(value, provider, fallback) {
    if (!value) return fallback;
    return String(value).includes("/") ? value : `${provider}/${value}`;
}

function stripKnownKeys(config, knownKeys) {
    return Object.fromEntries(
        Object.entries(config ?? {})
            .filter(([key]) => !knownKeys.includes(key))
    );
}

const submitForm = async (FormData, form$) => {
    const requestData = { ...form$.requestData };
    requestData.domain_uuid = props.domain_uuid ?? null;
    requestData.provider_config = {
        ...extraProviderConfig.value,
        ...compactProviderConfig(requestData),
    };

    knownProviderConfigKeys.forEach((key) => {
        delete requestData[key];
    });

    return await form$.$vueform.services.axios.post(props.routes.ai_receptionist_settings_store_route, requestData);
};

function compactProviderConfig(requestData) {
    return Object.fromEntries(
        knownProviderConfigKeys
            .map((key) => [key, requestData[key]])
            .filter(([, value]) => value !== null && value !== undefined && value !== "")
    );
}

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    isOverride.value = false;
    getSettings();
};

const handleError = (error) => {
    emit("error", error);
};

async function getAgentServiceStatus() {
    if (props.domain_uuid || selectedAgentRuntime.value !== "local_worker" || !props.routes.ai_receptionist_service_status_route) return;

    serviceLoading.value = true;
    try {
        const { data } = await axios.get(props.routes.ai_receptionist_service_status_route);
        agentServiceStatus.value = data;
    } catch (error) {
        emit("error", error);
    } finally {
        serviceLoading.value = false;
    }
}

async function submitAgentServiceAction(action) {
    if (props.domain_uuid || selectedAgentRuntime.value !== "local_worker" || !props.routes.ai_receptionist_service_control_route) return;

    serviceLoading.value = true;
    serviceActionLoading.value = action;
    try {
        const { data } = await axios.post(props.routes.ai_receptionist_service_control_route, { action });
        agentServiceStatus.value = data.service ?? agentServiceStatus.value;
        emit("success", "success", data.messages);
    } catch (error) {
        if (error.response?.data?.service) {
            agentServiceStatus.value = error.response.data.service;
        }
        emit("error", error);
    } finally {
        serviceLoading.value = false;
        if (serviceActionLoading.value === action) {
            serviceActionLoading.value = null;
        }
    }
}

function isServiceActionLoading(action) {
    return serviceActionLoading.value === action;
}

function detectLiveKitHosting(value) {
    if (!value) {
        return {
            label: "Not configured",
            description: "Enter a LiveKit URL to identify where the media server is hosted.",
        };
    }

    let host = "";
    try {
        host = new URL(value).hostname.toLowerCase();
    } catch {
        host = String(value)
            .replace(/^wss?:\/\//, "")
            .replace(/^https?:\/\//, "")
            .split("/")[0]
            .split(":")[0]
            .toLowerCase();
    }

    if (host.endsWith("livekit.cloud")) {
        return {
            label: "LiveKit Cloud",
            description: "Media, SIP, and rooms are hosted by LiveKit Cloud.",
        };
    }

    if (host.endsWith("livekit-telnyx.com")) {
        return {
            label: "LiveKit on Telnyx",
            description: "Media, SIP, and rooms are hosted on Telnyx infrastructure.",
        };
    }

    if (["localhost", "127.0.0.1", "::1"].includes(host)) {
        return {
            label: "Local LiveKit",
            description: "The LiveKit media server appears to be running locally.",
        };
    }

    return {
        label: "Custom or self-hosted LiveKit",
        description: "The LiveKit media server appears to use a custom host.",
    };
}
</script>
