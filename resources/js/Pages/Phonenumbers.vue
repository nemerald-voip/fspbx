<template>
    <MainLayout :menu-options="menus" :domain-select-permission="domainSelectPermission" :selected-domain="selectedDomain"
                :selected-domain-uuid="selectedDomainUuid" :domains="domains" />

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Phone Numbers</template>

            <template #filters>
                <div class="relative min-w-64 focus-within:z-10 mb-2 sm:mr-4">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                        <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true"/>
                    </div>
                    <input type="text" v-model="filterData.search" name="mobile-search-candidate"
                           id="mobile-search-candidate"
                           class="block w-full rounded-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:hidden"
                           placeholder="Search"/>
                    <input type="text" v-model="filterData.search" name="desktop-search-candidate"
                           id="desktop-search-candidate"
                           class="hidden w-full rounded-md border-0 py-1.5 pl-10 text-sm leading-6 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-600 sm:block"
                           placeholder="Search"/>
                </div>
            </template>

            <template #action>
                <button type="button" :href="routeDevicesStore" @click.prevent="handleAdd()"
                        class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Add number
                </button>
                <button v-if="!showGlobal" type="button" @click.prevent="handleShowGlobal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>
                <button v-if="showGlobal" type="button" @click.prevent="handleShowLocal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show local
                </button>
            </template>

            <template #navigation>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                           :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                           :links="data.links"
                           @pagination-change-page="renderRequestedPage"/>
            </template>
            <template #table-header>
                <TableColumnHeader header=" "
                                   class="py-3.5 text-sm font-semibold text-gray-900 text-center">
                    <input type="checkbox" v-model="selectAll" @change="handleSelectAll"
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                </TableColumnHeader>
                <TableColumnHeader v-if="showGlobal" header="Domain"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="MAC Address"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Template" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Profile" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Assigned extension"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.device_uuid">
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500 text-center">
                        <input v-if="row.extension" v-model="selectedItems" type="checkbox" name="action_box[]"
                               :value="row.device_uuid"
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    </TableField>
                    <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                                :text="row.domain_description">
                        {{row.domain_description}}
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500 flex" :text="row.device_address">
                        <ejs-tooltip :content="'Click to edit device'" position='TopLeft' class="flex-initial cursor-pointer hover:text-gray-900" @click="handleEdit(row.edit_path)" target="#destination_tooltip_target">
                            <div id="destination_tooltip_target">
                                {{row.device_address}}
                            </div>
                        </ejs-tooltip>
                        <ejs-tooltip :content="tooltipCopyContent" position='TopLeft' class="flex-initial ml-2" target="#destination_tooltip_target">
                            <div id="destination_tooltip_target">
                                <ClipboardDocumentIcon class="h-4 w-4 text-black-500 hover:text-black-900 pt-1 cursor-pointer" />
                            </div>
                        </ejs-tooltip>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_template"/>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_profile_name"/>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <ejs-tooltip :content="'Click to edit extension'" position='TopLeft' target="#destination_tooltip_target">
                            <div id="destination_tooltip_target">
                                <a class="hover:text-gray-900 cursor-pointer block" v-if="row.extension_edit_path" :href="row.extension_edit_path">
                                    {{ row.extension }} {{row.extension_description}}
                                </a>
                            </div>
                        </ejs-tooltip>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center space-x-2 whitespace-nowrap">
                                <ejs-tooltip :content="'Edit phone number'" position='TopLeft' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <DocumentTextIcon v-if="row.edit_path" @click="handleEdit(row.edit_path)"
                                                  class="h-5 w-5 text-black-500 hover:text-black-900 active:h-5 active:w-5 cursor-pointer"/>
                                    </div>
                                </ejs-tooltip>
                                <ejs-tooltip :content="'Remove phone number'" position='TopLeft' target="#destination_tooltip_target">
                                    <div id="destination_tooltip_target">
                                        <TrashIcon v-if="row.destroy_path" @click="handleDestroyConfirmation(row.destroy_path)"
                                           class="h-5 w-5 text-black-500 hover:text-black-900 active:h-5 active:w-5 cursor-pointer"/>
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
                    <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400"/>
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Adjust your search and try again.
                    </p>
                </div>
            </template>

            <template #loading>
                <Loading :show="loading" />
            </template>

            <template #footer>
                <Paginator :previous="data.prev_page_url" :next="data.next_page_url" :from="data.from" :to="data.to"
                           :total="data.total" :currentPage="data.current_page" :lastPage="data.last_page"
                           :links="data.links"
                           @pagination-change-page="renderRequestedPage"/>
            </template>
        </DataTable>
        <div class="px-4 sm:px-6 lg:px-8"></div>
    </div>
    <NotificationError
        :show="actionError"
        :errors="actionErrorsList"
        :header="actionErrorMessage"
        @update:show="handleErrorsReset"
    />
    <AddEditItemModal
        :show="addModalTrigger"
        :header="'Add New PhoneNumber'"
        :loading="loadingModal"
        @close="handleClose"
    >
        <template #modal-body>
            <AddEditDeviceForm
                :device="DeviceObject"
            />
        </template>
        <template #modal-action-buttons>
            <button type="button"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    @click="handleSaveAdd" ref="saveButtonRef">Save
            </button>
            <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="handleClose" ref="cancelButtonRef">Cancel
            </button>
        </template>
    </AddEditItemModal>
    <AddEditItemModal
        :show="editModalTrigger"
        :header="'Edit Phone Number'"
        :loading="loadingModal"
        @close="handleClose"
    >
        <template #modal-body>
            <AddEditDeviceForm
                :device="DeviceObject"
                :isEdit="true"
                @update:show="editModalTrigger = false"
            />
        </template>
        <template #modal-action-buttons>
            <button type="button"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    @click="handleSaveEdit" ref="saveButtonRef">Save
            </button>
            <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="handleClose" ref="cancelButtonRef">Cancel
            </button>
        </template>
    </AddEditItemModal>
    <DeleteConfirmationModal
        :show="confirmationModalTrigger"
        @close="confirmationModalTrigger = false"
        @confirm="handleDestroy(confirmationModalDestroyPath)"
    />
    <NotificationError
        :show="actionError"
        :errors="actionErrorsList"
        :header="actionErrorMessage"
        @update:show="handleErrorsReset"
    />
</template>

<script setup>
import {computed, onMounted, reactive, ref} from "vue";
import axios from 'axios';
import {router} from "@inertiajs/vue3";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import NotificationError from "./components/notifications/Error.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import AddEditDeviceForm from "./components/forms/AddEditDeviceForm.vue";
import Loading from "./components/general/Loading.vue";
import {registerLicense} from '@syncfusion/ej2-base';
import {DocumentTextIcon, MagnifyingGlassIcon, TrashIcon, ClipboardDocumentIcon} from "@heroicons/vue/24/solid";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import MainLayout from "../Layouts/MainLayout.vue";
const today = new Date();

const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const showGlobal = ref(false);
const addModalTrigger = ref(false);
const editModalTrigger = ref(false);
const bulkEditModalTrigger = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationModalDestroyPath = ref(null);
const actionError = ref(false);
const actionErrorsList = ref({});
const actionErrorMessage = ref(null);

const props = defineProps({
    data: Object,
    menus: Array,
    domains: Array,
    domainSelectPermission: Boolean,
    deviceRestartPermission: Boolean,
    deviceGlobalView: Boolean,
    selectedDomain: String,
    selectedDomainUuid: String,
    search: String,
    routeDevicesStore: String,
    routeDevicesOptions: String,
    routeDevicesBulkUpdate: String,
    routeDevices: String,
    routeSendEventNotifyAll: String
});

let DeviceObject = reactive({
    update_path: props.routeDevicesStore,
    domain_uuid: '',
    device_uuid: '',
    device_address: '',
    extension_uuid: '',
    device_profile_uuid: '',
    device_template: '',
    device_options: {
        templates: Array,
        profiles: Array,
        extensions: Array
    }
});

onMounted(() => {
    showGlobal.value = props.deviceGlobalView;
})

const selectedItemsExtensions = computed(() => {
    return selectedItems.value.map(id => {
        const foundItem = props.data.data.find(item => item.device_uuid === id);
        return foundItem ? foundItem.extension_uuid : null;
    });
});

const handleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = props.data.data.map(item => item.device_uuid);
        selectedItemsExtensions.value = props.data.data.map(item => item.extension_uuid);
    } else {
        selectedItems.value = [];
        selectedItemsExtensions.value = [];
    }
};

const filterData = ref({
    search: props.search,
    showGlobal: props.deviceGlobalView,
});

const handleDestroy = (url) => {
    router.delete(url, {
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            confirmationModalTrigger.value = false;
            confirmationModalDestroyPath.value = null;
        }
    });
}

const handleShowGlobal = () => {
    filterData.value.showGlobal = true;
    showGlobal.value = true;
    handleSearchButtonClick();
}

const handleShowLocal = () => {
    filterData.value.showGlobal = false;
    showGlobal.value = false;
    handleSearchButtonClick();
}

const handleAdd = () => {
    DeviceObject.update_path = props.routeDevicesStore;
    axios.get(props.routeDevicesOptions).then((response) => {
        DeviceObject.device_options.templates = response.data.templates
        DeviceObject.device_options.profiles = response.data.profiles
        DeviceObject.device_options.extensions = response.data.extensions
        loadingModal.value = false
        addModalTrigger.value = true;
    }).catch((error) => {
        console.error('Failed to get device data:', error);
    });
}

const handleEdit = (url) => {
    editModalTrigger.value = true
    loadingModal.value = true
    axios.get(url).then((response) => {
        DeviceObject.domain_uuid = response.data.device.domain_uuid
        DeviceObject.update_path = response.data.device.update_path
        DeviceObject.device_uuid = response.data.device.device_uuid
        DeviceObject.device_address = response.data.device.device_address
        DeviceObject.device_profile_uuid = response.data.device.device_profile_uuid
        DeviceObject.device_template = response.data.device.device_template
        DeviceObject.extension_uuid = response.data.device.extension_uuid
        DeviceObject.device_options.templates = response.data.device.options.templates
        DeviceObject.device_options.profiles = response.data.device.options.profiles
        DeviceObject.device_options.extensions = response.data.device.options.extensions
        loadingModal.value = false
    }).catch((error) => {
        console.error('Failed to get device data:', error);
    });
}

const handleSearchButtonClick = () => {
    loading.value = true;
    router.visit(props.routeDevices, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            loading.value = false;
        }
    });
};

const handleFiltersReset = () => {
    filterData.value.search = null;
    // After resetting the filters, call handleSearchButtonClick to perform the search with the updated filters
    handleSearchButtonClick();
}

const handleErrorsReset = () => {
    actionError.value = false;
    actionErrorsList.value = {};
    actionErrorMessage.value = null;
}

const handleErrorsPush = (message, errors = null) => {
    actionError.value = true;
    if(errors !== null) {
        actionErrorsList.value = errors;
    } else {
        actionErrorsList.value = {};
    }
    actionErrorMessage.value = message;
}

const handleDeviceObjectReset = () => {
    DeviceObject = reactive({
        update_path: props.routeDevicesStore,
        domain_uuid: '',
        device_uuid: '',
        device_address: '',
        extension_uuid: '',
        device_profile_uuid: '',
        device_template: '',
        device_options: {
            templates: Array,
            profiles: Array,
            extensions: Array
        }
    });
}

const renderRequestedPage = (url) => {
    loading.value = true;
    router.visit(url, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["data"],
        onSuccess: (page) => {
            loading.value = false;
        }
    });
};

const handleSaveAdd = () => {
    axios.post(props.routeDevicesStore, {
        device_address: DeviceObject.device_address,
        device_template: DeviceObject.device_template,
        device_profile_uuid: DeviceObject.device_profile_uuid,
        extension_uuid: DeviceObject.extension_uuid
    }).then((response) => {
        handleSearchButtonClick()
        handleClose()
    }).catch((error) => {
        console.error('Failed to add device data:', error);
        if(error.response.data.errors)  {
            handleErrorsPush(error.response.data.message, error.response.data.errors)
        }
    });
}

const handleSaveEdit = () => {
    axios.put(DeviceObject.update_path, {
        domain_uuid: DeviceObject.domain_uuid,
        device_address: DeviceObject.device_address,
        device_template: DeviceObject.device_template,
        device_profile_uuid: DeviceObject.device_profile_uuid,
        extension_uuid: DeviceObject.extension_uuid
    }).then((response) => {
        handleSearchButtonClick()
        handleClose()
    }).catch((error) => {
        console.error('Failed to save device data:', error);
        console.log(error.response.data.errors)
        if(error.response.data.errors.length > 0)  {
            handleErrorsPush(error.response.data.message, error.response.data.errors)
        }
    });
}

const handleClose = () => {
    addModalTrigger.value = false
    editModalTrigger.value = false
    setTimeout(handleDeviceObjectReset, 1000)
}

const handleBulkClose = () => {
    bulkEditModalTrigger.value = false
    setTimeout(handleDeviceObjectReset, 1000)
}

const handleDestroyConfirmation = (url) => {
    confirmationModalTrigger.value = true
    confirmationModalDestroyPath.value = url;
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";</style>
