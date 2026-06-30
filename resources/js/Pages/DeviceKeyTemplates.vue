<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Device Key Templates</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-subtle" aria-hidden="true" />
                    </div>
                    <input type="text" v-model="filterData.search"
                        class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:block"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                    <input type="text" v-model="filterData.search"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-heading ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus sm:hidden"
                        placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                </div>
            </template>

            <template #action>
                <a :href="routes.devices"
                    class="rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Devices
                </a>
                <button v-if="permissions.create" type="button" @click.prevent="handleCreateButtonClick"
                    class="ml-2 sm:ml-4 rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover">
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
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-heading items-center justify-start">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                        class="h-4 w-4 rounded border-strong text-accent-fg">
                    <div class="pl-4 flex items-center cursor-pointer select-none" @click="handleSortRequest('name')">
                        <span class="mr-2">Name</span>
                        <ChevronUpIcon v-if="sortData.name === 'name' && sortData.order === 'asc'"
                            class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'name' && sortData.order === 'desc'"
                            class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader header="Keys" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Enabled" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-heading" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-heading" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="5">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                        <button v-if="!selectAll && selectedItems.length !== data.total"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle hover:text-info"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle hover:text-info"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.device_key_template_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-muted" :text="row.name">
                        <div class="flex items-center">
                            <input v-model="selectedItems" type="checkbox" :value="row.device_key_template_uuid"
                                class="h-4 w-4 rounded border-strong text-accent-fg">
                            <div class="ml-4" :class="{ 'cursor-pointer hover:text-heading': permissions.update }"
                                @click="permissions.update && handleEditButtonClick(row.device_key_template_uuid)">
                                {{ row.name }}
                            </div>
                        </div>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="row.keys_count" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <Badge :text="row.enabled === 'true' ? 'True' : 'False'" v-bind="enabledBadgeProps(row.enabled)" />
                    </TableField>
                    <TableField class="px-2 py-2 text-sm text-muted" :text="row.description" />
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-muted">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <PencilSquareIcon v-if="permissions.update" @click="handleEditButtonClick(row.device_key_template_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                    title="Edit" />
                                <TrashIcon v-if="permissions.destroy" @click="handleSingleItemDeleteRequest(row.device_key_template_uuid)"
                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer"
                                    title="Delete" />
                                <div v-if="permissions.create" class="relative z-20 ml-2">
                                    <AdvancedActionButton :actions="advancedActions"
                                        @advanced-action="(action) => handleAdvancedActionRequest(action, row.device_key_template_uuid)" />
                                </div>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                    <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    :page-size="perPage" :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true"
                    @pagination-change-page="renderRequestedPage" @page-size-change="handlePageSizeChange" />
            </template>
        </DataTable>
    </div>

    <ConfirmationModal :show="confirmationModalTrigger" @close="handleModalClose" @confirm="confirmAction"
        :header="confirmationHeader" :text="confirmationText" :confirm-button-label="confirmationButtonLabel"
        cancel-button-label="Cancel" />

    <DeviceKeyTemplateForm :show="showForm" :options="itemOptions" :mode="formMode" :loading="loadingForm"
        :header="formHeader" @close="handleFormClose" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" />

    <AddEditItemModal :show="showCopyModal" header="Copy Key Template To Domain" @close="handleCopyModalClose">
        <template #modal-body>
            <Vueform :endpoint="submitCopyForm" @success="handleCopySuccess" @error="handleErrorResponse" :display-errors="false">
                <template #empty>
                    <FormElements>
                        <SelectElement name="target_domain_uuid" label="Target domain" :items="copyDomainOptions"
                            :native="false" :search="true" input-type="search" autocomplete="off" placeholder="Select domain"
                            :strict="false" :floating="false" />
                        <ButtonElement name="submit" button-label="Copy" :submits="true" align="right" />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import DeviceKeyTemplateForm from "./components/forms/DeviceKeyTemplateForm.vue";
import AdvancedActionButton from "./components/general/AdvancedActionButton.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import { ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, PencilSquareIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
    pagination: Object,
    options: Object,
});

const routes = props.routes;
const permissions = props.permissions;
const loading = ref(false);
const currentPage = ref(1);
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const confirmationModalTrigger = ref(false);
const confirmAction = ref(null);
const confirmationHeader = ref("Are you sure?");
const confirmationText = ref("");
const confirmationButtonLabel = ref("Continue");
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const showForm = ref(false);
const formMode = ref("create");
const loadingForm = ref(false);
const itemOptions = ref({ item: {}, extensions: [], routes: {} });
const perPage = ref(props.pagination?.per_page);
const showCopyModal = ref(false);
const copyTemplateUuid = ref(null);

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

const filterData = ref({ search: null });
const sortData = ref({ name: "name", order: "asc" });

const bulkActions = computed(() => permissions.destroy ? [
    { id: "bulk_delete", label: "Delete", icon: "TrashIcon" },
] : []);

const copyDomainOptions = computed(() => props.options?.domains ?? []);

const advancedActions = computed(() => [
    {
        category: "Advanced",
        actions: [
            { id: "duplicate", label: "Duplicate", icon: "DocumentDuplicateIcon" },
            ...(permissions.copy_to_domain && copyDomainOptions.value.length
                ? [{ id: "copy_to_domain", label: "Copy to domain", icon: "DocumentDuplicateIcon" }]
                : []),
        ],
    },
]);

const formHeader = computed(() => formMode.value === "create"
    ? "Create Device Key Template"
    : `Update Device Key Template - ${itemOptions.value?.item?.name || "Loading..."}`);

onMounted(() => getData());

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
    if (sortData.value.order === "desc") sort = `-${sort}`;

    axios.get(routes.data_route, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            per_page: perPage.value,
            sort,
        },
    }).then((response) => {
        data.value = response.data;
        currentPage.value = response.data.current_page ?? currentPage.value;
    }).catch(handleErrorResponse).finally(() => {
        loading.value = false;
    });
};

const handleSearchButtonClick = () => getData(1);
const refreshCurrentPage = () => getData(currentPage.value);
const handlePageSizeChange = (newPerPage) => {
    perPage.value = newPerPage;
    getData(1);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;
    const urlObj = new URL(url, window.location.origin);
    getData(urlObj.searchParams.get("page") ?? 1);
};

const handleCreateButtonClick = () => {
    showForm.value = true;
    formMode.value = "create";
    getItemOptions();
};

const handleEditButtonClick = (uuid) => {
    showForm.value = true;
    formMode.value = "update";
    getItemOptions(uuid);
};

const handleAdvancedActionRequest = async (action, uuid) => {
    if (action === "copy_to_domain") {
        copyTemplateUuid.value = uuid;
        showCopyModal.value = true;
        return;
    }

    if (action !== "duplicate") return;

    loading.value = true;

    try {
        const response = await axios.post(routes.duplicate, { uuid });
        showNotification("success", response.data.messages);
        refreshCurrentPage();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const submitCopyForm = async (FormData, form) => {
    return await form.$vueform.services.axios.post(routes.copy_to_domain, {
        ...form.requestData,
        uuid: copyTemplateUuid.value,
    });
};

const handleCopySuccess = (response) => {
    handleCopyModalClose();
    showNotification("success", response.data.messages);
};

const handleCopyModalClose = () => {
    showCopyModal.value = false;
    copyTemplateUuid.value = null;
};

const getItemOptions = (itemUuid = null) => {
    loadingForm.value = true;
    axios.post(routes.item_options, itemUuid ? { itemUuid } : {})
        .then((response) => itemOptions.value = response.data)
        .catch((error) => {
            handleFormClose();
            handleErrorResponse(error);
        })
        .finally(() => loadingForm.value = false);
};

const handleFormClose = () => {
    showForm.value = false;
    formMode.value = "create";
    itemOptions.value = { item: {}, extensions: [], routes: {} };
};

const handleSelectPageItems = () => {
    selectedItems.value = selectPageItems.value
        ? data.value.data.map((item) => item.device_key_template_uuid)
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
        header: "Confirm Deletion",
        text: "Delete the selected device key template?",
        button: "Delete",
        action: () => executeBulkDelete([uuid]),
    });
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        showConfirmation({
            header: "Confirm Deletion",
            text: "Delete the selected device key template(s)?",
            button: "Delete",
            action: () => executeBulkDelete(),
        });
    }
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

const enabledBadgeProps = (enabled) => enabled === "true"
    ? { backgroundColor: "bg-success-subtle", textColor: "text-success", ringColor: "ring-success/20" }
    : { backgroundColor: "bg-surface-2", textColor: "text-body", ringColor: "ring-strong/20" };
</script>
