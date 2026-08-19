<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">{{ $t('Close') }}</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="h-full w-full py-10">
                                <div class="flex items-center justify-center space-x-3">
                                    <svg class="h-10 w-10 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="m-auto text-lg text-blue-600">{{ $t('Loading...') }}</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" :default="defaults" :display-errors="false"
                            autocomplete="off" data-lpignore="true" data-1p-ignore data-bwignore="true"
                            @success="handleSuccess" @error="handleError" @response="handleResponse">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" :label="$t('Settings')" :elements="[
                                                    'settings_header', 'uuid', 'name', 'extension',
                                                    'provider', 'enabled', 'description', 'settings_button_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="call_handling" :label="$t('Call Handling')" :elements="[
                                                    'call_handling_header', 'inbound_agent_id', 'outbound_agent_id',
                                                    'recording_policy',
                                                    'call_handling_button_container', 'call_handling_submit',
                                                ]" />
                                                <FormTab v-if="canManageDomain" name="advanced" :label="$t('Advanced')" :elements="[
                                                    'advanced_header', 'domain_uuid', 'advanced_button_container',
                                                    'advanced_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="space-y-6 bg-gray-50 px-4 py-6 text-gray-600 shadow sm:rounded-md sm:px-6 lg:col-span-9">
                                            <FormElements>
                                                <StaticElement name="settings_header" tag="h4" :content="$t('Agent Settings')"
                                                    :description="$t('Configure the extension and provider used for this AI agent.')" />

                                                <StaticElement name="uuid"
                                                    :conditions="[() => Boolean(options.item?.ai_agent_uuid)]">
                                                    <div class="mb-1">
                                                        <div class="mb-1 text-sm font-medium text-gray-600">{{ $t('Agent UUID') }}</div>
                                                        <div class="flex items-center">
                                                            <span class="select-all break-all font-mono text-sm font-normal text-gray-900">
                                                                {{ options.item?.ai_agent_uuid }}
                                                            </span>
                                                            <button type="button"
                                                                class="ml-2 rounded-full p-1 text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                                :title="$t('Copy to clipboard')"
                                                                :aria-label="$t('Copy to clipboard')"
                                                                @click="handleCopyToClipboard(options.item?.ai_agent_uuid)">
                                                                <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <TextElement name="name" :label="$t('Name')" :floating="false"
                                                    autocomplete="off" :attrs="nonCredentialFieldAttrs"
                                                    :columns="{ sm: { container: canManageProvider ? 8 : 6 } }" />
                                                <TextElement name="extension" :label="$t('Extension')" :floating="false"
                                                    autocomplete="off" :attrs="nonCredentialFieldAttrs"
                                                    :columns="{ sm: { container: canManageProvider ? 4 : 3 } }" />
                                                <SelectElement v-if="canManageProvider" name="provider" :label="$t('Provider')"
                                                    :items="options.providers || []" :native="false" :floating="false"
                                                    autocomplete="off" :attrs="nonCredentialFieldAttrs"
                                                    :disabled="mode === 'update'" @change="emit('provider-change', $event)"
                                                    :columns="{ sm: { container: 8 } }" />
                                                <ToggleElement name="enabled" :text="$t('Enabled')"
                                                    :labels="{ on: $t('On'), off: $t('Off') }"
                                                    :columns="{ sm: { container: canManageProvider ? 4 : 3 } }" label="&nbsp;" />
                                                <TextareaElement name="description" :label="$t('Description')" :rows="2"
                                                    autocomplete="off" :attrs="nonCredentialFieldAttrs" />

                                                <GroupElement name="settings_button_container" />
                                                <ButtonElement name="settings_submit" :button-label="$t('Save')"
                                                    :submits="true" align="right" />

                                                <StaticElement name="call_handling_header" tag="h4" :content="$t('Call Handling')"
                                                    :description="$t('Choose the provider agents used for inbound and outbound calls.')" />
                                                <SelectElement name="inbound_agent_id" :label="$t('Inbound Agent')"
                                                    :items="providerAgents" :search="true" :native="false" :floating="false"
                                                    input-type="search" autocomplete="off" :attrs="nonCredentialFieldAttrs"
                                                    :placeholder="$t('Select a voice agent')"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <SelectElement name="outbound_agent_id" :label="$t('Outbound Agent')"
                                                    :items="providerAgents" :search="true" :native="false" :floating="false"
                                                    input-type="search" autocomplete="off" :attrs="nonCredentialFieldAttrs"
                                                    :placeholder="$t('Optional')" :columns="{ sm: { container: 6 } }" />
                                                <ToggleElement name="recording_policy" :text="$t('Record Calls')"
                                                    true-value="always" false-value="inherit"
                                                    :labels="{ on: $t('On'), off: $t('Off') }" label="&nbsp;"
                                                    :description="$t('When enabled, FS PBX records calls to this agent. When disabled, recording settings from the phone number or call route still apply. This does not control recordings made by the AI provider.')" />

                                                <GroupElement name="call_handling_button_container" />
                                                <ButtonElement name="call_handling_submit" :button-label="$t('Save')"
                                                    :submits="true" align="right" />

                                                <template v-if="canManageDomain">
                                                    <StaticElement name="advanced_header" tag="h4"
                                                        :content="$t('Advanced Settings')" />
                                                    <SelectElement name="domain_uuid" :label="$t('Account')"
                                                        :items="options.domains || []" :search="true" :native="false"
                                                        input-type="search" autocomplete="off" :attrs="nonCredentialFieldAttrs"
                                                        :floating="false" :columns="{ sm: { container: 6 } }" />

                                                    <GroupElement name="advanced_button_container" />
                                                    <ButtonElement name="advanced_submit" :button-label="$t('Save')"
                                                        :submits="true" align="right" />
                                                </template>

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
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { trans } from "@i18n";

const props = defineProps({
    show: Boolean,
    loading: Boolean,
    mode: String,
    options: Object,
    permissions: Object,
    providerAgents: Array,
});
const emit = defineEmits(["close", "error", "success", "refresh-data", "provider-change"]);
const form$ = ref(null);
const nonCredentialFieldAttrs = {
    autocomplete: "off",
    "data-lpignore": "true",
    "data-1p-ignore": "true",
    "data-bwignore": "true",
    "data-form-type": "other",
};

const header = computed(() => props.mode === "create"
    ? trans("Create AI Agent")
    : trans("Update AI Agent - :name", { name: props.options.item?.name || trans("Loading...") }));
const canManageDomain = computed(() => Boolean(props.permissions?.manage_domain));
const canManageProvider = computed(() => Boolean(props.permissions?.manage_provider));
const defaults = computed(() => ({
    domain_uuid: props.options.item?.domain_uuid ?? null,
    provider: props.options.item?.provider ?? props.options.providers?.[0]?.value ?? null,
    name: props.options.item?.name ?? null,
    extension: props.options.item?.extension ?? null,
    enabled: props.options.item?.enabled ?? true,
    inbound_agent_id: props.options.item?.inbound_agent_id ?? null,
    outbound_agent_id: props.options.item?.outbound_agent_id ?? null,
    recording_policy: props.options.item?.recording_policy === "always" ? "always" : "inherit",
    description: props.options.item?.description ?? null,
}));

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit("success", "success", { message: [trans("Copied to clipboard.")] });
    }).catch(() => {
        emit("error", { response: { data: { errors: { request: [trans("Failed to copy to clipboard.")] } } } });
    });
};

const submitForm = async (FormData, form$) => {
    const data = { ...form$.requestData };
    if (!canManageDomain.value) delete data.domain_uuid;
    if (!canManageProvider.value) delete data.provider;
    if (props.mode === "update") data.provider = props.options.item?.provider;
    data.inbound_agent_name = props.providerAgents.find((item) => item.value === data.inbound_agent_id)?.label ?? null;
    data.outbound_agent_name = props.providerAgents.find((item) => item.value === data.outbound_agent_id)?.label ?? null;
    return props.mode === "create"
        ? form$.$vueform.services.axios.post(props.options.routes.submit, data)
        : form$.$vueform.services.axios.put(props.options.routes.submit, data);
};
function clearErrorsRecursive(element) {
    element.messageBag?.clear();

    if (element.children$) {
        Object.values(element.children$).forEach((child) => clearErrorsRecursive(child));
    }
}

const handleResponse = (response, form) => {
    Object.values(form.elements$).forEach((element) => clearErrorsRecursive(element));

    Object.entries(response.data.errors ?? {}).forEach(([name, messages]) => {
        form.el$(name)?.messageBag.append(messages[0]);
    });
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
};
const handleError = (error, details, form) => {
    form.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form.messageBag.append(trans("Could not submit form"));
};
</script>
