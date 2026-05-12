<template>
    <MainLayout />

    <div class="m-3">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Agent Status</h1>
                <p class="mt-1 text-sm text-gray-500">Basic Queue agents and their live call center state.</p>
            </div>
            <div class="flex items-center gap-2">
                <a :href="routes.back"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Back
                </a>
                <button type="button" @click="getData"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Refresh
                </button>
            </div>
        </div>

        <div v-if="runtimeAvailable === false"
            class="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 ring-1 ring-inset ring-amber-200">
            FreeSWITCH event socket is unavailable. Showing saved defaults only.
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">Agent</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Runtime Status</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">State</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Default</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Answered</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">No Answer</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Set Status</th>
                            <th class="px-4 py-3.5 text-right text-sm font-semibold text-gray-900"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-if="loading">
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">Loading...</td>
                        </tr>
                        <tr v-else-if="agents.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">No agents found.</td>
                        </tr>
                        <tr v-for="agent in agents" v-else :key="agent.call_center_agent_uuid">
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <div class="font-medium text-gray-900">{{ agent.agent_name }}</div>
                                <div class="text-gray-500">{{ agent.agent_id || agent.agent_type || "-" }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm">
                                <Badge :text="agent.runtime_status || '-'" v-bind="statusBadge(agent.runtime_status)" />
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">{{ agent.runtime_state || "-" }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">{{ agent.default_status || "-" }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">{{ agent.calls_answered || "0" }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500">{{ agent.no_answer_count || "0" }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm">
                                <select v-model="agent.pending_status" :disabled="!permissions.update || updatingUuid === agent.call_center_agent_uuid"
                                    class="block w-52 rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 disabled:bg-gray-100 disabled:text-gray-500">
                                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                <button type="button" :disabled="!permissions.update || updatingUuid === agent.call_center_agent_uuid"
                                    @click="updateStatus(agent)"
                                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-300">
                                    Save
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { onMounted, ref } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;
const agents = ref([]);
const statusOptions = ref([]);
const runtimeAvailable = ref(null);
const loading = ref(false);
const updatingUuid = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);

onMounted(() => {
    getData();
});

const getData = () => {
    loading.value = true;

    axios.get(routes.data)
        .then((response) => {
            agents.value = response.data.data.map((agent) => ({
                ...agent,
                pending_status: agent.runtime_status || agent.default_status || "Logged Out",
            }));
            statusOptions.value = response.data.status_options;
            runtimeAvailable.value = response.data.runtime_available;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loading.value = false;
        });
};

const updateStatus = (agent) => {
    updatingUuid.value = agent.call_center_agent_uuid;

    axios.post(routes.update, {
        agent_uuid: agent.call_center_agent_uuid,
        status: agent.pending_status,
    })
        .then((response) => {
            showNotification("success", response.data.messages);
            getData();
        })
        .catch(handleErrorResponse)
        .finally(() => {
            updatingUuid.value = null;
        });
};

const statusBadge = (status) => {
    if (status === "Available" || status === "Available (On Demand)") {
        return { backgroundColor: "bg-green-50", textColor: "text-green-700", ringColor: "ring-green-600/20" };
    }

    if (status === "On Break") {
        return { backgroundColor: "bg-amber-50", textColor: "text-amber-700", ringColor: "ring-amber-600/20" };
    }

    return { backgroundColor: "bg-gray-50", textColor: "text-gray-700", ringColor: "ring-gray-600/20" };
};

const hideNotification = () => {
    notificationShow.value = false;
};

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const handleErrorResponse = (error) => {
    if (error?.response?.data?.errors) {
        showNotification("error", error.response.data.errors);
        return;
    }

    if (error?.response?.data?.messages) {
        showNotification("error", error.response.data.messages);
        return;
    }

    showNotification("error", { request: [error?.message || "Request failed."] });
};
</script>
