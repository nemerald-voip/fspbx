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
                                description="Configure OpenAI Realtime SIP, model choices, and the local call controller used by AI Receptionists." />

                            <ToggleElement name="enabled" text="Enable AI Receptionists" :true-value="true"
                                :false-value="false" :disabled="canEdit" @change="handleEnabledChange" />

                            <StaticElement name="global_header" tag="h4" content="OpenAI SIP Connection"
                                description="FreeSWITCH bridges calls to OpenAI Realtime SIP. The controller accepts OpenAI webhooks and keeps the Realtime WebSocket alive for tools and transcripts."
                                :conditions="[showEnabledSystemFields]" />

                            <TextElement name="openai_project_id" label="OpenAI Project ID" :floating="false"
                                :disabled="canEdit" :conditions="[showEnabledSystemFields]"
                                description="Used to build sip:PROJECT_ID@sip.api.openai.com;transport=tls when a custom bridge target is not provided."
                                :columns="{ lg: { wrapper: 6 } }" />

                            <TextElement name="openai_sip_bridge_target" label="FreeSWITCH SIP Bridge Target" placeholder="Optional"
                                :floating="false" :disabled="canEdit" :conditions="[showEnabledSystemFields]"
                                description="Optional full bridge target. Leave blank to generate sofia/external/sip:PROJECT_ID@sip.api.openai.com;transport=tls."
                                :columns="{ lg: { wrapper: 6 } }" />

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

                            <StaticElement name="openai_realtime_header" tag="h4" content="OpenAI Realtime"
                                description="Uses OpenAI Realtime SIP directly through the system OPENAI_API_KEY."
                                :conditions="[showOpenAIRealtimeFields]" />

                            <SelectElement name="openai_realtime_model" label="OpenAI Realtime Model" :native="false"
                                :items="openaiRealtimeModelOptions" label-prop="label" value-prop="value" :search="true"
                                allow-absent :strict="false" placeholder="Select or enter a model" :floating="false"
                                :disabled="canEdit" :conditions="[showOpenAIRealtimeFields]"
                                :columns="{ lg: { wrapper: 6 } }" />

                            <StaticElement v-if="showLocalAgentControls" name="agent_service" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12' },
                                StaticElement: { container: 'rounded-md border border-gray-200 bg-white p-4' }
                            }">
                                <template #default>
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900">Call Controller</h4>
                                            <p class="mt-1 text-sm text-gray-600">
                                                Start the Python OpenAI Realtime controller after the settings above are saved.
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
const selectedEngine = ref("openai_realtime");
const extraProviderConfig = ref({});
const agentServiceStatus = ref(null);
const serviceLoading = ref(false);
const serviceActionLoading = ref(null);

const engineOptions = [
    {
        value: "openai_realtime",
        label: "OpenAI Realtime SIP",
        description: "Low-latency speech-to-speech calls over OpenAI Realtime SIP using the system OPENAI_API_KEY.",
    },
];

const openaiRealtimeModelOptions = [
    { value: "gpt-realtime-2", label: "GPT Realtime 2 (Recommended)" },
    { value: "gpt-realtime-1.5", label: "GPT Realtime 1.5" },
    { value: "gpt-realtime", label: "GPT Realtime" },
    { value: "gpt-realtime-mini", label: "GPT Realtime mini" },
    { value: "gpt-4o-realtime-preview", label: "GPT-4o Realtime Preview" },
    { value: "gpt-4o-mini-realtime-preview", label: "GPT-4o mini Realtime Preview" },
];

const isInheriting = computed(() => settings.value?.scope === "system" && !!settings.value?.domain_uuid);
const hasDomainOverride = computed(() => settings.value?.scope === "domain" && !!settings.value?.domain_uuid);
const showOverrideBtn = computed(() => isInheriting.value && !isOverride.value);
const showSaveBtn = computed(() => !props.domain_uuid || hasDomainOverride.value || isOverride.value);
const showRevertBtn = computed(() => hasDomainOverride.value);
const showCancelBtn = computed(() => isOverride.value && isInheriting.value);
const canEdit = computed(() => props.domain_uuid && !hasDomainOverride.value && !isOverride.value);
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
const showLocalAgentControls = computed(() => (
    !props.domain_uuid && selectedEnabled.value
));

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
    selectedEngine.value = value || "openai_realtime";
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

function showOpenAIRealtimeFields() {
    return selectedEnabled.value && selectedEngine.value === "openai_realtime";
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
    selectedEngine.value = data.default_engine ?? "openai_realtime";
    extraProviderConfig.value = stripKnownKeys(providerConfig, knownProviderConfigKeys);

    form$.value?.update({
        domain_uuid: props.domain_uuid ?? null,
        enabled: data.enabled ?? false,
        default_engine: data.default_engine ?? "openai_realtime",
        openai_project_id: providerConfig.openai_project_id ?? null,
        openai_sip_bridge_target: providerConfig.openai_sip_bridge_target ?? null,
        openai_realtime_model: providerConfig.openai_realtime_model ?? "gpt-realtime-2",
    });

    if (!props.domain_uuid && selectedEnabled.value) {
        getAgentServiceStatus();
    } else {
        agentServiceStatus.value = null;
    }
}

const knownProviderConfigKeys = [
    "openai_project_id",
    "openai_sip_bridge_target",
    "openai_realtime_model",
    "openai_voice",
];

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
    if (props.domain_uuid || !props.routes.ai_receptionist_service_status_route) return;

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
    if (props.domain_uuid || !props.routes.ai_receptionist_service_control_route) return;

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

</script>
