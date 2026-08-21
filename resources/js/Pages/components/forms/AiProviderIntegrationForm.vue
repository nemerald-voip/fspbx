<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500/75" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                    <DialogPanel class="w-full max-w-5xl rounded-lg bg-white p-6 shadow-xl">
                        <div class="flex items-start justify-between">
                            <DialogTitle class="text-base font-semibold text-gray-900">{{ $t('Provider Settings') }}</DialogTitle>
                            <button type="button"
                                class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="emit('close')">
                                <span class="sr-only">{{ $t('Close') }}</span>
                                <XMarkIcon class="h-6 w-6" />
                            </button>
                        </div>
                        <div v-if="loading" class="py-12 text-center text-sm text-gray-500">{{ $t('Loading...') }}</div>
                        <Vueform v-else ref="form$" :endpoint="submitForm" :default="defaults" :display-errors="false"
                            autocomplete="off" data-lpignore="true" data-1p-ignore data-bwignore="true"
                            @success="handleSuccess" @error="handleError" @response="handleResponse">
                            <template #empty>
                                <div class="mt-4 lg:grid lg:grid-cols-12 lg:gap-x-5">
                                    <div class="px-2 py-4 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                        <FormTabs view="vertical">
                                            <FormTab name="retell" :label="$t('Retell')" :elements="[
                                                'summary',
                                                'enabled',
                                                'api_key',
                                                'public_sip_host',
                                                'provider_ips_header',
                                                'provider_cidrs',
                                                'buttons',
                                            ]" />
                                        </FormTabs>
                                    </div>

                                    <div class="space-y-6 bg-gray-50 px-4 py-6 text-gray-600 shadow sm:rounded-md sm:px-6 lg:col-span-9">
                                        <FormElements>
                                            <StaticElement name="summary" tag="h4" :content="$t('Retell Integration')"
                                                :description="$t('Connect FS PBX to Retell so AI agents can place, receive, and transfer calls.')" />

                                            <ToggleElement name="enabled" :text="$t('Integration Enabled')"
                                                :labels="{ on: $t('On'), off: $t('Off') }" />

                                            <TextElement name="api_key" input-type="password" :label="$t('Retell API Key')"
                                                :placeholder="$t('Enter API key')"
                                                :description="integration.has_api_key ? $t('A key is stored. Enter a new key to replace it.') : null"
                                                :attrs="{ ...nonCredentialFieldAttrs, autocomplete: 'new-password', onFocus: selectMaskedApiKey }"
                                                :rules="integration.has_api_key ? [] : ['required']" :floating="false" />
                                            <TextElement name="public_sip_host" :label="$t('Public SIP Host')"
                                                :description="$t('The public hostname or IP address Retell uses to send calls to this FS PBX server.')"
                                                autocomplete="off" :attrs="nonCredentialFieldAttrs" :floating="false" />

                                            <StaticElement name="provider_ips_header" tag="h4" :content="$t('Provider IPs')"
                                                :description="$t('Enter the IP addresses or CIDR ranges this provider sends traffic from.')" />

                                            <ListElement name="provider_cidrs" :sort="true" size="sm" :initial="0"
                                                :controls="{ add: true, remove: true, sort: true }"
                                                :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                                <template #default="{ index }">
                                                    <ObjectElement :name="index">
                                                        <TextElement name="node_cidr" :label="$t('IP / CIDR')" autocomplete="off"
                                                            :attrs="nonCredentialFieldAttrs"
                                                            placeholder="203.0.113.10 or 198.51.100.0/24" :floating="false"
                                                            :columns="{ sm: { container: 12 } }" />
                                                    </ObjectElement>
                                                </template>
                                            </ListElement>

                                            <GroupElement name="buttons" :columns="{ container: 12 }">
                                                <ButtonElement name="test" :button-label="$t('Test Connection')" :submits="false"
                                                    @click="testConnection" :columns="{ container: 6 }" />
                                                <ButtonElement name="save" :button-label="$t('Save')" :submits="true" align="right"
                                                    :columns="{ container: 6 }" />
                                            </GroupElement>
                                        </FormElements>
                                    </div>
                                </div>
                            </template>
                        </Vueform>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({ show: Boolean, loading: Boolean, integration: Object, updateRoute: String, testRoute: String });
const emit = defineEmits(["close", "error", "success", "test-success"]);
const form$ = ref(null);
const MASKED_API_KEY = "********************************";
const nonCredentialFieldAttrs = {
    autocomplete: "off",
    "data-lpignore": "true",
    "data-1p-ignore": "true",
    "data-bwignore": "true",
    "data-form-type": "other",
};
const defaults = computed(() => ({
    api_key: props.integration.has_api_key ? MASKED_API_KEY : null,
    public_sip_host: props.integration.public_sip_host ?? null,
    provider_cidrs: (props.integration.provider_cidrs || []).map((cidr) => ({ node_cidr: cidr })),
    enabled: props.integration.enabled ?? false,
}));
const submitForm = async (FormData, form$) => {
    return form$.$vueform.services.axios.put(props.updateRoute, requestData(form$));
};
const testConnection = async () => {
    const form = form$.value;
    clearErrors(form);

    try {
        const response = await form.$vueform.services.axios.post(props.testRoute, requestData(form));
        emit("test-success", response.data.messages);
    } catch (error) {
        if (error.response) handleResponse(error.response, form);
        if (error.response?.status !== 422) emit("error", error);
    }
};
const handleSuccess = (response, form) => {
    if (response.data.integration?.has_api_key) {
        form?.el$("api_key")?.update(MASKED_API_KEY);
    }
    emit("success", response);
};
const handleError = (error) => emit("error", error);
const handleResponse = (response, form$) => {
    clearErrors(form$);
    if (!response.data.errors) return;
    Object.entries(response.data.errors).forEach(([name, messages]) => {
        const fieldName = name.replace(/^provider_cidrs\.(\d+)$/, "provider_cidrs.$1.node_cidr");
        form$.el$(fieldName)?.messageBag.append(messages[0]);
    });
};
const clearErrors = (form) => {
    form?.messageBag?.clear();
    Object.values(form?.elements$ || {}).forEach((element) => clearElementErrors(element));
};
const clearElementErrors = (element) => {
    element?.messageBag?.clear();
    Object.values(element?.children$ || {}).forEach((child) => clearElementErrors(child));
};
const requestData = (form) => {
    const data = { ...form.requestData };
    if (data.api_key === MASKED_API_KEY) data.api_key = null;
    return data;
};
const selectMaskedApiKey = (event) => {
    if (event?.target?.value === MASKED_API_KEY) event.target.select();
};
</script>
