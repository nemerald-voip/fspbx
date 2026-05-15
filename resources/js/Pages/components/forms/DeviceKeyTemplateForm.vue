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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
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

                            <div v-if="loading" class="w-full py-10">
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
                                                    'settings_header',
                                                    'name',
                                                    'enabled',
                                                    'description',
                                                    'settings_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="main" label="Function Keys" :elements="[
                                                    'main_header',
                                                    'keys',
                                                    'main_container',
                                                    'main_submit',
                                                ]" />
                                                <FormTab name="multi" label="Multi Purpose Keys" :elements="[
                                                    'multi_header',
                                                    'multi_purpose_keys',
                                                    'multi_container',
                                                    'multi_submit',
                                                ]" />
                                                <FormTab name="expansion" label="Expansion Keys" :elements="[
                                                    'expansion_header',
                                                    'expansion_keys',
                                                    'expansion_container',
                                                    'expansion_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <StaticElement name="settings_header" tag="h4" content="Template Settings" />

                                                <TextElement name="name" label="Name" placeholder="Template name"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="enabled" text="Enabled" true-value="true"
                                                    false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextareaElement name="description" label="Description" :rows="2"
                                                    :floating="false" />

                                                <GroupElement name="settings_container" />

                                                <ButtonElement name="settings_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <StaticElement name="main_header" tag="h4" content="Function Keys" />
                                                <DeviceKeyTemplateKeyList name="keys" area="main" :key-types="keyTypes"
                                                    :form-data="form$?.data" :get-next-key-number="getNextKeyNumber"
                                                    :get-key-value-select-items="getKeyValueSelectItems"
                                                    :update-label="updateLabel" />
                                                <GroupElement name="main_container" />
                                                <ButtonElement name="main_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <StaticElement name="multi_header" tag="h4" content="Multi Purpose Keys" />
                                                <DeviceKeyTemplateKeyList name="multi_purpose_keys" area="multi_purpose"
                                                    :key-types="keyTypes" :form-data="form$?.data"
                                                    :get-next-key-number="getNextKeyNumber"
                                                    :get-key-value-select-items="getKeyValueSelectItems"
                                                    :update-label="updateLabel" />
                                                <GroupElement name="multi_container" />
                                                <ButtonElement name="multi_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <StaticElement name="expansion_header" tag="h4" content="Expansion Keys" />
                                                <DeviceKeyTemplateKeyList name="expansion_keys" area="expansion"
                                                    :key-types="keyTypes" :form-data="form$?.data"
                                                    :get-next-key-number="getNextKeyNumber"
                                                    :get-key-value-select-items="getKeyValueSelectItems"
                                                    :update-label="updateLabel" />
                                                <GroupElement name="expansion_container" />
                                                <ButtonElement name="expansion_submit" button-label="Save"
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
import DeviceKeyTemplateKeyList from "./DeviceKeyTemplateKeyList.vue";

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
const keyValueOptionsByIndex = {};

const keyTypes = [
    { value: "", name: "N/A" },
    { value: "line", name: "Line" },
    { value: "blf", name: "BLF" },
    { value: "speed_dial", name: "Speed Dial" },
    { value: "check_voicemail", name: "Check Voicemail" },
    { value: "park", name: "Park & Retrieve" },
];

const defaultValues = computed(() => ({
    name: props.options?.item?.name ?? null,
    enabled: props.options?.item?.enabled ?? "true",
    description: props.options?.item?.description ?? null,
    keys: normalizeKeysForForm(filterKeysByArea(props.options?.item?.keys ?? [], "main")),
    multi_purpose_keys: normalizeKeysForForm(filterKeysByArea(props.options?.item?.keys ?? [], "multi_purpose")),
    expansion_keys: normalizeKeysForForm(filterKeysByArea(props.options?.item?.keys ?? [], "expansion")),
}));

const filterKeysByArea = (keys = [], area = "main") => {
    if (!Array.isArray(keys)) return [];

    return keys.filter((key) => (key?.key_area ?? "main") === area);
};

const normalizeKeysForForm = (keys = []) => {
    const keyTypesWithSelect = ["line", "check_voicemail", "blf", "speed_dial", "park"];

    return keys.map((key) => {
        const keyType = key?.key_type ?? "";
        const keyValue = key?.key_value ?? null;
        const row = {
            ...key,
            key_uuid: key?.device_key_template_key_uuid,
            key_type: keyType,
            key_value: keyValue,
            key_value_select: null,
            key_value_text: null,
            _generated_label: null,
        };

        if (!keyType || keyValue == null || keyValue === "") return row;

        if (keyTypesWithSelect.includes(keyType)) {
            row.key_value_select = String(keyValue);
        } else {
            row.key_value_text = String(keyValue);
        }

        return row;
    });
};

const getNextKeyNumber = (listName) => {
    const list = form$?.value?.el$(listName);
    const children = list?.children$Array ?? [];
    const maxKey = children.reduce((max, child) => {
        const n = parseInt(child?.value?.key_index, 10);
        return Number.isFinite(n) && n > max ? n : max;
    }, 0);

    return maxKey + 1;
};

const getKeyCacheKey = (listName, index) => `${listName}.${index}`;

const getKeyValueSelectItems = async (query, input, index, listName) => {
    const form = input.$parent.el$.form$;
    const keyTypeEl = form.el$(`${listName}.${index}.key_type`);
    const keyType = keyTypeEl?.value;
    const cacheKey = getKeyCacheKey(listName, index);

    if (keyType === "line") {
        return Array.from({ length: 16 }, (_, i) => ({
            extension: `${i + 1}`,
            name: `Line ${i + 1}`,
        }));
    }

    if (keyType === "park") {
        return Array.from({ length: 10 }, (_, i) => {
            const ext = String(5901 + i);
            return { extension: ext, name: `Park ${i + 1} (${ext})` };
        });
    }

    const category = keyType === "check_voicemail" ? "voicemails" : "extensions";
    if (!["check_voicemail", "blf", "speed_dial"].includes(keyType)) {
        return [];
    }

    try {
        const axios = keyTypeEl.$vueform.services.axios;
        const response = await axios.post(props.options.routes.get_routing_options, { category });
        keyValueOptionsByIndex[cacheKey] = response.data.options ?? [];
        return response.data.options ?? [];
    } catch (error) {
        emit("error", error);
        return [];
    }
};

const parkLabelFromValue = (value, base = 5900) => {
    const n = parseInt(value, 10);
    return Number.isFinite(n) && n > base ? `Park ${n - base}` : "";
};

const nameOnlyFromOption = (opt) => {
    const s = String(opt?.name ?? "").trim();
    if (!s) return null;

    const parts = s.split(" - ");
    return (parts.length > 1 ? parts.slice(1).join(" - ") : s).trim();
};

const updateLabel = (newValue, oldValue, el$, index, listName) => {
    const row = el$?.form$?.el$(listName)?.children$?.[index]?.children$;
    if (!row) return;

    row.key_value.update(newValue);

    const keyType = el$?.form$.el$(`${listName}.${index}.key_type`)?.value;
    const cacheKey = getKeyCacheKey(listName, index);
    let label = null;

    if (keyType === "park") {
        label = parkLabelFromValue(newValue);
    }

    if (keyType === "check_voicemail") {
        const selected = (keyValueOptionsByIndex[cacheKey] ?? [])
            .find((option) => String(option.extension) === String(newValue));
        label = selected?.extension ? `VM ${selected.extension}` : null;
    }

    if (keyType === "blf" || keyType === "speed_dial") {
        const selected = (keyValueOptionsByIndex[cacheKey] ?? [])
            .find((option) => String(option.extension) === String(newValue));
        label = nameOnlyFromOption(selected);
    }

    row._generated_label.update(label);
    row.key_label.update(keyType === "blf" || keyType === "speed_dial" ? null : label);
};

const submitForm = async (FormData, form) => {
    const data = form.data;
    data.keys = [
        ...(data.keys ?? []).map((key) => ({ ...key, key_area: "main" })),
        ...(data.multi_purpose_keys ?? []).map((key) => ({ ...key, key_area: "multi_purpose" })),
        ...(data.expansion_keys ?? []).map((key) => ({ ...key, key_area: "expansion" })),
    ];

    delete data.multi_purpose_keys;
    delete data.expansion_keys;

    if (props.mode === "create") {
        return await form.$vueform.services.axios.post(props.options.routes.store_route, data);
    }

    return await form.$vueform.services.axios.put(props.options.routes.update_route, data);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

const handleResponse = (response, form) => {
    Object.values(form.elements$).forEach((el$) => clearErrorsRecursive(el$));

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form.el$(elName)) {
                form.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
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

    form.messageBag.append("Could not submit form");
};
</script>
