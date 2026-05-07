<template>
    <MainLayout />

    <div class="m-3">
        <DataTable>
            <template #title>{{ data.conference.name || conference }}</template>

            <template #subtitle>
                Interactive conference controls and live participant status.
            </template>

            <template #action>
                <button :class="[
                    isRefreshing
                        ? 'rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                        : 'rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
                ]" title="Auto refresh" @click="toggleRefreshing">
                    <Refresh class="h-5 w-5" :class="{ 'animate-spin': isRefreshing }" />
                </button>

                <button type="button" @click.prevent="handleRefreshButtonClick"
                    class="ml-2 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Refresh
                </button>

                <a :href="routes.active_conferences"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Active Conferences
                </a>
            </template>

            <template #navigation>
                <div class="flex items-center justify-between border-b border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :text="`${data.conference.member_count || 0} Members`" background-color="bg-blue-50"
                            text-color="text-blue-700" ring-color="ring-blue-600/20" />
                        <Badge :text="data.conference.recording ? 'Recording' : 'Not Recording'"
                            :background-color="data.conference.recording ? 'bg-red-50' : 'bg-gray-50'"
                            :text-color="data.conference.recording ? 'text-red-700' : 'text-gray-600'"
                            :ring-color="data.conference.recording ? 'ring-red-600/20' : 'ring-gray-500/20'" />
                        <Badge :text="data.conference.locked ? 'Locked' : 'Unlocked'"
                            :background-color="data.conference.locked ? 'bg-yellow-50' : 'bg-green-50'"
                            :text-color="data.conference.locked ? 'text-yellow-800' : 'text-green-700'"
                            :ring-color="data.conference.locked ? 'ring-yellow-600/20' : 'ring-green-600/20'" />
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <button v-if="permissions.lock" type="button" class="secondary-button"
                            @click="executeAction(data.conference.locked ? 'unlock' : 'lock')">
                            {{ data.conference.locked ? 'Unlock' : 'Lock' }}
                        </button>
                        <button v-if="permissions.mute" type="button" class="secondary-button"
                            @click="executeAction(data.conference.mute_all ? 'unmute_non_moderator' : 'mute_non_moderator')">
                            {{ data.conference.mute_all ? 'Unmute All' : 'Mute All' }}
                        </button>
                        <button v-if="permissions.kick" type="button" class="danger-button"
                            @click="executeAction('kick_all')">
                            End Conference
                        </button>
                    </div>
                </div>
            </template>

            <template #table-header>
                <TableColumnHeader header="" class="w-1 px-4 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Caller Name" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Caller Number" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Joined" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Quiet" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Floor" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Hand Raised" class="w-32 px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Capabilities" class="w-40 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader v-if="permissions.energy" header="Energy" class="w-28 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader v-if="permissions.volume" header="Volume" class="w-28 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader v-if="permissions.gain" header="Gain" class="w-28 px-2 py-3.5 text-center text-sm font-semibold text-gray-900 [&>div]:justify-center" />
                <TableColumnHeader header="Action" class="w-72 px-2 py-3.5 text-right text-sm font-semibold text-gray-900 [&>div]:justify-end" />
            </template>

            <template #table-body>
                <tr v-for="member in data.members" :key="member.uuid || member.id">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                        <Badge :text="member.is_moderator ? 'Moderator' : 'Participant'"
                            :background-color="member.is_moderator ? 'bg-purple-50' : 'bg-gray-50'"
                            :text-color="member.is_moderator ? 'text-purple-700' : 'text-gray-600'"
                            :ring-color="member.is_moderator ? 'ring-purple-600/20' : 'ring-gray-500/20'" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm font-medium text-gray-900">
                        <span>{{ member.caller_id_name || '-' }}</span>
                        <Badge v-if="member.talking" text="Talking" background-color="bg-green-50"
                            text-color="text-green-700" ring-color="ring-green-600/20" class="ml-2" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="member.caller_id_number || '-'" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="formatDuration(member.join_time)" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="formatDuration(member.last_talking)" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="member.has_floor ? 'Yes' : 'No'" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <Badge :text="member.hand_raised ? 'Yes' : 'No'"
                            :background-color="member.hand_raised ? 'bg-yellow-50' : 'bg-gray-50'"
                            :text-color="member.hand_raised ? 'text-yellow-800' : 'text-gray-600'"
                            :ring-color="member.hand_raised ? 'ring-yellow-600/20' : 'ring-gray-500/20'" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500">
                        <div class="flex justify-center gap-1">
                            <Badge :text="member.can_speak ? 'Speak' : 'Muted'"
                                :background-color="member.can_speak ? 'bg-green-50' : 'bg-red-50'"
                                :text-color="member.can_speak ? 'text-green-700' : 'text-red-700'"
                                :ring-color="member.can_speak ? 'ring-green-600/20' : 'ring-red-600/20'" />
                            <Badge :text="member.can_hear ? 'Hear' : 'Deaf'"
                                :background-color="member.can_hear ? 'bg-green-50' : 'bg-red-50'"
                                :text-color="member.can_hear ? 'text-green-700' : 'text-red-700'"
                                :ring-color="member.can_hear ? 'ring-green-600/20' : 'ring-red-600/20'" />
                            <Badge v-if="permissions.video && member.has_video" text="Video" background-color="bg-blue-50"
                                text-color="text-blue-700" ring-color="ring-blue-600/20" />
                        </div>
                    </TableField>
                    <TableField v-if="permissions.energy" class="whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500">
                        <StepperButtons @down="executeAction('energy', member, 'down')" @up="executeAction('energy', member, 'up')" />
                    </TableField>
                    <TableField v-if="permissions.volume" class="whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500">
                        <StepperButtons @down="executeAction('volume_in', member, 'down')" @up="executeAction('volume_in', member, 'up')" />
                    </TableField>
                    <TableField v-if="permissions.gain" class="whitespace-nowrap px-2 py-2 text-center text-sm text-gray-500">
                        <StepperButtons @down="executeAction('volume_out', member, 'down')" @up="executeAction('volume_out', member, 'up')" />
                    </TableField>
                    <TableField class="w-72 whitespace-nowrap px-2 py-2 text-right text-sm text-gray-500">
                        <div class="flex justify-end gap-2">
                            <button v-if="permissions.mute" type="button" class="secondary-button"
                                @click="executeAction(member.can_speak ? 'mute' : 'unmute', member)">
                                {{ member.can_speak ? 'Mute' : 'Unmute' }}
                            </button>
                            <button v-if="permissions.deaf" type="button" class="secondary-button"
                                @click="executeAction(member.can_hear ? 'deaf' : 'undeaf', member)">
                                {{ member.can_hear ? 'Deaf' : 'Undeaf' }}
                            </button>
                            <button v-if="permissions.kick" type="button" class="danger-button"
                                @click="executeAction('kick', member)">
                                Kick
                            </button>
                        </div>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.members.length === 0" class="text-center my-5">
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No members found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Refresh the list to check whether the conference is still active.
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>
        </DataTable>
    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { defineComponent, h, onMounted, onUnmounted, ref } from "vue";
import axios from "axios";
import Badge from "@generalComponents/Badge.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Refresh from "./components/icons/Refresh.vue";

const StepperButtons = defineComponent({
    emits: ["down", "up"],
    setup(_, { emit }) {
        return () => h("div", { class: "inline-flex rounded-md shadow-sm" }, [
            h("button", {
                type: "button",
                class: "relative inline-flex items-center rounded-l-md bg-white px-2 py-1 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50",
                onClick: () => emit("down"),
            }, "-"),
            h("button", {
                type: "button",
                class: "relative -ml-px inline-flex items-center rounded-r-md bg-white px-2 py-1 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50",
                onClick: () => emit("up"),
            }, "+"),
        ]);
    },
});

const props = defineProps({
    conference: String,
    display_name: String,
    routes: Object,
    permissions: Object,
});

const conference = props.conference;
const displayName = props.display_name || conference;
const routes = props.routes;
const permissions = props.permissions;

const loading = ref(false);
const isRefreshing = ref(false);
const refreshTimeoutId = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);

const data = ref({
    conference: {
        identifier: conference,
        name: displayName,
        member_count: 0,
        locked: false,
        recording: false,
        mute_all: true,
    },
    members: [],
});

onMounted(() => {
    getData();
});

onUnmounted(() => {
    stopRefreshing();
});

const getData = (showLoading = true) => {
    if (showLoading) {
        loading.value = true;
    }

    return axios.get(routes.data_route)
        .then((response) => {
            data.value = response.data;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            if (showLoading) {
                loading.value = false;
            }
        });
};

const handleRefreshButtonClick = () => {
    getData(false);
};

const toggleRefreshing = () => {
    isRefreshing.value = !isRefreshing.value;

    if (isRefreshing.value) {
        handleAutoRefresh();
        return;
    }

    stopRefreshing();
};

const handleAutoRefresh = () => {
    if (!isRefreshing.value) return;

    getData(false)
        .finally(() => {
            if (isRefreshing.value) {
                refreshTimeoutId.value = setTimeout(handleAutoRefresh, 5000);
            }
        });
};

const stopRefreshing = () => {
    isRefreshing.value = false;

    if (refreshTimeoutId.value) {
        clearTimeout(refreshTimeoutId.value);
        refreshTimeoutId.value = null;
    }
};

const executeAction = (action, member = null, direction = null) => {
    axios.post(routes.action, {
        action,
        id: member?.id ?? null,
        uuid: member?.uuid ?? null,
        direction,
    })
        .then((response) => {
            showNotification("success", response.data.messages);
            getData(false);
        })
        .catch(handleErrorResponse);
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification("error", error.response.data.errors || error.response.data.messages || { request: [error.message] });
    } else if (error.request) {
        showNotification("error", { request: [error.request] });
    } else {
        showNotification("error", { request: [error.message] });
    }
};

const formatDuration = (seconds) => {
    const value = Number(seconds) || 0;
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    const remainingSeconds = value % 60;
    const pad = (num) => num.toString().padStart(2, "0");

    return `${pad(hours)}:${pad(minutes)}:${pad(remainingSeconds)}`;
};
</script>

<style scoped>
.secondary-button {
    @apply rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50;
}

.danger-button {
    @apply rounded-md bg-red-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500;
}
</style>
