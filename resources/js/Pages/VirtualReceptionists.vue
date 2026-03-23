<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Virtual Receptionists</template>

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
                <button v-if="permissions.virtual_receptionist_create" type="button"
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
                    <input type="checkbox" v-model="selectPageItems" @click.stop
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('ivr_menu_name')">
                        <span class="mr-2">Virtual Receptionist</span>
                        <ChevronUpIcon v-if="sortData.name === 'ivr_menu_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'ivr_menu_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('ivr_menu_extension')">
                        <span class="mr-2">Extension</span>
                        <ChevronUpIcon v-if="sortData.name === 'ivr_menu_extension' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'ivr_menu_extension' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('ivr_menu_description')">
                        <span class="mr-2">Description</span>
                        <ChevronUpIcon v-if="sortData.name === 'ivr_menu_description' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'ivr_menu_description' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('ivr_menu_enabled')">
                        <span class="mr-2">Status</span>
                        <ChevronUpIcon v-if="sortData.name === 'ivr_menu_enabled' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'ivr_menu_enabled' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
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
                <tr v-for="row in data.data" :key="row.ivr_menu_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.ivr_menu_name">
                        <div class="flex items-center">
                            <input v-if="row.ivr_menu_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.ivr_menu_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-4"
                                :class="{ 'cursor-pointer hover:text-gray-900': permissions.virtual_receptionist_update, }"
                                @click="permissions.virtual_receptionist_update && handleEditRequest(row.ivr_menu_uuid)">
                                {{ row.ivr_menu_name }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.ivr_menu_extension" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.ivr_menu_description" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ivr_menu_enabled">
                        <Badge v-if="row.ivr_menu_enabled == 'true'" text="Enabled" backgroundColor="bg-green-50"
                            textColor="text-green-700" ringColor="ring-green-600/20" />
                        <Badge v-else text="Disabled" backgroundColor="bg-rose-50" textColor="text-rose-700"
                            ringColor="ring-rose-600/20" />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="permissions.virtual_receptionist_update" :content="'Edit'"
                                    position='TopCenter' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditRequest(row.ivr_menu_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="permissions.virtual_receptionist_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.ivr_menu_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <AdvancedActionButton :actions="advancedActions"
                                    @advanced-action="(action) => handleAdvancedActionRequest(action, row.ivr_menu_uuid)" />
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>
            <template #empty>
                <!-- Conditional rendering for 'no records' message -->
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


    <CreateVirtualReceptionistForm :options="itemOptions" @refresh-data="handleSearchButtonClick"
        :show="showCreateModal" @close="showCreateModal = false" @created="handleCreatedVirtualReceptionist"
        :loading="loadingModal" @success="showNotification" />

    <UpdateVirtualReceptionistForm :options="itemOptions" @refresh-data="handleSearchButtonClick"
        :show="showUpdateModal" @close="showUpdateModal = false"
        :header="'Edit Virtual Receptionist Settings - ' + itemOptions?.item?.ivr_menu_name" :loading="loadingModal"
        @success="showNotification" />

    <DeleteConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmDeleteAction" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import MainLayout from "../Layouts/MainLayout.vue";
import UpdateVirtualReceptionistForm from "./components/forms/UpdateVirtualReceptionistForm.vue";
import CreateVirtualReceptionistForm from "./components/forms/CreateVirtualReceptionistForm.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import AdvancedActionButton from "./components/general/AdvancedActionButton.vue";

const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const showCreateModal = ref(false);
const showUpdateModal = ref(false);
const bulkUpdateModalTrigger = ref(false);
const confirmationModalTrigger = ref(false);
const confirmDeleteAction = ref(null);
const formErrors = ref(null);
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

const sortData = ref({
    name: 'ivr_menu_extension',
    order: 'asc',
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

    let sort = sortData.value.name;
    if (sortData.value.order === 'desc') {
        sort = `-${sort}`;
    }

    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page,
            sort,
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

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        sortData.value.name = column;
        sortData.value.order = 'asc';
    }

    getData(1);
}

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [];
    if (props.permissions.virtual_receptionist_destroy) {
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

const handleCreatedVirtualReceptionist = async (itemUuid) => {
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
    if (action === 'bulk_update') {
        formErrors.value = [];
        getItemOptions();
        loadingModal.value = true
        bulkUpdateModalTrigger.value = true;
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
    handleClearSelection(); // <--- ADD THIS
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

const advancedActions = computed(() => [
    {
        category: "Advanced",
        actions: [
            { id: 'duplicate', label: 'Duplicate', icon: 'DocumentDuplicateIcon' },
        ],
    },
]);

const handleAdvancedActionRequest = async (action, ivr_menu_uuid) => {
    if (!ivr_menu_uuid) {
        console.error('Missing IVR Menu UUID');
        return;
    }

    const actionUrls = {
        duplicate: props.routes.duplicate_virtual_receptionist,
    };

    const url = actionUrls[action];
    if (!url) return;

    try {
        const payload = { ivr_menu_uuid };
        const response = await axios.post(url, payload);
        showNotification('success', response.data.messages);
        handleSearchButtonClick();
        return response.data;
    } catch (error) {
        handleErrorResponse(error);
    }
};

const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        showNotification('error', { request: ["Session expired. Reload the page"] });
    } else if (error.response) {
        showNotification('error', error.response.data.errors || { request: [error.message] });
        formErrors.value = error.response.data.errors;
    } else if (error.request) {
        showNotification('error', { request: [error.request] });
    } else {
        showNotification('error', { request: [error.message] });
    }
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
        // Returns true if we have data and every item on the current page is in selectedItems
        return data.value.data.length > 0 &&
            data.value.data.every(item => selectedItems.value.includes(item.ivr_menu_uuid));
    },
    set(value) {
        if (value) {
            // Add all items on current page to selection (avoiding duplicates)
            const currentPageIds = data.value.data.map(item => item.ivr_menu_uuid);
            const newSelection = new Set([...selectedItems.value, ...currentPageIds]);
            selectedItems.value = Array.from(newSelection);
        } else {
            // Remove only the items on the current page from selection
            const currentPageIds = data.value.data.map(item => item.ivr_menu_uuid);
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
    bulkUpdateModalTrigger.value = false;
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
