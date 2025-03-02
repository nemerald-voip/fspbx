
<template>
    <MainLayout>

        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Wakeup Calls</template>

                <template #action>

                    <button v-if="page.props.auth.can.wakeup_calls_create" type="button"
                        @click.prevent="handleCreateButtonClick()"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Create
                    </button>

                    <button v-if="!filterData.showGlobal && page.props.auth.can.wakeup_calls_view_global" type="button"
                        @click.prevent="handleShowGlobal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show global
                    </button>

                    <button v-if="filterData.showGlobal && page.props.auth.can.wakeup_calls_view_global" type="button"
                        @click.prevent="handleShowLocal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Show local
                    </button>

                </template>

                <template #filters>
                    <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                        <input type="search" v-model="filterData.search" name="mobile-search-candidate"
                            id="mobile-search-candidate"
                            class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                            placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                        <input type="search" v-model="filterData.search" name="desktop-search-candidate"
                            id="desktop-search-candidate"
                            class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                            placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    </div>


                    <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                        <DatePicker :dateRange="filterData.dateRange" :timezone="filterData.timezone"
                            @update:date-range="handleUpdateDateRange" />
                    </div>


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
                        <span class="pl-4">Wake-Up Time</span>
                    </TableColumnHeader>

                    <TableColumnHeader header="Extension" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader v-if="filterData.showGlobal" header="Domain"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Daily Repeat"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Call Status"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Next Attempt"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>
                    <TableColumnHeader header="Retry Count"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    </TableColumnHeader>

                    <TableColumnHeader header="" class="px-2 py-3.5 text-sm font-semibold text-center text-gray-900" />

                </template>

                <template v-if="selectPageItems" v-slot:current-selection>
                    <td colspan="10">
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
                    <tr v-for="row in data.data" :key="row.uuid">
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center">
                                <input v-if="row.uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                    :value="row.uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                                <div class="ml-9">
                                    <span class="flex items-center">
                                        {{ row.wake_up_time_formatted }}
                                    </span>

                                </div>
                            </div>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.extension.name_formatted" />

                        <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.domain?.domain_description">
                            <ejs-tooltip :content="row.domain?.domain_name" position='TopLeft'
                                target="#domain_tooltip_target">
                                <div id="domain_tooltip_target">
                                    {{ row.domain?.domain_description }}
                                </div>
                            </ejs-tooltip>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <span v-if="row.recurring">Yes</span>
                            <span v-else>No</span>
                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            <Badge :text="row.status" :backgroundColor="determineColor(row.status).backgroundColor"
                                :textColor="determineColor(row.status).textColor"
                                :ringColor="determineColor(row.status).ringColor" />
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                            :text="row.next_attempt_at_formatted" />

                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.retry_count" />

                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap">
                                    <ejs-tooltip :content="'Edit wakeup call'" position='TopLeft'
                                        target="#edit_tooltip_target">
                                        <div id="edit_tooltip_target">
                                            <PencilSquareIcon @click="handleEditRequest(row.uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                        </div>
                                    </ejs-tooltip>

                                    <ejs-tooltip v-if="page.props.auth.can.wakeup_calls_delete" :content="'Delete'"
                                        position='TopCenter' target="#delete_tooltip_target">
                                        <div id="delete_tooltip_target">
                                            <TrashIcon @click="handleSingleItemDeleteRequest(row.destroy_route)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                        </div>
                                    </ejs-tooltip>

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
        </div>
    </MainLayout>

    <AddEditItemModal :show="showCreateModal" :header="'Create a New Wakeup Call'" :loading="loadingModal"
        :customClass="'sm:max-w-4xl'" @close="handleModalClose">
        <template #modal-body>
            <CreateWakeupCallForm :options="itemOptions" :errors="formErrors" :is-submitting="createFormSubmitting"
                @submit="handleCreateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="showEditModal" :header="'Update Wakeup Call Settings'" :loading="loadingModal"
        :customClass="'sm:max-w-4xl'" @close="handleModalClose">
        <template #modal-body>
            <UpdateWakeupCallForm :options="itemOptions" :errors="formErrors" :is-submitting="updateFormSubmitting"
                @submit="handleUpdateRequest" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>


    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction"     :header="'Confirm Deletion'"
    :text="'This action will permanently delete the selected wakup call(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { usePage } from '@inertiajs/vue3'
import { router } from "@inertiajs/vue3";
import MainLayout from '../Layouts/MainLayout.vue'
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import moment from 'moment-timezone';
import { registerLicense } from '@syncfusion/ej2-base';
import DatePicker from "./components/general/DatePicker.vue";
import Notification from "./components/notifications/Notification.vue";
import { MagnifyingGlassIcon, TrashIcon } from "@heroicons/vue/24/solid";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import RestartIcon from "./components/icons/RestartIcon.vue";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import Badge from "./components/general/Badge.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import { PencilSquareIcon } from "@heroicons/vue/24/solid/index.js";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import UpdateWakeupCallForm from "./components/forms/UpdateWakeupCallForm.vue";
import CreateWakeupCallForm from "./components/forms/CreateWakeupCallForm.vue";
import {
    startOfDay, endOfDay,
} from 'date-fns';
import Loading from "./components/general/Loading.vue";

const page = usePage()
const today = new Date();
const loading = ref(false)
const loadingModal = ref(false)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const selectAll = ref(false);
const showRetryConfirmationModal = ref(false);
const confirmRetryAction = ref(null);
const showEditModal = ref(false);
const showCreateModal = ref(false);
const formErrors = ref(null);
const updateFormSubmitting = ref(null);
const createFormSubmitting = ref(null);
const showDeleteConfirmationModal = ref(false);
const confirmDeleteAction = ref(null);

const props = defineProps({
    data: Object,
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    statusOptions: Object,
});

const itemOptions = ref({})

// onMounted(() => {
//     //request list of entities
//     // getEntities();
//     if (props.data.data.length === 0) {
//         handleSearchButtonClick();
//     }
// })

const filterData = ref({
    search: props.search,
    showGlobal: false,
    dateRange: [moment.tz(props.startPeriod, props.timezone).startOf('day').format(), moment.tz(props.endPeriod, props.timezone).endOf('day').format()],
    // dateRange: ['2024-07-01T00:00:00', '2024-07-01T23:59:59'],
    timezone: props.timezone,

});

const handleCreateButtonClick = () => {
    showCreateModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions();
}

const handleCreateRequest = (form) => {
    createFormSubmitting.value = true;
    formErrors.value = null;

    axios.post(props.routes.store, form)
        .then((response) => {
            createFormSubmitting.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            createFormSubmitting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
        });

};

const handleEditRequest = (itemUuid) => {
    showEditModal.value = true
    formErrors.value = null;
    loadingModal.value = true;
    getItemOptions(itemUuid);
}

const handleUpdateRequest = (form) => {
    updateFormSubmitting.value = true;
    formErrors.value = null;

    axios.put(itemOptions.value.routes.update_route, form)
        .then((response) => {
            updateFormSubmitting.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
            handleModalClose();
            handleClearSelection();
        }).catch((error) => {
            updateFormSubmitting.value = false;
            handleClearSelection();
            handleFormErrorResponse(error);
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

const handleSingleItemDeleteRequest = (url) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(url);
}

const executeSingleDelete = (url) => {
    router.delete(url, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: (page) => {
            if (page.props.flash.error) {
                showNotification('error', page.props.flash.error);
            }
            if (page.props.flash.message) {
                showNotification('success', page.props.flash.message);
            }
            showDeleteConfirmationModal.value = false;
        },
        onFinish: () => {
            showDeleteConfirmationModal.value = false;
        },
        onError: (errors) => {
            console.log(errors);
        },
    });
}

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    handleSearchButtonClick();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    handleSearchButtonClick();
}

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
        },
        onError: (error) => {
            loading.value = false;
            handleErrorResponse(error);
        }

    });
};

const handleFiltersReset = () => {
    filterData.value.dateRange = [moment.tz(props.startPeriod, props.timezone).startOf('day').format(), moment.tz(props.endPeriod, props.timezone).endOf('day').format()],
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

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        {
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        }
    ];

    return actions;
});

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showDeleteConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }

}

const executeBulkDelete = () => {
    axios.post(`${props.routes.bulk_delete}`, { items: selectedItems.value })
        .then((response) => {
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


const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
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
        selectedItems.value = props.data.data.map(item => item.fax_queue_uuid);
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
    showRetryConfirmationModal.value = false;
    showEditModal.value = false;
    showCreateModal.value = false;
    showDeleteConfirmationModal.value = false;
}

const determineColor = (status) => {
    switch (status) {
        case 'completed':
            return {
                backgroundColor: 'bg-green-50',
                textColor: 'text-green-700',
                ringColor: 'ring-green-600/20'
            };
        case 'scheduled':
            return {
                backgroundColor: 'bg-blue-50',
                textColor: 'text-blue-700',
                ringColor: 'ring-blue-600/20'
            };
        case 'trying':
            return {
                backgroundColor: 'bg-cyan-50',
                textColor: 'text-cyan-700',
                ringColor: 'ring-cyan-600/20'
            };
        case 'failed':
            return {
                backgroundColor: 'bg-rose-50',
                textColor: 'text-rose-700',
                ringColor: 'ring-rose-600/20'
            };
        default:
            return {
                backgroundColor: 'bg-yellow-50',
                textColor: 'text-yellow-700',
                ringColor: 'ring-yellow-600/20'
            };
    }
};

const handleFormErrorResponse = (error) => {
    if (error.request?.status === 419) {
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

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');


</script>


<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";</style>