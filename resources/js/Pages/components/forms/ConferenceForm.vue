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
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-blue-600"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'conference_uuid',
                                                    'conference_uuid_clean',
                                                    'settings_header',
                                                    'conference_name',
                                                    'conference_enabled',
                                                    'conference_extension',
                                                    'conference_profile',
                                                    'conference_pin_number',
                                                    'conference_description',
                                                    'button_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_header',
                                                    'conference_order',
                                                    'conference_flags',
                                                    'conference_email_address',
                                                    'conference_account_code',
                                                    'advanced_button_container',
                                                    'advanced_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="conference_uuid" :meta="true" />

                                                <StaticElement name="settings_header" tag="h4" content="Conference Settings"
                                                    description="Configure the dialed extension, profile, PIN, and dialplan state." />

                                                <StaticElement name="conference_uuid_clean"
                                                    :conditions="[() => props.options?.item?.conference_uuid]">
                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-gray-600 mb-1">
                                                            Unique ID
                                                        </div>

                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-gray-900 select-all font-normal">
                                                                {{ props.options?.item?.conference_uuid }}
                                                            </span>

                                                            <button type="button"
                                                                @click="handleCopyToClipboard(props.options?.item?.conference_uuid)"
                                                                class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 text-gray-500 hover:text-gray-900 cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <TextElement name="conference_name" label="Name"
                                                    placeholder="Conference name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="conference_enabled" text="Conference Enabled"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextElement name="conference_extension" label="Extension"
                                                    placeholder="Extension callers dial" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="conference_profile" label="Profile" :items="profiles"
                                                    :search="true" :native="false" input-type="search"
                                                    placeholder="Conference profile" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="conference_pin_number" label="PIN Number"
                                                    placeholder="Optional PIN" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextareaElement name="conference_description" label="Description"
                                                    :rows="2" />

                                                <GroupElement name="button_container" />

                                                <ButtonElement name="settings_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <StaticElement name="advanced_header" tag="h4" content="Advanced Settings"
                                                    description="Optional conference order, conference flags, and billing fields." />

                                                <SelectElement name="conference_order" label="Order" :items="orderOptions"
                                                    :search="true" :native="false" input-type="search"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="conference_flags" label="Flags"
                                                    placeholder="mute|deaf|waste|moderator" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="conference_email_address" label="Email Address"
                                                    input-type="email" :floating="false"
                                                    :conditions="[() => permissions.email_address]"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="conference_account_code" label="Account Code"
                                                    :floating="false" :conditions="[() => permissions.account_code]"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="advanced_button_container" />

                                                <ButtonElement name="advanced_submit" button-label="Save"
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
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Conference",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const permissions = computed(() => props.options?.permissions ?? {});
const profiles = computed(() => props.options?.profiles ?? []);

const defaultValues = computed(() => ({
    conference_uuid: props.options?.item?.conference_uuid ?? null,
    conference_name: props.options?.item?.conference_name ?? null,
    conference_extension: props.options?.item?.conference_extension ?? null,
    conference_pin_number: props.options?.item?.conference_pin_number ?? null,
    conference_profile: props.options?.item?.conference_profile ?? "default",
    conference_flags: props.options?.item?.conference_flags ?? null,
    conference_email_address: props.options?.item?.conference_email_address ?? null,
    conference_account_code: props.options?.item?.conference_account_code ?? null,
    conference_order: props.options?.item?.conference_order ?? 0,
    conference_enabled: props.options?.item?.conference_enabled ?? "true",
    conference_description: props.options?.item?.conference_description ?? null,
}));

const orderOptions = Array.from({ length: 1000 }, (_, index) => ({
    value: index,
    label: String(index).padStart(3, "0"),
}));

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit("success", "success", { message: ["Copied to clipboard."] });
    }).catch(() => {
        emit("error", { response: { data: { errors: { request: ["Failed to copy to clipboard."] } } } });
    });
};

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
