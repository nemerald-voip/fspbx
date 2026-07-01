<template>
    <MainLayout />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Group Manager</template>

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
                <button v-if="permissions.create" type="button" @click.prevent="handleCreateButtonClick()"
                    class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                    Create
                </button>

                <a v-if="permissions.domain_groups_view" type="button" href="/domain-groups"
                    class="rounded-md bg-surface px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">
                    Domain Groups
                </a>


            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                    :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page" :links="data.links"
                    @pagination-change-page="renderRequestedPage" :bulk-actions="bulkActions"
                    @bulk-action="handleBulkActionRequest" :has-selected-items="selectedItems.length > 0" />
            </template>
            <template #table-header>

                <TableColumnHeader
                    class="flex whitespace-nowrap px-4 py-3.5 text-left text-sm font-semibold text-heading items-center justify-start"
                    field="group_name" :sort-order="sortData.order" :sorted-field="sortData.name"
                    @sort="handleSortRequest">
                    <input type="checkbox" v-model="selectPageItems" @change="handleSelectPageItems" @click.stop
                        class="h-4 w-4 rounded border-strong text-accent-fg">
                    <span class="pl-4">Name</span>
                </TableColumnHeader>

                <TableColumnHeader header="Level" class="px-2 py-3.5 text-left text-sm font-semibold text-heading"
                    field="group_level" :sort-order="sortData.order" :sorted-field="sortData.name"
                    @sort="handleSortRequest" />
                <TableColumnHeader header="Permissions" class="px-2 py-3.5 text-left text-sm font-semibold text-heading"
                    field="permissions_count" :sort-order="sortData.order" :sorted-field="sortData.name"
                    @sort="handleSortRequest" />
                <TableColumnHeader header="Members" class="px-2 py-3.5 text-left text-sm font-semibold text-heading"
                    field="user_groups_count" :sort-order="sortData.order" :sorted-field="sortData.name"
                    @sort="handleSortRequest" />
                <TableColumnHeader header="Description" class="px-2 py-3.5 text-left text-sm font-semibold text-heading"
                    field="group_description" :sort-order="sortData.order" :sorted-field="sortData.name"
                    @sort="handleSortRequest" />
                <TableColumnHeader header="" class="px-2 py-3.5 text-right text-sm font-semibold text-heading"
                    :sortable="false" />
            </template>

            <template v-if="selectPageItems" v-slot:current-selection>
                <td colspan="6">
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
                <tr v-for="row in data.data" :key="row.group_uuid">
                    <TableField class="whitespace-nowrap px-4 py-2 text-sm text-muted" :text="row.group_uuid">
                        <div class="flex items-center">
                            <input v-if="row.group_uuid" v-model="selectedItems" type="checkbox" name="action_box[]"
                                :value="row.group_uuid" class="h-4 w-4 rounded border-strong text-accent-fg">
                            <div class="ml-9"
                                :class="{ 'cursor-pointer hover:text-heading': permissions.update, }"
                                @click="permissions.update && handleEditButtonClick(row.group_uuid)">
                                <span class="flex items-center">
                                    {{ row.group_name }}
                                </span>
                            </div>
                        </div>
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <Badge :text="row.group_level" backgroundColor="bg-accent-subtle" textColor="text-accent-fg"
                            ringColor="ring-accent/20" class="px-2 py-1 text-xs font-semibold" />
                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <a :href="`/groups/${row.group_uuid}/permissions`"
                            class="inline-block rounded bg-surface px-2 py-1 text-sm text-body shadow-sm hover:text-heading">
                            Permissions
                            ({{ row.permissions_count }})
                        </a>

                    </TableField>

                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted">
                        <button v-if="permissions.members" type="button"
                            @click="handleMembersButtonClick(row)"
                            class="inline-block rounded bg-surface px-2 py-1 text-sm text-body shadow-sm hover:text-heading">
                            Members
                            ({{ row.user_groups_count }})
                        </button>
                        <span v-else>
                            {{ row.user_groups_count }}
                        </span>

                    </TableField>


                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-muted" :text="row.group_description" />


                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-muted">
                        <template #action-buttons>
                            <div class="flex items-center whitespace-nowrap justify-end">
                                <ejs-tooltip v-if="permissions.update" :content="'Edit'" position='TopCenter'
                                    target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <PencilSquareIcon @click="handleEditButtonClick(row.group_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />

                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="permissions.create" :content="'Clone'" position='TopCenter'
                                    target="#clone_tooltip_target">
                                    <div id="clone_tooltip_target">
                                        <DocumentDuplicateIcon @click="handleSingleItemCloneRequest(row.group_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>

                                <ejs-tooltip v-if="permissions.destroy" :content="'Delete'"
                                    position='TopCenter' target="#delete_tooltip_target">
                                    <div id="delete_tooltip_target">
                                        <TrashIcon @click="handleSingleItemDeleteRequest(row.group_uuid)"
                                            class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-subtle hover:bg-surface-3 hover:text-body active:bg-surface-3 active:duration-150 cursor-pointer" />
                                    </div>
                                </ejs-tooltip>
                            </div>
                        </template>
                    </TableField>
                </tr>
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

    <CreatePermissionGroupForm :show="showCreateModal" :options="itemOptions" :loading="isModalLoading"
        @close="showCreateModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" />

    <UpdatePermissionGroupForm :show="showUpdateModal" :options="itemOptions" :loading="isModalLoading"
        @close="showUpdateModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="refreshCurrentPage" />

    <GroupMembersModal :show="showMembersModal" :group="selectedMembersGroup" :routes="routes"
        @close="handleMembersModalClose" @error="handleErrorResponse" @success="showNotification"
        @count-changed="handleMemberCountChanged" />

    <ConfirmationModal :show="showConfirmationModal" @close="handleModalClose"
        @confirm="confirmAction" :header="confirmationHeader"
        :text="confirmationText"
        :confirm-button-label="confirmationButtonLabel" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
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
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { DocumentDuplicateIcon } from "@heroicons/vue/24/outline";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import MainLayout from "../Layouts/MainLayout.vue";
import UpdatePermissionGroupForm from "./components/forms/UpdatePermissionGroupForm.vue"
import CreatePermissionGroupForm from "./components/forms/CreatePermissionGroupForm.vue"
import GroupMembersModal from "./components/modal/GroupMembersModal.vue";
import Notification from "./components/notifications/Notification.vue";
import Badge from "@generalComponents/Badge.vue";



const loading = ref(false)
const isModalLoading = ref(false)
const currentPage = ref(1);
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const showCreateModal = ref(false);
const showUpdateModal = ref(false);
const showMembersModal = ref(false);
const selectedMembersGroup = ref(null);
const showConfirmationModal = ref(false);
const confirmAction = ref(null);
const confirmationHeader = ref('Are you sure?');
const confirmationText = ref('');
const confirmationButtonLabel = ref('Continue');
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);

const props = defineProps({
    routes: Object,
    pagination: Object,
    permissions: Object,
});

const routes = props.routes;
const permissions = props.permissions;
const perPage = ref(props.pagination?.per_page);

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
    name: 'group_name',
    order: 'asc',
});

const itemOptions = ref({})

const bulkActions = computed(() => {
    const actions = [];

    if (permissions.create) {
        actions.push({
            id: 'clone',
            label: 'Clone',
            icon: 'DocumentDuplicateIcon',
        });
    }

    if (permissions.destroy) {
        actions.push({
            id: 'bulk_delete',
            label: 'Delete',
            icon: 'TrashIcon'
        });
    }

    return actions;
});

onMounted(() => getData());


const handleEditButtonClick = (itemUuid) => {
    showUpdateModal.value = true
    getItemOptions(itemUuid);
}

const handleMembersButtonClick = (group) => {
    selectedMembersGroup.value = group;
    showMembersModal.value = true;
};

const handleMembersModalClose = () => {
    showMembersModal.value = false;
    selectedMembersGroup.value = null;
};

const handleMemberCountChanged = ({ group_uuid, count }) => {
    data.value.data = data.value.data.map(group => {
        if (group.group_uuid !== group_uuid) return group;

        return {
            ...group,
            user_groups_count: count,
        };
    });
};

const handleSingleItemDeleteRequest = (uuid) => {
    showConfirmation({
        header: 'Confirm Deletion',
        text: 'This action will permanently delete the selected group. Are you sure you want to proceed?',
        button: 'Delete',
        action: () => executeBulkDelete([uuid]),
    });
};

const handleSingleItemCloneRequest = (uuid) => {
    showConfirmation({
        header: 'Clone Group',
        text: 'Clone this group and all of its permissions?',
        button: 'Clone',
        action: () => executeClone([uuid]),
    });
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(routes.bulk_delete, { items })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            refreshCurrentPage();
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}

const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showConfirmation({
            header: 'Confirm Deletion',
            text: 'This action will permanently delete the selected group(s). Are you sure you want to proceed?',
            button: 'Delete',
            action: () => executeBulkDelete(),
        });
    }

    if (action === 'clone') {
        if (selectedItems.value.length !== 1) {
            showNotification('error', { clone: ['Select exactly one group to clone.'] });
            return;
        }

        showConfirmation({
            header: 'Clone Group',
            text: 'Clone the selected group and all of its permissions?',
            button: 'Clone',
            action: () => executeClone(),
        });
    }

}

const showConfirmation = ({ header, text, button, action }) => {
    confirmationHeader.value = header;
    confirmationText.value = text;
    confirmationButtonLabel.value = button;
    confirmAction.value = action;
    showConfirmationModal.value = true;
};

const executeClone = (items = selectedItems.value) => {
    axios.post(routes.clone, { items })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            refreshCurrentPage();
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
};

const handleCreateButtonClick = () => {
    showCreateModal.value = true
    isModalLoading.value = true
    getItemOptions();
}

const handleSelectAll = () => {
    axios.post(routes.select_all, { filter: filterData.value })
        .then((response) => {
            selectedItems.value = response.data.items;
            selectAll.value = true;
            showNotification('success', response.data.messages);

        }).catch((error) => {
            handleClearSelection();
            handleErrorResponse(error);
        });

};



const getData = (page = 1) => {
    loading.value = true;
    currentPage.value = Number(page) || 1;

    const sort = sortData.value.order === 'desc' ? `-${sortData.value.name}` : sortData.value.name;

    axios.get(routes.data_route, {
        params: {
            filter: filterData.value,
            page: currentPage.value,
            per_page: perPage.value,
            sort,
        }
    })
        .then((response) => {
            data.value = response.data;
            currentPage.value = response.data.current_page ?? currentPage.value;
            handleClearSelection();
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

const handleSortRequest = ({ field, order }) => {
    sortData.value.name = field;
    sortData.value.order = order;
    getData(currentPage.value);
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    getData(1);
}


const handlePageSizeChange = (newPerPage) => {
    perPage.value = newPerPage;
    getData(1);
};

const renderRequestedPage = (url) => {
    if (!url) return;
    const urlObj = new URL(url, window.location.origin);
    getData(urlObj.searchParams.get("page") ?? 1);
};


const getItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {}; // Conditionally add itemUuid to payload
    isModalLoading.value = true
    axios.post(routes.item_options, payload)
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
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || error.response.data.messages || { request: [error.message] });
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
    selectedItems.value = selectPageItems.value
        ? data.value.data.map(item => item.group_uuid).filter(Boolean)
        : [];
};



const handleClearSelection = () => {
    selectedItems.value = [];
    selectPageItems.value = false;
    selectAll.value = false;
}

const handleModalClose = () => {
    showCreateModal.value = false;
    showUpdateModal.value = false;
    showConfirmationModal.value = false;
    confirmAction.value = null;
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
