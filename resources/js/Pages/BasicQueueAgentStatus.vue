<template>
    <MainLayout />

    <div class="m-3">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-heading">Agent Status</h1>
                <p class="mt-1 text-sm text-muted">Basic Queue agents and their live call center state.</p>
            </div>
            <div class="flex items-center gap-2">
                <a :href="routes.back"
                    class="rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Back
                </a>
                <button type="button" @click="getData"
                    class="rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Refresh
                </button>
            </div>
        </div>

        <div v-if="runtimeAvailable === false"
            class="mb-4 rounded-md bg-warning-subtle px-4 py-3 text-sm font-medium text-warning ring-1 ring-inset ring-warning">
            FreeSWITCH event socket is unavailable. Showing saved defaults only.
        </div>

        <div v-if="permissions.update"
            class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-surface px-4 py-3 shadow ring-1 ring-black ring-opacity-5">
            <div class="flex flex-wrap items-center gap-3">
                <BulkActions :actions="bulkActions" :has-selected-items="selectedItems.length > 0"
                    @bulk-action="handleBulkActionRequest" />
                <p class="text-sm text-body">
                    <span class="font-semibold">{{ selectedItems.length }}</span> selected
                </p>
                <button v-if="selectedItems.length > 0" type="button" @click="handleClearSelection"
                    class="text-sm font-semibold text-info hover:text-info">
                    Clear selection
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg bg-surface shadow ring-1 ring-black ring-opacity-5">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-strong">
                    <thead class="bg-surface-2">
                        <tr>
                            <th v-if="permissions.update" class="px-4 py-3.5 text-left">
                                <input v-model="selectPageItems" type="checkbox" @change="handleSelectPageItems"
                                    class="h-4 w-4 rounded border-strong text-accent-fg">
                            </th>
                            <th class="px-4 py-3.5 text-left text-sm font-semibold text-heading">Agent</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-heading">Runtime Status</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-heading">State</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-heading">Default</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-heading">Answered</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-heading">No Answer</th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-heading">Set Status</th>
                            <th class="px-4 py-3.5 text-right text-sm font-semibold text-heading"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-default bg-surface">
                        <tr v-if="loading">
                            <td :colspan="tableColspan" class="px-4 py-8 text-center text-sm text-muted">Loading...</td>
                        </tr>
                        <tr v-else-if="agents.length === 0">
                            <td :colspan="tableColspan" class="px-4 py-8 text-center text-sm text-muted">No agents found.</td>
                        </tr>
                        <tr v-for="agent in agents" v-else :key="agent.call_center_agent_uuid">
                            <td v-if="permissions.update" class="whitespace-nowrap px-4 py-3 text-sm">
                                <input v-model="selectedItems" type="checkbox" name="agent_status_action_box[]"
                                    :value="agent.call_center_agent_uuid"
                                    class="h-4 w-4 rounded border-strong text-accent-fg">
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <div class="font-medium text-heading">{{ agent.agent_name }}</div>
                                <div class="text-muted">{{ agent.agent_id || agent.agent_type || "-" }}</div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm">
                                <Badge :text="agent.runtime_status || '-'" v-bind="statusBadge(agent.runtime_status)" />
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-muted">{{ agent.runtime_state || "-" }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-muted">{{ agent.default_status || "-" }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-muted">{{ agent.calls_answered || "0" }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm text-muted">{{ agent.no_answer_count || "0" }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-sm">
                                <select v-model="agent.pending_status" :disabled="!permissions.update || updatingUuid === agent.call_center_agent_uuid"
                                    class="block w-52 rounded-md border-0 py-1.5 pl-3 pr-8 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-focus disabled:bg-surface-3 disabled:text-muted">
                                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                <button type="button" :disabled="!permissions.update || updatingUuid === agent.call_center_agent_uuid"
                                    @click="updateStatus(agent)"
                                    class="rounded-md bg-accent px-3 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover disabled:cursor-not-allowed disabled:bg-surface-3">
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

    <ConfirmationModal :show="confirmationModalTrigger" @close="handleModalClose" @confirm="confirmAction"
        :header="confirmationHeader" :text="confirmationText" :confirm-button-label="confirmationButtonLabel"
        cancel-button-label="Cancel" />
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import BulkActions from "./components/general/BulkActions.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;
const agents = ref([]);
const statusOptions = ref([]);
const selectPageItems = ref(false);
const selectedItems = ref([]);
const runtimeAvailable = ref(null);
const loading = ref(false);
const updatingUuid = ref(null);
const confirmationModalTrigger = ref(false);
const confirmAction = ref(null);
const confirmationHeader = ref("Confirm Status Change");
const confirmationText = ref("");
const confirmationButtonLabel = ref("Update");
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);

const bulkActions = computed(() => [
    { id: "bulk_available", label: "Set Available", icon: "PlayIcon" },
    { id: "bulk_on_break", label: "Set On Break", icon: "StopIcon" },
    { id: "bulk_logged_out", label: "Set Logged Out", icon: "PencilSquareIcon" },
]);

const tableColspan = computed(() => permissions.update ? 9 : 8);

onMounted(() => {
    getData();
});

watch(selectedItems, () => {
    const visibleUuids = agents.value.map((agent) => agent.call_center_agent_uuid);
    selectPageItems.value = visibleUuids.length > 0
        && visibleUuids.every((uuid) => selectedItems.value.includes(uuid));
}, { deep: true });

const getData = () => {
    loading.value = true;

    axios.get(routes.data)
        .then((response) => {
            agents.value = response.data.data.map((agent) => ({
                ...agent,
                pending_status: normalizeStatus(agent.runtime_status || agent.default_status),
            }));
            statusOptions.value = response.data.status_options;
            runtimeAvailable.value = response.data.runtime_available;
            syncPageSelectionState();
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loading.value = false;
        });
};

const handleSelectPageItems = () => {
    selectedItems.value = selectPageItems.value
        ? agents.value.map((agent) => agent.call_center_agent_uuid)
        : [];
};

const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
};

const syncPageSelectionState = () => {
    const visibleUuids = agents.value.map((agent) => agent.call_center_agent_uuid);
    selectedItems.value = selectedItems.value.filter((uuid) => visibleUuids.includes(uuid));
    selectPageItems.value = visibleUuids.length > 0
        && visibleUuids.every((uuid) => selectedItems.value.includes(uuid));
};

const handleBulkActionRequest = (action) => {
    const statusMap = {
        bulk_available: "Available",
        bulk_on_break: "On Break",
        bulk_logged_out: "Logged Out",
    };
    const status = statusMap[action];

    if (!status || selectedItems.value.length === 0) {
        return;
    }

    showConfirmation({
        header: "Confirm Status Change",
        text: `Set ${selectedItems.value.length} selected agent(s) to ${status}?`,
        button: "Update",
        action: () => executeBulkStatusUpdate(status),
    });
};

const executeBulkStatusUpdate = (status) => {
    updatingUuid.value = "bulk";

    axios.post(routes.update, {
        agent_uuids: selectedItems.value,
        status,
    })
        .then((response) => {
            handleModalClose();
            handleClearSelection();
            showNotification("success", response.data.messages);
            getData();
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        })
        .finally(() => {
            updatingUuid.value = null;
        });
};

const showConfirmation = ({ header, text, button, action }) => {
    confirmationHeader.value = header;
    confirmationText.value = text;
    confirmationButtonLabel.value = button;
    confirmAction.value = action;
    confirmationModalTrigger.value = true;
};

const handleModalClose = () => {
    confirmationModalTrigger.value = false;
    confirmAction.value = null;
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
    if (status === "Available") {
        return { backgroundColor: "bg-success-subtle", textColor: "text-success", ringColor: "ring-success/20" };
    }

    if (status === "On Break") {
        return { backgroundColor: "bg-warning-subtle", textColor: "text-warning", ringColor: "ring-warning/20" };
    }

    return { backgroundColor: "bg-surface-2", textColor: "text-body", ringColor: "ring-strong/20" };
};

const normalizeStatus = (status) => status || "Logged Out";

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
