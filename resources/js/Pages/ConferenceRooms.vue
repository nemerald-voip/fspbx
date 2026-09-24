<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>{{ $t('Conference Rooms') }}</template>

            <template #subtitle>
                {{ $t('Manage individual meeting rooms within conference centers.') }}
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-conference-rooms"
                        id="mobile-search-conference-rooms"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                        :placeholder="$t('Search')" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search" name="desktop-search-conference-rooms"
                        id="desktop-search-conference-rooms"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                        :placeholder="$t('Search')" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <a :href="routes.centers"
                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    {{ $t('Conference Centers') }}
                </a>

                <a v-if="permissions.profile_view" :href="routes.conference_profiles"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    {{ $t('Profiles') }}
                </a>

                <a v-if="permissions.active_view" :href="routes.active_conferences"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    {{ $t('Active Conferences') }}
                </a>

                <button v-if="permissions.create" type="button" @click.prevent="handleCreateButtonClick"
                    class="ml-2 sm:ml-4 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    {{ $t('Create') }}
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
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    <div class="pl-4 flex items-center cursor-pointer select-none"
                        @click="handleSortRequest('conference_room_name')">
                        <span class="mr-2">{{ $t('Name') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'conference_room_name' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'conference_room_name' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>

                <TableColumnHeader :header="$t('Center')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('moderator_pin')">
                        <span class="mr-2">{{ $t('Moderator PIN') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'moderator_pin' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'moderator_pin' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('participant_pin')">
                        <span class="mr-2">{{ $t('Participant PIN') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'participant_pin' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'participant_pin' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('record')">
                        <span class="mr-2">{{ $t('Record') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'record' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'record' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('wait_mod')">
                        <span class="mr-2">{{ $t('Wait Moderator') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'wait_mod' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'wait_mod' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('mute')">
                        <span class="mr-2">{{ $t('Muted') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'mute' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'mute' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('sounds')">
                        <span class="mr-2">{{ $t('Sounds') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'sounds' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'sounds' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader :header="$t('Members')" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader v-if="permissions.enabled" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('enabled')">
                        <span class="mr-2">{{ $t('Enabled') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'enabled' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'enabled' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center cursor-pointer select-none" @click="handleSortRequest('description')">
                        <span class="mr-2">{{ $t('Description') }}</span>
                        <ChevronUpIcon v-if="sortData.name === 'description' && sortData.order === 'asc'"
                            class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'description' && sortData.order === 'desc'"
                            class="h-4 w-4 text-gray-500" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td :colspan="selectionColspan">
                    <div class="text-sm text-center m-2">
                        {{ $tChoice('{1} :count item is selected.|[0,*] :count items are selected.', selectedItems.length) }}
                        <button v-if="!selectAll && selectedItems.length !== data.total"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            {{ $tChoice('{1} Select all :count item|[0,*] Select all :count items', data.total) }}
                        </button>
                        <button v-if="selectAll"
                            class="text-blue-500 rounded py-2 px-2 hover:bg-blue-200 hover:text-blue-500 focus:outline-none focus:ring-1 focus:bg-blue-200 focus:ring-blue-300 transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            {{ $t('Clear selection') }}
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.conference_room_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-gray-500" :text="row.conference_room_name">
                        <div class="flex items-center">
                            <input v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.conference_room_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div class="ml-4" :class="{ 'cursor-pointer hover:text-gray-900': permissions.update }"
                                @click="permissions.update && handleEditButtonClick(row.conference_room_uuid)">
                                {{ row.conference_room_name }}
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="centerLabel(row)" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="formatPin(row.moderator_pin)" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                        :text="formatPin(row.participant_pin)" />

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <BooleanToggle :enabled="row.record" :editable="permissions.update && permissions.record"
                            @toggle="executeToggle([row.conference_room_uuid], 'record')" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <BooleanToggle :enabled="row.wait_mod" :editable="permissions.update && permissions.wait_mod"
                            @toggle="executeToggle([row.conference_room_uuid], 'wait_mod')" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <BooleanToggle :enabled="row.mute" :editable="permissions.update && permissions.mute"
                            @toggle="executeToggle([row.conference_room_uuid], 'mute')" />
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <BooleanToggle :enabled="row.sounds" :editable="permissions.update && permissions.sounds"
                            @toggle="executeToggle([row.conference_room_uuid], 'sounds')" />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.member_count" />

                    <TableField v-if="permissions.enabled" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <BooleanToggle :enabled="row.enabled" :editable="permissions.update"
                            @toggle="executeToggle([row.conference_room_uuid], 'enabled')" />
                    </TableField>

                    <TableField class="px-2 py-2 text-sm text-gray-500" :text="row.description" />

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end gap-1">
                                <a v-if="permissions.interactive_view || permissions.active_view"
                                    :href="toolUrl(permissions.interactive_view ? routes.interactive : routes.active_conferences, row.conference_room_uuid)"
                                    class="rounded px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    {{ $t('View') }}
                                </a>
                                <a v-if="permissions.cdr_view" :href="toolUrl(routes.cdr, row.conference_room_uuid)"
                                    class="rounded px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    CDR
                                </a>
                                <a v-if="permissions.session_view" :href="toolUrl(routes.sessions, row.conference_room_uuid)"
                                    class="rounded px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                                    {{ $t('Sessions') }}
                                </a>
                                <PencilSquareIcon v-if="permissions.update" @click="handleEditButtonClick(row.conference_room_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                    :title="$t('Edit')" />
                                <TrashIcon v-if="permissions.destroy" @click="handleSingleItemDeleteRequest(row.conference_room_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                    :title="$t('Delete')" />
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ $t('No results found') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $t('Adjust your search and try again.') }}</p>
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

    <ConfirmationModal :show="confirmationModalTrigger" @close="confirmationModalTrigger = false"
        @confirm="confirmAction" :header="confirmationHeader" :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel" :cancel-button-label="$t('Cancel')" />

    <ConferenceRoomForm :show="showForm" :options="itemOptions" :mode="formMode" :loading="loadingForm"
        :header="formHeader" @close="handleFormClose" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { trans } from '@i18n';
import { computed, defineComponent, h, onMounted, ref } from "vue";
import axios from "axios";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import ConferenceRoomForm from "./components/forms/ConferenceRoomForm.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import Badge from "@generalComponents/Badge.vue";
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/solid";

const BooleanToggle = defineComponent({
    props: {
        enabled: { type: String, default: "false" },
        editable: { type: Boolean, default: false },
    },
    emits: ["toggle"],
    setup(props, { emit }) {
        return () => h(
            props.editable ? "button" : "span",
            props.editable ? { type: "button", onClick: () => emit("toggle") } : {},
            h(Badge, {
                text: props.enabled === "true" ? trans('True') : trans('False'),
                ...enabledBadgeProps(props.enabled),
            }),
        );
    },
});

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const loading = ref(false);
const currentPage = ref(1);
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmationModalTrigger = ref(false);
const confirmAction = ref(null);
const confirmationHeader = ref(trans('Are you sure?'));
const confirmationText = ref("");
const confirmationButtonLabel = ref(trans('Continue'));
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const showForm = ref(false);
const formMode = ref("create");
const loadingForm = ref(false);
const editingItemUuid = ref(null);
const itemOptions = ref({
    item: {},
    conference_centers: [],
    profiles: [],
    permissions: {},
    routes: {},
});

const routes = props.routes;
const permissions = props.permissions;

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
    name: "description",
    order: "asc",
});

const bulkActions = computed(() => {
    const actions = [];

    if (permissions.update) {
        actions.push({ id: "toggle_enabled", label: trans('Toggle Enabled'), icon: "PencilSquareIcon" });
        actions.push({ id: "toggle_record", label: trans('Toggle Record'), icon: "PencilSquareIcon" });
        actions.push({ id: "toggle_wait_mod", label: trans('Toggle Wait Moderator'), icon: "PencilSquareIcon" });
    }

    if (permissions.destroy) {
        actions.push({ id: "bulk_delete", label: trans('Delete'), icon: "TrashIcon" });
    }

    return actions;
});

const selectionColspan = computed(() => permissions.enabled ? 12 : 11);

const formHeader = computed(() => {
    if (formMode.value === "create") {
        return trans('Create Conference Room');
    }

    return trans('Update Conference Room - :name', { name: itemOptions.value?.item?.conference_room_name || trans('Loading...') });
});

onMounted(() => {
    getData();
});

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    getData(currentPage.value);
};

const getData = (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;

    let sort = sortData.value.name;
    if (sortData.value.order === "desc") {
        sort = `-${sort}`;
    }

    axios.get(routes.data_route, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            sort,
        },
    })
        .then((response) => {
            data.value = response.data;
            currentPage.value = response.data.current_page ?? currentPage.value;
        })
        .catch(handleErrorResponse)
        .finally(() => {
            loading.value = false;
        });
};

const handleSearchButtonClick = () => {
    getData(1);
};

const refreshCurrentPage = () => {
    getData(currentPage.value);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;

    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;
    getData(pageParam);
};

const handleCreateButtonClick = () => {
    showForm.value = true;
    formMode.value = "create";
    editingItemUuid.value = null;
    getItemOptions();
};

const handleEditButtonClick = (uuid) => {
    showForm.value = true;
    formMode.value = "update";
    editingItemUuid.value = uuid;
    getItemOptions(uuid);
};

const getItemOptions = (itemUuid = null) => {
    loadingForm.value = true;

    axios.post(routes.item_options, itemUuid ? { itemUuid } : {})
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch((error) => {
            handleFormClose();
            handleErrorResponse(error);
        })
        .finally(() => {
            loadingForm.value = false;
        });
};

const handleFormClose = () => {
    showForm.value = false;
    formMode.value = "create";
    editingItemUuid.value = null;
    itemOptions.value = {
        item: {},
        conference_centers: [],
        profiles: [],
        permissions: {},
        routes: {},
    };
};

const handleSelectPageItems = () => {
    selectedItems.value = selectPageItems.value
        ? data.value.data.map((item) => item.conference_room_uuid)
        : [];
};

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
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
    selectPageItems.value = false;
    selectAll.value = false;
};

const handleSingleItemDeleteRequest = (uuid) => {
    showConfirmation({
        header: trans('Confirm Deletion'),
        text: trans('This action will permanently delete the selected conference room.'),
        button: trans('Delete'),
        action: () => executeBulkDelete([uuid]),
    });
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        showConfirmation({
            header: trans('Confirm Deletion'),
            text: trans('This action will permanently delete the selected conference room(s).'),
            button: trans('Delete'),
            action: () => executeBulkDelete(),
        });
        return;
    }

    const field = action.replace("toggle_", "");
    showConfirmation({
        header: trans('Confirm Toggle'),
        text: trans('Toggle this setting for the selected conference room(s)?'),
        button: trans('Toggle'),
        action: () => executeToggle(selectedItems.value, field),
    });
};

const showConfirmation = ({ header, text, button, action }) => {
    confirmationHeader.value = header;
    confirmationText.value = text;
    confirmationButtonLabel.value = button;
    confirmAction.value = action;
    confirmationModalTrigger.value = true;
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(routes.bulk_delete, { items })
        .then((response) => {
            handleModalClose();
            handleClearSelection();
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch((error) => {
            handleModalClose();
            handleClearSelection();
            handleErrorResponse(error);
        });
};

const executeToggle = (items, field) => {
    axios.post(routes.bulk_toggle, { items, field })
        .then((response) => {
            handleModalClose();
            handleClearSelection();
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch((error) => {
            handleModalClose();
            handleClearSelection();
            handleErrorResponse(error);
        });
};

const handleModalClose = () => {
    confirmationModalTrigger.value = false;
    confirmAction.value = null;
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

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification("error", error.response.data.errors || error.response.data.messages || { request: [error.message] });
    } else if (error.request) {
        showNotification("error", { request: [error.request] });
    } else {
        showNotification("error", { request: [error.message] });
    }
};

function enabledBadgeProps(value) {
    return value === "true"
        ? { backgroundColor: "bg-green-50", textColor: "text-green-700", ringColor: "ring-green-600/20" }
        : { backgroundColor: "bg-gray-50", textColor: "text-gray-600", ringColor: "ring-gray-500/20" };
}

const formatPin = (pin) => {
    const value = String(pin || "");
    if (value.length === 9) {
        return `${value.slice(0, 3)}-${value.slice(3, 6)}-${value.slice(6)}`;
    }

    return value;
};

const centerLabel = (row) => {
    if (!row.conference_center) return "-";
    return `${row.conference_center.conference_center_name} (${row.conference_center.conference_center_extension})`;
};

const toolUrl = (template, uuid) => template.replace(":uuid", encodeURIComponent(uuid));
</script>
