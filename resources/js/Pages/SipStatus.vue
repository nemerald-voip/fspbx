<template>
    <MainLayout />

    <div class="m-3 space-y-6">
        <DataTable @search-action="handleSearch" @reset-filters="resetFilters">
            <template #title>SIP Status</template>
            <template #subtitle>
                Current Sofia profiles, gateways, aliases, profile details, and switch status.
                <span v-if="statusData.generated_at" class="ml-2 text-gray-500">
                    Updated {{ formatDate(statusData.generated_at) }}
                </span>
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        placeholder="Search"
                        @keydown.enter="handleSearch"
                    />
                </div>
            </template>

            <template #action>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button
                        v-if="permissions.can_run_commands"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="submitAction('cache-flush')"
                    >
                        <ArchiveBoxXMarkIcon class="h-4 w-4 text-gray-500" />
                        Flush Cache
                    </button>
                    <button
                        v-if="permissions.can_run_commands"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="submitAction('reloadacl')"
                    >
                        <ShieldCheckIcon class="h-4 w-4 text-gray-500" />
                        Reload ACL
                    </button>
                    <button
                        v-if="permissions.can_run_commands"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="actionLoading"
                        @click="submitAction('reloadxml')"
                    >
                        <CodeBracketIcon class="h-4 w-4 text-gray-500" />
                        Reload XML
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                        :disabled="loading"
                        @click="fetchData"
                    >
                        <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                        Refresh
                    </button>
                </div>
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    Name
                </TableColumnHeader>
                <TableColumnHeader header="Type" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Data" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="State" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in filteredSummary" :key="row.id">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <a
                            v-if="row.edit_url"
                            :href="row.edit_url"
                            class="font-medium text-gray-900 hover:text-blue-600"
                        >
                            {{ row.name }}
                        </a>
                        <span v-else class="font-medium text-gray-900">{{ row.name }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.type" />
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <span class="break-all">{{ row.data || '-' }}</span>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <Badge
                            :text="row.state || '-'"
                            :backgroundColor="statusColor(row.state).backgroundColor"
                            :textColor="statusColor(row.state).textColor"
                            :ringColor="statusColor(row.state).ringColor"
                        />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <button
                                v-if="row.action && row.action.gateway"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction(row.action.action, { profile: row.action.profile, gateway: row.action.gateway })"
                            >
                                {{ row.action.label }}
                            </button>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && filteredSummary.length === 0" class="my-5 text-center">
                    <ServerStackIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No SIP status rows found</h3>
                    <p class="mt-1 text-sm text-gray-500">Refresh the page or adjust your search.</p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>
        </DataTable>

        <section v-if="permissions.system_status_sofia_status_profile" class="px-4 sm:px-6 lg:px-8">
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold leading-6 text-gray-600">Sofia Status Profiles</h2>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="expandAllProfiles"
                    >
                        Expand
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        @click="collapseAllProfiles"
                    >
                        Collapse
                    </button>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="profile in statusData.profiles"
                    :key="profile.sip_profile_uuid"
                    class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5"
                >
                    <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <button
                            type="button"
                            class="flex min-w-0 items-center gap-2 text-left text-sm font-semibold text-gray-900"
                            @click="toggleProfile(profile.sip_profile_name)"
                        >
                            <ChevronDownIcon v-if="isProfileOpen(profile.sip_profile_name)" class="h-5 w-5 flex-none text-gray-500" />
                            <ChevronRightIcon v-else class="h-5 w-5 flex-none text-gray-500" />
                            <span class="truncate">{{ profile.sip_profile_name }}</span>
                            <Badge
                                :text="profile.state"
                                :backgroundColor="statusColor(profile.state).backgroundColor"
                                :textColor="statusColor(profile.state).textColor"
                                :ringColor="statusColor(profile.state).ringColor"
                            />
                        </button>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="permissions.can_run_commands"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('flush_inbound_reg', { profile: profile.sip_profile_name })"
                            >
                                Flush Registrations
                            </button>
                            <a
                                :href="profile.registrations_url"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                            >
                                Registrations ({{ profile.registration_count }})
                            </a>
                            <button
                                v-if="permissions.can_run_commands && profile.state === 'stopped'"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('start', { profile: profile.sip_profile_name })"
                            >
                                Start
                            </button>
                            <button
                                v-if="permissions.can_run_commands && profile.state === 'running'"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('stop', { profile: profile.sip_profile_name })"
                            >
                                Stop
                            </button>
                            <button
                                v-if="permissions.can_run_commands"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('restart', { profile: profile.sip_profile_name })"
                            >
                                Restart
                            </button>
                            <button
                                v-if="permissions.can_run_commands"
                                type="button"
                                class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="actionLoading"
                                @click="submitAction('rescan', { profile: profile.sip_profile_name })"
                            >
                                Rescan
                            </button>
                        </div>
                    </div>

                    <div v-if="isProfileOpen(profile.sip_profile_name)" class="border-t border-gray-200">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="detail in profile.details" :key="detail.label">
                                        <td class="w-64 whitespace-nowrap bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700">
                                            {{ detail.label }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-600">
                                            <span class="break-all">{{ detail.value || '-' }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="permissions.sip_status_switch_status" class="px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-left text-lg font-semibold leading-6 text-gray-600"
                    @click="showSwitchStatus = !showSwitchStatus"
                >
                    <span>Status</span>
                    <ChevronDownIcon v-if="showSwitchStatus" class="h-5 w-5 text-gray-500" />
                    <ChevronRightIcon v-else class="h-5 w-5 text-gray-500" />
                </button>
                <div v-if="showSwitchStatus" class="border-t border-gray-200 bg-gray-950 px-4 py-3">
                    <pre class="max-h-[36rem] overflow-auto whitespace-pre-wrap text-xs leading-5 text-gray-100">{{ statusData.switch_status || '-' }}</pre>
                </div>
            </div>
        </section>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Badge from "./components/general/Badge.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import {
    ArchiveBoxXMarkIcon,
    ArrowPathIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    CodeBracketIcon,
    MagnifyingGlassIcon,
    ServerStackIcon,
    ShieldCheckIcon,
} from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const statusData = ref({
    connected: false,
    generated_at: null,
    summary: [],
    profiles: [],
    switch_status: null,
});
const loading = ref(false);
const actionLoading = ref(false);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const filterData = ref({ search: "" });
const activeSearch = ref("");
const openProfiles = ref([]);
const showSwitchStatus = ref(true);

const permissions = computed(() => props.permissions ?? {});

const filteredSummary = computed(() => {
    const needle = activeSearch.value.trim().toLowerCase();

    if (!needle) {
        return statusData.value.summary;
    }

    return statusData.value.summary.filter((row) => {
        return [row.name, row.type, row.data, row.state]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(needle));
    });
});

onMounted(() => {
    fetchData();
});

const fetchData = () => {
    loading.value = true;

    axios.get(props.routes.data_route)
        .then((response) => {
            statusData.value = {
                connected: response.data.connected,
                generated_at: response.data.generated_at,
                summary: response.data.summary || [],
                profiles: response.data.profiles || [],
                switch_status: response.data.switch_status,
            };
        })
        .catch(handleError)
        .finally(() => {
            loading.value = false;
        });
};

const submitAction = (action, payload = {}) => {
    actionLoading.value = true;

    axios.post(props.routes.action, { action, ...payload })
        .then((response) => {
            showNotification("success", response.data.messages || { success: ["Request successfully processed."] });
            fetchData();
        })
        .catch(handleError)
        .finally(() => {
            actionLoading.value = false;
        });
};

const handleSearch = () => {
    activeSearch.value = filterData.value.search || "";
};

const resetFilters = () => {
    filterData.value.search = "";
    activeSearch.value = "";
};

const toggleProfile = (profileName) => {
    if (isProfileOpen(profileName)) {
        openProfiles.value = openProfiles.value.filter((name) => name !== profileName);
        return;
    }

    openProfiles.value = [...openProfiles.value, profileName];
};

const isProfileOpen = (profileName) => openProfiles.value.includes(profileName);

const expandAllProfiles = () => {
    openProfiles.value = statusData.value.profiles.map((profile) => profile.sip_profile_name);
};

const collapseAllProfiles = () => {
    openProfiles.value = [];
};

const statusColor = (status) => {
    const normalized = String(status || "").toLowerCase();

    if (normalized.includes("running") || normalized.includes("reged") || normalized.includes("up")) {
        return {
            backgroundColor: "bg-emerald-50",
            textColor: "text-emerald-700",
            ringColor: "ring-emerald-600/20",
        };
    }

    if (normalized.includes("down") || normalized.includes("fail") || normalized.includes("stopped")) {
        return {
            backgroundColor: "bg-red-50",
            textColor: "text-red-700",
            ringColor: "ring-red-600/20",
        };
    }

    return {
        backgroundColor: "bg-gray-50",
        textColor: "text-gray-700",
        ringColor: "ring-gray-600/20",
    };
};

const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};

const handleError = (error) => {
    notificationType.value = "error";
    notificationMessages.value = normalizeMessages(error);
    notificationShow.value = true;
};

const normalizeMessages = (error) => {
    const payload = error?.response?.data;

    if (payload?.errors) {
        return payload.errors;
    }

    if (payload?.messages) {
        return payload.messages;
    }

    if (payload?.message) {
        return { request: [payload.message] };
    }

    if (error?.message) {
        return { request: [error.message] };
    }

    return { request: ["An unexpected error occurred."] };
};

const formatDate = (value) => {
    if (!value) {
        return "-";
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};
</script>
