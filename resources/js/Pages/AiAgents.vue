<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>AI Agents</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <button v-if="permissions.ai_agent_create" type="button"
                    @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>
                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <span class="pl-4">AI Agent</span>
                </TableColumnHeader>
                <TableColumnHeader header="Extension"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Description"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="6">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold ">{{ selectedItems.length }} </span> items are selected.
                        <button v-if="!selectAll && selectedItems.length != data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200  hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.ai_agent_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.agent_name">
                        <div class="flex items-center">
                            <input v-if="row.ai_agent_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.ai_agent_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-4"
                                :class="{ 'cursor-pointer hover:text-gray-900': permissions.ai_agent_update, }"
                                @click="permissions.ai_agent_update && handleEditRequest(row.ai_agent_uuid)">
                                {{ row.agent_name }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.agent_extension" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.description" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.agent_enabled">
                        <Badge v-if="row.agent_enabled == 'true'" text="Enabled" backgroundColor="bg-green-50"
                            textColor="text-green-700" ringColor="ring-green-600/20" />
                        <Badge v-else text="Disabled" backgroundColor="bg-rose-50" textColor="text-rose-700"
                            ringColor="ring-rose-600/20" />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="permissions.ai_agent_update" :content="'Edit'"
                                    position='TopCenter' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.ai_agent_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="permissions.ai_agent_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.ai_agent_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>
            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5 ">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Adjust your search and try again.
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>


    <CreateAiAgentForm :options="itemOptions" @refresh-data="handleSearchButtonClick"
        :show="showCreateModal" @close="showCreateModal = false" @created="handleCreatedAiAgent"
        :loading="loadingModal" @success="showNotification" />

    <UpdateAiAgentForm :options="itemOptions" @refresh-data="handleSearchButtonClick"
        :show="showUpdateModal" @close="showUpdateModal = false"
        :header="'Edit AI Agent - ' + itemOptions?.item?.agent_name" :loading="loadingModal"
        @success="showNotification" />

    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import MainLayout from "../Layouts/MainLayout.vue";
import UpdateAiAgentForm from "./components/forms/UpdateAiAgentForm.vue";
import CreateAiAgentForm from "./components/forms/CreateAiAgentForm.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";

const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const showCreateModal = ref(false);
const showUpdateModal = ref(false);
const confirmationModalTrigger = ref(false);
const confirmDeleteAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const data = ref({
    data: [],
    prev_page_url: null,
    next_page_url: null,
    from: 0,
    to: 0,
    total: 0,
    current_page: 1,
    last_page: 1,
    links: [],
});

const filterData = ref({
    search: null,
});

const itemOptions = ref({})

onMounted(() => {
    handleSearchButtonClick();
})

const handleSearchButtonClick = () => {
    getData()
};

const getData = (page = 1) => {
    loading.value = true;

    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page,
        }
    })
        .then((response) => {
            data.value = response.data;
        }).catch((error) => {
            handleErrorResponse(error);
        }).finally(() => {
            loading.value = false
        })
}

const bulkActions = computed(() => {
    const actions = [];
    if (props.permissions.ai_agent_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }
    return actions;
});

const handleCreateButtonClick = () => {
    showCreateModal.value = true
    loadingModal.value = true
    getItemOptions();
}

const handleEditRequest = (itemUuid) => {
    showUpdateModal.value = true
    loadingModal.value = true
    getItemOptions(itemUuid);
}

const handleCreatedAiAgent = async (itemUuid) => {
    showCreateModal.value = false;
    await getData();
    if (itemUuid) {
        handleEditRequest(itemUuid);
    }
};

const handleSingleItemDeleteRequest = (uuid) => {
    confirmationModalTrigger.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        confirmationModalTrigger.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
}

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.bulk_delete, { items })
        .then((response) => {
            handleClearSelection();
            handleModalClose();
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleSelectAll = () => {
    axios.post(props.routes.select_all, filterData._rawValue)
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);
        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    handleClearSelection();
    handleSearchButtonClick();
}

const renderRequestedPage = (url) => {
    loading.value = true;
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;
    getData(pageParam);
};

const getItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {};

    axios.post(props.routes.item_options, payload)
        .then((response) => {
            loadingModal.value = false;
            itemOptions.value = response.data;
        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        showNotification('error', { request: [error.request] });
    } else {
        showNotification('error', { request: [error.message] });
    }
}

const selectPageItems = computed({
    get() {
        return data.value.data.length > 0 &&
            data.value.data.every(item => selectedItems.value.includes(item.ai_agent_uuid));
    },
    set(value) {
        if (value) {
            const currentPageIds = data.value.data.map(item => item.ai_agent_uuid);
            const newSelection = new Set([...selectedItems.value, ...currentPageIds]);
            selectedItems.value = Array.from(newSelection);
        } else {
            const currentPageIds = data.value.data.map(item => item.ai_agent_uuid);
            selectedItems.value = selectedItems.value.filter(id => !currentPageIds.includes(id));
        }
    }
});

const handleClearSelection = () => {
    selectedItems.value = [],
    selectAll.value = false;
}

const handleModalClose = () => {
    showCreateModal.value = false;
    showUpdateModal.value = false;
    confirmationModalTrigger.value = false;
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');
</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
