<template>
    <MainLayout />
    <div class="m-3">
        <DataTable @search-action="getData(1)" @reset-filters="resetFilters">
            <template #title>{{ $t('AI Agents') }}</template>
            <template #subtitle>{{ $t('Manage AI voice agents as extension-based call destinations.') }}</template>

            <template #filters>
                <div v-if="toolStatus.failed > 0"
                    class="mb-3 flex w-full items-start gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 flex-none" aria-hidden="true" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $t('Tool synchronization needs attention') }}</p>
                        <p class="mt-0.5">{{ toolFailureText }}</p>
                    </div>
                    <button v-if="permissions.sync_tools" type="button" :disabled="syncingTools" @click="syncTools"
                        class="flex-none rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-red-800 shadow-sm ring-1 ring-inset ring-red-300 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ syncingTools ? $t('Queueing...') : $t('Retry Sync') }}
                    </button>
                </div>
                <div class="relative mb-2 min-w-64 sm:mr-4">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-2 h-5 w-5 text-gray-400" />
                    <input v-model="search" type="search" :placeholder="$t('Search')" @keydown.enter="getData(1)"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600" />
                </div>
            </template>

            <template #action>
                <div class="flex items-center gap-2">
                    <span v-if="toolStatus.total > 0 && toolStatus.failed === 0" :class="toolCompactStatusClasses"
                        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset">
                        <CheckCircleIcon v-if="toolsAreCurrent" class="h-4 w-4" aria-hidden="true" />
                        <ExclamationTriangleIcon v-else-if="toolConfigurationRequired" class="h-4 w-4" aria-hidden="true" />
                        <ArrowPathIcon v-else class="h-4 w-4 motion-safe:animate-spin" aria-hidden="true" />
                        {{ toolCompactStatusText }}
                    </span>
                    <button v-if="permissions.sync_tools && toolStatus.failed === 0" type="button" :disabled="syncingTools" @click="syncTools"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-600 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ syncingTools ? $t('Queueing...') : $t('Sync Tools') }}
                    </button>
                    <button v-if="permissions.manage_integration" type="button" @click="openIntegration"
                        class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        {{ $t('Provider Settings') }}
                    </button>
                    <button v-if="permissions.create" type="button" @click="openAgent()"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ $t('Create') }}
                    </button>
                </div>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>

            <template #table-header>
                <TableColumnHeader :header="$t('Name')" class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Extension')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Provider Agents')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Recording')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Status')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader :header="$t('Enabled')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.ai_agent_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-700">
                        <button v-if="permissions.update" type="button" class="text-left font-medium text-gray-900 hover:text-indigo-600" @click="openAgent(row.ai_agent_uuid)">{{ row.name }}</button>
                        <div v-else class="font-medium text-gray-900">{{ row.name }}</div>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 font-mono text-sm text-gray-700" :text="row.extension" />
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <div class="max-w-56 truncate" :title="row.inbound_agent_id">{{ $t('Inbound') }}: {{ row.inbound_agent_name || row.inbound_agent_id }}</div>
                        <div class="max-w-56 truncate text-xs" :title="row.outbound_agent_id">{{ $t('Outbound') }}: {{ row.outbound_agent_name || row.outbound_agent_id || $t('Not assigned') }}</div>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="recordingLabel(row.recording_policy)" />
                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <Badge :text="statusLabel(row.provisioning_status)" v-bind="statusBadge(row.provisioning_status)" />
                        <p v-if="row.provisioning_error" class="mt-1 max-w-64 break-words text-xs text-red-600">{{ row.provisioning_error }}</p>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <button v-if="permissions.update && row.provisioning_status === 'synced'" type="button" @click="action(row, 'toggle')">
                            <Badge :text="row.enabled ? $t('On') : $t('Off')" v-bind="enabledBadge(row.enabled)" />
                        </button>
                        <Badge v-else :text="row.enabled ? $t('On') : $t('Off')" v-bind="enabledBadge(row.enabled)" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end">
                                <button v-if="permissions.update && row.provisioning_status === 'failed'" type="button" class="rounded px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50" @click="action(row, 'retry')">{{ $t('Retry') }}</button>
                                <div v-if="permissions.update" class="group relative inline-flex">
                                    <button :id="'refresh_agent_' + row.ai_agent_uuid" type="button" class="action-button"
                                        :aria-label="$t('Refresh from provider')"
                                        :aria-describedby="'refresh_agent_tooltip_' + row.ai_agent_uuid"
                                        @click="action(row, 'refresh')">
                                        <ArrowPathIcon class="h-5 w-5" aria-hidden="true" />
                                    </button>
                                    <div :id="'refresh_agent_tooltip_' + row.ai_agent_uuid" role="tooltip"
                                        class="action-tooltip">
                                        <div class="action-tooltip-content">
                                            {{ $t('Refresh from provider') }}
                                            <div class="action-tooltip-arrow"></div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="permissions.update" class="group relative inline-flex">
                                    <button :id="'edit_agent_' + row.ai_agent_uuid" type="button" class="action-button"
                                        :aria-label="$t('Edit')" :aria-describedby="'edit_agent_tooltip_' + row.ai_agent_uuid"
                                        @click="openAgent(row.ai_agent_uuid)">
                                        <PencilSquareIcon class="h-5 w-5" aria-hidden="true" />
                                    </button>
                                    <div :id="'edit_agent_tooltip_' + row.ai_agent_uuid" role="tooltip"
                                        class="action-tooltip">
                                        <div class="action-tooltip-content">
                                            {{ $t('Edit') }}
                                            <div class="action-tooltip-arrow"></div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="permissions.destroy" class="group relative inline-flex">
                                    <button :id="'delete_agent_' + row.ai_agent_uuid" type="button" class="action-button"
                                        :aria-label="$t('Delete')" :aria-describedby="'delete_agent_tooltip_' + row.ai_agent_uuid"
                                        @click="confirmDelete(row)">
                                        <TrashIcon class="h-5 w-5" aria-hidden="true" />
                                    </button>
                                    <div :id="'delete_agent_tooltip_' + row.ai_agent_uuid" role="tooltip"
                                        class="action-tooltip">
                                        <div class="action-tooltip-content">
                                            {{ $t('Delete') }}
                                            <div class="action-tooltip-arrow"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="py-10 text-center">
                    <CpuChipIcon class="mx-auto h-10 w-10 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t('No AI agents') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $t('Configure an AI provider, then bind your first voice agent to an extension.') }}</p>
                </div>
            </template>
            <template #loading><Loading :show="loading" /></template>
            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
    </div>

    <AiAgentForm :show="agentForm.show" :loading="agentForm.loading" :mode="agentForm.mode" :options="agentForm.options"
        :permissions="permissions"
        :provider-agents="providerAgents" @provider-change="loadProviderAgents" @close="closeAgent" @error="handleError"
        @success="showNotification" @refresh-data="getData(data.current_page)" />
    <AiProviderIntegrationForm :show="integrationForm.show" :loading="integrationForm.loading" :integration="integrationForm.data"
        :update-route="routes.integration_update" :test-route="routes.integration_test" @close="integrationForm.show = false"
        @error="handleError" @success="integrationSaved" @test-success="showNotification('success', $event)" />
    <ConfirmationModal :show="confirmation.show" @close="confirmation.show = false" @confirm="deleteAgent"
        :header="$t('Delete AI Agent')" :text="$t('The provider resource will be deleted first. If the provider rejects the request, the local AI agent remains unchanged.')"
        :confirm-button-label="$t('Delete')" :cancel-button-label="$t('Cancel')" />
    <Notification :show="notification.show" :type="notification.type" :messages="notification.messages" @update:show="notification.show = false" />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Badge from "@generalComponents/Badge.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import AiAgentForm from "./components/forms/AiAgentForm.vue";
import AiProviderIntegrationForm from "./components/forms/AiProviderIntegrationForm.vue";
import { ArrowPathIcon, CheckCircleIcon, CpuChipIcon, ExclamationTriangleIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/solid";
import { trans } from "@i18n";

const props = defineProps({ routes: Object, permissions: Object });
const routes = props.routes;
const permissions = props.permissions;
const loading = ref(false);
const search = ref("");
const providerAgents = ref([]);
const data = ref({ data: [], current_page: 1, last_page: 1, links: [], from: 0, to: 0, total: 0 });
const notification = reactive({ show: false, type: null, messages: null });
const confirmation = reactive({ show: false, item: null });
const agentForm = reactive({ show: false, loading: false, mode: "create", options: { item: {}, domains: [], providers: [], routes: {} } });
const integrationForm = reactive({ show: false, loading: false, data: {} });
const syncingTools = ref(false);
const toolStatus = ref({ total: 0, pending: 0, failed: 0, syncing: 0, configuration_required: 0, current: 0 });
let toolStatusTimer = null;

onMounted(() => { getData(); getToolStatus(); });
onBeforeUnmount(() => { if (toolStatusTimer) window.clearTimeout(toolStatusTimer); });

const remainingToolSyncs = computed(() => toolStatus.value.pending + toolStatus.value.syncing);
const toolConfigurationRequired = computed(() => toolStatus.value.configuration_required > 0);
const toolsAreCurrent = computed(() => !syncingTools.value && remainingToolSyncs.value === 0 && !toolConfigurationRequired.value);
const toolCompactStatusText = computed(() => {
    if (syncingTools.value && remainingToolSyncs.value === 0) return trans("Queueing tool sync...");
    if (remainingToolSyncs.value > 0) return trans("Syncing tools (:count remaining)", { count: remainingToolSyncs.value });
    if (toolConfigurationRequired.value) return trans("Email recipient required");
    return trans("Tools current");
});
const toolCompactStatusClasses = computed(() => toolsAreCurrent.value
    ? "bg-green-50 text-green-700 ring-green-600/20"
    : "bg-amber-50 text-amber-700 ring-amber-600/20");
const toolFailureText = computed(() => trans(":count provider agent tool syncs failed. Try syncing again or check the queue logs.", { count: toolStatus.value.failed }));

const getData = async (page = 1) => {
    loading.value = true;
    try {
        const response = await axios.get(routes.data, { params: { page, filter: { search: search.value } } });
        data.value = response.data;
    } catch (error) { handleError(error); } finally { loading.value = false; }
};
const resetFilters = () => { search.value = ""; getData(1); };
const renderRequestedPage = (url) => { if (url) getData(new URL(url, window.location.origin).searchParams.get("page") || 1); };
const getToolStatus = async (pollAttempts = 0) => {
    try {
        toolStatus.value = (await axios.get(routes.tool_status)).data.tools;
        if (pollAttempts > 0) {
            toolStatusTimer = window.setTimeout(() => getToolStatus(pollAttempts - 1), 5000);
        }
    } catch (error) {
        if (pollAttempts === 0) handleError(error);
    }
};
const syncTools = async () => {
    syncingTools.value = true;
    if (toolStatusTimer) window.clearTimeout(toolStatusTimer);
    try {
        const response = await axios.post(routes.sync_tools, { force: true });
        showNotification("success", response.data.messages);
        await getToolStatus();
        toolStatusTimer = window.setTimeout(() => getToolStatus(12), 3000);
    } catch (error) {
        handleError(error);
    } finally {
        syncingTools.value = false;
    }
};

const openAgent = async (uuid = null) => {
    agentForm.show = true;
    agentForm.loading = true;
    agentForm.mode = uuid ? "update" : "create";
    try {
        const options = await axios.post(routes.item_options, uuid ? { item_uuid: uuid } : {});
        agentForm.options = options.data;
        await loadProviderAgents(options.data.item?.provider || options.data.providers?.[0]?.value);
    } catch (error) { closeAgent(); handleError(error); } finally { agentForm.loading = false; }
};
const closeAgent = () => { agentForm.show = false; agentForm.options = { item: {}, domains: [], providers: [], routes: {} }; providerAgents.value = []; };
const loadProviderAgents = async (selection) => {
    const provider = selection?.value || selection;
    if (!provider) { providerAgents.value = []; return; }
    try { providerAgents.value = (await axios.get(routes.provider_agents, { params: { provider } })).data.agents; }
    catch (error) { providerAgents.value = []; handleError(error); }
};

const openIntegration = async () => {
    integrationForm.show = true;
    integrationForm.loading = true;
    try { integrationForm.data = (await axios.get(routes.integration)).data.integration; }
    catch (error) { integrationForm.show = false; handleError(error); }
    finally { integrationForm.loading = false; }
};
const integrationSaved = (response) => {
    integrationForm.data = response.data.integration;
    showNotification("success", response.data.messages);
    getToolStatus(12);
};
const action = async (row, name) => {
    try {
        const response = await axios.post(`${routes.store}/${row.ai_agent_uuid}/${name}`);
        showNotification("success", response.data.messages);
        getData(data.value.current_page);
    } catch (error) { handleError(error); }
};
const confirmDelete = (row) => { confirmation.item = row; confirmation.show = true; };
const deleteAgent = async () => {
    try {
        const response = await axios.delete(`${routes.store}/${confirmation.item.ai_agent_uuid}`);
        confirmation.show = false;
        showNotification("success", response.data.messages);
        getData(data.value.current_page);
    } catch (error) { confirmation.show = false; handleError(error); }
};
const showNotification = (type, messages) => Object.assign(notification, { show: true, type, messages });
const handleError = (error) => showNotification("error", error.response?.data?.errors || error.response?.data?.messages || { request: [error.message] });
const recordingLabel = (policy) => policy === "always" ? trans("Always record") : trans("Use route settings");
const statusLabel = (status) => ({ provisioning: trans("Provisioning"), synced: trans("Synced"), failed: trans("Failed") }[status] || status);
const statusBadge = (status) => status === "synced"
    ? { backgroundColor: "bg-green-50", textColor: "text-green-700", ringColor: "ring-green-600/20" }
    : status === "failed"
        ? { backgroundColor: "bg-red-50", textColor: "text-red-700", ringColor: "ring-red-600/20" }
        : { backgroundColor: "bg-amber-50", textColor: "text-amber-700", ringColor: "ring-amber-600/20" };
const enabledBadge = (enabled) => enabled
    ? { backgroundColor: "bg-green-50", textColor: "text-green-700", ringColor: "ring-green-600/20" }
    : { backgroundColor: "bg-gray-50", textColor: "text-gray-700", ringColor: "ring-gray-600/20" };
</script>

<style scoped>
.action-button { @apply inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-1 active:bg-gray-300; }
.action-tooltip { @apply pointer-events-none invisible absolute bottom-full left-1/2 z-50 -translate-x-1/2 pb-2 opacity-0 transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100; }
.action-tooltip-content { @apply relative whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs font-medium text-white shadow-lg; }
.action-tooltip-arrow { @apply absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1/2 rotate-45 bg-gray-900; }
</style>
