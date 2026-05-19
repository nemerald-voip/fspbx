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
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <TextElement name="name" label="Name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="extension" label="Extension" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

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
import { XMarkIcon } from "@heroicons/vue/24/solid";

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

const defaultValues = computed(() => ({
    name: props.options?.item?.name ?? null,
    extension: props.options?.item?.extension ?? null,
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
}));

const routingTypes = computed(() => props.options?.routing_types ?? []);

const formatTarget = (value) => {
    if (!value) return value;

    if (typeof value === "object") {
        form$.value?.el$("fallback_label")?.update(value.name ?? null);
        return value.extension ?? value.value ?? null;
    }

    return value;
};

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
    const targetValue = form$.el$("fallback_target")?.value;

    if (targetValue && typeof targetValue === "object") {
        requestData.fallback_target = targetValue.extension ?? targetValue.value ?? null;
        requestData.fallback_label = targetValue.name ?? null;
    }

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
