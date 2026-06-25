<template>
    <AddEditItemModal
        :show="show"
        :header="header"
        :loading="loading"
        custom-class="sm:max-w-6xl"
        body-class="max-h-[75vh] overflow-y-auto"
        @close="emit('close')"
    >
        <template #modal-body>
            <Vueform
                v-if="!loading"
                ref="form$"
                :endpoint="submitForm"
                :default="defaultValues"
                :display-errors="false"
                @success="handleSuccess"
                @error="handleError"
                @response="handleResponse"
            >
                <template #empty>
                    <FormElements>
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                                <TextElement name="sip_profile_name" label="Name" placeholder="internal" :floating="false" :columns="{ md: { container: 4 } }" />
                                <TextElement name="sip_profile_hostname" label="Hostname" placeholder="Default switch" :floating="false" :columns="{ md: { container: 4 } }" />
                                <ToggleElement
                                    name="sip_profile_enabled"
                                    text="Enabled"
                                    true-value="true"
                                    false-value="false"
                                    :labels="{ on: 'On', off: 'Off' }"
                                    label="&nbsp;"
                                    :columns="{ md: { container: 4 } }"
                                />
                                <TextareaElement name="sip_profile_description" label="Description" :rows="2" :columns="{ md: { container: 12 } }" />
                            </div>

                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <section>
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-900">Domains</h4>
                                        <p class="text-xs text-gray-500">Optional domain aliases included in the generated profile.</p>
                                    </div>
                                    <button
                                        v-if="childPermissions.domain_create"
                                        type="button"
                                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        @click="addDomain"
                                    >
                                        Add
                                    </button>
                                </div>

                                <div class="overflow-x-auto rounded-md border border-gray-200">
                                    <table class="min-w-[620px] divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Alias</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Parse</th>
                                                <th v-if="canRemoveDomains" class="w-12 px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <tr v-for="(domain, index) in domains" :key="domain.local_key">
                                                <td class="px-3 py-2">
                                                    <input v-model="domain.sip_profile_domain_name" :disabled="!canEditDomains(domain)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 disabled:bg-gray-50 disabled:text-gray-500" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <select v-model="domain.sip_profile_domain_alias" :disabled="!canEditDomains(domain)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 disabled:bg-gray-50 disabled:text-gray-500">
                                                        <option value=""></option>
                                                        <option value="true">True</option>
                                                        <option value="false">False</option>
                                                    </select>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <select v-model="domain.sip_profile_domain_parse" :disabled="!canEditDomains(domain)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 disabled:bg-gray-50 disabled:text-gray-500">
                                                        <option value=""></option>
                                                        <option value="true">True</option>
                                                        <option value="false">False</option>
                                                    </select>
                                                </td>
                                                <td v-if="canRemoveDomains" class="px-3 py-2 text-right">
                                                    <button v-if="canRemoveDomain(domain)" type="button" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-red-600" title="Remove" @click="removeDomain(index)">
                                                        <TrashIcon class="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="domains.length === 0">
                                                <td :colspan="canRemoveDomains ? 4 : 3" class="px-3 py-6 text-center text-sm text-gray-500">No domains.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                </section>

                                <section>
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-900">Settings</h4>
                                        <p class="text-xs text-gray-500">Name/value parameters passed to Sofia.</p>
                                    </div>
                                    <button
                                        v-if="childPermissions.setting_create"
                                        type="button"
                                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                        @click="addSetting"
                                    >
                                        Add
                                    </button>
                                </div>

                                <div class="overflow-x-auto rounded-md border border-gray-200">
                                    <table class="min-w-[860px] divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Value</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Enabled</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Description</th>
                                                <th v-if="canRemoveSettings" class="w-12 px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <tr v-for="(setting, index) in settings" :key="setting.local_key">
                                                <td class="px-3 py-2">
                                                    <input v-model="setting.sip_profile_setting_name" :disabled="!canEditSettings(setting)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 disabled:bg-gray-50 disabled:text-gray-500" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input v-model="setting.sip_profile_setting_value" :disabled="!canEditSettings(setting)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 disabled:bg-gray-50 disabled:text-gray-500" />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <select v-model="setting.sip_profile_setting_enabled" :disabled="!canEditSettings(setting)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 disabled:bg-gray-50 disabled:text-gray-500">
                                                        <option value="true">True</option>
                                                        <option value="false">False</option>
                                                    </select>
                                                </td>
                                                <td class="px-3 py-2">
                                                    <input v-model="setting.sip_profile_setting_description" :disabled="!canEditSettings(setting)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 disabled:bg-gray-50 disabled:text-gray-500" />
                                                </td>
                                                <td v-if="canRemoveSettings" class="px-3 py-2 text-right">
                                                    <button v-if="canRemoveSetting(setting)" type="button" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-red-600" title="Remove" @click="removeSetting(index)">
                                                        <TrashIcon class="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="settings.length === 0">
                                                <td :colspan="canRemoveSettings ? 5 : 4" class="px-3 py-6 text-center text-sm text-gray-500">No settings.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                </section>
                            </div>

                            <div class="flex justify-end">
                                <ButtonElement name="submit" button-label="Save" :submits="true" />
                            </div>
                        </div>
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { TrashIcon } from "@heroicons/vue/24/outline";
import AddEditItemModal from "../modal/AddEditItemModal.vue";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "SIP Profile",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data", "reload-options"]);

const form$ = ref(null);
const domains = ref([]);
const settings = ref([]);
let localKey = 0;

const defaultValues = computed(() => ({
    sip_profile_name: props.options?.item?.sip_profile_name ?? null,
    sip_profile_hostname: props.options?.item?.sip_profile_hostname ?? null,
    sip_profile_enabled: props.options?.item?.sip_profile_enabled ?? "true",
    sip_profile_description: props.options?.item?.sip_profile_description ?? null,
}));

const childPermissions = computed(() => props.options?.permissions ?? {});
const canRemoveDomains = computed(() => childPermissions.value.domain_destroy || childPermissions.value.domain_create);
const canRemoveSettings = computed(() => childPermissions.value.setting_destroy || childPermissions.value.setting_create);

watch(
    () => props.options,
    (options) => {
        domains.value = normalizeRows(options?.domains ?? [], newDomain);
        settings.value = normalizeRows(options?.settings ?? [], newSetting);
    },
    { immediate: true },
);

const submitForm = async (FormData, formRef) => {
    const requestData = {
        ...formRef.requestData,
        domains: domains.value.map(cleanDomain),
        settings: settings.value.map(cleanSetting),
    };
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    if (props.mode === "create") {
        return await formRef.$vueform.services.axios.post(route, requestData);
    }

    return await formRef.$vueform.services.axios.put(route, requestData);
};

function normalizeRows(rows, factory) {
    return rows.map((row) => ({ ...factory(), ...row, local_key: ++localKey }));
}

function newDomain() {
    return {
        local_key: ++localKey,
        sip_profile_domain_uuid: null,
        sip_profile_domain_name: "",
        sip_profile_domain_alias: "",
        sip_profile_domain_parse: "",
    };
}

function newSetting() {
    return {
        local_key: ++localKey,
        sip_profile_setting_uuid: null,
        sip_profile_setting_name: "",
        sip_profile_setting_value: "",
        sip_profile_setting_enabled: "true",
        sip_profile_setting_description: "",
    };
}

function addDomain() {
    domains.value.push(newDomain());
}

function removeDomain(index) {
    domains.value.splice(index, 1);
}

function addSetting() {
    settings.value.push(newSetting());
}

function removeSetting(index) {
    settings.value.splice(index, 1);
}

function canEditDomains(domain) {
    return domain.sip_profile_domain_uuid ? childPermissions.value.domain_update : childPermissions.value.domain_create;
}

function canEditSettings(setting) {
    return setting.sip_profile_setting_uuid ? childPermissions.value.setting_update : childPermissions.value.setting_create;
}

function canRemoveDomain(domain) {
    return domain.sip_profile_domain_uuid ? childPermissions.value.domain_destroy : childPermissions.value.domain_create;
}

function canRemoveSetting(setting) {
    return setting.sip_profile_setting_uuid ? childPermissions.value.setting_destroy : childPermissions.value.setting_create;
}

function cleanDomain(domain) {
    return {
        sip_profile_domain_uuid: domain.sip_profile_domain_uuid,
        sip_profile_domain_name: domain.sip_profile_domain_name,
        sip_profile_domain_alias: domain.sip_profile_domain_alias,
        sip_profile_domain_parse: domain.sip_profile_domain_parse,
    };
}

function cleanSetting(setting) {
    return {
        sip_profile_setting_uuid: setting.sip_profile_setting_uuid,
        sip_profile_setting_name: setting.sip_profile_setting_name,
        sip_profile_setting_value: setting.sip_profile_setting_value,
        sip_profile_setting_enabled: setting.sip_profile_setting_enabled || "true",
        sip_profile_setting_description: setting.sip_profile_setting_description,
    };
}

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

function handleResponse(response, formRef) {
    Object.values(formRef.elements$).forEach((el$) => clearErrorsRecursive(el$));

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (formRef.el$(elName)) {
                formRef.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
}

function handleSuccess(response) {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
}

function handleError(error, params, formRef) {
    formRef.messageBag.clear();

    if (params.type === "submit") {
        emit("error", error);
        return;
    }

    formRef.messageBag.append("Could not submit form");
}
</script>
