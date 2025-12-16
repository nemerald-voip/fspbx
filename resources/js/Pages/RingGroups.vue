<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Ring Groups</template>

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
                <button v-if="page.props.auth.can.ring_group_create" type="button" @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create
                </button>


            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>

                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">Name</span>
                </TableColumnHeader>

                <TableColumnHeader header="Extension" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Members" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="9">
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
                <tr v-for="row in data.data" :key="row.ring_group_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.ring_group_extension">
                        <div class="flex items-center">
                            <input v-if="row.ring_group_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.ring_group_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9"
                                :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.ring_group_update, }"
                                @click="page.props.auth.can.ring_group_update && handleEditButtonClick(row.ring_group_uuid)">
                                <span class="flex flex-col lg:flex-row items-start gap-2">
                                    {{ row.ring_group_name }}
                                    <Badge v-if="row.ring_group_forward_enabled == 'true'" :text="'FWD'"
                                            :backgroundColor="'bg-blue-100'" :textColor="'text-blue-800'"
                                            ringColor="ring-blue-400/20" class="px-2 py-1 text-xs" />
                                </span>
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.ring_group_extension" />

                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <div class="flex flex-wrap gap-1">
                            <ejs-tooltip
                                v-for="destination in row.destinations"
                                :key="destination.ring_group_destination_uuid"
                                :content="
                                    destination.suspended
                                        ? 'Suspended Extension'
                                        : destination.destination_enabled === false
                                            ? 'Disabled Extension'
                                            : 'Active Extension'
                                "
                                position="TopCenter"
                            >
                                <Badge
                                    :text="destination.destination_number"

                                    :backgroundColor="
                                        destination.suspended
                                            ? 'bg-red-50'                  // Use the palest red
                                            : destination.destination_enabled === false
                                                ? 'bg-gray-50'              // Use the palest gray
                                                : 'bg-blue-100'               // Soft blue for active
                                    "

                                    :textColor="
                                        destination.suspended
                                            ? 'text-red-500'               // Softer red text
                                            : destination.destination_enabled === false
                                                ? 'text-gray-500'           // Lighter gray text
                                                : 'text-blue-800'             // Darker blue for contrast
                                    "

                                    :ringColor="
                                        destination.suspended
                                            ? 'ring-red-200/20'
                                            : destination.destination_enabled === false
                                                ? 'ring-gray-300/20'
                                                : 'ring-blue-200/20'
                                    "

                                    :class="[
                                        'px-2 py-1 text-xs font-semibold',
                                        { 'opacity-75': destination.suspended || destination.destination_enabled === false }
                                    ]"
                                />
                            </ejs-tooltip>
                        </div>
                    </TableField>
                    
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="row.ring_group_description" />


                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">

                                <ejs-tooltip v-if="page.props.auth.can.ring_group_update" :content="'Edit'"
                                    position='TopCenter' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditButtonClick(row.ring_group_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="page.props.auth.can.ring_group_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.ring_group_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <AdvancedActionButton :actions="advancedActions"
                                    @advanced-action="(action) => handleAdvancedActionRequest(action, row.ring_group_uuid)" />
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

    <AddEditItemModal :customClass="'sm:max-w-6xl'" :show="showCreateModal" :header="'Create New Ring Group'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateRingGroupForm :options="itemOptions" @close="handleModalClose" @error="handleErrorResponse"  @success="showNotification" @refresh-data="handleSearchButtonClick" @open-edit-form="handleEditButtonClick"/>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-6xl'" :show="showEditModal"
        :header="'Update Ring Group Settings - ' + itemOptions?.ring_group?.ring_group_name"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdateRingGroupForm :options="itemOptions" @close="handleModalClose" @error="handleErrorResponse" @success="showNotification" @refresh-data="handleSearchButtonClick"/>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="bulkUpdateModalTrigger" :header="'Bulk Edit'" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <BulkUpdateDeviceForm :items="selectedItems" :options="itemOptions" :errors="formErrors"
                :is-submitting="bulkUpdateFormSubmiting" @submit="handleBulkUpdateRequest" @cancel="handleModalClose"
                @domain-selected="getItemOptions" />
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete the selected ring group(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import { router } from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkUpdateDeviceForm from "./components/forms/BulkUpdateDeviceForm.vue";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import CreateRingGroupForm from "./components/forms/CreateRingGroupForm.vue";
import UpdateRingGroupForm from "./components/forms/UpdateRingGroupForm.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import AdvancedActionButton from "./components/general/AdvancedActionButton.vue";


const page = usePage()
const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showCreateModal = ref(false);
const showEditModal = ref(false);
const bulkUpdateModalTrigger = ref(false);
const confirmDeleteAction = ref(null);
const bulkUpdateFormSubmiting = ref(null);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDeleteConfirmationModal = ref(false);

const props = defineProps({
    data: Object,
    routes: Object,
    itemData: Object,
});

const filterData = ref({
    search: null,
});

const itemOptions = ref({})

const advancedActions = computed(() => [
    {
        category: "Advanced",
        actions: [
            { id: 'duplicate', label: 'Duplicate', icon: 'DocumentDuplicateIcon' },
        ],
    },
]);

const handleAdvancedActionRequest = async (action, uuid) => {
    if (action === 'duplicate') {
        const url = props.routes.duplicate || '/ring-groups/duplicate';
        
        try {
            loading.value = true;
            const response = await axios.post(url, { uuid: uuid });
            showNotification('success', response.data.messages);
            handleSearchButtonClick(); 
        } catch (error) {
            handleErrorResponse(error);
        } finally {
            loading.value = false;
        }
    }
};

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        // {
        //     id: 'bulk_update',
        //     label: 'Edit',
        //     icon: 'PencilSquareIcon'
        // }
    ];

    // Conditionally add the delete action if permission is granted
    if (page.props.auth.can.ring_group_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

onMounted(() => {
});

const handleEditButtonClick = (itemUuid) => {
    showEditModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions(itemUuid);
}

const handleSingleItemDeleteRequest = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.bulk_delete, { items })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showDeleteConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        formErrors.value = [];
        getItemOptions();
        loadingModal.value = true
        bulkUpdateModalTrigger.value = true;
    }

}

const handleBulkUpdateRequest = (form) => {
    bulkUpdateFormSubmiting.value = true
    axios.post(`${props.routes.bulk_update}`, form)
        .then((response) => {
            bulkUpdateFormSubmiting.value = false;
            handleModalClose();
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            bulkUpdateFormSubmiting.value = false;
            handleFormErrorResponse(error);
        });
}

const handleCreateButtonClick = () => {
    showCreateModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions();
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



const handleSearchButtonClick = () => {
    loading.value = true;
    router.visit(props.routes.current_page, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: [
            "data",
        ],
        onSuccess: (page) => {
            loading.value = false;
            handleClearSelection();
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}


const renderRequestedPage = (url) => {
    loading.value = true;
    router.visit(url, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            loading.value = false;
        }
    });
};


const getItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {}; // Conditionally add itemUuid to payload

    axios.post(props.routes.item_options, payload)
        .then((response) => {
            loadingModal.value = false;
            itemOptions.value = response.data;
            // console.log(itemOptions.value);

        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        showNotification('error', { request: ["Session expired. Reload the page"] });
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
        formErrors.value = error.response.data.errors;
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }

}

const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

const handleSelectPageItems = () => {
    if (selectPageItems.value) {
        selectedItems.value = props.data.data.map(item => item.ring_group_uuid);
    } else {
        selectedItems.value = [];
    }
};



const handleClearSelection = () => {
    selectedItems.value = [],
        selectPageItems.value = false;
    selectAll.value = false;
}

const handleModalClose = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    showDeleteConfirmationModal.value = false;
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
