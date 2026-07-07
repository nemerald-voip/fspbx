<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Extensions</template>

            <template #filters>
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
            </template>

            <template #action>
                <button v-if="permissions.extension_create" type="button"
                    @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    Create
                </button>

                <button v-if="permissions.extension_import"
                    type="button" @click.prevent="handleImportButtonClick()"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    <DocumentArrowUpIcon class="h-5 w-5" aria-hidden="true" />
                    Import CSV
                </button>
                <button type="button"
                    v-if="permissions.extension_export"
                    @click.prevent="exportExtensionsCsv()"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    <DocumentArrowDownIcon class="h-5 w-5" aria-hidden="true" />
                    Export CSV
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>


            <template #table-header>
                <!-- Checkbox + Extension column -->
            <TableColumnHeader
                class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-heading items-center justify-start">
                <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems"
                    class="h-4 w-4 rounded border-strong text-accent-fg">

                <div class="flex items-center cursor-pointer select-none pl-14"
                    @click="handleSortRequest('extension')">
                    <span class="mr-2">Extension</span>
                    <ChevronUpIcon
                        v-if="sortData.name === 'extension' && sortData.order === 'asc'"
                        class="h-4 w-4 text-muted" />
                    <ChevronDownIcon
                        v-else-if="sortData.name === 'extension' && sortData.order === 'desc'"
                        class="h-4 w-4 text-muted" />
                </div>
            </TableColumnHeader>

                <TableColumnHeader header="Email"
                    class="hidden px-2 py-3.5 text-left text-sm font-semibold text-heading sm:table-cell" />
                <TableColumnHeader header=""
                    class="whitespace-nowrap hidden px-2 py-3.5...text-left text-sm font-semibold text-heading md:table-cell">
                    <div class="flex items-center cursor-pointer select-none"
                        @click="handleSortRequest('outbound_caller_id_number')">
                        <span class="mr-2">Outbound Caller ID</span>
                        <ChevronUpIcon
                            v-if="sortData.name === 'outbound_caller_id_number' && sortData.order === 'asc'"
                            class="h-4 w-4 text-muted" />
                        <ChevronDownIcon
                            v-else-if="sortData.name === 'outbound_caller_id_number' && sortData.order === 'desc'"
                            class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader header=""
                    class="hidden px-2 py-3.5 text-left text-sm font-semibold text-heading lg:table-cell">
                    <div class="flex items-center cursor-pointer select-none"
                        @click="handleSortRequest('description')">
                        <span class="mr-2">Description</span>
                        <ChevronUpIcon v-if="sortData.name === 'description' && sortData.order === 'asc'"
                            class="h-4 w-4 text-muted" />
                        <ChevronDownIcon v-else-if="sortData.name === 'description' && sortData.order === 'desc'"
                            class="h-4 w-4 text-muted" />
                    </div>
                </TableColumnHeader>
                <TableColumnHeader header="Services"
                    class="hidden px-2 py-3.5 text-left text-sm font-semibold text-heading lg:table-cell" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-heading" />
            </template>


            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="9">
                    <div class="text-sm text-center m-2">
                        <span class="font-semibold ">{{ selectedItems.length }} </span> items are selected.
                        <button v-if="!selectAll && selectedItems.length != data.total"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle  hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                            @click="handleSelectAll">
                            Select all {{ data.total }} items
                        </button>
                        <button v-if="selectAll"
                            class="text-info rounded py-2 px-2 hover:bg-info-subtle  hover:text-info focus:outline-none focus:ring-1 focus:bg-info-subtle focus:ring-focus transition duration-500 ease-in-out"
                            @click="handleClearSelection">
                            Clear selection
                        </button>
                    </div>
                </td>
            </template>


            <template #table-body>
                <template v-for="row in data.data" :key="row.extension_uuid">

                    <tr>
                        <!-- Checkbox + Extension -->
                        <TableField class="whitespace-nowrap px-4 py-2 text-sm text-muted">
                            <div class="flex items-center gap-5">
                                <input v-if="row.extension_uuid" v-model="selectedItems" type="checkbox"
                                    name="action_box[]" :value="row.extension_uuid"
                                    class="h-4 w-4 rounded border-strong text-accent-fg">
                                <span
                                    v-if="!isRegsLoading && registrations && Array.isArray(registrations[String(row.extension)])"
                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-success text-on-accent text-xs cursor-pointer focus:outline-none"
                                    :title="`${registrations[String(row.extension)].length} device(s) registered`"
                                    @click="toggleExpand(row.extension_uuid)">
                                    {{ registrations[String(row.extension)].length }}
                                </span>
                                <span v-else
                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-surface-3 text-body text-xs"
                                    title="Not registered" @click="toggleExpand(row.extension_uuid)">
                                </span>

                                <div :class="{ 'cursor-pointer hover:text-heading': permissions.extension_update, }"
                                    @click="permissions.extension_update && handleEditButtonClick(row.extension_uuid)">
                                    <span class="flex flex-col lg:flex-row items-start gap-2">
                                        {{ row.name_formatted }}
                                        <span class="italic text-xs sm:hidden"> {{ row.email || '' }}</span>
                                        <Badge v-if="row.suspended" :text="'Suspended'" :backgroundColor="'bg-danger-subtle'"
                                            :textColor="'text-danger'" ringColor="ring-danger/20"
                                            class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.do_not_disturb == 'true' && !row.suspended" :text="'DND'"
                                            :backgroundColor="'bg-danger-subtle'" :textColor="'text-danger'"
                                            ringColor="ring-danger/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.forward_all_enabled == 'true'" :text="'FWD All'"
                                            :backgroundColor="'bg-info-subtle'" :textColor="'text-info'"
                                            ringColor="ring-info/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.forward_busy_enabled == 'true'" :text="'FWD Busy'"
                                            :backgroundColor="'bg-info-subtle'" :textColor="'text-info'"
                                            ringColor="ring-info/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.forward_no_answer_enabled == 'true'" :text="'FWD no Ans'"
                                            :backgroundColor="'bg-info-subtle'" :textColor="'text-info'"
                                            ringColor="ring-info/20" class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.forward_user_not_registered_enabled == 'true'"
                                            :text="'FWD no Reg'" :backgroundColor="'bg-info-subtle'"
                                            :textColor="'text-info'" ringColor="ring-info/20"
                                            class="px-2 py-1 text-xs" />
                                        <Badge v-if="row.follow_me_enabled == 'true'" :text="'Sequence'"
                                            :backgroundColor="'bg-info-subtle'" :textColor="'text-info'"
                                            ringColor="ring-info/20" class="px-2 py-1 text-xs" />
                                    </span>

                                </div>
                            </div>
                        </TableField>


                        <!-- Email -->
                        <TableField class="hidden px-2 py-2 text-sm text-muted sm:table-cell">
                            {{ row.email || '' }}
                        </TableField>
                        <!-- Outbound Caller ID -->
                        <TableField class="whitespace-nowrap hidden spx-2 py-2 text-sm text-muted md:table-cell">
                            {{ row.outbound_caller_id_number_formatted || row.outbound_caller_id_number || '' }}

                        </TableField>
                        <!-- Description -->
                        <TableField class="hidden px-2 py-2 text-sm text-muted lg:table-cell">
                            {{ row.description }}
                        </TableField>

                        <TableField class="hidden whitespace-nowrap px-2 py-1 text-sm text-muted lg:table-cell">

                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap">
                                    <div v-if="String(row.mobile_app?.status) === '1'"
                                        class="group relative inline-block cursor-help focus:outline-none"
                                        tabindex="0">
                                        <span class="relative inline-flex">
                                            <DevicePhoneMobileSolidIcon
                                                class="h-5 w-5 text-blue-400 hover:text-blue-600 active:bg-blue-300"
                                                aria-label="Mobile App (Activated)" />
                                            <span v-if="ringotelStatusFor(row)"
                                                :class="ringotelDotClass(ringotelStatusFor(row))"
                                                class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white shadow-sm">
                                            </span>
                                        </span>
                                        <div
                                            class="invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus:visible group-focus:opacity-100 transition-opacity duration-300 absolute z-50 bottom-full left-1/2 -translate-x-1/2 pb-2">
                                            <div class="relative w-64 max-w-xs px-3 py-2 text-xs leading-relaxed text-white bg-gray-900 rounded shadow-lg whitespace-normal cursor-text select-text">
                                                {{ mobileAppTooltip(row) }}
                                                <div
                                                    class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="String(row.mobile_app?.status) === '-1'"
                                        class="group relative inline-block cursor-help focus:outline-none"
                                        tabindex="0">
                                        <DevicePhoneMobileIcon
                                            class="h-5 w-5 text-subtle hover:text-body active:bg-surface-3"
                                            aria-label="Mobile App (Phonebook Only)" />
                                        <div
                                            class="invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus:visible group-focus:opacity-100 transition-opacity duration-300 absolute z-50 bottom-full left-1/2 -translate-x-1/2 pb-2">
                                            <div class="relative w-64 max-w-xs px-3 py-2 text-xs leading-relaxed text-white bg-gray-900 rounded shadow-lg whitespace-normal cursor-text select-text">
                                                Mobile App (Phonebook Only)
                                                <div
                                                    class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <ejs-tooltip v-if="!!row.user_record" :content="'Record Calls'"
                                        position='TopCenter'>
                                        <MicrophoneIcon
                                            class="h-5 w-5 text-danger hover:text-danger active:bg-danger-subtle"
                                            aria-label="Record Calls" />
                                    </ejs-tooltip>

                                </div>
                            </template>


                        </TableField>

                        <TableField class="whitespace-nowrap px-2 py-1 text-sm text-muted">

                            <template #action-buttons>
                                <div class="flex items-center whitespace-nowrap justify-end">

                                    <ejs-tooltip v-if="permissions.extension_update" :content="'Edit'"
                                        position='TopCenter' target="#destination_tooltip_target">
                                        <div id="destination_tooltip_target">
                                            <PencilSquareIcon @click="handleEditButtonClick(row.extension_uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />

                                        </div>
                                    </ejs-tooltip>

                                    <ejs-tooltip v-if="permissions.extension_destroy" :content="'Delete'"
                                        position='TopCenter' target="#delete_tooltip_target">
                                        <div id="delete_tooltip_target">
                                            <TrashIcon @click="handleSingleItemDeleteRequest(row.extension_uuid)"
                                                class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                                        </div>
                                    </ejs-tooltip>

                                    <AdvancedActionButton :actions="advancedActions"
                                        @advanced-action="(action) => handleAdvancedActionRequest(action, row.extension_uuid)" />

                                </div>
                            </template>


                        </TableField>
                    </tr>

                    <!-- EXPANDABLE ROW -->
                    <tr v-if="expandedExtension === row.extension_uuid">
                        <td :colspan="5" class="bg-surface-2 px-6 py-4">
                            <div
                                v-if="registrations && Array.isArray(registrations[String(row.extension)]) && registrations[String(row.extension)].length">
                                <div class="ml-9 space-y-2 text-sm text-muted">
                                    <div v-for="(reg, idx) in registrations[String(row.extension)]" :key="idx"
                                        class="flex flex-col md:flex-row gap-4 border-b last:border-0 pb-2">
                                        <div><span class="font-semibold">Device:</span> {{ reg.agent }}</div>
                                        <div><span class="font-semibold">Remote IP Address:</span> {{ reg.wan_ip }}
                                        </div>
                                        <div><span class="font-semibold">Connection Type:</span> {{ reg.transport }}
                                        </div>
                                        <div><span class="font-semibold">Expires in:</span> {{ reg.expsecs }}s</div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-subtle text-sm ">No registered devices found.</div>
                        </td>
                    </tr>
                </template>
            </template>




            <template #empty>
                <!-- Conditional rendering for 'no records' message -->
                <div v-if="data.data.length === 0" class="text-center my-5 ">
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-subtle" />
                    <h3 class="mt-2 text-sm font-semibold text-heading">No results found</h3>
                    <p class="mt-1 text-sm text-muted">
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
                    :page-size="perPage" :page-size-options="props.pagination?.per_page_options ?? []"
                    :show-page-size-selector="true"
                    @pagination-change-page="renderRequestedPage" @page-size-change="handlePageSizeChange" />
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>

    <UpdateExtensionForm :show="showUpdateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Update Extension - ' + (itemOptions?.item?.name_formatted ?? 'loading')"
        @close="showUpdateModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" />

    <CreateExtensionForm :show="showCreateModal" :options="itemOptions" :loading="isModalLoading"
        :header="'Create Extension'" @close="showCreateModal = false" @error="handleErrorResponse"
        @success="showNotification" @open-edit-form="handleEditButtonClick" @refresh-data="refreshCurrentPage" />

    <BulkUpdateExtensionForm :items="selectedItems" :options="itemOptions" :show="bulkUpdateModalTrigger"
        :header="'Bulk Update'" :loading="isModalLoading" @close="handleModalClose"
        @error="handleErrorResponse" @success="showNotification" @refresh-data="refreshCurrentPage" />

    <ConfirmationModal 
        :show="showDeleteConfirmationModal" 
        @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" 
        :header="'Confirm Deletion'" 
        :loading="isModalLoading"
        :confirm-button-label="'Delete'" 
        cancel-button-label="Cancel" 
    >
        <div>
            <p class="text-sm text-muted mb-5">
                This action will permanently delete the selected extension(s). Are you sure you want to proceed?
            </p>
            
            <div class="flex items-center bg-surface-2 p-3 rounded-md border border-default">
                <input 
                    id="retain_voicemail" 
                    v-model="retainVoicemail" 
                    type="checkbox" 
                    class="h-4 w-4 rounded border-strong text-accent-fg focus:ring-focus cursor-pointer"
                >
                <label for="retain_voicemail" class="ml-3 block text-sm font-medium text-body cursor-pointer">
                    Retain voicemail (convert to team inbox)
                </label>
            </div>
        </div>
    </ConfirmationModal>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <UploadModal :show="showUploadModal" @close="showUploadModal = false" :header="'Upload File'" @upload="uploadFile"
        @download-template="downloadTemplateFile" :is-submitting="isUploadingFile" :errors="uploadErrors" />
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import axios from 'axios';
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Loading from "./components/general/Loading.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon, ChevronUpIcon, ChevronDownIcon, DevicePhoneMobileIcon as DevicePhoneMobileSolidIcon } from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import AdvancedActionButton from "./components/general/AdvancedActionButton.vue";
import MainLayout from "../Layouts/MainLayout.vue";
import CreateExtensionForm from "./components/forms/CreateExtensionForm.vue";
import UpdateExtensionForm from "./components/forms/UpdateExtensionForm.vue";
import BulkUpdateExtensionForm from "./components/forms/BulkUpdateExtensionForm.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";
import { MicrophoneIcon } from "@heroicons/vue/24/outline";
import UploadModal from "./components/modal/UploadModal.vue";
import { DocumentArrowUpIcon, DocumentArrowDownIcon, DevicePhoneMobileIcon } from "@heroicons/vue/24/outline";


const loading = ref(false)
const isModalLoading = ref(false)
const currentPage = ref(1)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showCreateModal = ref(false);
const showUpdateModal = ref(false);
const bulkUpdateModalTrigger = ref(false);
const confirmDeleteAction = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDeleteConfirmationModal = ref(false);
const isRegsLoading = ref(false)
const isRingotelLoading = ref(false)
const showUploadModal = ref(false);
const isUploadingFile = ref(null);
const uploadErrors = ref(null);

const props = defineProps({
    routes: Object,
    permissions: Object,
    pagination: Object,
});

const perPage = ref(props.pagination?.per_page);

const permissions = computed(() => props.permissions ?? {});

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

// onMounted(() => {
//     handleSearchButtonClick();
// })

const filterData = ref({
    search: null,
});

const sortData = ref({
    name: 'extension',
    order: 'asc',
});

const itemOptions = ref({})
const registrations = ref({})
const ringotelStatuses = ref({})
const expandedExtension = ref(null)


const toggleExpand = (extension_uuid) => {
    expandedExtension.value = expandedExtension.value === extension_uuid ? null : extension_uuid
}

// Computed property for bulk actions based on permissions
const bulkActions = computed(() => {
    const actions = [];

    if (permissions.value.extension_update) {
        actions.push({
            id: 'bulk_update',
            label: 'Edit',
            icon: 'PencilSquareIcon'
        });
    }

    // Conditionally add the delete action if permission is granted
    if (permissions.value.extension_destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

const advancedActions = computed(() => {
    const actions = [
        {
            category: "Advanced",
            actions: [
                { id: 'duplicate', label: 'Duplicate', icon: 'DocumentDuplicateIcon' },
            ],
        },
        {
            category: "Users",
            actions: [],
        },
    ];

    // Only show if permission allows
    if (permissions.value.create_user) {
        actions[1].actions.push({
            id: 'make_user',
            label: 'Make User',
            icon: 'UserPlusIcon',
        });
    }

    if (permissions.value.create_admin) {
        actions[1].actions.push({
            id: 'make_admin',
            label: 'Make Admin',
            icon: 'KeyIcon',
        });
    }

    if (props.routes?.create_contact_center_user) {
        actions.push({
            category: "Contact Center",
            actions: [
                { id: 'make_cc_agent', label: 'Make Agent', icon: 'SupportAgent' },
                { id: 'make_cc_admin', label: 'Make Admin', icon: 'KeyIcon' },
            ],
        });
    }

    // Optional: hide empty categories (like "Users" if no permissions)
    return actions.filter(category => category.actions.length > 0);
});


onMounted(async () => {
    handleSearchButtonClick();
    isRegsLoading.value = true
    try {
        // Make your additional API call (example URL)
        const response = await axios.get(props.routes.registrations)
        registrations.value = response.data.registrations || {}
        // console.log(response.data.registrations)
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        isRegsLoading.value = false
        getRingotelStatuses()
    }
})

const getRingotelStatuses = async () => {
    if (!props.routes?.ringotel_status) {
        return
    }

    isRingotelLoading.value = true

    try {
        const response = await axios.get(props.routes.ringotel_status)
        ringotelStatuses.value = response.data.data || {}
    } catch (error) {
        ringotelStatuses.value = {}
    } finally {
        isRingotelLoading.value = false
    }
}

const ringotelStatusFor = (row) => {
    return ringotelStatuses.value?.[row.extension_uuid] ?? null
}

const ringotelDotClass = (status) => {
    if (Number(status?.state) === 0) {
        return 'bg-white ring-2 ring-gray-400'
    }

    return {
        green: 'bg-green-600',
        blue: 'bg-blue-600',
        yellow: 'bg-amber-400',
        red: 'bg-rose-600',
        gray: 'bg-gray-300',
    }[status?.state_color] ?? 'bg-gray-300'
}

const formatRingotelTimestamp = (timestamp) => {
    if (!timestamp) {
        return 'Never'
    }

    const normalized = Number(timestamp) > 9999999999 ? Number(timestamp) : Number(timestamp) * 1000

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(normalized))
}

const mobileAppTooltip = (row) => {
    const status = ringotelStatusFor(row)

    if (isRingotelLoading.value && !status) {
        return 'Mobile App (Activated). Mobile App status is loading.'
    }

    if (!status) {
        return 'Mobile App (Activated). Mobile App status unavailable.'
    }

    return `Mobile App (Activated). State: ${status.state_label}. Last Seen: ${formatRingotelTimestamp(status.last_login_ts)}.`
}


const handleImportButtonClick = () => {
    uploadErrors.value = null;
    showUploadModal.value = true;
};

const uploadFile = (file) => {
    isUploadingFile.value = true;
    uploadErrors.value = null;
    const formData = new FormData();
    formData.append('file', file);

    axios.post(props.routes.import, formData)
        .then((response) => {
            showNotification('success', response.data.messages);
            handleModalClose();
            refreshCurrentPage();
        })
        .catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
            if (error.response) {
                uploadErrors.value = error.response.data.errors;
            }
        })
        .finally(() => {
            isUploadingFile.value = false;
        });
}

function downloadTemplateFile() {
    // Make a GET request to your Laravel route
    axios.get(props.routes.download_template, {
        responseType: 'blob' // Important: so we get back a Blob object
    })
        .then((response) => {
            // Create a Blob from the response data
            const fileBlob = new Blob([response.data], { type: 'text/csv' })
            // Create a URL for the blob
            const fileURL = window.URL.createObjectURL(fileBlob)

            // Create a hidden link element, set it to the blob URL, and trigger a download
            const link = document.createElement('a')
            link.href = fileURL
            link.setAttribute('download', 'extensions_template.csv') // The filename you want
            document.body.appendChild(link)
            link.click()
            link.remove()
        })
        .catch((error) => {
            console.error('Error downloading template:', error)
        })
}

const exportExtensionsCsv = () => {
    axios.get(props.routes.export, {
        params: {
            // mirrors your search filter (and leaves room for future filters)
            filter: { search: filterData.value.search },
        },
        responseType: 'blob',
    })
        .then((response) => {
            const blob = new Blob([response.data], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'extensions.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        })
        .catch(handleErrorResponse);
};

const handleEditButtonClick = (itemUuid) => {
    showUpdateModal.value = true
    getItemOptions(itemUuid, { mode: 'update' });
}

const retainVoicemail = ref(false);

const handleSingleItemDeleteRequest = (uuid) => {
    retainVoicemail.value = false;
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
    isModalLoading.value = true;
    axios.post(props.routes.bulk_delete, { 
        items,
        retain_voicemail: retainVoicemail.value 
    })
    .then((response) => {
        showNotification('success', response.data.messages);
        refreshCurrentPage();
        handleClearSelection();
    })
    .catch((error) => {
        handleErrorResponse(error);
    })
    .finally(() => {
        handleModalClose();
        isModalLoading.value = false;
    });
};

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        retainVoicemail.value = false;
        showDeleteConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        getItemOptions(null, { include_mobile_app_bulk: true, mode: 'bulk_update' });
        bulkUpdateModalTrigger.value = true;
    }
};

const handleAdvancedActionRequest = (action, extension_uuid) => {

    if (action === 'duplicate') {
        const url = props.routes.duplicate || '/extensions/duplicate';
        loading.value = true;

        axios.post(url, { uuid: extension_uuid })
            .then((response) => {
                showNotification('success', response.data.messages);
                refreshCurrentPage();
            })
            .catch((error) => {
                handleErrorResponse(error);
            })
            .finally(() => {
                loading.value = false;
            });

        return;
    }

    let role = null;
    let url = null;

    if (action === 'make_cc_agent') {
        url = props.routes.create_contact_center_user
        role = 'agent';
    } else if (action === 'make_cc_admin') {
        url = props.routes.create_contact_center_user
        role = 'admin';
    } else if (action === 'make_admin') {
        url = props.routes.create_user
        role = 'admin';
    } else if (action === 'make_user') {
        url = props.routes.create_user
        role = 'user';
    } else {
        return; // ignore other actions
    }

    if (!url) {
        return;
    }

    const payload = {
        extension_uuid,
        role,
    };

    axios.post(url, payload)
        .then((response) => {
            showNotification('success', response.data.messages);
        })
        .catch((error) => {
            handleErrorResponse(error);
        })
        .finally(() => {
            // reset loading state, close modal, etc.
        });
};


const handleCreateButtonClick = () => {
    showCreateModal.value = true
    getItemOptions(null, { mode: 'create' });
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

const handleSortRequest = (column) => {
    if (sortData.value.name === column) {
        sortData.value.order = sortData.value.order === 'asc' ? 'desc' : 'asc';
    } else {
        sortData.value.name = column;
        sortData.value.order = 'asc';
    }

    getData();
};

const getData = (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;

     let sort = sortData.value.name;

    if (sortData.value.order === 'desc') {
        sort = `-${sort}`;
    }


    return axios.get(props.routes.data_route, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            per_page: perPage.value,
            sort: sort,
        }
    })
        .then((response) => {
            data.value = response.data;
            currentPage.value = response.data.current_page ?? currentPage.value;
            // console.log(data.value);

        }).catch((error) => {

            handleErrorResponse(error);
        }).finally(() => {
            loading.value = false
        })
}

const handleSearchButtonClick = () => {
    getData(1)
};

const refreshCurrentPage = async () => {
    await getData(currentPage.value)
    await getRingotelStatuses()
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    getData(1);
}


const handlePageSizeChange = (newPerPage) => {
    perPage.value = newPerPage;
    getData(1);
};

const renderRequestedPage = (url) => {
    loading.value = true;
    // Extract the page number from the url, e.g. "?page=3"
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;

    // Now call getData with the page number
    getData(pageParam);
};


const getItemOptions = (itemUuid = null, extraPayload = {}) => {
    itemOptions.value = {}
    const payload = {
        ...extraPayload,
        ...(itemUuid ? { item_uuid: itemUuid } : {}),
    };    isModalLoading.value = true
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
        selectedItems.value = data.value.data.map(item => item.extension_uuid);
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
    showUploadModal.value = false;
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


registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";
</style>
