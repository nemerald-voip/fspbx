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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ $t('Start PCAP Capture') }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">{{ $t('Close') }}</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600 m-auto">{{ $t('Loading...') }}</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                validate-on="" :default="defaultValues">
                                <template #empty>
                                    <div class="shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                        <FormElements>
                                            <StaticElement name="intro" tag="p"
                                                :content="$t('Each matching call is saved as one PCAP file with its SIP signalling and audio, ready to open in Wireshark.')" />

                                            <TextElement name="name" :label="$t('Name')"
                                                :placeholder="$t('Example: One-way audio on reception phone')" :floating="false" />

                                            <RadiogroupElement name="duration" :label="$t('Capture for')" view="tabs"
                                                :items="durationOptions" />

                                            <RadiogroupElement name="keep_hours" :label="$t('Keep files for')" view="tabs"
                                                :items="keepOptions"
                                                :info="$t('Files are deleted automatically this long after the capture ends, or straight away if you delete the capture.')" />

                                            <CheckboxgroupElement name="directions" :label="$t('Calls to capture')" view="tabs"
                                                :items="directionOptions" />

                                            <RadiogroupElement name="scope" :label="$t('Extensions')" view="tabs" :items="[
                                                { value: 'all', label: $t('Tenant :name', { name: tenantName }) },
                                                { value: 'extensions', label: $t('Selected extensions') },
                                            ]" />

                                            <TagsElement name="extension_uuids" :label="$t('Choose extensions')"
                                                :items="extensionOptions" :search="true" :close-on-select="false"
                                                :native="false" :floating="false" :placeholder="$t('Choose extensions')"
                                                :conditions="[['scope', 'extensions']]" />

                                            <ButtonElement name="submit" :button-label="$t('Start Capture')" :submits="true" align="right" />
                                        </FormElements>
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
import { trans } from "@i18n";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const durationOptions = [
    { value: "1h", label: trans("1 hour") },
    { value: "4h", label: trans("4 hours") },
    { value: "8h", label: trans("8 hours") },
    { value: "24h", label: trans("24 hours") },
    { value: "48h", label: trans("48 hours") },
    { value: "1w", label: trans("1 week") },
];

const keepOptions = [
    { value: 24, label: trans("24 hours") },
    { value: 48, label: trans("48 hours") },
    { value: 72, label: trans("72 hours") },
];

const directionOptions = [
    { value: "inbound", label: trans("Incoming") },
    { value: "outbound", label: trans("Outgoing") },
    { value: "local", label: trans("Internal") },
];

const extensionOptions = computed(() => props.options?.extension_options ?? []);
const tenantName = computed(() => props.options?.tenant_name ?? "");

const defaultValues = computed(() => {
    const item = props.options?.item ?? {};

    return {
        name: item.name ?? null,
        duration: item.duration ?? "1h",
        keep_hours: item.keep_hours ?? 24,
        directions: item.directions ?? ["inbound", "outbound", "local"],
        scope: item.scope ?? "all",
        extension_uuids: item.extension_uuids ?? [],
    };
});

const submitForm = async (FormData, form$) => {
    return await form$.$vueform.services.axios.post(props.options.routes.store_route, form$.requestData);
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
            const name = elName.split(".")[0];
            if (form$.el$(name)) {
                form$.el$(name).messageBag.append(response.data.errors[elName][0]);
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

    form$.messageBag.append(trans("Could not submit form"));
};
</script>
