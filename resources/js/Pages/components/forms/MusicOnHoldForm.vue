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
                        <DialogPanel class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full py-10">
                                <div class="flex items-center justify-center space-x-3">
                                    <svg class="h-10 w-10 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="m-auto text-lg text-blue-600">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="settingsElements" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_header',
                                                    'music_on_hold_interval',
                                                    'music_on_hold_timer_name',
                                                    'music_on_hold_chime_list',
                                                    'music_on_hold_chime_freq',
                                                    'music_on_hold_chime_max',
                                                    'advanced_button_container',
                                                    'advanced_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div class="space-y-6 bg-gray-50 px-4 py-6 text-gray-600 shadow sm:rounded-md sm:px-6 sm:p-6 lg:col-span-9">
                                            <FormElements>
                                                <HiddenElement name="music_on_hold_uuid" :meta="true" />

                                                <StaticElement name="settings_header" tag="h4" content="Stream Settings"
                                                    description="Configure the stream category, source path, and playback behavior." />

                                                <TextElement name="music_on_hold_name" label="Name" placeholder="default" :floating="false" :columns="{ sm: { container: 6 } }" />
                                                <SelectElement v-if="canManageDomain" name="domain_uuid" :items="domainOptions" label="Domain" :native="false" :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="music_on_hold_channels" :items="channelOptions" label="Channels" :native="false" :floating="false" :columns="{ sm: { container: 6 } }" />
                                                <ToggleElement name="music_on_hold_shuffle" text="Shuffle" true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }" :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <StaticElement v-if="canViewPath" name="music_on_hold_path" tag="div" :columns="{ container: 12 }">
                                                    <div class="rounded-md bg-gray-100 px-3 py-2">
                                                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Generated path</div>
                                                        <div class="mt-1 break-all font-mono text-xs text-gray-700">{{ suggestedPath }}</div>
                                                    </div>
                                                </StaticElement>

                                                <GroupElement name="settings_button_container" />
                                                <ButtonElement name="settings_submit" button-label="Save" :submits="true" align="right" />

                                                <StaticElement name="advanced_header" tag="h4" content="Advanced Settings"
                                                    description="Optional local stream settings passed to FreeSWITCH." />

                                                <TextElement name="music_on_hold_interval" input-type="number" label="Interval" :floating="false" :columns="{ sm: { container: 4 } }" />
                                                <TextElement name="music_on_hold_timer_name" label="Timer Name" :floating="false" :columns="{ sm: { container: 4 } }" />

                                                <SelectElement name="music_on_hold_chime_list" :items="chimeOptions" :groups="true" :search="true" :native="false"
                                                    label="Chime" input-type="search" allow-absent autocomplete="off" placeholder="No chime"
                                                    :floating="false" :strict="false" :columns="{ sm: { container: 4 } }" />

                                                <TextElement name="music_on_hold_chime_freq" input-type="number" label="Chime Frequency" :floating="false" :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="music_on_hold_chime_max" input-type="number" label="Chime Maximum" :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="advanced_button_container" />
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
import { computed, ref, watch, watchEffect } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    permissions: Object,
    routes: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Music on Hold",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);
const form$ = ref(null);
const previousGeneratedPath = ref(null);
const GLOBAL_DOMAIN_VALUE = "__global__";

const defaultValues = computed(() => ({
    music_on_hold_uuid: props.options?.item?.music_on_hold_uuid ?? null,
    domain_uuid: normalizeDomainValue(props.options?.item?.domain_uuid),
    music_on_hold_name: props.options?.item?.music_on_hold_name ?? null,
    music_on_hold_path: props.options?.item?.music_on_hold_path ?? buildSuggestedPath(
        props.options?.item?.music_on_hold_name,
        props.options?.item?.domain_uuid
    ),
    music_on_hold_shuffle: props.options?.item?.music_on_hold_shuffle ?? "false",
    music_on_hold_channels: String(props.options?.item?.music_on_hold_channels ?? "1"),
    music_on_hold_interval: props.options?.item?.music_on_hold_interval ?? 20,
    music_on_hold_timer_name: props.options?.item?.music_on_hold_timer_name ?? "soft",
    music_on_hold_chime_list: props.options?.item?.music_on_hold_chime_list ?? null,
    music_on_hold_chime_freq: props.options?.item?.music_on_hold_chime_freq ?? null,
    music_on_hold_chime_max: props.options?.item?.music_on_hold_chime_max ?? null,
}));

const domainOptions = computed(() => props.options?.domains ?? []);
const canManageDomain = computed(() => Boolean(props.permissions?.manage_domain) && domainOptions.value.length > 0);
const canViewPath = computed(() => Boolean(props.permissions?.view_path));
const settingsElements = computed(() => [
    'music_on_hold_uuid',
    'settings_header',
    'music_on_hold_name',
    'domain_uuid',
    'music_on_hold_channels',
    'music_on_hold_shuffle',
    ...(canViewPath.value ? ['music_on_hold_path'] : []),
    'settings_button_container',
    'settings_submit',
]);
const chimeOptions = computed(() => props.options?.chime_options ?? []);
const channelOptions = [
    { label: "Mono", value: "1" },
    { label: "Stereo", value: "2" },
];

const suggestedPath = computed(() => buildSuggestedPath(
    form$.value?.data?.music_on_hold_name ?? props.options?.item?.music_on_hold_name,
    normalizeDomainValue(form$.value?.data?.domain_uuid ?? props.options?.item?.domain_uuid)
));

watch(
    () => [props.show, props.mode],
    () => {
        previousGeneratedPath.value = null;
    }
);

watchEffect(() => {
    if (!props.show || props.mode !== "create" || !form$.value?.data) {
        return;
    }

    const path = buildSuggestedPath(
        form$.value.data.music_on_hold_name,
        form$.value.data.domain_uuid
    );
    const currentPath = form$.value.data.music_on_hold_path;

    if (currentPath && previousGeneratedPath.value === null && currentPath === path) {
        previousGeneratedPath.value = path;
    }

    if (currentPath && currentPath !== previousGeneratedPath.value) {
        return;
    }

    form$.value.data.music_on_hold_path = path;
    previousGeneratedPath.value = path;
});

function buildSuggestedPath(name, domainUuid) {
    const pathName = safeCategoryName(name || "default");
    const domainName = domainNameForPath(domainUuid);

    return `${props.options?.sounds_path_prefix || "$${sounds_dir}/music"}/${domainName}/${pathName}`;
}

function domainNameForPath(domainUuid) {
    const normalizedDomainUuid = normalizeDomainValue(domainUuid);

    if (!normalizedDomainUuid || normalizedDomainUuid === GLOBAL_DOMAIN_VALUE) {
        return "global";
    }

    const option = props.options?.domains?.find((domain) => String(domain.value) === String(normalizedDomainUuid));

    if (option?.domain_name) {
        return option.domain_name;
    }

    return canManageDomain.value ? "global" : (props.options?.current_domain_name || "global");
}

function normalizeDomainValue(domainUuid) {
    if (domainUuid === null || domainUuid === undefined) {
        return GLOBAL_DOMAIN_VALUE;
    }

    if (typeof domainUuid === "object") {
        return domainUuid.value ?? GLOBAL_DOMAIN_VALUE;
    }

    return domainUuid;
}

function safeCategoryName(name) {
    return String(name)
        .replace(/[\\/]/g, "")
        .replace(/\s+/g, "_")
        .trim() || "default";
}

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(props.routes.store, requestData);
    }

    return await form$.$vueform.services.axios.put(
        props.routes.update.replace("__STREAM__", requestData.music_on_hold_uuid || props.options?.item?.music_on_hold_uuid),
        requestData
    );
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
