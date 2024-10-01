<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Sansay Active Calls</template>

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
                <button :class="[
                    isRefreshing
                        ? 'rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600'
                        : 'rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
                ]" @click="toggleRefreshing">
                    <Refresh :class="{ 'animate-spin': isRefreshing }" />
                </button>

                <button type="button" @click.prevent="handleRefreshButtonClick()"
                    class="rounded-md bg-indigo-600 ml-2 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Refresh
                </button>

            </template>

            <template #navigation>
                <Paginator v-if="data" :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from"
                    :to="data.to" :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                    :links="data.links" @pagination-change-page="renderRequestedPage" />
            </template>
            <template #table-header>
                <TableColumnHeader header="User"
                    class="flex whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-gray-900 items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <BulkActionButton :actions="bulkActions" @bulk-action="handleBulkActionRequest"
                        :has-selected-items="selectedItems.length > 0" />
                    <span class="pl-4 whitespace-nowrap">Orig TID</span>
                </TableColumnHeader>

                <TableColumnHeader header="Term TID"
                    class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="DNIS" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="ANI" class=" px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Orig IP"
                    class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader header="Term IP"
                    class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Inv Time"
                    class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Ans Time"
                    class="whitespace-nowrap px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Duration" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <!-- <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" /> -->
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
                <tr v-for="row in data.data" :key="row.callID">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 ">
                        <div class="flex items-center">
                            <input v-if="row.callID" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.callID" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-9">
                                {{ row.orig_tid }}
                            </div>

                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.term_tid" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.dnis" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ani" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.orig_ip" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.term_ip" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.inv_time" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.ans_time" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.duration_formatted" />

                    <!-- <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap">
                                <ejs-tooltip v-if="page.props.auth.can.device_destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.ssm_index)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>


                            </div>
                        </template>
                    </TableField> -->
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
import { computed, onMounted, ref, onUnmounted } from "vue";
import { usePage } from '@inertiajs/vue3'
import axios from 'axios';
import { router } from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import NotificationSimple from "./components/notifications/Simple.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import RestartIcon from "./components/icons/RestartIcon.vue";
import SyncIcon from "./components/icons/SyncIcon.vue";
import LinkOffIcon from "./components/icons/LinkOffIcon.vue";
import Notification from "./components/notifications/Notification.vue";
import ComboBox from "./components/general/ComboBox.vue"
import Refresh from "./components/icons/Refresh.vue"


const page = usePage()
const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmationModalDestroyPath = ref(null);
const confirmAction = ref(null);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const confirmationActionTrigger = ref(false);
const restartRequestNotificationSuccessTrigger = ref(false);
const restartRequestNotificationErrorTrigger = ref(false);
const bulkActionLabel = ref('');
const isRefreshing = ref(false)
const intervalId = ref(null);


const props = defineProps({
    data: Object,
    routes: Object,
});


const filterData = ref({
    search: null,
    server: 'server1',
});

const servers = [
    { value: 'server1', name: 'SBC1' },
    { value: 'server2', name: 'SBC2' },
]

const isDeleteConfirmationModalVisible = ref(false);
const isDeleteRequestProcessing = ref(false);
const confirmDeleteAction = ref(null);


// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [
        // {
        //     id: 'bulk_delete',
        //     label: 'Delete',
        //     icon: 'TrashIcon'
        // }

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

    const callsData = [
        {
            'ssm_index': id,
        }
    ];

    axios.post(props.routes.delete,
        {
            'callsData': callsData,
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
    const callsData = selectedItems.value.map(item => ({
        callID: item
    }));

    axios.post(props.routes.delete,
        {
            'callsData': callsData,
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

const handleRefresh = () => {
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
            handleClearSelection();
        },
        onError: (error) => {
            handleErrorResponse(error);
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
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

const toggleRefreshing = () => {
    isRefreshing.value = !isRefreshing.value;

    if (isRefreshing.value) {
        // Start calling handleSearchButtonClick every few seconds
        intervalId.value = setInterval(() => {
            handleRefresh();
        }, 7000); // Run every 5 seconds
    } else {
        // Stop the interval when refreshing is disabled
        clearInterval(intervalId.value);
        intervalId.value = null;
    }
};

// Make sure to clear the interval when the component is destroyed
onUnmounted(() => {
    if (intervalId.value) {
        clearInterval(intervalId.value);
    }
});


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
        selectedItems.value = props.data.data.map(item => item.callID);
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

const determineColor = (status) => {
    switch (status) {
        default:
            return {
                backgroundColor: 'bg-blue-50',
                textColor: 'text-blue-700',
                ringColor: 'ring-blue-600/20'
            };
    }
};

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";</style>
