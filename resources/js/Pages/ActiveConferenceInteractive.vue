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
                        ? 'rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent'
                        : 'rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2'
                ]" title="Auto refresh" @click="toggleRefreshing">
                    <Refresh class="h-5 w-5" :class="{ 'animate-spin': isRefreshing }" />
                </button>

                <button type="button" @click.prevent="handleRefreshButtonClick"
                    class="ml-2 rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    Refresh
                </button>

                <a :href="routes.active_conferences"
                    class="ml-2 sm:ml-4 rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Active Conferences
                </a>
            </template>

            <template #navigation>
                <div class="flex items-center justify-between border-b border-t border-default bg-surface px-4 py-3 sm:px-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :text="`${data.conference.member_count || 0} Members`" background-color="bg-info-subtle"
                            text-color="text-info" ring-color="ring-info/20" />
                        <Badge :text="data.conference.recording ? 'Recording' : 'Not Recording'"
                            :background-color="data.conference.recording ? 'bg-danger-subtle' : 'bg-surface-2'"
                            :text-color="data.conference.recording ? 'text-danger' : 'text-body'"
                            :ring-color="data.conference.recording ? 'ring-danger/20' : 'ring-strong/20'" />
                        <Badge :text="data.conference.locked ? 'Locked' : 'Unlocked'"
                            :background-color="data.conference.locked ? 'bg-warning-subtle' : 'bg-success-subtle'"
                            :text-color="data.conference.locked ? 'text-warning' : 'text-success'"
                            :ring-color="data.conference.locked ? 'ring-warning/20' : 'ring-success/20'" />
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
                <TableColumnHeader header="" class="w-1 px-4 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Caller Name" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Caller Number" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Joined" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Quiet" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Floor" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Hand Raised" class="w-32 px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Capabilities" class="w-40 px-2 py-3.5 text-center text-sm font-semibold text-heading [&>div]:justify-center" />
                <TableColumnHeader v-if="permissions.energy" header="Energy" class="w-28 px-2 py-3.5 text-center text-sm font-semibold text-heading [&>div]:justify-center" />
                <TableColumnHeader v-if="permissions.volume" header="Volume" class="w-28 px-2 py-3.5 text-center text-sm font-semibold text-heading [&>div]:justify-center" />
                <TableColumnHeader v-if="permissions.gain" header="Gain" class="w-28 px-2 py-3.5 text-center text-sm font-semibold text-heading [&>div]:justify-center" />
                <TableColumnHeader header="Action" class="w-72 px-2 py-3.5 text-right text-sm font-semibold text-heading [&>div]:justify-end" />
            </template>

            <template #table-body>
                <tr v-for="member in data.members" :key="member.uuid || member.id">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-muted">
                        <Badge :text="member.is_moderator ? 'Moderator' : 'Participant'"
                            :background-color="member.is_moderator ? 'bg-purple-50 dark:bg-purple-900/40' : 'bg-surface-2'"
                            :text-color="member.is_moderator ? 'text-purple-700 dark:text-purple-300' : 'text-body'"
                            :ring-color="member.is_moderator ? 'ring-purple-600/20 dark:ring-purple-400/30' : 'ring-strong/20'" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm font-medium text-heading">
                        <span>{{ member.caller_id_name || '-' }}</span>
                        <Badge v-if="member.talking" text="Talking" background-color="bg-success-subtle"
                            text-color="text-success" ring-color="ring-success/20" class="ml-2" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="member.caller_id_number || '-'" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="formatDuration(member.join_time)" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="formatDuration(member.last_talking)" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="member.has_floor ? 'Yes' : 'No'" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <Badge :text="member.hand_raised ? 'Yes' : 'No'"
                            :background-color="member.hand_raised ? 'bg-warning-subtle' : 'bg-surface-2'"
                            :text-color="member.hand_raised ? 'text-warning' : 'text-body'"
                            :ring-color="member.hand_raised ? 'ring-warning/20' : 'ring-strong/20'" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-center text-sm text-muted">
                        <div class="flex justify-center gap-1">
                            <Badge :text="member.can_speak ? 'Speak' : 'Muted'"
                                :background-color="member.can_speak ? 'bg-success-subtle' : 'bg-danger-subtle'"
                                :text-color="member.can_speak ? 'text-success' : 'text-danger'"
                                :ring-color="member.can_speak ? 'ring-success/20' : 'ring-danger/20'" />
                            <Badge :text="member.can_hear ? 'Hear' : 'Deaf'"
                                :background-color="member.can_hear ? 'bg-success-subtle' : 'bg-danger-subtle'"
                                :text-color="member.can_hear ? 'text-success' : 'text-danger'"
                                :ring-color="member.can_hear ? 'ring-success/20' : 'ring-danger/20'" />
                            <Badge v-if="permissions.video && member.has_video" text="Video" background-color="bg-info-subtle"
                                text-color="text-info" ring-color="ring-info/20" />
                        </div>
                    </TableField>
                    <TableField v-if="permissions.energy" class="whitespace-nowrap px-2 py-2 text-center text-sm text-muted">
                        <StepperButtons @down="executeAction('energy', member, 'down')" @up="executeAction('energy', member, 'up')" />
                    </TableField>
                    <TableField v-if="permissions.volume" class="whitespace-nowrap px-2 py-2 text-center text-sm text-muted">
                        <StepperButtons @down="executeAction('volume_in', member, 'down')" @up="executeAction('volume_in', member, 'up')" />
                    </TableField>
                    <TableField v-if="permissions.gain" class="whitespace-nowrap px-2 py-2 text-center text-sm text-muted">
                        <StepperButtons @down="executeAction('volume_out', member, 'down')" @up="executeAction('volume_out', member, 'up')" />
                    </TableField>
                    <TableField class="w-72 whitespace-nowrap px-2 py-2 text-right text-sm text-muted">
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
                    <h3 class="mt-2 text-sm font-semibold text-heading">No members found</h3>
                    <p class="mt-1 text-sm text-muted">
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
                class: "relative inline-flex items-center rounded-l-md bg-surface px-2 py-1 text-sm font-semibold text-body ring-1 ring-inset ring-strong hover:bg-surface-2",
                onClick: () => emit("down"),
            }, "-"),
            h("button", {
                type: "button",
                class: "relative -ml-px inline-flex items-center rounded-r-md bg-surface px-2 py-1 text-sm font-semibold text-body ring-1 ring-inset ring-strong hover:bg-surface-2",
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
    @apply rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2;
}

.danger-button {
    @apply rounded-md bg-danger-solid px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-danger-solid-hover;
}
</style>
