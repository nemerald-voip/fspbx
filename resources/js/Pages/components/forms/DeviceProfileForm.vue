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
                            class="relative transform  rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">

                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="closeModal">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full">
                                <div class="flex justify-center items-center space-x-3">
                                    <div>
                                        <svg class="animate-spin  h-10 w-10 text-blue-600"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4">
                                            </circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" :default="defaultValues"
                                :display-errors="false" @success="handleSuccess" @error="handleError"
                                @response="handleResponse">

                                <template #empty>

                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="profile" :label="$t('Profile')" :elements="[
                                                    'profile_title',
                                                    'scope',
                                                    'device_profile_name',
                                                    'domain_uuid',
                                                    'device_profile_description',
                                                    'device_profile_enabled',
                                                    'form_error',
                                                    'profile_container',
                                                    'profile_submit',
                                                ]" />
                                                <FormTab v-if="options?.permissions?.keys?.view" name="keys"
                                                    :label="keyTabLabel" :elements="[
                                                        'keys_title',
                                                        'keys_read_only',
                                                        'keys_editor',
                                                        'form_error',
                                                        'keys_container',
                                                        'keys_submit',
                                                    ]" />
                                                <FormTab v-if="options?.permissions?.settings?.view" name="settings"
                                                    :label="settingTabLabel" :elements="[
                                                        'settings_title',
                                                        'settings_read_only',
                                                        'settings_editor',
                                                        'form_error',
                                                        'settings_container',
                                                        'settings_submit',
                                                    ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>

                                                <!-- Profile tab -->
                                                <StaticElement name="profile_title" tag="h4"
                                                    :content="$t('Profile details')"
                                                    :description="$t('Name the profile and choose where it is available.')" />

                                                <HiddenElement name="scope" :meta="true" />

                                                <TextElement name="device_profile_name" :label="$t('Name')"
                                                    :placeholder="$t('Profile name')" :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" :rules="['required', 'max:255']" />

                                                <SelectElement v-if="options?.permissions?.manage_domain"
                                                    name="domain_uuid" :label="$t('Account')" :items="options.domains"
                                                    value-prop="value" label-prop="label" :search="true"
                                                    :native="false" input-type="search" autocomplete="off"
                                                    :floating="false" :columns="{
                                                        sm: {
                                                            container: 6,
                                                        },
                                                    }" />

                                                <TextareaElement name="device_profile_description"
                                                    :label="$t('Description')"
                                                    :placeholder="$t('Describe when this profile should be used')"
                                                    :rows="3" :floating="false" :rules="['max:255']" />

                                                <ToggleElement name="device_profile_enabled" :text="$t('Enabled')"
                                                    true-value="true" false-value="false"
                                                    :description="$t('Disabled profiles are skipped when devices are provisioned.')" />

                                                <!-- Keys tab -->
                                                <StaticElement v-if="options?.permissions?.keys?.view" name="keys_title"
                                                    tag="h4" :content="$t('Profile keys')"
                                                    :description="$t('Define vendor-specific key positions and functions. The same profile can contain keys for multiple phone vendors.')" />

                                                <StaticElement
                                                    v-if="options?.permissions?.keys?.view && !canChangeKeys"
                                                    name="keys_read_only">
                                                    <p
                                                        class="rounded-md bg-white px-3 py-2 text-sm text-gray-600 ring-1 ring-inset ring-gray-200">
                                                        {{ $t("You can view these keys, but you do not have permission to change them.") }}
                                                    </p>
                                                </StaticElement>

                                                <StaticElement v-if="options?.permissions?.keys?.view"
                                                    name="keys_editor">
                                                    <DeviceProfileKeyTable ref="keyTable$" :rows="keyRows"
                                                        :vendors="options.vendors ?? []"
                                                        :vendor-functions="options.vendor_functions ?? []"
                                                        :extension-names="options.extension_names ?? {}"
                                                        :permissions="options.permissions.keys"
                                                        :field-permissions="options.permissions.key_fields ?? {}"
                                                        :errors="keyErrors" />
                                                </StaticElement>

                                                <!-- Settings tab -->
                                                <StaticElement v-if="options?.permissions?.settings?.view"
                                                    name="settings_title" tag="h4" :content="$t('Profile settings')"
                                                    :description="$t('Add provisioning setting name and value pairs that devices inherit from this profile.')" />

                                                <StaticElement
                                                    v-if="options?.permissions?.settings?.view && !canChangeSettings"
                                                    name="settings_read_only">
                                                    <p
                                                        class="rounded-md bg-white px-3 py-2 text-sm text-gray-600 ring-1 ring-inset ring-gray-200">
                                                        {{ $t("You can view these settings, but you do not have permission to change them.") }}
                                                    </p>
                                                </StaticElement>

                                                <StaticElement v-if="options?.permissions?.settings?.view"
                                                    name="settings_editor">
                                                    <DeviceProfileSettingTable :rows="settingRows"
                                                        :permissions="options.permissions.settings"
                                                        :errors="settingErrors" />
                                                </StaticElement>

                                                <!-- Shared -->
                                                <StaticElement name="form_error">
                                                    <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
                                                </StaticElement>

                                                <GroupElement name="profile_container" />
                                                <ButtonElement name="profile_submit" :button-label="$t('Save')"
                                                    :submits="true" align="right" />

                                                <GroupElement v-if="options?.permissions?.keys?.view"
                                                    name="keys_container" />
                                                <ButtonElement v-if="options?.permissions?.keys?.view"
                                                    name="keys_submit" :button-label="$t('Save')" :submits="true"
                                                    align="right" />

                                                <GroupElement v-if="options?.permissions?.settings?.view"
                                                    name="settings_container" />
                                                <ButtonElement v-if="options?.permissions?.settings?.view"
                                                    name="settings_submit" :button-label="$t('Save')" :submits="true"
                                                    align="right" />

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
import { computed, ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import { trans } from "@i18n";
import DeviceProfileKeyTable from "./DeviceProfileKeyTable.vue";
import DeviceProfileSettingTable from "./DeviceProfileSettingTable.vue";

const props = defineProps({
    show: Boolean,
    options: {
        type: Object,
        default: () => ({}),
    },
    loading: Boolean,
    header: String,
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);
const keyTable$ = ref(null);
const keyRows = ref([]);
const settingRows = ref([]);
const keyErrors = ref({});
const settingErrors = ref({});
const formError = ref(null);
const isSubmitting = ref(false);

const defaultValues = computed(() => ({
    scope: props.options?.scope ?? "current",
    domain_uuid: props.options?.item?.domain_uuid ?? "__global__",
    device_profile_name: props.options?.item?.device_profile_name ?? null,
    device_profile_enabled: props.options?.item?.device_profile_enabled ?? "true",
    device_profile_description: props.options?.item?.device_profile_description ?? null,
}));

const keyTabLabel = computed(() => `${trans("Keys")} (${keyRows.value.length})`);

const settingTabLabel = computed(() => `${trans("Settings")} (${settingRows.value.length})`);

const canChangeKeys = computed(() => {
    const permissions = props.options?.permissions?.keys ?? {};

    return Boolean(permissions.create || permissions.update || permissions.destroy);
});

const canChangeSettings = computed(() => {
    const permissions = props.options?.permissions?.settings ?? {};

    return Boolean(permissions.create || permissions.update || permissions.destroy);
});

watch(
    () => props.options,
    (options) => {
        keyRows.value = normalizeKeys(options?.item?.keys ?? []);
        settingRows.value = normalizeSettings(options?.item?.settings ?? []);
        keyErrors.value = {};
        settingErrors.value = {};
        formError.value = null;
    },
    { immediate: true }
);

function normalizeKeys(keys) {
    if (!Array.isArray(keys)) {
        return [];
    }

    return keys.map((key, index) => ({
        ...key,
        _row_id: key.device_profile_key_uuid ?? `row-${index}`,
        profile_key_id: toNumberOrNull(key.profile_key_id),
        profile_key_line: toNumberOrNull(key.profile_key_line),
        profile_key_vendor: key.profile_key_vendor || null,
        profile_key_category: key.profile_key_category || "line",
        profile_key_type: key.profile_key_type || null,
        profile_key_protected: key.profile_key_protected || null,
    }));
}

function normalizeSettings(settings) {
    if (!Array.isArray(settings)) {
        return [];
    }

    return settings.map((setting, index) => ({
        ...setting,
        _row_id: setting.device_profile_setting_uuid ?? `row-${index}`,
        profile_setting_enabled: setting.profile_setting_enabled ?? "true",
    }));
}

function toNumberOrNull(value) {
    return value === null || value === undefined || value === "" ? null : Number(value);
}

function cleanRows(rows) {
    return rows.map((row) => {
        const { _row_id, ...rest } = row;

        return Object.fromEntries(
            Object.entries(rest).map(([field, value]) => [field, value === "" ? null : value])
        );
    });
}

function closeModal() {
    if (!isSubmitting.value) {
        emit("close");
    }
}

async function submitForm(FormData, form) {
    const data = { ...form.data };

    if (!props.options?.permissions?.manage_domain) {
        delete data.domain_uuid;
    }

    if (props.options?.permissions?.keys?.view) {
        data.keys = cleanRows(keyRows.value);
    }

    if (props.options?.permissions?.settings?.view) {
        data.settings = cleanRows(settingRows.value);
    }

    isSubmitting.value = true;

    try {
        if (props.mode === "create") {
            return await form.$vueform.services.axios.post(props.options.routes.store_route, data);
        }

        return await form.$vueform.services.axios.put(props.options.routes.update_route, data);
    } finally {
        isSubmitting.value = false;
    }
}

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

function handleResponse(response, form) {
    Object.values(form.elements$).forEach((el$) => clearErrorsRecursive(el$));
    keyErrors.value = {};
    settingErrors.value = {};
    formError.value = null;

    const errors = response?.data?.errors;

    if (!errors) {
        return;
    }

    const collected = { keys: {}, settings: {} };
    let hasProfileError = false;

    Object.entries(errors).forEach(([name, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : messages;
        const match = name.match(/^(keys|settings)\.(\d+)\.(.+)$/);

        if (match) {
            const [, section, index, field] = match;
            collected[section][index] = { ...(collected[section][index] ?? {}), [field]: message };

            return;
        }

        if (form.el$(name)) {
            form.el$(name).messageBag.append(message);
            hasProfileError = true;

            return;
        }

        formError.value = message;
    });

    keyErrors.value = collected.keys;
    settingErrors.value = collected.settings;

    if (hasProfileError) {
        form.tabs$?.goTo?.("profile");

        return;
    }

    if (Object.keys(collected.keys).length) {
        form.tabs$?.goTo?.("keys");
        keyTable$.value?.clearVendorFilter();
        formError.value = formError.value ?? trans("Check the highlighted keys and try again.");

        return;
    }

    if (Object.keys(collected.settings).length) {
        form.tabs$?.goTo?.("settings");
        formError.value = formError.value ?? trans("Check the highlighted settings and try again.");
    }
}

function handleSuccess(response) {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
}

function handleError(error, details, form) {
    form.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);

        return;
    }

    formError.value = trans("Could not submit form.");
}
</script>
