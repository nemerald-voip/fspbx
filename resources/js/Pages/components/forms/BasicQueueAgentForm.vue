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
                        leave-from="opacity-100 sm:scale-100" leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6">
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
                                                    'agent_contact',
                                                    'agent_name',
                                                    'agent_type',
                                                    'agent_status',
                                                    'agent_call_timeout',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'agent_id',
                                                    'agent_password',
                                                    'agent_max_no_answer',
                                                    'agent_no_answer_delay_time',
                                                    'agent_wrap_up_time',
                                                    'agent_reject_delay_time',
                                                    'agent_busy_delay_time',
                                                    'agent_record',
                                                    'advanced_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <SelectElement name="agent_contact" label="Contact" :items="contactOptions"
                                                    :search="true" :native="false" :strict="false" allow-absent
                                                    @change="handleAgentContactChange"
                                                    :columns="{ sm: { container: 12 } }" />

                                                <TextElement name="agent_name" label="Agent Name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="agent_type" label="Type" :native="false"
                                                    :items="typeOptions" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="agent_status" label="Default Status" :native="false"
                                                    :items="statusOptions" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="agent_call_timeout" input-type="number" label="Call Timeout"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <ButtonElement name="settings_submit" button-label="Save" :submits="true" align="right" />

                                                <TextElement name="agent_id" input-type="number" label="Agent ID"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="agent_password" input-type="password" label="Agent Password"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="agent_max_no_answer" input-type="number" label="Max No Answer"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="agent_no_answer_delay_time" input-type="number"
                                                    label="No Answer Delay" :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="agent_wrap_up_time" input-type="number" label="Wrap Up Time"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="agent_reject_delay_time" input-type="number"
                                                    label="Reject Delay" :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="agent_busy_delay_time" input-type="number" label="Busy Delay"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="agent_record" text="Record Agent"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    label="&nbsp;" :columns="{ sm: { container: 6 } }" />

                                                <ButtonElement name="advanced_submit" button-label="Save" :submits="true" align="right" />
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
    header: String,
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);
const form$ = ref(null);

const typeOptions = [
    { value: "callback", label: "Callback" },
    { value: "uuid-standby", label: "UUID Standby" },
];

const statusOptions = [
    { value: "Logged Out", label: "Logged Out" },
    { value: "Available", label: "Available" },
    { value: "On Break", label: "On Break" },
];

const defaultValues = computed(() => ({
    agent_name: props.options?.item?.agent_name ?? null,
    agent_type: props.options?.item?.agent_type ?? "callback",
    agent_call_timeout: props.options?.item?.agent_call_timeout ?? 20,
    agent_id: props.options?.item?.agent_id ?? null,
    agent_password: props.options?.item?.agent_password ?? null,
    agent_contact: props.options?.item?.agent_contact ?? null,
    agent_status: props.options?.item?.agent_status ?? "Logged Out",
    agent_no_answer_delay_time: props.options?.item?.agent_no_answer_delay_time ?? 30,
    agent_max_no_answer: props.options?.item?.agent_max_no_answer ?? 0,
    agent_wrap_up_time: props.options?.item?.agent_wrap_up_time ?? 10,
    agent_reject_delay_time: props.options?.item?.agent_reject_delay_time ?? 90,
    agent_busy_delay_time: props.options?.item?.agent_busy_delay_time ?? 90,
    agent_record: props.options?.item?.agent_record ?? "true",
}));

const contactOptions = computed(() => props.options?.contact_options ?? []);

function handleAgentContactChange(newValue, oldValue, el$) {
    if (String(newValue ?? '') === String(oldValue ?? '')) {
        return;
    }

    const extension = extensionFromAgentContact(newValue);
    if (!extension) {
        return;
    }

    const form = el$.form$;
    const name = agentNameFromContact(newValue);

    if (name) {
        form.el$('agent_name')?.update(name);
    }

    form.el$('agent_id')?.update(extension);
    form.el$('agent_password')?.update(extension);
}

function extensionFromAgentContact(value) {
    const contact = typeof value === "object" && value !== null
        ? (value.value ?? value.agent_contact ?? "")
        : value;

    const match = String(contact ?? "").match(/^user\/([^@]+)@/);
    return match?.[1] ?? null;
}

function agentNameFromContact(value) {
    const contact = typeof value === "object" && value !== null
        ? (value.value ?? value.agent_contact ?? "")
        : value;
    const selectedContact = contactOptions.value.find(
        (option) => String(option.value) === String(contact)
    );

    if (!selectedContact?.label) {
        return null;
    }

    const extension = extensionFromAgentContact(contact);
    const label = String(selectedContact.label);

    if (extension && label.startsWith(`${extension} - `)) {
        return label.slice(`${extension} - `.length).trim() || extension;
    }

    return label.trim() || null;
}

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    return props.mode === "create"
        ? await form$.$vueform.services.axios.post(route, requestData)
        : await form$.$vueform.services.axios.put(route, requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();
    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => clearErrorsRecursive(el$));

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
