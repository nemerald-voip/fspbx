<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Sansay Registrations</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                        id="mobile-search-candidate"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        placeholder="Search" />
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                        id="desktop-search-candidate"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        placeholder="Search" />


                </div>
                <div class="relative min-w-64 mb-2 shrink-0 sm:mr-4">
                    <ComboBox :options="servers" :selectedItem="filterData.server" :placeholder="'Select SBC'"
                        @update:model-value="handleUpdateServerFilter" />
                </div>
            </template>

            <template #action>
                <button type="button" @click.prevent="handleRefreshButtonClick()"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Refresh
                </button>

                <button v-if="!showGlobal" type="button"
                    @click.prevent="handleShowGlobal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>

                <button v-if="showGlobal" type="button"
                    @click.prevent="handleShowLocal()"
                    class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show local
                </button>

            </template>

            <template #navigation>
                <Paginator v-if="data" :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from"
                    :to="data.to" :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                    :links="data.links" @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>
                <TableColumnHeader header="User" field="username" :sortable="true" :sortedField="filterData.sortedField"
                    :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems" @click.stop
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest" @click.stop
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4">User</span>
                </TableColumnHeader>

                <TableColumnHeader header="Host" field="userDomain" :sortable="true" :sortedField="filterData.sortedField"
                    :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />


                <!-- <TableColumnHeader header="ID" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <!-- <TableColumnHeader header="Contact" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <TableColumnHeader header="State" field="states" :sortable="true" :sortedField="filterData.sortedField"
                    :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="External IP" field="userIp" :sortable="true"
                    :sortedField="filterData.sortedField" :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class=" whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="Source Port" field="userPort" :sortable="true"
                    :sortedField="filterData.sortedField" :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <!-- <TableColumnHeader header="NAT" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <!-- <TableColumnHeader header="Auth TID" class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <!-- <TableColumnHeader header="SPID" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
                <TableColumnHeader header="Protocol" field="protocol" :sortable="true" :sortedField="filterData.sortedField"
                    :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="Created" field="createTime" :sortable="true"
                    :sortedField="filterData.sortedField" :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Expiration" field="expiration" :sortable="true"
                    :sortedField="filterData.sortedField" :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="User-Agent" field="agent" :sortable="true"
                    :sortedField="filterData.sortedField" :sortOrder="filterData.sortOrder" @sort="handleSort"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Action" :sortable="false"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="14">
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
                <tr v-for="row in data.data" :key="row.contact">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 ">
                        <div class="flex items-center">
                            <input v-if="row.id" v-model="selectedItems" type="checkbox" name="action_box[]" :value="row.id"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9">
                                {{ row.username }}
                            </div>

                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.userDomain" />

                    <!-- <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.id" /> -->
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.states" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.userIp" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.userPort" />
                    <!-- <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.nat" /> -->
                    <!-- <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.trunkId" /> -->
                    <!-- <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.spid" /> -->
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.protocol" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.createTime" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.expiration" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.agent" />

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap">
                                <ejs-tooltip v-if="page.props.auth.can.device_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.id)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>


                                <!-- <div id="tooltip-no-arrow-sync" role="tooltip" 
                                    class="inline-block absolute invisible text-xs z-10 py-1 px-2 font-medium text-white rounded-sm shadow-sm opacity-0 tooltip dark:bg-gray-600 delay-150" >
                                tooltip
                                </div> -->


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

    <ConfirmationModal :show="isDeleteConfirmationModalVisible" @close="isDeleteConfirmationModalVisible = false"
        @confirm="confirmDeleteAction" :header="'Are you sure?'" :text="'Confirm deleting selected item(s).'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" :loading="isDeleteRequestProcessing" />


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
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import ComboBox from "./components/general/ComboBox.vue"


const page = usePage()
const loading = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

const props = defineProps({
    data: Object,
    routes: Object,
});


const filterData = ref({
    search: null,
    server: 'server1',
    sortedField: null,
    sortOrder: null,
    showGlobal: false,
});

const servers = [
    { value: 'server1', name: 'SBC1' },
    { value: 'server2', name: 'SBC2' },
    { value: 'server3', name: 'SBC3' },
    { value: 'server3', name: 'SBC4' },
]

const showGlobal = ref(false);

const isDeleteConfirmationModalVisible = ref(false);
const isDeleteRequestProcessing = ref(false);
const confirmDeleteAction = ref(null);


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

onMounted(() => {
    // console.log(props.data);
    if (props.data.data.length === 0) {
        handleSearchButtonClick();
    }

});


const handleSelectAll = () => {
    axios.post(props.routes.select_all,
        {
            'filterData': filterData._rawValue
        },
    )
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};


const handleSingleItemDeleteRequest = (id) => {
    isDeleteConfirmationModalVisible.value = true;
    confirmDeleteAction.value = () => executeSingleDelete(id);
}

const executeSingleDelete = (id) => {
    isDeleteRequestProcessing.value = true;

    const statsData = [
        {
            // 'username': username,
            // 'userDomain': userDomain,
            'id': id,
            // 'userIp': userIp,
            // 'trunkId': trunkId,
        }
    ];

    axios.post(props.routes.delete,
        {
            'statsData': statsData,
            'filterData': filterData._rawValue
        },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            isDeleteRequestProcessing.value = false;
            handleSearchButtonClick();
        }).catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
            isDeleteRequestProcessing.value = false;
        });


}


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        isDeleteConfirmationModalVisible.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
}


const executeBulkDelete = () => {
    isDeleteRequestProcessing.value = true;
    const statsData = selectedItems.value.map(item => ({
        id: item
    }));

    axios.post(props.routes.delete,
        {
            'statsData': statsData,
            'filterData': filterData._rawValue

        })
        .then((response) => {
            handleModalClose();
            isDeleteRequestProcessing.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleClearSelection();
            handleModalClose();
            isDeleteRequestProcessing.value = false;
            handleErrorResponse(error);
        });
}


const handleRefreshButtonClick = () => {
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
            handleClearSelection();
        },
        onError: (error) => {
            loading.value = false;
            handleErrorResponse(error);
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    filterData.value.server = 'server1';
    filterData.value.sortedField = null;
    filterData.value.sortOrder = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const handleUpdateServerFilter = (newSelectedItem) => {
    filterData.value.server = newSelectedItem.value;
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
        selectedItems.value = props.data.data.map(item => item.id);
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
    isDeleteConfirmationModalVisible.value = false;
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


const handleSort = ({ field, order }) => {
    filterData.value.sortedField = field;
    filterData.value.sortOrder = order;
    // Fetch the data with the updated sort field and order
    handleSearchButtonClick();
};

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    showGlobal.value = true;
    handleSearchButtonClick();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
    handleSearchButtonClick();
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
