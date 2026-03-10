<template>
    <MainLayout />

    <div class="mt-6 px-10">
        <!-- <h3 class="text-base font-semibold text-gray-900">Last 30 days</h3> -->
        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-4">
            <div v-for="item in stats" :key="item.name"
                class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">{{ item.name }}</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">{{ item.stat }}</dd>
            </div>
            <div
                class="flex overflow-hidden justify-center items-center rounded-lg bg-white px-4 py-5 shadow-sm sm:p-6">
                <!-- <dt class="truncate text-sm font-medium text-gray-500">Buton</dt> -->
                <button type="button" @click.prevent="handleNewFaxButtonClick()"
                    class="sm:hidden lg:block rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Send New Fax
                </button>

                <button type="button" @click.prevent="handleNewFaxButtonClick()"
                    class="hidden sm:block lg:hidden rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    New
                </button>

            </div>
        </dl>
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2">

        <div class="m-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Recent Outbound Faxes
                </template>

                <template #table-header>
                    <!-- Checkbox + From column -->
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">

                        <span class="pl-2">From</span>
                    </TableColumnHeader>

                    <!-- To column -->
                    <TableColumnHeader header="To" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- Date column -->
                    <TableColumnHeader header="Date"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                    <!-- Status column -->
                    <TableColumnHeader header="Status"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />


                </template>

                <template #table-body>
                    <tr v-for="row in recentOutboundFaxes.data" :key="row.fax_queue_uuid">
                        <!-- Checkbox + From -->
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center">
                                <div class="ml-2">
                                    {{ row.fax_caller_id_number_formatted }}
                                </div>
                            </div>
                        </TableField>

                        <!-- To -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            {{ row.fax_number_formatted }}
                        </TableField>

                        <!-- Date -->
                        <TableField class="px-2 py-2 text-sm text-gray-500">
                            {{ row.fax_date_formatted }}
                        </TableField>

                        <!-- Status -->
                        <TableField class="px-2 py-2 text-sm">
                            <Badge :text="row.fax_status"
                                :backgroundColor="determineColor(row.fax_status).backgroundColor"
                                :textColor="determineColor(row.fax_status).textColor"
                                :ringColor="determineColor(row.fax_status).ringColor" />
                        </TableField>


                    </tr>
                </template>




                <template #empty>
                    <!-- Conditional rendering for 'no records' message -->
                    <div v-if="!recentOutboundLoading && recentOutboundFaxes?.data?.length === 0"
                        class="text-center my-5 ">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">
                            Adjust your search and try again.
                        </p> -->
                    </div>
                </template>

                <template #loading>
                    <Loading :show="recentOutboundLoading" />
                </template>


            </DataTable>
            <div class="px-4 sm:px-6 lg:px-8"></div>
        </div>

        <div class="m-3 -mt-10 lg:mt-3">
            <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
                <template #title>Recent Inbound Faxes
                </template>

                <template #table-header>
                    <TableColumnHeader
                        class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                        <span class="pl-2">From</span>
                    </TableColumnHeader>
                    <TableColumnHeader header="To" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                    <TableColumnHeader header="Date"
                        class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                </template>

                <template #table-body>
                    <tr v-for="row in recentInboundFaxes.data" :key="row.fax_file_uuid">
                        <!-- From -->
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500">
                            {{ row.fax_caller_id_number_formatted }}
                        </TableField>

                        <!-- To (extension) -->
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                            {{ row.fax?.fax_caller_id_number_formatted ?? '-' }}
                        </TableField>

                        <!-- Date -->
                        <TableField class="px-2 py-2 text-sm text-gray-500">
                            {{ row.fax_date_formatted }}
                        </TableField>
                    </tr>
                </template>





                <template #empty>
                    <!-- Conditional rendering for 'no records' message -->
                    <div v-if="!recentOutboundLoading && recentInboundFaxes?.data?.length === 0"
                        class="text-center my-5 ">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">
                            Adjust your search and try again.
                        </p> -->
                    </div>
                </template>

                <template #loading>
                    <Loading :show="recentOutboundLoading" />
                </template>


            </DataTable>
            <div class="px-4 sm:px-6 lg:px-8"></div>
        </div>
    </div>

    <div class="m-3 -mt-10">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Fax Servers

            </template>

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
                <button v-if="page.props.auth.can.fax_server_create" type="button"
                    @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Create Fax Server
                </button>


            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>


            <template #table-header>
                <!-- Checkbox + Name column -->
                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">Name</span>
                </TableColumnHeader>

                <!-- Extension -->
                <TableColumnHeader header="Extension"
                    class="hidden lg:table-cell px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <!-- Caller ID -->
                <TableColumnHeader header="Caller ID"
                    class="hidden lg:table-cell px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <!-- Email column -->
                <TableColumnHeader header="Email" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <!-- Tools column -->
                <TableColumnHeader header="Tools" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <!-- Actions column -->
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
                <tr v-for="row in data.data" :key="row.fax_uuid">
                    <!-- Checkbox + Name -->
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500"
                        :text="row.ring_group_extension">
                        <div class="flex items-center">
                            <input v-if="row.fax_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.fax_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9"
                                :class="{ 'cursor-pointer hover:text-gray-900': page.props.auth.can.fax_server_update, }"
                                @click="page.props.auth.can.fax_server_update && handleEditButtonClick(row.fax_uuid)">
                                <span class="flex items-center">
                                    {{ row.fax_name }}
                                </span>
                            </div>
                        </div>
                    </TableField>

                    <!-- Extension -->
                    <TableField class="hidden lg:table-cell px-2 py-2 text-sm text-gray-500"
                        :text="row.fax_extension" />

                    <!-- Caller ID -->
                    <TableField class="hidden lg:table-cell px-2 py-2 text-sm text-gray-500"
                        :text="row.fax_caller_id_number_formatted" />

                    <TableField class="px-2 py-2 text-sm">
                        <span v-for="(email, i) in (row.fax_email || '').split(',').filter(e => e.trim())" :key="i">
                            <Badge :text="email.trim()" backgroundColor="bg-gray-100" textColor="text-gray-700"
                                ringColor="ring-gray-400/20" class="px-2 py-1 text-xs font-semibold" />
                        </span>
                    </TableField>


                    <!--  Tools -->
                    <TableField class="px-2 py-2 text-sm flex-col sm:flex-row gap-2">
                        <template v-if="page.props.auth.can.fax_send">
                            <button @click.prevent="handleNewFaxButtonClick()"
                                class="inline-flex items-center px-2 py-1 rounded text-gray-700 hover:bg-gray-100 transition text-xs font-medium"
                                title="New Fax">
                                <DocumentPlusIcon class="w-4 h-4 mr-1" />
                                <span class="text-nowrap">New Fax</span>
                            </button>
                        </template>

                        <template v-if="page.props.auth.can.fax_inbox_view">
                            <a :href="`/fax/${row.fax_uuid}/inbox`"
                                class="inline-flex items-center px-2 py-1 rounded text-gray-700 hover:bg-gray-100 transition text-xs font-medium"
                                title="Inbox">
                                <EnvelopeIcon class="w-4 h-4 mr-1" />
                                Inbox
                            </a>
                        </template>

                        <template v-if="page.props.auth.can.fax_sent_view">
                            <a :href="`/fax/${row.fax_uuid}/sent`"
                                class="inline-flex items-center px-2 py-1 rounded text-gray-700 hover:bg-gray-100 transition text-xs font-medium"
                                title="Sent">
                                <DocumentArrowUpIcon class="w-4 h-4 mr-1" />
                                Sent
                            </a>
                        </template>

                        <template v-if="page.props.auth.can.fax_log_view">
                            <a :href="`/fax/${row.fax_uuid}/log`"
                                class="inline-flex items-center px-2 py-1 rounded text-gray-700 hover:bg-gray-100 transition text-xs font-medium"
                                title="Logs">
                                <DocumentTextIcon class="w-4 h-4 mr-1" />
                                Logs
                            </a>
                        </template>
                    </TableField>

                    <!-- Action buttons -->
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">

                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="page.props.auth.can.fax_server_update" :content="'Edit'"
                                    position='TopCenter' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditButtonClick(row.fax_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="page.props.auth.can.fax_server_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.fax_uuid)"
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
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>

    <UpdateFaxServerForm :show="showUpdateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Update Fax Server - ' + (itemOptions?.item?.fax_name ?? 'loading')" @close="showUpdateModal = false"
        @error="handleErrorResponse" @success="showNotification" @refresh-data="handleSearchButtonClick" />

    <CreateFaxServerForm :show="showCreateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Create New Fax Server'" @close="showCreateModal = false" @error="handleErrorResponse"
        @success="showNotification" @refresh-data="handleSearchButtonClick" />

    <NewFaxForm :show="showNewFaxModal" :options="newFaxOptions" :loading="isModalLoading"
        :header="'Create New Fax Server'" @close="showNewFaxModal = false" @error="handleErrorResponse"
        @success="showNotification" @refresh-data="getRecentOutboundFaxes"/>


    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete the selected fax server(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, ref, onMounted } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import { router } from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import CreateFaxServerForm from "./components/forms/CreateFaxServerForm.vue";
import UpdateFaxServerForm from "./components/forms/UpdateFaxServerForm.vue";
import NewFaxForm from "./components/forms/NewFaxForm.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import { DocumentPlusIcon, EnvelopeIcon, DocumentArrowUpIcon, DocumentTextIcon } from "@heroicons/vue/24/outline";




const page = usePage()
const loading = ref(false)
const recentOutboundLoading = ref(false)
const recentInboundLoading = ref(false)
const isModalLoading = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showCreateModal = ref(false);
const showUpdateModal = ref(false);
const showNewFaxModal = ref(false);
const bulkUpdateModalTrigger = ref(false);
const confirmDeleteAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDeleteConfirmationModal = ref(false);

const props = defineProps({
    data: Object,
    stats: Object,
    routes: Object,
});

const filterData = ref({
    search: null,
});

// console.log(props.data);

const itemOptions = ref({})
const newFaxOptions = ref({})
const recentOutboundFaxes = ref({})
const recentInboundFaxes = ref({})


onMounted(() => {
    getRecentOutboundFaxes();
    getRecentInboundFaxes();
});

const getRecentOutboundFaxes = () => {
    recentOutboundLoading.value = true
    axios.get(props.routes.recent_outbound_route)
        .then((response) => {
            recentOutboundFaxes.value = response.data;
            // console.log(recentOutboundFaxes.value);

        }).catch((error) => {

            handleErrorResponse(error);
        }).finally(() => {
            recentOutboundLoading.value = false
        })
}

const getRecentInboundFaxes = () => {
    recentInboundLoading.value = true
    axios.get(props.routes.recent_inbound_route)
        .then((response) => {
            recentInboundFaxes.value = response.data;
            // console.log(recentInboundFaxes.value);

        }).catch((error) => {

            handleErrorResponse(error);
        }).finally(() => {
            recentInboundLoading.value = false
        })
}

const handleNewFaxButtonClick = (itemUuid) => {
    showNewFaxModal.value = true
    getNewFaxOptions(itemUuid);
}

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
    if (page.props.auth.can.fax_server_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});



const handleEditButtonClick = (itemUuid) => {
    showUpdateModal.value = true
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


const handleCreateButtonClick = () => {
    showCreateModal.value = true
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
            filter: {
                search: filterData.value.search,
            },
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
            filter: {
                search: filterData.value.search,
            },
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
    isModalLoading.value = true
    axios.post(props.routes.item_options, payload)
        .then((response) => {
            itemOptions.value = response.data;
            // console.log(itemOptions.value);

        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        }).finally(() => {
            isModalLoading.value = false
        })
}

const getNewFaxOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {}; // Conditionally add itemUuid to payload
    isModalLoading.value = true
    axios.post(props.routes.new_fax_options, payload)
        .then((response) => {
            newFaxOptions.value = response.data;
            // console.log(newFaxOptions.value);

        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        }).finally(() => {
            isModalLoading.value = false
        })
}


const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        console.log(error.response.data);
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
        selectedItems.value = props.data.data.map(item => item.fax_uuid);
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
    showUpdateModal.value = false;
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

const determineColor = (status) => {
    switch (status) {
        case 'sent':
            return {
                backgroundColor: 'bg-green-50',
                textColor: 'text-green-700',
                ringColor: 'ring-green-600/20'
            };
        case 'sending':
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


registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
