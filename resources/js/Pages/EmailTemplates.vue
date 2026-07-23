<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Email Templates</template>
            <template #subtitle>
                {{ activeTab === "default"
                    ? "Read-only templates shipped with FS PBX. Create a custom override to make account-specific changes."
                    : "Custom overrides and account-specific templates you can edit." }}
            </template>

            <template #filters>
                <!-- Default / Custom tabs -->
                <div class="mb-4 w-full">
                    <!-- Mobile -->
                    <div class="grid grid-cols-1 sm:hidden">
                        <select
                            v-model="activeTab"
                            aria-label="Select a template type"
                            class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-2 pl-3 pr-8 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600"
                        >
                            <option v-for="tab in tabs" :key="tab.id" :value="tab.id">{{ tab.name }}</option>
                        </select>
                        <ChevronDownIcon
                            class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end fill-gray-500"
                            aria-hidden="true"
                        />
                    </div>

                    <!-- Desktop -->
                    <div class="hidden sm:block">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <a
                                    v-for="tab in tabs"
                                    :key="tab.id"
                                    href="#"
                                    :class="[
                                        activeTab === tab.id
                                            ? '!border-indigo-600 !text-indigo-600'
                                            : 'border-transparent !text-gray-500 hover:!border-gray-300 hover:!text-gray-700',
                                        'whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium',
                                    ]"
                                    :aria-current="activeTab === tab.id ? 'page' : undefined"
                                    @click.prevent="activeTab = tab.id"
                                >
                                    {{ tab.name }}
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>

                <div class="relative mb-2 min-w-64 focus-within:z-10 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="search"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                        placeholder="Search subjects, categories, and descriptions"
                        @keydown.enter="handleSearchButtonClick"
                    />
                </div>

                <select
                    v-model="filterData.category"
                    class="mb-2 min-w-48 rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:mr-4"
                    aria-label="Filter by category"
                    @change="handleSearchButtonClick"
                >
                    <option :value="null">All categories</option>
                    <option v-for="category in categoryOptions" :key="category.value" :value="category.value">
                        {{ formatLabel(category.label) }}
                    </option>
                </select>

                <select
                    v-model="filterData.language"
                    class="mb-2 min-w-40 rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:mr-4"
                    aria-label="Filter by language"
                    @change="handleSearchButtonClick"
                >
                    <option :value="null">All languages</option>
                    <option v-for="language in languageOptions" :key="language.value" :value="language.value">
                        {{ language.label }}
                    </option>
                </select>
            </template>

            <template #action>
                <button
                    v-if="permissions.create && activeTab === 'custom'"
                    type="button"
                    class="ml-2 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:ml-4"
                    @click="handleCreateButtonClick"
                >
                    Create
                </button>
            </template>

            <template #navigation>
                <Paginator
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :current-page="data.current_page"
                    :last-page="data.last_page"
                    :links="data.links"
                    :bulk-actions="bulkActions"
                    :has-selected-items="selectedItems.length > 0"
                    @pagination-change-page="renderRequestedPage"
                    @bulk-action="handleBulkActionRequest"
                />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center">
                        <input
                            v-model="selectPageItems"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                            aria-label="Select this page"
                            @change="handleSelectPageItems"
                        />
                        <button type="button" class="ml-4 flex items-center" @click="handleSortRequest('template_category')">
                            <span class="mr-2">Category</span>
                            <ChevronUpIcon v-if="sortData.name === 'template_category' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'template_category' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>
                </TableColumnHeader>
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button type="button" class="flex items-center" @click="handleSortRequest('template_subcategory')">
                        <span class="mr-2">Purpose</span>
                        <ChevronUpIcon v-if="sortData.name === 'template_subcategory' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'template_subcategory' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader header="Language" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button type="button" class="flex items-center" @click="handleSortRequest('template_subject')">
                        <span class="mr-2">Subject</span>
                        <ChevronUpIcon v-if="sortData.name === 'template_subject' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'template_subject' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>
                <TableColumnHeader v-if="activeTab === 'default'" header="Version" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader v-if="activeTab === 'custom'" header="Scope" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader v-if="activeTab === 'custom'" header="Status" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td :colspan="columnCount">
                    <div class="m-2 text-center text-sm">
                        <span class="font-semibold">{{ selectedItems.length }}</span> templates selected.
                        <button
                            v-if="!selectAll && selectedItems.length !== data.total"
                            type="button"
                            class="rounded px-2 py-2 text-blue-600 hover:bg-blue-50"
                            @click="handleSelectAll"
                        >
                            Select all {{ data.total }} matching templates
                        </button>
                        <button
                            v-if="selectAll"
                            type="button"
                            class="rounded px-2 py-2 text-blue-600 hover:bg-blue-50"
                            @click="handleClearSelection"
                        >
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.email_template_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-600">
                        <div class="flex min-w-56 items-center">
                            <input
                                v-model="selectedItems"
                                type="checkbox"
                                :value="row.email_template_uuid"
                                class="h-4 w-4 flex-none rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                :aria-label="`Select ${formatLabel(row.template_category)}`"
                            />
                            <button
                                type="button"
                                class="ml-4 min-w-0 cursor-pointer text-left font-medium text-gray-900 hover:text-indigo-600"
                                @click="handleEditButtonClick(row.email_template_uuid)"
                            >
                                {{ formatLabel(row.template_category) }}
                            </button>
                        </div>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-600" :text="formatLabel(row.template_subcategory)" />
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-600" :text="row.template_language" />
                    <TableField class="max-w-sm px-2 py-2 text-sm text-gray-600">
                        <span class="line-clamp-2">{{ row.template_subject }}</span>
                    </TableField>

                    <!-- Default tab: version -->
                    <TableField v-if="activeTab === 'default'" class="whitespace-nowrap px-2 py-2 text-sm text-gray-600">
                        <Badge
                            :text="row.version ? `v${row.version}` : 'v1'"
                            background-color="bg-gray-50"
                            text-color="text-gray-700"
                            ring-color="ring-gray-500/20"
                        />
                    </TableField>

                    <!-- Custom tab: scope -->
                    <TableField v-if="activeTab === 'custom'" class="whitespace-nowrap px-2 py-2 text-sm text-gray-600">
                        <Badge
                            :text="row.domain_label"
                            :background-color="row.domain_uuid ? 'bg-blue-50' : 'bg-indigo-50'"
                            :text-color="row.domain_uuid ? 'text-blue-700' : 'text-indigo-700'"
                            :ring-color="row.domain_uuid ? 'ring-blue-600/20' : 'ring-indigo-600/20'"
                        />
                    </TableField>

                    <!-- Custom tab: status -->
                    <TableField v-if="activeTab === 'custom'" class="whitespace-nowrap px-2 py-2 text-sm text-gray-600">
                        <button
                            v-if="row.manageable"
                            type="button"
                            class="rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            :disabled="!permissions.update"
                            :aria-label="`Toggle status for ${formatLabel(row.template_category)}`"
                            @click="permissions.update && executeToggle([row.email_template_uuid])"
                        >
                            <Badge
                                :text="row.template_enabled ? 'Enabled' : 'Disabled'"
                                :background-color="row.template_enabled ? 'bg-green-50' : 'bg-gray-50'"
                                :text-color="row.template_enabled ? 'text-green-700' : 'text-gray-600'"
                                :ring-color="row.template_enabled ? 'ring-green-600/20' : 'ring-gray-500/20'"
                            />
                        </button>
                        <Badge
                            v-else
                            :text="row.template_enabled ? 'Enabled' : 'Disabled'"
                            :background-color="row.template_enabled ? 'bg-green-50' : 'bg-gray-50'"
                            :text-color="row.template_enabled ? 'text-green-700' : 'text-gray-600'"
                            :ring-color="row.template_enabled ? 'ring-green-600/20' : 'ring-gray-500/20'"
                        />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end">
                                <!-- Default: view + create custom override -->
                                <button
                                    v-if="activeTab === 'default'"
                                    type="button"
                                    class="rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    title="View template"
                                    @click="handleEditButtonClick(row.email_template_uuid)"
                                >
                                    <span class="sr-only">View template</span>
                                    <MagnifyingGlassIcon class="h-9 w-9 p-2" />
                                </button>
                                <button
                                    v-if="activeTab === 'default' && permissions.create"
                                    type="button"
                                    class="rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    title="Create custom override"
                                    @click="executeCopy([row.email_template_uuid])"
                                >
                                    <span class="sr-only">Create custom override</span>
                                    <DocumentDuplicateIcon class="h-9 w-9 p-2" />
                                </button>

                                <!-- Custom: edit + delete -->
                                <button
                                    v-if="activeTab === 'custom' && permissions.update && row.manageable"
                                    type="button"
                                    class="rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                    title="Edit"
                                    @click="handleEditButtonClick(row.email_template_uuid)"
                                >
                                    <span class="sr-only">Edit</span>
                                    <PencilSquareIcon class="h-9 w-9 p-2" />
                                </button>
                                <button
                                    v-if="activeTab === 'custom' && permissions.destroy && row.manageable"
                                    type="button"
                                    class="rounded-full text-gray-400 hover:bg-red-50 hover:text-red-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                                    title="Delete"
                                    @click="handleSingleItemDeleteRequest(row.email_template_uuid)"
                                >
                                    <span class="sr-only">Delete</span>
                                    <TrashIcon class="h-9 w-9 p-2" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="px-6 py-12 text-center">
                    <EnvelopeIcon class="mx-auto h-10 w-10 text-gray-300" />
                    <h3 class="mt-3 text-sm font-semibold text-gray-900">No email templates found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ activeTab === "default"
                            ? "Adjust the filters to find a default template."
                            : "Create a custom template or copy one from the default templates." }}
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :current-page="data.current_page"
                    :last-page="data.last_page"
                    :links="data.links"
                    :page-size="perPage"
                    :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true"
                    @pagination-change-page="renderRequestedPage"
                    @page-size-change="handlePageSizeChange"
                />
            </template>
        </DataTable>
    </div>

    <ConfirmationModal
        :show="confirmationModalTrigger"
        :header="confirmationHeader"
        :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel"
        cancel-button-label="Cancel"
        @close="handleModalClose"
        @confirm="confirmAction"
    />

    <EmailTemplateForm
        :show="showForm"
        :options="itemOptions"
        :mode="formMode"
        :loading="loadingForm"
        :header="formHeader"
        @close="handleFormClose"
        @error="handleErrorResponse"
        @success="showNotification"
        @refresh-data="refreshCurrentPage"
    />

    <Notification
        :show="notificationShow"
        :type="notificationType"
        :messages="notificationMessages"
        @update:show="hideNotification"
    />
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
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
import EmailTemplateForm from "./components/forms/EmailTemplateForm.vue";
import {
    ChevronDownIcon,
    ChevronUpIcon,
    DocumentDuplicateIcon,
    EnvelopeIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    TrashIcon,
} from "@heroicons/vue/24/solid";

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
const perPage = ref(props.pagination?.per_page);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const selectAll = ref(false);
const showForm = ref(false);
const formMode = ref("create");
const loadingForm = ref(false);
const itemOptions = ref({ item: {}, routes: {} });
const confirmationModalTrigger = ref(false);
const confirmAction = ref(null);
const confirmationHeader = ref("Confirm action");
const confirmationText = ref("");
const confirmationButtonLabel = ref("Continue");
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);

const tabs = [
    { id: "default", name: "Default Templates" },
    { id: "custom", name: "Custom Templates" },
];
const activeTab = ref("default");

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

const defaultLanguage = props.options?.default_language ?? null;

const filterData = ref({
    search: null,
    category: null,
    language: defaultLanguage,
    type: "default",
});

const sortData = ref({
    name: "template_category",
    order: "asc",
});

const categoryOptions = computed(() => props.options?.categories ?? []);
const languageOptions = computed(() => props.options?.languages ?? []);
const columnCount = computed(() => (activeTab.value === "default" ? 6 : 7));

const bulkActions = computed(() => {
    if (activeTab.value === "default") {
        return permissions.create
            ? [{ id: "bulk_copy", label: "Create custom from defaults", icon: "DocumentDuplicateIcon" }]
            : [];
    }

    return [
        ...(permissions.update ? [{ id: "bulk_toggle", label: "Toggle status", icon: "SyncIcon" }] : []),
        ...(permissions.destroy ? [{ id: "bulk_delete", label: "Delete", icon: "TrashIcon" }] : []),
    ];
});

const formHeader = computed(() => {
    if (formMode.value === "create") {
        return "Create Custom Email Template";
    }

    const category = formatLabel(itemOptions.value?.item?.template_category);
    const subcategory = formatLabel(itemOptions.value?.item?.template_subcategory);
    const label = [category, subcategory].filter(Boolean).join(" / ");

    if (itemOptions.value?.locked) {
        if (itemOptions.value?.item?.template_type === "default") {
            return label ? `Default Email Template: ${label}` : "Default Email Template";
        }

        return label ? `Global Email Template: ${label}` : "Global Email Template";
    }
    return label ? `Update Custom Template: ${label}` : "Update Custom Email Template";
});

onMounted(() => getData());

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

watch(activeTab, (id) => {
    filterData.value.type = id;
    handleClearSelection();
    getData(1);
});

const handleSearchButtonClick = () => {
    handleClearSelection();
    getData(1);
};

const refreshCurrentPage = () => getData(currentPage.value);

const handleFiltersReset = () => {
    filterData.value = {
        search: null,
        category: null,
        language: defaultLanguage,
        type: activeTab.value,
    };
    handleSearchButtonClick();
};

const handlePageSizeChange = (value) => {
    perPage.value = value;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) {
        return;
    }

    const parsed = new URL(url, window.location.origin);
    getData(parsed.searchParams.get("page") ?? 1);
};

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    getData(currentPage.value);
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
    itemOptions.value = { item: {}, routes: {} };
};

const handleSelectPageItems = () => {
    selectedItems.value = selectPageItems.value
        ? data.value.data.map((item) => item.email_template_uuid)
        : [];
};

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification("success", response.data.messages);
        })
        .catch(handleErrorResponse);
};

const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
};

const handleSingleItemDeleteRequest = (uuid) => {
    showConfirmation({
        header: "Delete Email Template",
        text: "Delete this email template? This action cannot be undone.",
        button: "Delete",
        action: () => executeDelete([uuid]),
    });
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_copy") {
        showConfirmation({
            header: "Create Custom Email Templates",
            text: "Create an editable custom override from each selected default template? Existing overrides are left unchanged.",
            button: "Create custom",
            action: () => executeCopy(),
        });
    }

    if (action === "bulk_toggle") {
        showConfirmation({
            header: "Toggle Email Template Status",
            text: "Toggle the enabled status of each selected email template?",
            button: "Toggle status",
            action: () => executeToggle(),
        });
    }

    if (action === "bulk_delete") {
        showConfirmation({
            header: "Delete Email Templates",
            text: "Delete the selected email templates? This action cannot be undone.",
            button: "Delete",
            action: () => executeDelete(),
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

const handleModalClose = () => {
    confirmationModalTrigger.value = false;
    confirmAction.value = null;
};

const executeCopy = (items = selectedItems.value) => executeBulkRoute(routes.copy, items);
const executeToggle = (items = selectedItems.value) => executeBulkRoute(routes.bulk_toggle, items);
const executeDelete = (items = selectedItems.value) => executeBulkRoute(routes.bulk_delete, items);

const executeBulkRoute = (route, items) => {
    axios.post(route, { items })
        .then((response) => {
            handleModalClose();
            handleClearSelection();
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
};

const formatLabel = (value) => String(value ?? "")
    .replaceAll("_", " ")
    .replaceAll("-", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

const showNotification = (type, messages) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
};

const hideNotification = () => {
    notificationShow.value = false;
};

const handleErrorResponse = (error) => {
    const messages = error?.response?.data?.messages
        ?? error?.response?.data?.errors
        ?? { error: ["An unexpected error occurred."] };

    showNotification("error", messages);
};
</script>
