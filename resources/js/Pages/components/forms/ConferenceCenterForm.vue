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
                        <DialogPanel
                            class="relative transform rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
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

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'conference_center_uuid',
                                                    'conference_center_uuid_clean',
                                                    'settings_header',
                                                    'conference_center_name',
                                                    'conference_center_enabled',
                                                    'conference_center_extension',
                                                    'conference_center_greeting',
                                                    'conference_center_description',
                                                    'button_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_header',
                                                    'conference_center_pin_length',
                                                    'advanced_button_container',
                                                    'advanced_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-body bg-surface-2 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="conference_center_uuid" :meta="true" />

                                                <StaticElement name="settings_header" tag="h4" content="Conference Center Settings"
                                                    description="Configure the conference center extension, greeting, and dialplan state." />

                                                <StaticElement name="conference_center_uuid_clean"
                                                    :conditions="[() => props.options?.item?.conference_center_uuid]">
                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-body mb-1">
                                                            Unique ID
                                                        </div>

                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-heading select-all font-normal">
                                                                {{ props.options?.item?.conference_center_uuid }}
                                                            </span>

                                                            <button type="button"
                                                                @click="handleCopyToClipboard(props.options?.item?.conference_center_uuid)"
                                                                class="ml-2 p-1 rounded-full text-subtle hover:text-info hover:bg-info-subtle transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 text-muted hover:text-heading cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <TextElement name="conference_center_name" label="Name"
                                                    placeholder="Conference center name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="conference_center_enabled" text="Conference Center Enabled"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextElement name="conference_center_extension" label="Extension"
                                                    placeholder="Extension callers dial" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="conference_center_greeting" :items="soundOptions"
                                                    :groups="true" :search="true" :native="false" label="Greeting"
                                                    input-type="search" allow-absent autocomplete="off"
                                                    placeholder="Optional greeting" :floating="false" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextareaElement name="conference_center_description" label="Description"
                                                    :rows="2" />

                                                <GroupElement name="button_container" />

                                                <ButtonElement name="settings_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <StaticElement name="advanced_header" tag="h4" content="Advanced Settings"
                                                    description="PIN length is used by conference rooms that collect PIN digits from the dialed number." />

                                                <TextElement name="conference_center_pin_length" input-type="number"
                                                    label="PIN Length" :floating="false"
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
        default: "Conference Center",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const defaultValues = computed(() => ({
    conference_center_uuid: props.options?.item?.conference_center_uuid ?? null,
    conference_center_name: props.options?.item?.conference_center_name ?? null,
    conference_center_extension: props.options?.item?.conference_center_extension ?? null,
    conference_center_greeting: props.options?.item?.conference_center_greeting ?? null,
    conference_center_pin_length: props.options?.item?.conference_center_pin_length ?? 9,
    conference_center_enabled: props.options?.item?.conference_center_enabled ?? "true",
    conference_center_description: props.options?.item?.conference_center_description ?? null,
}));

const soundOptions = computed(() => props.options?.sound_options ?? []);

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
