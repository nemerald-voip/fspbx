<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <button type="button"
                                class="absolute right-4 top-4 rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="emit('close')">
                                <span class="sr-only">Close</span>
                                <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                            </button>

                            <div v-if="loading" class="py-10 text-center text-sm text-gray-500">Loading...</div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :float-placeholders="false" :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'name',
                                                    'extension',
                                                    'openai_voice',
                                                    'voice_preview',
                                                    'description',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="conversation" label="Conversation" :elements="[
                                                    'system_prompt',
                                                    'initial_message',
                                                    'max_duration_seconds',
                                                    'user_silence_checkin_seconds',
                                                    'user_idle_timeout_seconds',
                                                    'allow_interruptions',
                                                    'min_interruption_duration',
                                                    'transcript_enabled',
                                                    'tool_access_enabled',
                                                    'conversation_submit',
                                                ]" />
                                                <FormTab name="Fallback" label="Fallback" :elements="[
                                                    'fallback_type',
                                                    'fallback_target',
                                                    'fallback_label',
                                                    'fallback_submit',
                                                ]" />
                                                <FormTab name="routes" label="Routes" :elements="[
                                                    'routes',
                                                    'routes_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <TextElement name="name" label="Name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="extension" label="Extension" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="openai_voice" label="Voice" :native="false"
                                                    :items="openaiVoiceOptions" label-prop="label" value-prop="value"
                                                    :search="true" allow-absent :strict="false"
                                                    placeholder="Select or enter a voice" :floating="false"
                                                    :columns="{ sm: { container: 8 } }" />

                                                <ButtonElement v-if="!voicePreviewLoading" @click="previewVoice"
                                                    name="voice_preview" label="&nbsp;" :secondary="true"
                                                    :columns="{ sm: { container: 4 } }"
                                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                    <PlayCircleIcon
                                                        class="h-8 w-8 shrink-0 cursor-pointer rounded-full py-1 text-blue-400 ring-1 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150" />
                                                </ButtonElement>

                                                <ButtonElement v-if="voicePreviewLoading" name="voice_preview_loading"
                                                    label="&nbsp;" :secondary="true" :columns="{ sm: { container: 4 } }"
                                                    :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                    <ArrowPathIcon
                                                        class="h-8 w-8 shrink-0 animate-spin rounded-full py-1 text-blue-400 ring-1" />
                                                </ButtonElement>

                                                <TextareaElement name="description" label="Description" :rows="2" />

                                                <ButtonElement name="settings_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <TextareaElement name="system_prompt" label="System Prompt" :rows="8" />

                                                <TextareaElement name="initial_message" label="Initial Message" :rows="3" />

                                                <TextElement name="max_duration_seconds" input-type="number"
                                                    label="Max Duration Seconds" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="user_silence_checkin_seconds" input-type="number"
                                                    label="Check In After Silence Seconds" :floating="false"
                                                    description="How long the caller can be silent before the assistant prompts them to respond."
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="user_idle_timeout_seconds" input-type="number"
                                                    label="Stop After User Silence Seconds" :floating="false"
                                                    description="How long the caller can be silent before the active assistant session stops."
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="allow_interruptions" text="Allow Interruptions"
                                                    :true-value="true" :false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextElement name="min_interruption_duration" input-type="number"
                                                    label="Minimum Interruption Seconds" :floating="false"
                                                    description="Minimum caller speech duration before it interrupts the assistant."
                                                    :step="0.1" :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="transcript_enabled" text="Save Transcript"
                                                    :true-value="true" :false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <ToggleElement name="tool_access_enabled" text="Allow Tools"
                                                    :true-value="true" :false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <ButtonElement name="conversation_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <SelectElement name="fallback_type" :items="routingTypes"
                                                    label-prop="name" value-prop="value" :search="true" :native="false"
                                                    label="Fallback Action" input-type="search" autocomplete="off"
                                                    placeholder="Choose Action" :floating="false" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" @change="(newValue, oldValue, el$) => {
                                                        const target = el$.form$.el$('fallback_target');
                                                        if (oldValue !== null && oldValue !== undefined) {
                                                            target.clear();
                                                        }
                                                        target.updateItems();
                                                    }" />

                                                <SelectElement name="fallback_target" :items="async (query, input) => {
                                                    const fallbackType = input.$parent.el$.form$.el$('fallback_type');
                                                    try {
                                                        const response = await fallbackType.$vueform.services.axios.post(
                                                            props.options.routes.get_routing_options,
                                                            { category: fallbackType.value },
                                                        );
                                                        return response.data.options;
                                                    } catch (error) {
                                                        emit('error', error);
                                                        return [];
                                                    }
                                                }" :search="true" label-prop="name" :native="false" label="Fallback Target"
                                                    input-type="search" allow-absent :object="true"
                                                    :format-data="formatTarget" autocomplete="off"
                                                    placeholder="Choose Target" :floating="false" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" :conditions="[
                                                        ['fallback_type', 'not_empty'],
                                                        ['fallback_type', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                                    ]" />

                                                <HiddenElement name="fallback_label" :meta="true" />

                                                <ButtonElement name="fallback_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <ListElement name="routes" :sort="true" size="sm"
                                                    :controls="{ add: true, remove: true, sort: true }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="route_uuid" :meta="true" />
                                                            <HiddenElement name="destination_label" :meta="true" />

                                                            <TextElement name="name" label="Route Name"
                                                                :floating="false" :columns="{ sm: { container: 6 } }" />

                                                            <TagsElement name="match_phrases" label="Match Phrases"
                                                                :floating="false" :search="true" :create="true"
                                                                :columns="{ sm: { container: 6 } }" />

                                                            <SelectElement name="route_action" :items="actionOptions"
                                                                label="Action" label-prop="name" value-prop="value"
                                                                :native="false" :search="true" :floating="false"
                                                                :columns="{ sm: { container: 6 } }" @change="(newValue, oldValue, el$) => {
                                                                    if (oldValue !== null && oldValue !== undefined) {
                                                                        el$.form$.el$('routes.' + index + '.destination_type')?.clear();
                                                                        el$.form$.el$('routes.' + index + '.destination_target')?.clear();
                                                                        el$.form$.el$('routes.' + index + '.destination_label')?.update(null);
                                                                    }
                                                                }" />

                                                            <SelectElement name="destination_type" :items="routeRoutingTypes"
                                                                label="Destination Type" label-prop="label" value-prop="value"
                                                                :native="false" :search="true" :floating="false"
                                                                :columns="{ sm: { container: 6 } }" :conditions="[
                                                                    ['routes.' + index + '.route_action', 'in', ['warm_transfer', 'cold_transfer']]
                                                                ]" @change="(newValue, oldValue, el$) => {
                                                                    const action = el$.form$.el$('routes.' + index + '.route_action')?.value;
                                                                    const selectedType = optionValue(newValue);
                                                                    const target = el$.form$.el$('routes.' + index + '.destination_target');

                                                                    el$.messageBag.clear();
                                                                    if (action === 'warm_transfer' && !['extensions', 'external'].includes(selectedType)) {
                                                                        el$.messageBag.append('Warm transfer supports only Extension and External Number.');
                                                                    }
                                                                    if (oldValue !== null && oldValue !== undefined) {
                                                                        target?.clear();
                                                                        el$.form$.el$('routes.' + index + '.destination_label')?.update(null);
                                                                    }
                                                                    target?.updateItems();
                                                                }" />

                                                            <SelectElement name="destination_target" :items="async (query, input) => {
                                                                const type = input.$parent.el$.form$.el$('routes.' + index + '.destination_type');
                                                                const category = optionValue(type?.value);
                                                                if (!category || category === 'external') {
                                                                    return [];
                                                                }
                                                                try {
                                                                    const response = await type.$vueform.services.axios.post(
                                                                        props.options.routes.get_routing_options,
                                                                        { category },
                                                                    );
                                                                    return response.data.options;
                                                                } catch (error) {
                                                                    emit('error', error);
                                                                    return [];
                                                                }
                                                            }" :search="true" label-prop="name" :native="false"
                                                                label="Destination" input-type="search" allow-absent
                                                                :object="true" :create="true" autocomplete="off" placeholder="Choose Target"
                                                                :floating="false" :strict="false"
                                                                :columns="{ sm: { container: 6 } }" :conditions="[
                                                                    ['routes.' + index + '.route_action', 'in', ['warm_transfer', 'cold_transfer']],
                                                                    ['routes.' + index + '.destination_type', 'not_empty']
                                                                ]" />

                                                            <TextElement name="email_to" label="Email To"
                                                                :floating="false" :columns="{ sm: { container: 6 } }"
                                                                :conditions="[
                                                                    ['routes.' + index + '.route_action', 'email']
                                                                ]" />

                                                            <TextElement name="email_subject" label="Email Subject"
                                                                :floating="false" :columns="{ sm: { container: 6 } }"
                                                                :conditions="[
                                                                    ['routes.' + index + '.route_action', 'email']
                                                                ]" />

                                                            <TextareaElement name="email_instructions"
                                                                label="Message Instructions" :rows="2" :conditions="[
                                                                    ['routes.' + index + '.route_action', 'email']
                                                                ]" />

                                                            <ToggleElement name="notify_on_failed_warm_transfer"
                                                                text="Notify by Email if Warm Transfer Fails"
                                                                :true-value="true" :false-value="false"
                                                                :labels="{ on: 'On', off: 'Off' }"
                                                                :columns="{ sm: { container: 6 } }" label="&nbsp;"
                                                                :conditions="[
                                                                    ['routes.' + index + '.route_action', 'warm_transfer']
                                                                ]" />

                                                            <TextElement name="failed_transfer_email_to"
                                                                label="Failed Transfer Email To" :floating="false"
                                                                :columns="{ sm: { container: 6 } }" :conditions="[
                                                                    ['routes.' + index + '.route_action', 'warm_transfer'],
                                                                    ['routes.' + index + '.notify_on_failed_warm_transfer', true]
                                                                ]" />

                                                            <ToggleElement name="enabled" text="Enabled"
                                                                :true-value="true" :false-value="false"
                                                                :labels="{ on: 'On', off: 'Off' }"
                                                                :columns="{ sm: { container: 6 } }" label="&nbsp;" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <ButtonElement name="routes_submit" button-label="Save"
                                                    :submits="true" align="right" />
                                            </FormElements>
                                        </div>
                                    </div>
                                </template>
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { ArrowPathIcon, PlayCircleIcon, XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    permissions: Object,
    header: {
        type: String,
        default: "AI Receptionist",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);
const voicePreviewAudio = ref(null);
const voicePreviewLoading = ref(false);

const defaultValues = computed(() => ({
    name: props.options?.item?.name ?? null,
    extension: props.options?.item?.extension ?? null,
    openai_voice: props.options?.item?.openai_voice ?? "marin",
    description: props.options?.item?.description ?? null,
    system_prompt: props.options?.item?.system_prompt ?? "You are a helpful phone receptionist. Ask concise questions, confirm before transferring, and use approved tools only.",
    initial_message: props.options?.item?.initial_message ?? "Thank you for calling. How can I help you today?",
    max_duration_seconds: props.options?.item?.max_duration_seconds ?? 900,
    user_silence_checkin_seconds: props.options?.item?.user_silence_checkin_seconds ?? 15,
    user_idle_timeout_seconds: props.options?.item?.user_idle_timeout_seconds ?? 60,
    allow_interruptions: props.options?.item?.allow_interruptions ?? true,
    min_interruption_duration: props.options?.item?.min_interruption_duration ?? 0.5,
    transcript_enabled: props.options?.item?.transcript_enabled ?? true,
    tool_access_enabled: props.options?.item?.tool_access_enabled ?? true,
    fallback_type: props.options?.item?.fallback_type ?? null,
    fallback_target: {
        value: props.options?.item?.fallback_target_uuid ?? null,
        extension: props.options?.item?.fallback_target_extension ?? props.options?.item?.fallback_target ?? null,
        name: props.options?.item?.fallback_target_name ?? props.options?.item?.fallback_label ?? null,
    },
    fallback_label: props.options?.item?.fallback_label ?? null,
    routes: (props.options?.item?.routes ?? []).map((route) => ({
        route_uuid: route.route_uuid ?? null,
        name: route.name ?? null,
        match_phrases: route.match_phrases ?? [],
        route_action: route.action_type === "email"
            ? "email"
            : (route.transfer_type === "warm" ? "warm_transfer" : "cold_transfer"),
        destination_type: route.destination_type ?? null,
        destination_target: route.destination_target
            ? {
                value: route.destination_target,
                extension: route.destination_target,
                name: route.destination_label ?? route.destination_target,
            }
            : null,
        destination_label: route.destination_label ?? null,
        email_to: route.email_to ?? null,
        email_subject: route.email_subject ?? null,
        email_instructions: route.email_instructions ?? null,
        notify_on_failed_warm_transfer: route.notify_on_failed_warm_transfer ?? false,
        failed_transfer_email_to: route.failed_transfer_email_to ?? null,
        enabled: route.enabled ?? true,
    })),
}));

const routingTypes = computed(() => props.options?.routing_types ?? []);
const routeRoutingTypes = computed(() => props.options?.route_routing_types ?? []);
const actionOptions = [
    { value: "warm_transfer", name: "Warm Transfer" },
    { value: "cold_transfer", name: "Cold Transfer" },
    { value: "email", name: "Take Message and Email" },
];

const openaiVoiceOptions = [
    { value: "marin", label: "Marin (Recommended)" },
    { value: "cedar", label: "Cedar" },
    { value: "alloy", label: "Alloy" },
    { value: "ash", label: "Ash" },
    { value: "ballad", label: "Ballad" },
    { value: "coral", label: "Coral" },
    { value: "echo", label: "Echo" },
    { value: "sage", label: "Sage" },
    { value: "shimmer", label: "Shimmer" },
    { value: "verse", label: "Verse" },
];

const optionValue = (value) => {
    if (value && typeof value === "object") {
        return value.value ?? value.extension ?? null;
    }

    return value;
};

const formatTarget = (value) => {
    if (!value) return value;

    if (typeof value === "object") {
        form$.value?.el$("fallback_label")?.update(value.name ?? null);
        return value.extension ?? value.value ?? null;
    }

    return value;
};

const previewVoice = async () => {
    const route = props.options?.routes?.voice_preview_route;
    const voice = form$.value?.data?.openai_voice ?? "marin";

    if (!route) {
        emit("error", { message: "Voice preview route is missing." });
        return;
    }

    voicePreviewLoading.value = true;

    try {
        if (voicePreviewAudio.value) {
            voicePreviewAudio.value.pause();
        }

        const response = await axios.post(route, { voice }, { responseType: "blob" });
        const audioUrl = URL.createObjectURL(response.data);
        const audio = new Audio(audioUrl);
        voicePreviewAudio.value = audio;
        audio.addEventListener("ended", () => URL.revokeObjectURL(audioUrl), { once: true });
        await audio.play();
    } catch (error) {
        emit("error", error);
    } finally {
        voicePreviewLoading.value = false;
    }
};

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
    const targetValue = form$.el$("fallback_target")?.value;

    if (targetValue && typeof targetValue === "object") {
        requestData.fallback_target = targetValue.extension ?? targetValue.value ?? null;
        requestData.fallback_label = targetValue.name ?? null;
    }

    requestData.routes = (requestData.routes ?? []).map((route, index) => {
        const targetValue = route.destination_target;
        const routeAction = route.route_action ?? "cold_transfer";
        const destinationTarget = typeof targetValue === "object" && targetValue !== null
            ? (targetValue.extension ?? targetValue.value ?? null)
            : targetValue;
        const destinationLabel = typeof targetValue === "object" && targetValue !== null
            ? (targetValue.name ?? destinationTarget)
            : (route.destination_label ?? destinationTarget);

        return {
            ...route,
            action_type: routeAction === "email" ? "email" : "transfer",
            transfer_type: routeAction === "warm_transfer" ? "warm" : (routeAction === "cold_transfer" ? "cold" : null),
            destination_target: routeAction === "email" ? null : destinationTarget,
            destination_label: routeAction === "email" ? null : destinationLabel,
            sort_order: index,
        };
    });

    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(route, requestData);
    }

    return await form$.$vueform.services.axios.put(route, requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => {
            clearErrorsRecursive(childEl$);
        });
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => {
        clearErrorsRecursive(el$);
    });

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append("Could not submit form");
};
</script>
