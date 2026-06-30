<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Email Queue</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                    </div>

                    <input type="text" v-model="filterData.search"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
                        placeholder="Search" />
                </div>

                <div class="relative min-w-48 mb-2 shrink-0 sm:mr-4">
                    <SelectBox :options="props.statusOptions" :selectedItem="filterData.status" :placeholder="'Status'"
                        @update:model-value="handleUpdateStatusFilter" />
                </div>

                <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                    <DatePicker :dateRange="filterData.dateRange" :timezone="props.timezone"
                        @update:date-range="handleUpdateDateRange" />
                </div>
            </template>

            <template #action>
                <button v-if="!filterData.showGlobal" type="button" @click.prevent="handleShowGlobal"
                    class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Show global
                </button>

                <button v-if="filterData.showGlobal" type="button" @click.prevent="handleShowLocal"
                    class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Show local
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>

            <template #table-header>
                <TableColumnHeader class="whitespace-nowrap px-4 py-1.5 text-left text-sm font-semibold text-heading">
                    <div class="flex items-center justify-start">
                        <input type="checkbox" v-model="selectPageItems"
                            class="h-4 w-4 rounded border-strong text-accent-fg">
                        <span class="pl-4">From</span>
                    </div>
                </TableColumnHeader>

                <TableColumnHeader header="To" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader v-if="filterData.showGlobal" header="Domain"
                    class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Subject" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Host" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Date" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="" class="px-2 py-3.5" />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td :colspan="filterData.showGlobal ? 8 : 7">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                        <button v-if="!selectAll && selectedItems.length != data.total"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.email_queue_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-muted">
                        <div class="flex items-center">
                            <input v-if="row.email_queue_uuid" v-model="selectedItems" type="checkbox"
                                :value="row.email_queue_uuid" class="h-4 w-4 rounded border-strong text-accent-fg">
                            <div class="ml-9">
                                {{ row.email_from }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="px-2 py-2 text-sm text-muted" :text="row.email_to" />

                    <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-muted"
                        :text="row.domain?.domain_description">
                        <EjsTooltip :content="row.domain?.domain_name" position="TopLeft"
                            :target="'#domain_tooltip_' + row.email_queue_uuid">
                            <div :id="'domain_tooltip_' + row.email_queue_uuid">
                                {{ row.domain?.domain_description }}
                            </div>
                        </EjsTooltip>
                    </TableField>

                    <TableField class="px-2 py-2 text-sm text-muted" :text="row.email_subject" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="row.hostname" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted"
                        :text="row.email_date_formatted" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <Badge :text="row.email_status"
                            :backgroundColor="determineColor(row.email_status).backgroundColor"
                            :textColor="determineColor(row.email_status).textColor"
                            :ringColor="determineColor(row.email_status).ringColor" />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-muted">
                        <div class="flex items-center whitespace-nowrap justify-end">
                            <EjsTooltip v-if="props.permissions.email_queue_update"
                                :content="row.email_status === 'blank' ? 'Mark as sent' : 'Reset status'"
                                position="TopCenter" :target="'#status_tooltip_' + row.email_queue_uuid">
                                <div :id="'status_tooltip_' + row.email_queue_uuid">
                                    <button
                                        @click="executeStatusUpdate([row.email_queue_uuid], row.email_status === 'blank' ? 'sent' : null)"
                                        class="h-9 w-9 transition duration-500 ease-in-out p-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer flex items-center justify-center">
                                        <EnvelopeIcon v-if="row.email_status === 'blank'"
                                            class="h-full w-full text-info" />
                                        <ArrowPathIcon v-else class="h-full w-full" />
                                    </button>
                                </div>
                            </EjsTooltip>

                            <EjsTooltip v-if="props.permissions.email_queue_delete" :content="'Delete'"
                                position="TopCenter" :target="'#delete_tooltip_' + row.email_queue_uuid">
                                <div :id="'#delete_tooltip_' + row.email_queue_uuid">
                                    <TrashIcon @click="handleSingleItemDeleteRequest(row.email_queue_uuid)"
                                        class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                                </div>
                            </EjsTooltip>
                        </div>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-6">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                    <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                    <p class="mt-1 text-sm text-muted">
                        Adjust your search or filters and try again.
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

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <ConfirmationModal :show="showConfirmationModal" @close="showConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Are you sure?'"
        :text="'Confirm deleting selected email queue items. This action can not be undone.'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import moment from "moment-timezone";
import { registerLicense } from "@syncfusion/ej2-base";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import {
    MagnifyingGlassIcon,
    TrashIcon,
    EnvelopeIcon,
    ArrowPathIcon,
} from "@heroicons/vue/24/solid";

import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Loading from "./components/general/Loading.vue";
import DatePicker from "./components/general/DatePicker.vue";
import BulkActionButton from "./components/general/BulkActionButton.vue";
import Notification from "./components/notifications/Notification.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Badge from "./components/general/Badge.vue";
import SelectBox from "./components/general/SelectBox.vue";

const loading = ref(false);
const selectAll = ref(false);
const selectedItems = ref([]);
const showConfirmationModal = ref(false);
const confirmDeleteAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
    statusOptions: Array,
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

const selectPageItems = computed({
    get() {
        return data.value.data.length > 0 &&
            data.value.data.every(item => selectedItems.value.includes(item.email_queue_uuid));
    },
    set(value) {
        if (value) {
            const currentPageIds = data.value.data.map(item => item.email_queue_uuid);
            const newSelection = new Set([...selectedItems.value, ...currentPageIds]);
            selectedItems.value = Array.from(newSelection);
        } else {
            const currentPageIds = data.value.data.map(item => item.email_queue_uuid);
            selectedItems.value = selectedItems.value.filter(id => !currentPageIds.includes(id));
        }
    }
});

const getDefaultDateRange = () => {
    const startLocal = moment.utc(props.startPeriod).tz(props.timezone);
    const endLocal = moment.utc(props.endPeriod).tz(props.timezone);

    return [
        startLocal.clone().startOf("day").toISOString(),
        endLocal.clone().endOf("day").toISOString(),
    ];
};

const filterData = ref({
    search: null,
    status: "all",
    showGlobal: false,
    dateRange: getDefaultDateRange(),
});

const selectedStatusOption = computed(() => {
    return props.statusOptions.find(option => option.value === filterData.value.status) ?? null;
});

onMounted(() => {
    handleSearchButtonClick();
});

const bulkActions = computed(() => {
    const actions = [];

    if (props.permissions.email_queue_update) {
        actions.push({
            id: "bulk_reset",
            label: "Reset status",
            icon: "ArrowPathIcon",
        });
    }

    if (props.permissions.email_queue_delete) {
        actions.push({
            id: "bulk_delete",
            label: "Delete",
            icon: "TrashIcon",
        });
    }

    return actions;
});

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
};

const handleUpdateStatusFilter = (value) => {
    filterData.value.status = value?.value ?? "all";
};

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    handleClearSelection();
    handleSearchButtonClick();
};

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    handleClearSelection();
    handleSearchButtonClick();
};

const getData = (page = 1) => {
    loading.value = true;

    axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page,
        },
    })
        .then((response) => {
            data.value = response.data;
        })
        .catch((error) => {
            handleErrorResponse(error);
        })
        .finally(() => {
            loading.value = false;
        });
};

const handleSearchButtonClick = () => {
    getData();
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    filterData.value.status = "all";
    filterData.value.showGlobal = false;
    filterData.value.dateRange = getDefaultDateRange();
    handleClearSelection();
    handleSearchButtonClick();
};

const renderRequestedPage = (url) => {
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;
    getData(pageParam);
};

const handleSingleItemDeleteRequest = (uuid) => {
    showConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        showConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    } else if (action === "bulk_reset") {
        executeStatusUpdate(selectedItems.value, null);
    }
};

const executeStatusUpdate = (items, status) => {
    loading.value = true;

    axios.post(props.routes.update_status, {
        items,
        status,
        showGlobal: filterData.value.showGlobal,
    })
        .then((response) => {
            showNotification("success", response.data.messages);

            data.value.data.forEach(row => {
                if (items.includes(row.email_queue_uuid)) {
                    row.email_status = status || "blank";
                }
            });

            if (items.length > 1) {
                handleClearSelection();
            }
        })
        .catch((error) => {
            handleErrorResponse(error);
        })
        .finally(() => {
            loading.value = false;
        });
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.bulk_delete, {
        items,
        showGlobal: filterData.value.showGlobal,
    })
        .then((response) => {
            handleModalClose();
            showNotification("success", response.data.messages);
            handleClearSelection();
            handleSearchButtonClick();
        })
        .catch((error) => {
            handleClearSelection();
            handleModalClose();
            handleErrorResponse(error);
        });
};

const handleSelectAll = () => {
    axios.post(props.routes.select_all, {
        filter: filterData.value,
    })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification("success", response.data.messages);
        })
        .catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });
};

const handleClearSelection = () => {
    selectedItems.value = [];
    selectAll.value = false;
};

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification("error", error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        showNotification("error", { request: [error.message] });
    } else {
        showNotification("error", { request: [error.message] });
    }
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

const handleModalClose = () => {
    showConfirmationModal.value = false;
};

const determineColor = (status) => {
    switch (status) {
        case "sent":
            return {
                backgroundColor: "bg-success-subtle",
                textColor: "text-success",
                ringColor: "ring-success/20",
            };
        case "waiting":
            return {
                backgroundColor: "bg-warning-subtle",
                textColor: "text-warning",
                ringColor: "ring-warning/20",
            };
        case "blank":
            return {
                backgroundColor: "bg-surface-2",
                textColor: "text-body",
                ringColor: "ring-strong/20",
            };
        default:
            return {
                backgroundColor: "bg-info-subtle",
                textColor: "text-info",
                ringColor: "ring-info/20",
            };
    }
};

registerLicense("Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=");
</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>