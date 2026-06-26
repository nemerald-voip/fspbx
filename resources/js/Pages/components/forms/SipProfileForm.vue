<template>
    <AddEditItemModal :show="show" :header="header" :loading="loading" custom-class="sm:max-w-5xl" @close="emit('close')">
        <template #modal-body>
            <div class="flex h-[72vh] flex-col">
                <!-- Tabs -->
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex gap-6" aria-label="Tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            :class="[
                                activeTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                                'flex items-center gap-2 whitespace-nowrap border-b-2 px-1 pb-3 pt-1 text-sm font-medium',
                            ]"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                            <span
                                v-if="tab.count !== null"
                                :class="[activeTab === tab.id ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600', 'rounded-full px-2 py-0.5 text-xs font-semibold']"
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- ── Profile ─────────────────────────────────────────── -->
                <div v-show="activeTab === 'profile'" class="grid grid-cols-1 gap-4 overflow-y-auto py-5 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-900">Name <span class="text-red-500">*</span></label>
                        <input v-model.trim="form.sip_profile_name" type="text" placeholder="internal" :class="inputClass(fieldError('sip_profile_name'))" />
                        <p v-if="fieldError('sip_profile_name')" class="mt-1 text-xs text-red-600">{{ fieldError('sip_profile_name') }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-900">Hostname</label>
                        <input v-model.trim="form.sip_profile_hostname" type="text" placeholder="Optional" :class="inputClass(fieldError('sip_profile_hostname'))" />
                        <p class="mt-1 text-xs text-gray-500">Optional. Limit this profile to a specific FreeSWITCH hostname.</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-900">Description <span class="text-red-500">*</span></label>
                        <textarea v-model="form.sip_profile_description" rows="2" :class="inputClass(fieldError('sip_profile_description'))"></textarea>
                        <p v-if="fieldError('sip_profile_description')" class="mt-1 text-xs text-red-600">{{ fieldError('sip_profile_description') }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <Toggle v-model="enabledModel" label="Enabled" description="Include this profile when Sofia configuration is generated." />
                    </div>
                </div>

                <!-- ── Settings ────────────────────────────────────────── -->
                <div v-show="activeTab === 'settings'" class="flex min-h-0 flex-1 gap-4 py-4">
                    <!-- Sidebar -->
                    <aside class="flex w-52 shrink-0 flex-col overflow-y-auto border-r border-gray-200 pr-3">
                        <nav class="space-y-0.5">
                            <button type="button" :class="categoryClass('all')" @click="activeGroup = 'all'">
                                <span>All</span>
                                <span class="text-xs text-gray-400">{{ settings.length }}</span>
                            </button>
                            <button v-for="group in sidebarGroups" :key="group.name" type="button" :class="categoryClass(group.name)" @click="activeGroup = group.name">
                                <span class="truncate">{{ group.name }}</span>
                                <span class="text-xs text-gray-400">{{ group.count }}</span>
                            </button>
                        </nav>

                        <div v-if="mode === 'create' && childPermissions.setting_create" class="mt-4 border-t border-gray-200 pt-3">
                            <p class="mb-1.5 px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Templates</p>
                            <button
                                v-for="(tpl, id) in templates"
                                :key="id"
                                type="button"
                                class="block w-full rounded-md px-2 py-1.5 text-left text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                :title="tpl.description"
                                @click="applyTemplate(id)"
                            >
                                {{ tpl.label }}
                            </button>
                        </div>
                    </aside>

                    <!-- Content -->
                    <div class="flex min-w-0 flex-1 flex-col">
                        <div class="flex items-center gap-3">
                            <div class="relative flex-1">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" />
                                </div>
                                <input
                                    v-model="settingSearch"
                                    type="text"
                                    placeholder="Filter by name, value or note"
                                    class="block w-full rounded-md border-0 py-1.5 pl-9 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                                />
                            </div>
                            <button
                                v-if="childPermissions.setting_create"
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                                @click="addSetting"
                            >
                                <PlusIcon class="h-4 w-4" /> Add setting
                            </button>
                        </div>

                        <div ref="settingsScroll" class="mt-3 flex-1 space-y-5 overflow-y-auto pr-1">
                            <div v-if="settings.length === 0" class="rounded-md border border-dashed border-gray-300 px-3 py-12 text-center text-sm text-gray-500">
                                No settings yet. Use “Add setting”{{ mode === 'create' ? ' or pick a template' : '' }}.
                            </div>
                            <div v-else-if="renderGroups.length === 0" class="rounded-md border border-dashed border-gray-300 px-3 py-12 text-center text-sm text-gray-500">
                                No settings match “{{ settingSearch }}”.
                            </div>

                            <section v-for="group in renderGroups" :key="group.name" :class="group.pinned ? 'rounded-lg bg-indigo-50/50 p-2 ring-1 ring-indigo-100' : ''">
                                <h4
                                    class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide"
                                    :class="group.pinned ? 'text-indigo-600' : 'text-gray-500'"
                                >
                                    {{ group.name }}
                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                        :class="group.pinned ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'"
                                    >
                                        {{ group.rows.length }}
                                    </span>
                                    <span v-if="group.pinned" class="font-normal normal-case tracking-normal text-indigo-400">· stays here until saved</span>
                                </h4>
                                <div class="space-y-2">
                                    <SipSettingRow
                                        v-for="setting in group.rows"
                                        :key="setting.local_key"
                                        :setting="setting"
                                        :can-edit="canEditSetting(setting)"
                                        :can-remove="canRemoveSetting(setting)"
                                        :error="settingError(setting)"
                                        @remove="removeSetting(setting)"
                                    />
                                </div>
                            </section>
                        </div>
                    </div>
                </div>

                <!-- ── Domains ─────────────────────────────────────────── -->
                <div v-show="activeTab === 'domains'" class="flex min-h-0 flex-1 flex-col py-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-500">Optional domain aliases included in the generated profile.</p>
                        <button
                            v-if="childPermissions.domain_create"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                            @click="addDomain"
                        >
                            <PlusIcon class="h-4 w-4" /> Add domain
                        </button>
                    </div>

                    <div class="mt-3 flex-1 space-y-2 overflow-y-auto pr-1">
                        <div v-if="domains.length === 0" class="rounded-md border border-dashed border-gray-300 px-3 py-12 text-center text-sm text-gray-500">
                            No domains.
                        </div>
                        <div v-for="(domain, index) in domains" :key="domain.local_key" class="grid grid-cols-12 items-center gap-2 rounded-md border border-gray-200 bg-white px-2.5 py-2">
                            <div class="col-span-12 sm:col-span-6">
                                <input v-model.trim="domain.sip_profile_domain_name" :disabled="!canEditDomain(domain)" placeholder="domain name" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 disabled:bg-gray-50 disabled:text-gray-500" />
                            </div>
                            <div class="col-span-5 sm:col-span-2">
                                <select v-model="domain.sip_profile_domain_alias" :disabled="!canEditDomain(domain)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 disabled:bg-gray-50 disabled:text-gray-500">
                                    <option value="">Alias…</option>
                                    <option value="true">True</option>
                                    <option value="false">False</option>
                                </select>
                            </div>
                            <div class="col-span-5 sm:col-span-3">
                                <select v-model="domain.sip_profile_domain_parse" :disabled="!canEditDomain(domain)" class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 disabled:bg-gray-50 disabled:text-gray-500">
                                    <option value="">Parse…</option>
                                    <option value="true">True</option>
                                    <option value="false">False</option>
                                </select>
                            </div>
                            <div class="col-span-2 flex justify-end sm:col-span-1">
                                <button v-if="canRemoveDomain(domain)" type="button" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-red-600" title="Remove" @click="removeDomain(index)">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-gray-200 pt-4">
                    <p v-if="hasErrors" class="text-sm text-red-600">Please fix the highlighted fields.</p>
                    <span v-else></span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" @click="emit('close')">Cancel</button>
                        <button
                            type="button"
                            :disabled="saving"
                            class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-60"
                            @click="save"
                        >
                            {{ saving ? "Saving…" : "Save" }}
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, nextTick, ref, watch } from "vue";
import axios from "axios";
import { MagnifyingGlassIcon, PlusIcon, TrashIcon } from "@heroicons/vue/24/outline";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import Toggle from "@generalComponents/Toggle.vue";
import SipSettingRow from "./SipSettingRow.vue";
import {
    SIP_SETTING_GROUPS,
    CUSTOM_GROUP,
    SIP_PROFILE_TEMPLATES,
    resolveSettingGroup,
    templateSettings,
} from "../../data/sofiaSipProfileSettings";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: { type: String, default: "SIP Profile" },
    mode: { type: String, default: "create" },
});

const emit = defineEmits(["close", "error", "success", "refresh-data", "reload-options"]);

const templates = SIP_PROFILE_TEMPLATES;

const form = ref({ sip_profile_name: "", sip_profile_hostname: "", sip_profile_enabled: "true", sip_profile_description: "" });
const settings = ref([]);
const domains = ref([]);
const activeTab = ref("profile");
const settingSearch = ref("");
const activeGroup = ref("all");
const settingsScroll = ref(null);
const saving = ref(false);
const errors = ref({});
let localKey = 0;

const childPermissions = computed(() => props.options?.permissions ?? {});
const hasErrors = computed(() => Object.keys(errors.value).length > 0);

const enabledModel = computed({
    get: () => form.value.sip_profile_enabled === "true",
    set: (on) => { form.value.sip_profile_enabled = on ? "true" : "false"; },
});

const tabs = computed(() => [
    { id: "profile", label: "Profile", count: null },
    { id: "settings", label: "Settings", count: settings.value.length },
    { id: "domains", label: "Domains", count: domains.value.length },
]);

// A row's section is frozen at load time (group_key) so editing its name never
// makes it hop between categories mid-edit. It only re-sorts on the next reload.
const sidebarGroups = computed(() => {
    const counts = {};
    for (const setting of settings.value) {
        if (setting.is_new) continue;
        counts[setting.group_key] = (counts[setting.group_key] || 0) + 1;
    }
    return [...SIP_SETTING_GROUPS, CUSTOM_GROUP]
        .filter((group) => counts[group])
        .map((group) => ({ name: group, count: counts[group] }));
});

const renderGroups = computed(() => {
    const needle = settingSearch.value.trim().toLowerCase();
    const matches = (s) =>
        !needle ||
        [s.sip_profile_setting_name, s.sip_profile_setting_value, s.sip_profile_setting_description]
            .some((field) => (field ?? "").toLowerCase().includes(needle));
    const wantGroup = (group) => Boolean(needle) || activeGroup.value === "all" || activeGroup.value === group;

    const result = [];

    // Newly added rows stay pinned at the top until the profile is saved, so they
    // don't jump into a category the moment you pick a known parameter name.
    const pinned = settings.value.filter((s) => s.is_new);
    if (pinned.length) {
        result.push({ name: "New settings", rows: pinned, pinned: true });
    }

    const buckets = new Map();
    for (const setting of settings.value) {
        if (setting.is_new || !matches(setting)) continue;
        if (!wantGroup(setting.group_key)) continue;
        if (!buckets.has(setting.group_key)) buckets.set(setting.group_key, []);
        buckets.get(setting.group_key).push(setting);
    }

    for (const group of [...SIP_SETTING_GROUPS, CUSTOM_GROUP]) {
        if (buckets.has(group)) result.push({ name: group, rows: buckets.get(group) });
    }

    return result;
});

watch(
    () => props.options,
    (options) => {
        const item = options?.item ?? {};
        form.value = {
            sip_profile_name: item.sip_profile_name ?? "",
            sip_profile_hostname: item.sip_profile_hostname ?? "",
            sip_profile_enabled: item.sip_profile_enabled ?? "true",
            sip_profile_description: item.sip_profile_description ?? "",
        };
        settings.value = normalizeRows(options?.settings ?? [], newSetting).map(freezeGroup);
        domains.value = normalizeRows(options?.domains ?? [], newDomain);
        activeTab.value = "profile";
        settingSearch.value = "";
        activeGroup.value = "all";
        errors.value = {};
    },
    { immediate: true },
);

function normalizeRows(rows, factory) {
    return rows.map((row) => ({ ...factory(), ...row, local_key: ++localKey }));
}

function freezeGroup(setting) {
    setting.group_key = resolveSettingGroup(setting.sip_profile_setting_name);
    return setting;
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

function newDomain() {
    return {
        local_key: ++localKey,
        sip_profile_domain_uuid: null,
        sip_profile_domain_name: "",
        sip_profile_domain_alias: "",
        sip_profile_domain_parse: "",
    };
}

function addSetting() {
    settingSearch.value = "";
    settings.value.push({ ...newSetting(), is_new: true, group_key: CUSTOM_GROUP });
    nextTick(() => settingsScroll.value?.scrollTo({ top: 0 }));
}

function removeSetting(setting) {
    const index = settings.value.indexOf(setting);
    if (index !== -1) settings.value.splice(index, 1);
}

function addDomain() {
    domains.value.push(newDomain());
}

function removeDomain(index) {
    domains.value.splice(index, 1);
}

function applyTemplate(templateId) {
    settings.value = templateSettings(templateId).map((row) => freezeGroup({ ...row, local_key: ++localKey }));
    settingSearch.value = "";
    activeGroup.value = "all";
}

function categoryClass(group) {
    const active = settingSearch.value.trim() === "" && activeGroup.value === group;
    return [
        active ? "bg-indigo-50 text-indigo-700" : "text-gray-700 hover:bg-gray-100",
        "flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-sm font-medium",
    ];
}

function inputClass(error) {
    return [
        "mt-1 block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 shadow-sm ring-1 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600",
        error ? "ring-red-400" : "ring-gray-300",
    ];
}

function canEditSetting(setting) {
    return setting.sip_profile_setting_uuid ? childPermissions.value.setting_update : childPermissions.value.setting_create;
}

function canRemoveSetting(setting) {
    return setting.sip_profile_setting_uuid ? childPermissions.value.setting_destroy : childPermissions.value.setting_create;
}

function canEditDomain(domain) {
    return domain.sip_profile_domain_uuid ? childPermissions.value.domain_update : childPermissions.value.domain_create;
}

function canRemoveDomain(domain) {
    return domain.sip_profile_domain_uuid ? childPermissions.value.domain_destroy : childPermissions.value.domain_create;
}

function fieldError(name) {
    return errors.value[name]?.[0] ?? "";
}

function settingError(setting) {
    const index = settings.value.indexOf(setting);
    const key = Object.keys(errors.value).find((k) => k.startsWith(`settings.${index}.`));
    return key ? errors.value[key][0] : "";
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

function cleanDomain(domain) {
    return {
        sip_profile_domain_uuid: domain.sip_profile_domain_uuid,
        sip_profile_domain_name: domain.sip_profile_domain_name,
        sip_profile_domain_alias: domain.sip_profile_domain_alias,
        sip_profile_domain_parse: domain.sip_profile_domain_parse,
    };
}

async function save() {
    saving.value = true;
    errors.value = {};

    const payload = {
        ...form.value,
        settings: settings.value.map(cleanSetting),
        domains: domains.value.map(cleanDomain),
    };
    const route = props.mode === "create" ? props.options.routes.store_route : props.options.routes.update_route;

    try {
        const response = props.mode === "create"
            ? await axios.post(route, payload)
            : await axios.put(route, payload);

        emit("success", "success", response.data.messages);
        emit("refresh-data");
        emit("close");
    } catch (error) {
        if (error?.response?.status === 422 && error.response.data?.errors) {
            errors.value = error.response.data.errors;
            focusFirstError();
        }
        emit("error", error);
    } finally {
        saving.value = false;
    }
}

function focusFirstError() {
    const keys = Object.keys(errors.value);
    if (keys.some((key) => key.startsWith("settings"))) {
        activeTab.value = "settings";
    } else if (keys.some((key) => key.startsWith("domains"))) {
        activeTab.value = "domains";
    } else {
        activeTab.value = "profile";
    }
}
</script>
