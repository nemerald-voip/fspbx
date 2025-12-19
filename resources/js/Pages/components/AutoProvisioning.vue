<template>

    <!-- Mobile -->
    <div class="grid grid-cols-1 sm:hidden">
        <select v-model="activeTab" aria-label="Select a tab"
            class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-2 pr-8 pl-3 text-base text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-gray-100 dark:outline-white/10 dark:*:bg-gray-800 dark:focus:outline-indigo-500">
            <option v-for="tab in tabs" :key="tab.id" :value="tab.id">
                {{ tab.name }}
            </option>
        </select>
        <ChevronDownIcon
            class="pointer-events-none col-start-1 row-start-1 mr-2 size-5 self-center justify-self-end fill-gray-500 dark:fill-gray-400"
            aria-hidden="true" />
    </div>

    <!-- Desktop -->
    <div class="hidden sm:block">
        <div class="border-b border-gray-200 ">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a v-for="tab in tabs" :key="tab.id" href="#" @click.prevent="setTab(tab.id)" :class="[
                    activeTab === tab.id
                        ? '!border-indigo-600 !text-indigo-600 '
                        : 'border-transparent !text-gray-500 hover:!border-gray-300 hover:!text-gray-700',
                    'border-b-2 px-1 py-4 text-sm font-medium whitespace-nowrap'
                ]" :aria-current="activeTab === tab.id ? 'page' : undefined">
                    {{ tab.name }}
                </a>
            </nav>
        </div>
    </div>


    <div class="mt-8 flex flex-col">

        <div class="flex flex-col sm:flex-row sm:flex-wrap">
            <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </div>
                <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                    id="mobile-search-candidate"
                    class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                    placeholder="Search" @keydown.enter="handleSearchButtonClick" />
                <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                    id="desktop-search-candidate"
                    class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                    placeholder="Search" @keydown.enter="handleSearchButtonClick" />
            </div>
            <div class="relative">
                <div class="flex justify-between">

                    <button type="button" @click.prevent="handleSearchButtonClick"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500
                                focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Search
                    </button>

                    <button type="button" @click.prevent="handleFiltersReset"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2  sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button v-if="activeTab != 'default'" type="button" @click.prevent="handleCreateButtonClick()"
                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Create
            </button>
        </div>

        <div class="mt-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <Paginator class="border border-gray-200" :previous="data.prev_page_url" :next="data.next_page_url"
                    :from="data.from" :to="data.to" :total="data.total" :currentPage="data.current_page"
                    :lastPage="data.last_page" :links="data.links" @pagination-change-page="renderRequestedPage" />
                <div class="overflow-hidden-t border-l border-r border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Vendor</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Template Name
                                </th>
                                <th v-if="activeTab === 'default'"
                                    class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Version</th>

                                <th v-if="activeTab != 'default'"
                                    class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Revision</th>

                                <th v-if="activeTab != 'default'"
                                    class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Base Template</th>

                                <th v-if="activeTab != 'default'"
                                    class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Base Version</th>

                                <th
                                    class="relative px-6 py-3 text-left text-sm font-medium text-gray-500">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="!isTemplatesLoading && data.data?.length"
                            class="divide-y divide-gray-200 bg-white">
                            <tr v-for="template in data.data" :key="template.template_uuid">
                                <td class="whitespace-nowrap px-6 py-2 text-sm font-medium text-gray-900 capitalize">
                                    {{ template.vendor }}
                                </td>

                                <td class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                    {{ template.name }}
                                </td>
                                <td v-if="activeTab === 'default'"
                                    class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                    {{ template.version }}
                                </td>
                                <td v-if="activeTab != 'default'"
                                    class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                    {{ template.revision }}
                                </td>
                                <td v-if="activeTab != 'default'"
                                    class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                    {{ template.base_template }}
                                </td>
                                <td v-if="activeTab != 'default'"
                                    class="whitespace-nowrap px-6 py-2 text-sm text-gray-500">
                                    {{ template.base_version }}
                                </td>
                                <td 
                                    class="whitespace-nowrap px-6 py-2 text-right text-sm font-medium">
                                    <div class="flex items-center whitespace-nowrap justify-end">
                                        <ejs-tooltip v-if="activeTab != 'default'" :content="'Edit'" position='TopCenter'
                                            target="#destination_tooltip_target">
                                            <div id="destination_tooltip_target">
                                                <PencilSquareIcon @click="handleEditButtonClick(template.template_uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                            </div>
                                        </ejs-tooltip>

                                        <ejs-tooltip v-if="activeTab != 'default'" :content="'Delete'" position='TopCenter'
                                            target="#delete_tooltip_target">
                                            <div id="delete_tooltip_target">
                                                <TrashIcon
                                                    @click="handleSingleItemDeleteRequest(template.template_uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                            </div>
                                        </ejs-tooltip>

                                        <ejs-tooltip v-if="activeTab == 'default'"
                                            :content="'View details'" position='TopCenter'
                                            target="#view_tooltip_target">
                                            <div id="view_tooltip_target">
                                                <MagnifyingGlassIcon @click="handleViewButtonClick(template.template_uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                            </div>
                                        </ejs-tooltip>
                                    </div>

                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!isTemplatesLoading && data.data?.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">
                Adjust your search and try again.
              </p> -->
                    </div>

                    <!-- Loading -->
                    <div v-if="isTemplatesLoading" class="text-center my-5 text-sm text-gray-500">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-6 py-1">
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <AddEditItemModal :customClass="'sm:max-w-8xl'" :show="showCreateModal" :header="'Create New Provisioning Template'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateProvisioningTemplateForm :options="itemOptions" :errors="formErrors"
                :is-submitting="createFormSubmiting" @submit="handleCreateRequest" @cancel="handleModalClose"
                @error="handleFormErrorResponse" @success="showNotification('success', $event)"
                @clear-errors="handleClearErrors" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-8xl'" :show="showEditModal" :header="'Edit Provisioning Template'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdateProvisioningTemplateForm :options="itemOptions" :errors="formErrors" :read-only="readOnly"
                :is-submitting="updateFormSubmiting" @submit="handleUpdateRequest" @cancel="handleModalClose"
                @error="handleFormErrorResponse" @success="showNotification('success', $event)"
                @clear-errors="handleClearErrors" />
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete the selected emergency call(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import AddEditItemModal from "./modal/AddEditItemModal.vue";
import CreateProvisioningTemplateForm from "./forms/CreateProvisioningTemplateForm.vue";
import UpdateProvisioningTemplateForm from "./forms/UpdateProvisioningTemplateForm.vue";
import Notification from "./notifications/Notification.vue";
import ConfirmationModal from "./modal/ConfirmationModal.vue";
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { registerLicense } from '@syncfusion/ej2-base';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import { ChevronDownIcon } from '@heroicons/vue/20/solid'
import Paginator from "@generalComponents/Paginator.vue";


const selectedItems = ref([]);

const props = defineProps({
    domain_uuid: String,
    routes: Object,
    permissions: Object,
    trigger: Boolean
})

const showCreateModal = ref(false);
const showEditModal = ref(false);
const loadingModal = ref(false)
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const showDeleteConfirmationModal = ref(false);
const confirmDeleteAction = ref(null);
const itemOptions = ref([])
const createFormSubmiting = ref(null);
const updateFormSubmiting = ref(null);
const isTemplatesLoading = ref(false)
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


const filterData = ref({
    search: null,
    domain_uuid: props.domain_uuid,
    type: 'default',
});

// const emits = defineEmits(['edit-item', 'delete-item']);

const fetchTemplates = async (page = 1) => {
    isTemplatesLoading.value = true
    axios.get(props.routes.templates, {
        params: {
            filter: filterData.value,
            page,
        }
    })
        .then((response) => {
            data.value = response.data;
            // console.log(data.value);

        }).catch((error) => {
            handleErrorResponse(error)
        }).finally(() => {
            isTemplatesLoading.value = false
        });
}

const tabs = [
    { id: 'default', name: 'Default Templates' },
    { id: 'custom', name: 'Custom Templates' },
]

// state for tabs + templates
const activeTab = ref('default')

// keep filter[type] in sync with tab
watch(
    activeTab,
    (v) => {
        filterData.value.type = v
        fetchTemplates(1)               // reload when tab changes
    },
    { immediate: true }               // also fetch on mount/first render
)
// unify tab change handler (works for both mobile select and desktop clicks)
const setTab = (id) => {
    if (activeTab.value !== id) activeTab.value = id
}

watch(() => props.trigger, (newVal) => {
    setTab('default')
})


const renderRequestedPage = (url) => {
    isTemplatesLoading.value = true;
    // Extract the page number from the url, e.g. "?page=3"
    const urlObj = new URL(url, window.location.origin);
    const pageParam = urlObj.searchParams.get("page") ?? 1;

    // Now call getData with the page number
    fetchTemplates(pageParam);
};

// const handleUpdateTemplateButtonClick = (location) => {
//     selectedLocation.value = location;
//     // Dynamically build the update route
//     locationUpdateRoute.value = `/api/locations/${location.location_uuid}`; // or use your route helper if available
//     showUpdateLocationModal.value = true;
// }

// const handleDeleteTemplateButtonClick = (uuid) => {
//     showDeleteLocationConfirmationModal.value = true;
//     confirmDeleteLocationAction.value = () => executeLocationBulkDelete([uuid]);
// };

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
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions();
}

const handleSearchButtonClick = () => {
    fetchTemplates(1)
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const handleCreateRequest = (form) => {
    createFormSubmiting.value = true;
    formErrors.value = null;

    axios.post(props.routes.templates_store, form)
        .then((response) => {
            createFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            handleModalClose();
            handleSearchButtonClick();
        }).catch((error) => {
            console.log(error);
            createFormSubmiting.value = false;
            handleFormErrorResponse(error);
        });
};

const handleEditButtonClick = (uuid) => {
    showEditModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    readOnly.value = false
    getItemOptions(uuid);
}

const handleViewButtonClick = (uuid) => {
    showEditModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    readOnly.value = true
    getItemOptions(uuid);
}

const handleUpdateRequest = (form) => {
    updateFormSubmiting.value = true;
    formErrors.value = null;

    axios.put(`${props.routes.templates_store}/${itemOptions.value.item.template_uuid}`, form)
        .then((response) => {
            updateFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            handleSearchButtonClick();
        })
        .catch((error) => {
            updateFormSubmiting.value = false;
            handleFormErrorResponse(error);
        });
};

const handleSingleItemDeleteRequest = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.templates_bulk_delete, { items })
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
    showDeleteConfirmationModal.value = false;
    // bulkUpdateModalTrigger.value = false;
}

const getItemOptions = (itemUuid = null) => {
    loadingModal.value = true;

    axios.post(props.routes.templates_item_options, {
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


const handleBulkActionRequest = (action) => {
    if (action === 'bulk_delete') {
        showDeleteConfirmationModal.value = true;
        confirmDeleteAction.value = () => executeBulkDelete();
    }
    if (action === 'bulk_update') {
        formErrors.value = [];
        getItemOptions();
        loadingModal.value = true
        bulkUpdateModalTrigger.value = true;
    }

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

const handleClearErrors = () => {
    formErrors.value = null;
}

const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        showNotification('error', { request: ["Session expired. Reload the page"] });
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
        formErrors.value = error.response.data.errors;
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