<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearch" @reset-filters="resetFilters">
            <template #title>Recordings</template>

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
                        @keydown.enter="handleSearch"
                    />
                </div>
            </template>

            <template #action>
                <button
                    v-if="permissions.recording_create"
                    type="button"
                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    @click="openNewRecordingModal"
                >
                    New Recording
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
                    @bulk-action="handleBulkActionRequest"
                />
            </template>

            <template #table-header>
                <TableColumnHeader class="px-4 py-3.5 text-left text-sm font-semibold text-gray-900" :sortable="false">
                    <div class="flex items-center">
                        <input
                            v-model="selectPageItems"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600"
                            @click.stop
                        >
                        <button class="ml-9 flex items-center" @click="setSort('recording_name')">
                            <span class="mr-2">Name</span>
                            <ChevronUpIcon v-if="sortData.name === 'recording_name' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                            <ChevronDownIcon v-else-if="sortData.name === 'recording_name' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                        </button>
                    </div>
                </TableColumnHeader>

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('recording_filename')">
                        <span class="mr-2">File</span>
                        <ChevronUpIcon v-if="sortData.name === 'recording_filename' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'recording_filename' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>

                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900" />

                <TableColumnHeader class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900">
                    <button class="flex items-center" @click="setSort('insert_date')">
                        <span class="mr-2">Created</span>
                        <ChevronUpIcon v-if="sortData.name === 'insert_date' && sortData.order === 'asc'" class="h-4 w-4 text-gray-500" />
                        <ChevronDownIcon v-else-if="sortData.name === 'insert_date' && sortData.order === 'desc'" class="h-4 w-4 text-gray-500" />
                    </button>
                </TableColumnHeader>

                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-gray-900" />
            </template>

            <template v-if="selectPageItems" #current-selection>
                <td colspan="5">
                    <div class="m-2 text-center text-sm">
                        <span class="font-semibold">{{ selectedItems.length }}</span> items are selected.
                        <button
                            v-if="!selectAll && selectedItems.length !== data.total"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="handleSelectAll"
                        >
                            Select all {{ data.total }} items
                        </button>
                        <button
                            v-if="selectAll"
                            class="rounded px-2 py-2 text-blue-500 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-500 focus:bg-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-300"
                            @click="handleClearSelection"
                        >
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.recording_uuid">
                    <TableField class="px-4 py-2 text-sm text-gray-500">
                        <div class="flex items-center">
                            <input v-model="selectedItems" type="checkbox" :value="row.recording_uuid" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <div
                                class="ml-9"
                                :class="{ 'cursor-pointer hover:text-gray-900': permissions.recording_update }"
                                @click="permissions.recording_update && openEditModal(row)"
                            >
                                <span class="font-medium text-gray-900">{{ row.recording_name }}</span>
                            </div>
                        </div>
                    </TableField>

                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        <span class="break-all">{{ row.recording_filename }}</span>
                    </TableField>

                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        {{ row.recording_description || '-' }}
                    </TableField>

                    <TableField class="px-2 py-2 text-sm text-gray-500">
                        {{ formatDate(row.insert_date) }}
                    </TableField>

                    <TableField class="px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="row.file_exists && permissions.recording_play"
                                    type="button"
                                    class="rounded-full p-2 text-blue-500 transition hover:bg-blue-200 hover:text-blue-700"
                                    @click="openPlayerModal(row)"
                                >
                                    <PlayCircleIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.recording_download && row.download_url"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                    @click="downloadFile(row.download_url)"
                                >
                                    <ArrowDownTrayIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.recording_update"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                    @click="openEditModal(row)"
                                >
                                    <PencilSquareIcon class="h-5 w-5" />
                                </button>
                                <button
                                    v-if="permissions.recording_destroy"
                                    type="button"
                                    class="rounded-full p-2 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                    @click="confirmSingleDelete(row.recording_uuid)"
                                >
                                    <TrashIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </template>
                    </TableField>
                </tr>
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

    <NewGreetingForm
        :header="'New Recording'"
        :show="showNewRecordingModal"
        :routes="recordingOptions.routes"
        :voices="recordingOptions.voices"
        :default_voice="recordingOptions.default_voice"
        :speeds="recordingOptions.speeds"
        :phone_call_instructions="recordingOptions.phone_call_instructions"
        :sample_message="recordingOptions.sample_message"
        @close="showNewRecordingModal = false"
        @error="handleError"
        @success="showNotification"
        @saved="handleNewRecordingSaved"
    />

    <AddEditItemModal :show="showEditModal" :loading="formSubmitting" :header="'Edit Recording'" @close="closeModals">
        <template #modal-body>
            <form class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <label class="block text-sm font-medium text-gray-900">Name</label>
                    <input v-model="editForm.greeting_name" type="text" class="mt-2 block w-full rounded-md border-0 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600">
                    <p v-if="formErrors.greeting_name" class="mt-2 text-sm text-red-600">{{ formErrors.greeting_name[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900">Description</label>
                    <textarea v-model="editForm.greeting_description" rows="3" class="mt-2 block w-full rounded-md border-0 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-blue-600"></textarea>
                    <p v-if="formErrors.greeting_description" class="mt-2 text-sm text-red-600">{{ formErrors.greeting_description[0] }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50" @click="closeModals">Cancel</button>
                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50" :disabled="formSubmitting">
                        Save
                    </button>
                </div>
            </form>
        </template>
    </AddEditItemModal>

    <AddEditItemModal
        :show="showPlayerModal"
        :loading="false"
        :header="selectedRecording?.recording_name || 'Recording'"
        :customClass="'sm:max-w-5xl'"
        @close="showPlayerModal = false"
    >
        <template #modal-body>
            <div class="space-y-4">
                <div class="text-sm text-gray-500">
                    <div><span class="font-medium text-gray-700">File:</span> {{ selectedRecording?.recording_filename }}</div>
                    <div v-if="selectedRecording?.recording_description">
                        <span class="font-medium text-gray-700">Description:</span> {{ selectedRecording.recording_description }}
                    </div>
                </div>

                <AudioPlayer
                    v-if="selectedRecording?.play_url"
                    :url="selectedRecording.play_url"
                    :download-url="permissions.recording_download ? selectedRecording.download_url : null"
                    :file-name="selectedRecording.recording_filename || 'recording.wav'"
                />
            </div>
        </template>
    </AddEditItemModal>

    <ConfirmationModal
        :show="showDeleteConfirmationModal"
        :loading="formSubmitting"
        :header="'Confirm Deletion'"
        :text="'This action will permanently delete the selected recording(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'"
        cancel-button-label="Cancel"
        @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction"
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
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import AudioPlayer from "./components/general/AudioPlayer.vue";
import Notification from "./components/notifications/Notification.vue";
import NewGreetingForm from "./components/forms/NewGreetingForm.vue";
import { ArrowDownTrayIcon, ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon, PencilSquareIcon, PlayCircleIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
    recording_options: Object,
});

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
const formSubmitting = ref(false);
const showNewRecordingModal = ref(false);
const showEditModal = ref(false);
const showPlayerModal = ref(false);
const showDeleteConfirmationModal = ref(false);
const selectAll = ref(false);
const notificationShow = ref(false);
const notificationType = ref(null);
const notificationMessages = ref(null);
const selectedItems = ref([]);
const confirmDeleteAction = ref(() => {});

const selectedRecording = ref(null);
const editForm = ref({
    recording_uuid: null,
    greeting_name: "",
    greeting_description: "",
});
const formErrors = ref({});
const filterData = ref({ search: null });
const sortData = ref({ name: "insert_date", order: "desc" });
const recordingOptions = computed(() => props.recording_options ?? {
    routes: {},
    voices: {},
    default_voice: null,
    speeds: {},
    phone_call_instructions: [],
    sample_message: "",
});

const sortParam = computed(() => {
    return sortData.value.order === "desc" ? `-${sortData.value.name}` : sortData.value.name;
});

const bulkActions = computed(() => {
    const actions = [];

    if (props.permissions.recording_destroy) {
        actions.push({
            id: "bulk_delete",
            label: "Delete",
            icon: "TrashIcon",
        });
    }

    return actions;
});

const selectPageItems = computed({
    get() {
        return data.value.data.length > 0
            && data.value.data.every((item) => selectedItems.value.includes(item.recording_uuid));
    },
    set(value) {
        const currentPageIds = data.value.data.map((item) => item.recording_uuid);

        if (value) {
            const newSelection = new Set([...selectedItems.value, ...currentPageIds]);
            selectedItems.value = Array.from(newSelection);
            return;
        }

        selectedItems.value = selectedItems.value.filter((id) => !currentPageIds.includes(id));
        selectAll.value = false;
    },
});

onMounted(() => {
    fetchData();
});

const fetchData = (page = 1) => {
    loading.value = true;

    axios.get(props.routes.data_route, {
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

const handleSearch = () => fetchData(1);

const resetFilters = () => {
    filterData.value.search = null;
    handleClearSelection();
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
    if (!url) {
        return;
    }

    const page = Number(new URL(url, window.location.origin).searchParams.get("page") || 1);
    fetchData(page);
};

const handleBulkActionRequest = (action) => {
    if (action === "bulk_delete") {
        confirmBulkDelete();
    }
};

const openNewRecordingModal = () => {
    formErrors.value = {};
    showNewRecordingModal.value = true;
};

const handleNewRecordingSaved = () => {
    showNewRecordingModal.value = false;
    fetchData(1);
};

const openEditModal = (row) => {
    formErrors.value = {};
    editForm.value = {
        recording_uuid: row.recording_uuid,
        greeting_name: row.recording_name ?? "",
        greeting_description: row.recording_description ?? "",
    };
    showEditModal.value = true;
};

const openPlayerModal = (row) => {
    selectedRecording.value = row;
    showPlayerModal.value = true;
};

const submitEdit = () => {
    formErrors.value = {};
    formSubmitting.value = true;

    axios.put(buildItemRoute(props.routes.update, editForm.value.recording_uuid), {
        greeting_name: editForm.value.greeting_name,
        greeting_description: editForm.value.greeting_description,
    })
        .then(() => {
            closeModals();
            showSuccess("Recording updated successfully.");
            fetchData(data.value.current_page || 1);
        })
        .catch((error) => handleError(error, true))
        .finally(() => {
            formSubmitting.value = false;
        });
};

const confirmSingleDelete = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => deleteItems([uuid]);
};

const confirmBulkDelete = () => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => deleteItems(selectedItems.value);
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
            handleError(error);
        });
};

const handleClearSelection = () => {
    selectedItems.value = [];
    selectAll.value = false;
};

const deleteItems = (items) => {
    formSubmitting.value = true;

    axios.post(props.routes.bulk_delete, { items })
        .then(() => {
            closeModals();
            handleClearSelection();
            showSuccess("Recording deleted successfully.");
            fetchData(data.value.current_page || 1);
        })
        .catch(handleError)
        .finally(() => {
            formSubmitting.value = false;
        });
};

const closeModals = () => {
    showNewRecordingModal.value = false;
    showEditModal.value = false;
    showPlayerModal.value = false;
    showDeleteConfirmationModal.value = false;
    formErrors.value = {};
};

const showSuccess = (message) => {
    notificationType.value = "success";
    notificationMessages.value = { success: [message] };
    notificationShow.value = true;
};

const showNotification = (typeOrMessages, maybeMessages = null) => {
    notificationType.value = maybeMessages ? typeOrMessages : "success";
    notificationMessages.value = maybeMessages ?? typeOrMessages;
    notificationShow.value = true;
};

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
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

    if (payload?.errors) {
        return payload.errors;
    }

    if (payload?.messages) {
        return payload.messages;
    }

    if (payload?.message) {
        return { request: [payload.message] };
    }

    if (error?.message) {
        return { request: [error.message] };
    }

    return { request: ["An unexpected error occurred."] };
};

const buildItemRoute = (template, uuid) => template.replace("__RECORDING__", uuid);

const downloadFile = (url) => {
    const link = document.createElement("a");
    link.href = url;
    document.body.appendChild(link);
    link.click();
    link.remove();
};

const formatDate = (value) => {
    if (!value) {
        return "-";
    }

    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};
</script>
