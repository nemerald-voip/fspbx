<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel class="relative transform rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-heading">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-surface text-subtle hover:text-muted focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-info"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-info m-auto">Loading...</div>
                                </div>
                            </div>

                            <div v-if="duplicateCallBlock"
                                class="mb-4 rounded-md bg-warning-subtle p-3 text-sm text-warning ring-1 ring-inset ring-warning">
                                This caller already has a call block rule: {{ duplicateCallBlock.label }}.
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <FormElements>
                                        <HiddenElement name="call_block_uuid" :meta="true" />

                                        <StaticElement name="settings_header" tag="h4" content="Call Block Rule" />

                                        <SelectElement name="call_block_direction" :items="directionOptions"
                                            :native="false" label="Direction" :floating="false"
                                            :columns="{ sm: { container: 6 } }" />

                                        <SelectElement name="extension_uuid" :items="extensionScopeOptions"
                                            :native="false" label="Scope" :floating="false"
                                            :columns="{ sm: { container: 6 } }" />

                                        <StaticElement name="match_header" tag="h4" content="Match Caller"
                                            description="Use Caller ID Name, Caller ID Number, or both." />

                                        <TextElement name="call_block_name" label="Caller ID Name"
                                            description="Regular expressions (Regex) are supported. Example: ^\?SPAM"
                                            :floating="false" :columns="{ sm: { container: 12 } }" />

                                        <TextElement name="call_block_country_code" label="Country Code"
                                            :floating="false" :columns="{ sm: { container: 3 } }" />

                                        <TextElement name="call_block_number" label="Caller ID Number"
                                            :floating="false" :columns="{ sm: { container: 9 } }" />

                                        <StaticElement name="action_header" tag="h4" content="Action" />

                                        <SelectElement name="call_block_action" :items="actionOptions"
                                            :native="false" label="Action" :floating="false"
                                            :columns="{ sm: { container: 6 } }"
                                            @change="(newValue, oldValue, el$) => {
                                                if (oldValue !== null && oldValue !== undefined && selectedActionApp(newValue) !== 'voicemail') {
                                                    el$.form$.el$('call_block_voicemail')?.clear()
                                                }
                                            }" />

                                        <SelectElement name="call_block_voicemail" :items="voicemailOptions"
                                            :native="false" :search="true" label="Voicemail" :floating="false"
                                            placeholder="Select Mailbox" :columns="{ sm: { container: 6 } }"
                                            :conditions="[isVoicemailAction]" />

                                        <GroupElement name="enabled_row_spacer" />

                                        <ToggleElement name="call_block_enabled" text="Enabled"
                                            true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                            :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                        <TextareaElement name="call_block_description" label="Description"
                                            :floating="false" :rows="2" />

                                        <GroupElement name="button_container" />

                                        <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
                                    </FormElements>
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
    header: {
        type: String,
        default: "Call Block",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);
const form$ = ref(null);

const directionOptions = [
    { label: "Inbound", value: "inbound" },
    { label: "Outbound", value: "outbound" },
];

const allowedActions = ["reject", "busy", "voicemail"];

const selectedActionApp = (value) => {
    const rawValue = value && typeof value === "object"
        ? value.value ?? value.name ?? value.label ?? ""
        : value;

    return String(rawValue ?? "").split(":")[0];
};

const isVoicemailAction = (form$) => selectedActionApp(form$.el$("call_block_action")?.value) === "voicemail";

const actionValue = (item) => {
    const app = item?.call_block_app ?? "reject";

    if (!allowedActions.includes(app)) {
        return "reject:";
    }

    return app === "voicemail" ? "voicemail" : `${app}:`;
};

const defaultValues = computed(() => ({
    call_block_uuid: props.options?.item?.call_block_uuid ?? null,
    call_block_direction: props.options?.item?.call_block_direction ?? "inbound",
    extension_uuid: props.options?.item?.extension_uuid ?? "",
    call_block_name: props.options?.item?.call_block_name ?? null,
    call_block_country_code: props.options?.item?.call_block_country_code ?? null,
    call_block_number: props.options?.item?.call_block_number ?? null,
    call_block_action: actionValue(props.options?.item),
    call_block_voicemail: props.options?.item?.call_block_app === "voicemail"
        ? props.options?.item?.call_block_data ?? null
        : null,
    call_block_enabled: props.options?.item?.call_block_enabled ?? "true",
    call_block_description: props.options?.item?.call_block_description ?? null,
}));

const extensionScopeOptions = computed(() => props.options?.extension_scope_options ?? []);
const actionOptions = computed(() => props.options?.action_options ?? []);
const voicemailOptions = computed(() => props.options?.voicemail_options ?? []);
const duplicateCallBlock = computed(() => props.options?.duplicate_call_block ?? null);

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
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
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => clearErrorsRecursive(el$));

    if (response?.data?.errors) {
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
