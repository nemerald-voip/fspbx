<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="fetchData(1)" @reset-filters="resetFilters">
            <template #title>Music on Hold</template>

            <template #subtitle>
                Manage hold music streams and audio files.
            </template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                    </div>
                    <input
                        v-model="filterData.search"
                        type="text"
                        class="block w-full rounded-md border-0 py-1.5 pl-10 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600"
                        placeholder="Search"
                        @keydown.enter="fetchData(1)"
                    />
                </div>
            </template>

            <template #action>
                <button
                    v-if="permissions.create"
                    type="button"
                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="openUploadModal"
                >
                    Upload
                </button>

                <button
                    v-if="permissions.create"
                    type="button"
                    class="ml-2 sm:ml-4 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    @click="openCreateForm"
                >
                    Create
                </button>

                <button
                    v-if="permissions.view_all && filterData.showGlobal"
                    type="button"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="showLocal"
                >
                    Show local
                </button>

                <button
                    v-else-if="permissions.view_all"
                    type="button"
                    class="ml-2 sm:ml-4 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                    @click="showAll"
                >
                    Show all
                </button>
            </template>

            <template #navigation>
                <Paginator
                    :previous="data.prev_page_url"
                    :next="data.next_page_url"
                    :from="data.from"
                    :to="data.to"
                    :total="data.total"
                    :currentPage="data.current_page"
                    :lastPage="data.last_page"
                    :links="data.links"
                    :bulk-actions="bulkActions"
                    :has-selected-items="selectedItems.length > 0"
                    @pagination-change-page="changePage"
                    @bulk-action="handleBulkAction"
                />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <div class="flex items-center">
                        <input v-model="selectPageItems" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                        <button class="ml-4 flex items-center" @click="setSort('music_on_hold_name')">
                            <span class="mr-2">Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'music_on_hold_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'music_on_hold_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>
                </TableColumnHeader>
                <TableColumnHeader v-if="filterData.showGlobal" header="Domain" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Rate" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Options" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="Files" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td :colspan="columnCount">
                    <div class="m-2 text-center text-sm">
                        <span class="font-semibold">{{ selectedItems.length }}</span> streams are selected.
                        <button
                            v-if="!selectAll && selectedItems.length !== data.total"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="handleSelectAll"
                        >
                            Select all {{ data.total }} streams
                        </button>
                        <button
                            v-if="selectAll"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="clearSelection"
                        >
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <template v-for="row in data.data" :key="row.music_on_hold_uuid">
                    <tr>
                        <TableField class="px-4 py-2 text-sm text-gray-500">
                            <div class="flex items-center">
                                <input v-model="selectedItems" type="checkbox" :value="row.music_on_hold_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                <div class="ml-4 min-w-0">
                                    <button
                                        type="button"
                                        class="font-medium text-gray-900"
                                        :class="{ 'cursor-pointer hover:text-indigo-600': permissions.update }"
                                        @click="permissions.update && openEditForm(row.music_on_hold_uuid)"
                                    >
                                        {{ row.music_on_hold_name }}
                                    </button>
                                    <div v-if="permissions.view_path && row.music_on_hold_path" class="mt-1 max-w-md truncate text-xs text-gray-400">
                                        {{ row.music_on_hold_path }}
                                    </div>
                                </div>
                            </div>
                        </TableField>

                        <TableField v-if="filterData.showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.domain_label" />
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.rate_label" />
                        <TableField class="px-2 py-2 text-sm text-gray-500">
                            <div class="flex flex-wrap gap-1">
                                <Badge :text="row.music_on_hold_shuffle === 'true' ? 'Shuffle' : 'Ordered'" v-bind="row.music_on_hold_shuffle === 'true' ? blueBadge : grayBadge" />
                                <Badge :text="row.music_on_hold_channels === '2' ? 'Stereo' : 'Mono'" v-bind="grayBadge" />
                                <Badge v-if="row.music_on_hold_chime_list" text="Chime" v-bind="amberBadge" />
                            </div>
                        </TableField>
                        <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="`${row.files.length} file${row.files.length === 1 ? '' : 's'}`" />
                        <TableField class="px-2 py-1 text-sm text-gray-500">
                            <template #action-buttons>
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        v-if="permissions.update"
                                        type="button"
                                        class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                        title="Edit"
                                        @click="openEditForm(row.music_on_hold_uuid)"
                                    >
                                        <PencilSquareIcon class="h-5 w-5" />
                                    </button>
                                    <button
                                        v-if="permissions.destroy"
                                        type="button"
                                        class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                        title="Delete"
                                        @click="confirmStreamDelete([row.music_on_hold_uuid])"
                                    >
                                        <TrashIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </template>
                        </TableField>
                    </tr>

                    <tr>
                        <td :colspan="columnCount" class="bg-gray-50 px-8 py-3">
                            <div v-if="row.files.length" class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <tbody class="divide-y divide-gray-200">
                                        <tr v-for="file in row.files" :key="`${row.music_on_hold_uuid}-${file.name}`">
                                            <td class="py-2 pr-4 text-sm text-gray-700">
                                                <span class="break-all">{{ file.name }}</span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-right text-sm text-gray-500">
                                                {{ file.size_label }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2 text-right text-sm text-gray-500">
                                                {{ formatDate(file.modified_at) }}
                                            </td>
                                            <td class="whitespace-nowrap py-1 pl-4 text-right">
                                                <button type="button" class="rounded-full p-2 text-blue-500 transition hover:bg-blue-100 hover:text-blue-700" title="Play" @click="openPlayer(row, file)">
                                                    <PlayCircleIcon class="h-5 w-5" />
                                                </button>
                                                <button type="button" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600" title="Download" @click="downloadFile(file.download_url)">
                                                    <ArrowDownTrayIcon class="h-5 w-5" />
                                                </button>
                                                <button
                                                    v-if="permissions.destroy"
                                                    type="button"
                                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                                    title="Delete"
                                                    @click="confirmFileDelete(row, file)"
                                                >
                                                    <TrashIcon class="h-5 w-5" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-sm text-gray-500">No audio files in this stream.</div>
                        </td>
                    </tr>
                </template>
            </template>

            <template #empty>
                <div v-if="!loading && data.data.length === 0" class="text-center my-5">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">Adjust your search and try again.</p>
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
                    :currentPage="data.current_page"
                    :lastPage="data.last_page"
                    :links="data.links"
                    @pagination-change-page="changePage"
                />
            </template>
        </DataTable>
    </div>

    <MusicOnHoldForm
        :show="showForm"
        :loading="loadingForm"
        :header="formHeader"
        :mode="formMode"
        :routes="routes"
        :options="itemOptions"
        @close="closeForm"
        @error="handleError"
        @success="showNotification"
        @refresh-data="refreshCurrentPage"
    />

    <AddEditItemModal :show="showUploadModal" :loading="loadingForm || formSubmitting" header="Upload Music on Hold" custom-class="sm:max-w-2xl" @close="closeUploadModal">
        <template #modal-body>
            <Vueform :key="uploadFormKey" ref="uploadForm$" :endpoint="false" :default="uploadDefaultValues">
                <SelectElement name="music_on_hold_uuid" :items="uploadStreamOptions" label="Stream"
                    :native="false" :floating="false" :columns="{ container: 12, sm: 6 }" />

                <SelectElement v-if="permissions.manage_domain" name="domain_uuid" :items="itemOptions.domains"
                    label="Domain" :native="false" :floating="false" :columns="{ container: 12, sm: 6 }"
                    :conditions="[() => !selectedUploadStreamUuid]" />

                <TextElement name="music_on_hold_name" label="New Stream Name" :floating="false"
                    :columns="{ container: 12, sm: 6 }" :error="formErrors.music_on_hold_name?.[0]"
                    :conditions="[() => !selectedUploadStreamUuid]" />

                <SelectElement name="music_on_hold_rate" :items="itemOptions.rates" label="Rate"
                    :native="false" :floating="false" :columns="{ container: 12, sm: 6 }"
                    :conditions="[() => !selectedUploadStreamUuid]" />

                <FileElement name="file" label="Audio File" accept=".wav,.mp3,.ogg"
                    description="Supported formats: WAV, MP3, or OGG" :upload-temp-endpoint="false"
                    :remove-temp-endpoint="false" :remove-endpoint="false" :drop="true"
                    :error="formErrors.file?.[0]" @change="handleVueformFileUpload"
                    :columns="{ container: 12 }" />

                <GroupElement name="button_container" />

                <ButtonElement name="cancel" :secondary="true" :submits="false" align="right"
                    :columns="{ container: 12, sm: 6 }" @click="closeUploadModal">
                    Cancel
                </ButtonElement>

                <ButtonElement name="upload" :loading="formSubmitting" :submits="false" align="right"
                    :columns="{ container: 12, sm: 6 }" @click="submitUpload">
                    Upload
                </ButtonElement>
            </Vueform>
        </template>
    </AddEditItemModal>

    <AddEditItemModal :show="showPlayerModal" :loading="false" :header="selectedFile?.name || 'Music on Hold'" custom-class="sm:max-w-3xl" @close="showPlayerModal = false">
        <template #modal-body>
            <AudioPlayer
                v-if="selectedFile?.download_url"
                :url="selectedFile.download_url"
                :download-url="selectedFile.download_url"
                :file-name="selectedFile.name"
            />
        </template>
    </AddEditItemModal>

    <ConfirmationModal
        :show="showConfirmationModal"
        :loading="formSubmitting"
        :header="confirmationHeader"
        :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel"
        cancel-button-label="Cancel"
        @close="showConfirmationModal = false"
        @confirm="confirmAction"
    />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="hideNotification" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import Loading from "./components/general/Loading.vue";
import Badge from "./components/general/Badge.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Notification from "./components/notifications/Notification.vue";
import AudioPlayer from "./components/general/AudioPlayer.vue";
import MusicOnHoldForm from "./components/forms/MusicOnHoldForm.vue";
import { ArrowDownTrayIcon, ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, PencilSquareIcon, PlayCircleIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;

const data = ref({
    data: [],
    current_page: 1,
    from: 0,
    last_page: 1,
    links: [],
    next_page_url: null,
    prev_page_url: null,
    to: 0,
    total: 0,
});

const loading = ref(false);
const loadingForm = ref(false);
const formSubmitting = ref(false);
const showForm = ref(false);
const showUploadModal = ref(false);
const showPlayerModal = ref(false);
const showConfirmationModal = ref(false);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const selectedItems = ref([]);
const selectAll = ref(false);
const formMode = ref("create");
const formErrors = ref({});
const selectedFile = ref(null);
const uploadForm$ = ref(null);
const uploadFormKey = ref(0);
const uploadFile = ref(null);
const confirmAction = ref(() => {});
const confirmationHeader = ref("Are you sure?");
const confirmationText = ref("");
const confirmationButtonLabel = ref("Continue");
const filterData = ref({ search: null, showGlobal: false });
const sortData = ref({ name: "music_on_hold_name", order: "asc" });

const itemOptions = ref({
    item: {},
    rates: [],
    domains: [],
    current_domain_uuid: null,
    streams: [],
    chime_options: [],
});

const grayBadge = { backgroundColor: "bg-gray-50", textColor: "text-gray-700", ringColor: "ring-gray-600/20" };
const blueBadge = { backgroundColor: "bg-blue-50", textColor: "text-blue-700", ringColor: "ring-blue-600/20" };
const amberBadge = { backgroundColor: "bg-amber-50", textColor: "text-amber-700", ringColor: "ring-amber-600/20" };

const columnCount = computed(() => filterData.value.showGlobal ? 6 : 5);
const formHeader = computed(() => formMode.value === "create"
    ? "Create Music on Hold"
    : `Update Music on Hold - ${itemOptions.value?.item?.music_on_hold_name || "Loading..."}`);

const sortParam = computed(() => sortData.value.order === "desc" ? `-${sortData.value.name}` : sortData.value.name);
const uploadStreamOptions = computed(() => [
    { label: "New stream", value: "" },
    ...(itemOptions.value.streams ?? []),
]);

const uploadDefaultValues = computed(() => ({
    music_on_hold_uuid: "",
    music_on_hold_name: "",
    domain_uuid: itemOptions.value.current_domain_uuid
        ?? itemOptions.value.domains.find((domain) => domain.value)?.value
        ?? null,
    music_on_hold_rate: null,
}));

const selectedUploadStreamUuid = computed(() => uploadForm$.value?.data?.music_on_hold_uuid ?? "");

const bulkActions = computed(() => {
    if (!permissions.destroy) {
        return [];
    }

    return [{ id: "bulk_delete", label: "Delete", icon: "TrashIcon" }];
});

const selectPageItems = computed({
    get() {
        return data.value.data.length > 0
            && data.value.data.every((item) => selectedItems.value.includes(item.music_on_hold_uuid));
    },
    set(value) {
        const currentPageIds = data.value.data.map((item) => item.music_on_hold_uuid);

        if (value) {
            selectedItems.value = Array.from(new Set([...selectedItems.value, ...currentPageIds]));
            return;
        }

        selectedItems.value = selectedItems.value.filter((id) => !currentPageIds.includes(id));
        selectAll.value = false;
    },
});

onMounted(() => fetchData());

const fetchData = (page = 1) => {
    loading.value = true;

    axios.get(routes.data_route, {
        params: {
            filter: filterData.value,
            sort: sortParam.value,
            page,
        },
    })
        .then((response) => {
            data.value = response.data;
        })
        .catch(handleError)
        .finally(() => {
            loading.value = false;
        });
};

const refreshCurrentPage = () => fetchData(data.value.current_page || 1);

const resetFilters = () => {
    filterData.value.search = null;
    clearSelection();
    fetchData(1);
};

const setSort = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === "asc" ? "desc" : "asc";
    } else {
        sortData.value.name = column;
        sortData.value.order = "asc";
    }

    fetchData(1);
};

const changePage = (url) => {
    if (!url) return;
    fetchData(Number(new URL(url, window.location.origin).searchParams.get("page") || 1));
};

const showAll = () => {
    filterData.value.showGlobal = true;
    clearSelection();
    fetchData(1);
};

const showLocal = () => {
    filterData.value.showGlobal = false;
    clearSelection();
    fetchData(1);
};

const openCreateForm = () => {
    formMode.value = "create";
    showForm.value = true;
    getItemOptions();
};

const openEditForm = (uuid) => {
    formMode.value = "update";
    showForm.value = true;
    getItemOptions(uuid);
};

const closeForm = () => {
    showForm.value = false;
    formMode.value = "create";
    resetItemOptions();
};

const openUploadModal = () => {
    formErrors.value = {};
    showUploadModal.value = true;
    resetUploadForm();
    getItemOptions();
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    formErrors.value = {};
    resetUploadForm();
};

const getItemOptions = (itemUuid = null) => {
    loadingForm.value = true;

    axios.post(routes.item_options, itemUuid ? { itemUuid, filter: filterData.value } : { filter: filterData.value })
        .then((response) => {
            itemOptions.value = response.data;
        })
        .catch((error) => {
            closeForm();
            closeUploadModal();
            handleError(error);
        })
        .finally(() => {
            loadingForm.value = false;
        });
};

const submitUpload = () => {
    formErrors.value = {};
    formSubmitting.value = true;
    const uploadData = uploadForm$.value?.data ?? {};

    const requestData = new FormData();
    requestData.append("music_on_hold_uuid", uploadData.music_on_hold_uuid || "");
    requestData.append("music_on_hold_name", uploadData.music_on_hold_name || "");
    requestData.append("domain_uuid", uploadData.domain_uuid || "");
    requestData.append("music_on_hold_rate", uploadData.music_on_hold_rate || "");
    if (uploadFile.value) {
        requestData.append("file", uploadFile.value);
    }

    axios.post(routes.upload, requestData, { headers: { "Content-Type": "multipart/form-data" } })
        .then((response) => {
            closeUploadModal();
            showNotification("success", response.data.messages);
            fetchData(1);
        })
        .catch((error) => handleError(error, true))
        .finally(() => {
            formSubmitting.value = false;
        });
};

const handleVueformFileUpload = (newValue) => {
    uploadFile.value = newValue instanceof File ? newValue : null;
};

const handleBulkAction = (action) => {
    if (action === "bulk_delete") {
        confirmStreamDelete(selectedItems.value);
    }
};

const confirmStreamDelete = (items) => {
    showConfirmationModal.value = true;
    confirmationHeader.value = "Confirm Deletion";
    confirmationText.value = "This action will permanently delete the selected music on hold stream(s).";
    confirmationButtonLabel.value = "Delete";
    confirmAction.value = () => deleteStreams(items);
};

const confirmFileDelete = (stream, file) => {
    showConfirmationModal.value = true;
    confirmationHeader.value = "Confirm File Deletion";
    confirmationText.value = `Delete ${file.name}?`;
    confirmationButtonLabel.value = "Delete";
    confirmAction.value = () => deleteFile(stream, file);
};

const deleteStreams = (items) => {
    formSubmitting.value = true;

    axios.post(routes.bulk_delete, { items })
        .then((response) => {
            closeConfirmation();
            clearSelection();
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch(handleError)
        .finally(() => {
            formSubmitting.value = false;
        });
};

const deleteFile = (stream, file) => {
    formSubmitting.value = true;

    axios.post(routes.file_delete, {
        music_on_hold_uuid: stream.music_on_hold_uuid,
        file: file.name,
    })
        .then((response) => {
            closeConfirmation();
            showNotification("success", response.data.messages);
            refreshCurrentPage();
        })
        .catch(handleError)
        .finally(() => {
            formSubmitting.value = false;
        });
};

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification("success", response.data.messages);
        })
        .catch((error) => {
            clearSelection();
            handleError(error);
        });
};

const clearSelection = () => {
    selectedItems.value = [];
    selectAll.value = false;
};

const openPlayer = (stream, file) => {
    selectedFile.value = {
        ...file,
        stream: stream.music_on_hold_name,
    };
    showPlayerModal.value = true;
};

const downloadFile = (url) => {
    const link = document.createElement("a");
    link.href = url;
    link.download = "";
    document.body.appendChild(link);
    link.click();
    link.remove();
};

const closeConfirmation = () => {
    showConfirmationModal.value = false;
    confirmAction.value = () => {};
};

const resetItemOptions = () => {
    itemOptions.value = { item: {}, rates: [], domains: [], current_domain_uuid: null, streams: [], chime_options: [] };
};

const resetUploadForm = () => {
    uploadFile.value = null;
    uploadFormKey.value++;
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
};

const showNotification = (type, messages = null) => {
    notificationType.value = messages ? type : "success";
    notificationMessages.value = messages ?? type;
    notificationShow.value = true;
};

const handleError = (error, keepModalOpen = false) => {
    if (error?.response?.data?.errors) {
        formErrors.value = error.response.data.errors;
    } else if (!keepModalOpen) {
        formErrors.value = {};
    }

    notificationType.value = "error";
    notificationMessages.value = normalizeMessages(error);
    notificationShow.value = true;
};

const normalizeMessages = (error) => {
    const payload = error?.response?.data;
    if (payload?.errors) return payload.errors;
    if (payload?.messages) return payload.messages;
    if (payload?.message) return { request: [payload.message] };
    if (error?.message) return { request: [error.message] };

    return { request: ["An unexpected error occurred."] };
};

const formatDate = (value) => {
    if (!value) return "-";
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};
</script>
