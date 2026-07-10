<template>
    <div class="mt-4 flex flex-col">

        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                </div>
                <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                    id="mobile-search-candidate"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:hidden"
                    placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                    id="desktop-search-candidate"
                    class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
                    placeholder="Search" @keydown.enter="handleSearchButtonClick" />
            </div>

            <div class="relative z-10 min-w-64 -mt-0.5 mb-2 scale-y-95 shrink-0 sm:mr-4">
                <DatePicker :dateRange="filterData.dateRange" :timezone="timezone"
                    @update:date-range="handleUpdateDateRange" />
            </div>

            <div v-if="showDomainFilter" class="relative z-[1] min-w-72 -mt-0.5 mb-2 shrink-0 sm:mr-4">
                <Vueform :key="domainFilterKey" :display-errors="false" size="sm">
                    <SelectElement name="domain_uuid" :default="filterData.domain_uuid" :items="domainFilterOptions"
                        :native="false" :search="true" input-type="search" autocomplete="off"
                        :strict="false" :floating="false" @change="handleUpdateDomainFilter" />
                </Vueform>
            </div>

            <div class="relative">
                <div class="flex justify-between">

                    <button type="button" @click.prevent="handleSearchButtonClick"
                        class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover
                                focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        Search
                    </button>

                    <button type="button" @click.prevent="handleFiltersReset"
                        class="rounded-md bg-surface px-2.5 py-1.5 ml-2  sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator class="border border-default" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />
                <div class="overflow-hidden-t border-l border-r border-default">
                    <table class="min-w-full divide-y divide-default mb-4">
                        <thead class="bg-surface-3">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Date</th>
                                <th v-if="showDomainColumn" class="px-6 py-3 text-left text-sm font-semibold text-heading">Domain</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">To</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Subject</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Status</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-heading">Action</th>

                                
                            </tr>
                        </thead>
                        <tbody v-if="!isDataLoading && data.data?.length" class="divide-y divide-default bg-surface">
                            <template v-for="row in data.data" :key="row.uuid">
                                <tr @click="toggleExpand(row.uuid)" class="hover:bg-surface-2 cursor-pointer">
                                    <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-muted">
                                        {{ row.created_at_formatted ?? '' }}
                                    </td>

                                    <td v-if="showDomainColumn" class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ domainLabel(row) }}
                                    </td>

                                    <td class=" whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.to ?? '' }}
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        {{ row.subject ?? '' }}
                                    </td>

                                    <td class="whitespace-nowrap px-6 py-2 text-sm text-muted">
                                        <Badge v-if="row.status == 'queued'" :text="row.status"
                                            :backgroundColor="'bg-info-subtle'" :textColor="'text-info'"
                                            ringColor="ring-info/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.status == 'sending'" :text="row.status"
                                            :backgroundColor="'bg-info-subtle'" :textColor="'text-info'"
                                            ringColor="ring-info/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.status == 'sent'" :text="row.status"
                                            :backgroundColor="'bg-success-subtle'" :textColor="'text-success'"
                                            ringColor="ring-success/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.status == 'permanent_failed'" :text="row.status"
                                            :backgroundColor="'bg-danger-subtle'" :textColor="'text-danger'"
                                            ringColor="ring-danger/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.status == 'failed'" :text="row.status"
                                            :backgroundColor="'bg-danger-subtle'" :textColor="'text-danger'"
                                            ringColor="ring-danger/20" class="px-2 py-1 text-xs" />
                                    </td>

                                    <td>
                                        <div class="flex items-center justify-center gap-1">
                                        <!-- DELIVERY DETAILS ICON -->
                                        <div v-if="canShowDeliveryDetails(row)" class="relative group flex items-center justify-center cursor-pointer">
                                            <DocumentMagnifyingGlassIcon @click.stop="handleDeliveryDetails(row)"
                                                class="h-7 w-7 transition duration-300 ease-in-out p-1 text-subtle hover:bg-surface-3 hover:text-body rounded-full" />

                                            <div
                                                class="absolute bottom-full mb-1 hidden group-hover:block whitespace-nowrap bg-gray-800 text-white text-xs rounded py-1 px-2 z-10 shadow-lg">
                                                Delivery Details
                                                <div
                                                    class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-800 rotate-45">
                                                </div>
                                            </div>
                                        </div>

                                    <!-- RETRY ICON -->
                                        <div class="relative group flex items-center justify-center cursor-pointer">
                                            <RestartIcon @click.stop="handleRetry(row.uuid)"
                                                class="h-7 w-7 transition duration-300 ease-in-out p-1 text-subtle hover:bg-surface-3 hover:text-body rounded-full" />

                                            <!-- TOOLTIP -->
                                            <div
                                                class="absolute bottom-full mb-1 hidden group-hover:block whitespace-nowrap bg-gray-800 text-white text-xs rounded py-1 px-2 z-10 shadow-lg">
                                                Resend
                                                <!-- Little down arrow -->
                                                <div
                                                    class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-800 rotate-45">
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                    </td>
                                </tr>
                                <!-- EXPANDABLE ROW -->
                                <tr v-if="expandedRow === row.uuid">
                                    <td :colspan="emailColumnCount" class="bg-surface-2 px-6 py-4">

                                        <div class="flex gap-2">
                                            <div class="text-muted text-sm ">ID: </div>
                                            <div class="text-subtle text-sm "> {{ row.uuid }}</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <div class="text-muted text-sm ">From: </div>
                                            <div class="text-subtle text-sm "> {{ row.from }}</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <div class="text-muted text-sm ">To: </div>
                                            <div class="text-subtle text-sm "> {{ row.to }}</div>
                                        </div>
                                        <div v-if="row.cc" class="flex gap-2">
                                            <div class="text-muted text-sm ">CC: </div>
                                            <div class="text-subtle text-sm "> {{ row.cc }}</div>
                                        </div>
                                        <div v-if="row.bcc" class="flex gap-2">
                                            <div class="text-muted text-sm ">BCC: </div>
                                            <div class="text-subtle text-sm "> {{ row.bcc }}</div>
                                        </div>
                                        <div v-if="row.sent_debug_info" class="flex gap-2">
                                            <div class="text-muted text-sm ">Debug: </div>
                                            <div class="text-subtle text-sm "> {{ row.sent_debug_info }}</div>
                                        </div>

                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!isDataLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                        <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-muted">
                Adjust your search and try again.
              </p> -->
                    </div>

                    <!-- Loading -->
                    <div v-if="isDataLoading" class="text-center my-5 text-sm text-muted">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-6 py-1">
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
                                <div class="h-2 bg-surface-3 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete the selected hotel room(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <AddEditItemModal :show="showDeliveryDetailsModal" header="Delivery Details" :loading="deliveryDetailsLoading"
        custom-class="sm:max-w-4xl" body-class="max-h-[70vh] overflow-y-auto" @close="showDeliveryDetailsModal = false">
        <template #modal-body>
            <div v-if="deliveryDetails?.available" class="space-y-4">
                <div class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-subtle">Provider</div>
                        <div class="mt-1 text-heading">{{ deliveryDetails.provider }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-subtle">Provider ID</div>
                        <div class="mt-1 font-mono text-xs text-body">{{ deliveryDetails.message_id }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-subtle">Status</div>
                        <div class="mt-1 text-heading">{{ deliveryDetails.status || 'Unknown' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-subtle">Message Stream</div>
                        <div class="mt-1 text-heading">{{ deliveryDetails.message_stream || 'Default' }}</div>
                    </div>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-heading">Events</h4>
                    <div v-if="deliveryDetails.events?.length" class="mt-2 divide-y divide-default rounded-md border border-default">
                        <div v-for="(event, index) in deliveryDetails.events" :key="index" class="px-3 py-2 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span :class="['inline-flex h-2 w-2 rounded-full', eventTone(event)]"></span>
                                    <div class="font-medium text-heading">{{ eventTypeLabel(event) }}</div>
                                </div>
                                <div class="text-xs text-muted">{{ eventTimestamp(event) }}</div>
                            </div>
                            <div v-if="event.Recipient" class="mt-1 text-xs text-muted">
                                Recipient: {{ event.Recipient }}
                            </div>
                            <div v-if="eventDetailRows(event).length" class="mt-2 grid gap-2 sm:grid-cols-2">
                                <div v-for="detail in eventDetailRows(event)" :key="detail.label"
                                    :class="['rounded-md bg-surface-2 px-2 py-1.5', detail.wide ? 'sm:col-span-2' : '']">
                                    <div class="text-[11px] font-medium uppercase tracking-wide text-subtle">{{ detail.label }}</div>
                                    <div class="mt-0.5 break-words text-xs text-body">{{ detail.value }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-sm text-muted">No provider events were returned.</p>
                </div>

                <details>
                    <summary class="cursor-pointer text-sm font-medium text-body">Raw provider response</summary>
                    <pre class="mt-2 max-h-80 overflow-auto rounded-md bg-gray-900 p-3 text-xs text-gray-100">{{ prettyJson(deliveryDetails.raw) }}</pre>
                </details>
            </div>
            <div v-else class="text-sm text-body">
                {{ deliveryDetails?.message || 'Delivery details are not available.' }}
            </div>
        </template>
    </AddEditItemModal>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Notification from "./notifications/Notification.vue";
import ConfirmationModal from "./modal/ConfirmationModal.vue";
import AddEditItemModal from "./modal/AddEditItemModal.vue";
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { DocumentMagnifyingGlassIcon } from "@heroicons/vue/24/outline";
import { registerLicense } from '@syncfusion/ej2-base';
import DatePicker from "@generalComponents/DatePicker.vue";
import Paginator from "@generalComponents/Paginator.vue";
import Badge from "@generalComponents/Badge.vue";
import moment from 'moment-timezone';
import RestartIcon from "./icons/RestartIcon.vue";


const selectedItems = ref([]);

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
    trigger: Boolean,
    domainOptions: {
        type: Array,
        default: () => [],
    },
    selectedDomainUuid: String,
})

const showCreateModal = ref(false);
const showBulkCreateModal = ref(false);
const showEditModal = ref(false);
const loadingModal = ref(false)
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDeleteConfirmationModal = ref(false);
const showDeliveryDetailsModal = ref(false);
const confirmDeleteAction = ref(null);
const deliveryDetails = ref(null);
const deliveryDetailsLoading = ref(false);
const itemOptions = ref([])
const isDataLoading = ref(false)
const readOnly = ref(false)
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
const expandedRow = ref(null)
const domainFilterKey = ref(0)
const deliveryStatusSyncs = new Map()


const startLocal = moment.utc(props.startPeriod).tz(props.timezone)
const endLocal = moment.utc(props.endPeriod).tz(props.timezone)

const dateRange = [
    startLocal.clone().startOf('day').toISOString(), // UTC instant for local start-of-day
    endLocal.clone().endOf('day').toISOString(),     // UTC instant for local end-of-day
]

const filterData = ref({
    search: props.search,
    showGlobal: props.showGlobal,
    domain_uuid: props.selectedDomainUuid,
    dateRange: dateRange,
    // dateRange: ['2024-07-01T00:00:00', '2024-07-01T23:59:59'],

});

const showDomainFilter = computed(() => props.domainOptions.length > 1);
const showDomainColumn = computed(() => showDomainFilter.value);
const domainFilterOptions = computed(() => [
    { value: 'all', label: 'All domains' },
    ...props.domainOptions,
]);
const emailColumnCount = computed(() => showDomainColumn.value ? 6 : 5);

const domainLabel = (row) => {
    if (!row.domain_uuid) {
        return 'System';
    }

    return row.domain?.domain_description || row.domain?.domain_name || '';
};

const canShowDeliveryDetails = (row) => {
    return !!row.provider_message_id || ['sent', 'failed', 'permanent_failed'].includes(row.status);
}

// const emits = defineEmits(['edit-item', 'delete-item']);

const fetchData = async (page = 1) => {
    isDataLoading.value = true
    axios.get(props.routes.email_logs, {
        params: {
            filter: filterData.value,
            page,
        }
    })
        .then((response) => {
            data.value = response.data;
            syncVisibleDeliveryStatuses();
            // console.log(data.value);

        }).catch((error) => {
            handleErrorResponse(error)
        }).finally(() => {
            isDataLoading.value = false
        });
}


watch(() => props.trigger, (newVal) => {
    fetchData(1)
})

const syncVisibleDeliveryStatuses = () => {
    const rows = data.value.data ?? [];
    const now = Date.now();
    const rowsToSync = rows.filter((row) => {
        const lastSync = deliveryStatusSyncs.get(row.uuid) ?? 0;

        return row.provider === 'postmark'
            && row.status === 'sent'
            && now - lastSync > 30000
            && canShowDeliveryDetails(row);
    });

    if (!rowsToSync.length) return;

    rowsToSync.forEach((row) => deliveryStatusSyncs.set(row.uuid, now));

    Promise.allSettled(rowsToSync.map((row) => {
        return axios.get(props.routes.email_delivery_details.replace('__UUID__', row.uuid));
    })).then(() => {
        fetchData(data.value.current_page || 1);
    });
}

const toggleExpand = (uuid) => {
    expandedRow.value = expandedRow.value === uuid ? null : uuid;
};

const handleUpdateDateRange = (newDateRange) => {
    filterData.value.dateRange = newDateRange;
}

const handleUpdateDomainFilter = (newValue) => {
    filterData.value.domain_uuid = typeof newValue === 'object'
        ? (newValue?.value ?? null)
        : newValue;
}

const renderRequestedPage = (url) => {
    isDataLoading.value = true;
    // Extract the page number from the url, e.g. "?page=3"
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;

    // Now call getData with the page number
    fetchData(pageParam);
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
    if (props.permissions.user_destroy) {
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

const handleSearchButtonClick = () => {
    fetchData(1)
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    filterData.value.domain_uuid = props.selectedDomainUuid;
    domainFilterKey.value += 1;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const handleEditButtonClick = (uuid) => {
    showEditModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    readOnly.value = false
    getItemOptions(uuid);
}

const handleSingleItemDeleteRequest = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.hotel_rooms_bulk_delete, { items })
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


const handleModalClose = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    showBulkCreateModal.value = false;
    showDeleteConfirmationModal.value = false;
    // bulkUpdateModalTrigger.value = false;
}

const getItemOptions = (itemUuid = null) => {
    loadingModal.value = true;

    axios.post(props.routes.hotel_rooms_item_options, {
        item_uuid: itemUuid,
    })
        .then((response) => {
            itemOptions.value = response.data;
            // console.log(itemOptions.value);

        }).catch((error) => {
            handleErrorResponse(error)
        }).finally(() => {
            loadingModal.value = false
        });
}

const handleRetry = (uuid) => {
    // Single message sent in array format to bulk endpoint
    axios.post(props.routes.email_retry, { 'items': [uuid] })
        .then((response) => {
            showNotification('success', response.data.messages);
            handleClearSelection();
        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        }).finally(() => {
            fetchData(data.value.current_page || 1);
        });
}

const handleDeliveryDetails = (row) => {
    showDeliveryDetailsModal.value = true;
    deliveryDetails.value = null;
    deliveryDetailsLoading.value = true;

    axios.get(props.routes.email_delivery_details.replace('__UUID__', row.uuid))
        .then((response) => {
            deliveryDetails.value = response.data;
            fetchData(data.value.current_page || 1);
        }).catch((error) => {
            deliveryDetails.value = {
                available: false,
                message: error.response?.data?.messages?.error?.[0] || 'Unable to fetch delivery details.',
            };
        }).finally(() => {
            deliveryDetailsLoading.value = false;
        });
}

const prettyJson = (value) => {
    if (!value) return '';
    return JSON.stringify(value, null, 2);
}

const eventTypeLabel = (event) => {
    const type = event.Type || event.RecordType || 'Event';
    const labels = {
        Delivered: 'Delivered',
        Transient: 'Temporary delivery issue',
        Opened: 'Opened',
        LinkClicked: 'Link clicked',
        Bounced: 'Bounced',
        SubscriptionChanged: 'Subscription changed',
        SpamComplaint: 'Spam complaint',
    };

    return labels[type] || type;
}

const eventTone = (event) => {
    const type = event.Type || event.RecordType;

    if (['Delivered', 'Opened', 'LinkClicked'].includes(type)) return 'bg-success';
    if (['Transient'].includes(type)) return 'bg-warning';
    if (['Bounced', 'SpamComplaint'].includes(type)) return 'bg-danger';

    return 'bg-subtle';
}

const eventTimestamp = (event) => {
    const value = event.ReceivedAt || event.DeliveredAt || event.BouncedAt || event.OpenedAt || event.ClickedAt || '';
    return value ? moment(value).format('MMM D, YYYY h:mm A') : '';
}

const eventDetailRows = (event) => {
    const details = event.Details || {};
    const rows = [];

    const add = (label, value, wide = false) => {
        if (value === null || value === undefined || value === '') return;
        rows.push({ label, value: typeof value === 'object' ? JSON.stringify(value) : String(value), wide });
    };

    add('Delivery response', details.DeliveryMessage || details.Summary || event.Description, true);
    add('Destination server', details.DestinationServer);
    add('Destination IP', details.DestinationIP);
    add('Bounce ID', details.BounceID);
    add('Click URL', details.OriginalLink || details.Link);
    add('User agent', details.UserAgent);
    add('Geo location', [details.GeoLocation?.City, details.GeoLocation?.Region, details.GeoLocation?.Country].filter(Boolean).join(', '));

    Object.entries(details).forEach(([key, value]) => {
        const knownKeys = ['DeliveryMessage', 'Summary', 'DestinationServer', 'DestinationIP', 'BounceID', 'OriginalLink', 'Link', 'UserAgent', 'GeoLocation'];
        if (!knownKeys.includes(key)) {
            add(formatDetailLabel(key), value);
        }
    });

    return rows;
}

const formatDetailLabel = (value) => {
    return String(value)
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, char => char.toUpperCase());
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

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');


</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
