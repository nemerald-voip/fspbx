<template>
    <Menu :menus="menus" :domain-select-permission="domainSelectPermission" :selected-domain="selectedDomain"
          :selected-domain-uuid="selectedDomainUuid" :domains="domains"></Menu>

    <div class="m-3">
        <DataTable @search-action="handleSearchButtonClick" @reset-filters="handleFiltersReset">
            <template #title>Devices</template>

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
                <button type="button" :href="routeDevicesCreate" @click.prevent="handleAdd()"
                   class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Add device
                </button>
                <button v-if="deviceRestartPermission" type="button" @click.prevent="handleRestartSelected()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Restart selected devices
                </button>
                <button v-if="deviceRestartPermission" type="button" @click.prevent="handleRestartAll()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Restart all devices
                </button>

                <button v-if="showGlobal" type="button" @click.prevent="handleShowLocal()"
                        class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                    Show global
                </button>

                <button v-if="!showGlobal" type="button" @click.prevent="handleShowGlobal()"
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
                <TableColumnHeader v-if="deviceRestartPermission" header=" "
                                   class="py-3.5 text-sm font-semibold text-gray-900 text-center">
                    <input type="checkbox" v-model="selectAll" @change="handleSelectAll"
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                </TableColumnHeader>
                <TableColumnHeader v-if="showGlobal" header="Domain"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="MAC Address"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Name" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Template" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Profile" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Assigned extension"
                                   class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
                <TableColumnHeader header="Action" class="px-2 py-3.5 text-left text-sm font-semibold text-gray-900"/>
            </template>

            <template #table-body>
                <tr v-for="row in data.data" :key="row.device_uuid">
                    <!-- <TableField class="whitespace-nowrap py-2 pl-4 pr-3 text-sm text-gray-500 sm:pl-6"
                        :text="row.direction" /> -->
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500 text-center"
                                v-if="deviceRestartPermission">
                        <input v-if="row.extension" v-model="selectedItems" type="checkbox" name="action_box[]"
                               :value="row.device_uuid" :data-extension-uuid="row.extension_uuid"
                               class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                    </TableField>
                    <TableField v-if="showGlobal" class="whitespace-nowrap px-2 py-2 text-sm text-gray-500"
                                :text="row.domain_name"/>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_address"/>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_label"/>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.device_template"/>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500" :text="row.profile_name"/>
                    <TableField class="whitespace-nowrap px-2 py-2 text-sm text-gray-500">
                        <a v-if="row.extension_edit_path" :href="row.extension_edit_path">{{ row.extension }}</a>
                    </TableField>
                    <TableField class="whitespace-nowrap px-2 py-1 text-sm text-gray-500">
                        <template #action-buttons>
                            <div class="flex items-center space-x-2 whitespace-nowrap">
                                <DocumentTextIcon v-if="row.edit_path" @click="handleEdit(row.edit_path)"
                                                  class="h-5 w-5 text-black-500 hover:text-black-500 active:h-5 active:w-5 cursor-pointer"/>
                                <CogIcon v-if="row.send_notify_path" @click="handleRestart(row.send_notify_path)"
                                         class="h-5 w-5 text-black-500 hover:text-black-500 active:h-5 active:w-5 cursor-pointer"/>
                                <TrashIcon v-if="row.destroy_path" @click="handleDestroy(row.destroy_path)"
                                           class="h-5 w-5 text-black-500 hover:text-black-500 active:h-5 active:w-5 cursor-pointer"/>
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
                <TransitionRoot as="template" :show="loading" enter="transition-opacity duration-500 ease-out"
                                enter-from="opacity-0" enter-to="opacity-100"
                                leave="transition-opacity duration-300 ease-in"
                                leave-from="opacity-100" leave-to="opacity-0">
                    <!-- Backdrop -->
                    <div class="absolute w-full h-full bg-gray-400 bg-opacity-30">
                        <div class="flex justify-center items-center space-x-3 mt-20">
                            <div>
                                <svg class="animate-spin  h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                     fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4">
                                    </circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </div>

                            <div class="text-lg text-blue-600 m-auto">Loading...</div>
                        </div>
                    </div>
                    <!-- End Backdrop -->

                </TransitionRoot>
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
    <NotificationSimple
        :show="restartRequestNotificationErrorTrigger"
        :isSuccess="false"
        :header="'Warning'"
        :text="'Please select at least one device'"
        @update:show="restartRequestNotificationErrorTrigger = false"/>
    <NotificationSimple
        :show="restartRequestNotificationSuccessTrigger"
        :isSuccess="true"
        :header="'Success'"
        :text="'Restart request has been submitted'"
        @update:show="restartRequestNotificationSuccessTrigger = false"/>
    <AddEditItemModal
        :show="addModalTrigger"
        :header="'Add New Device'"
    >
        <template #modal-body>
            <AddEditDeviceForm
                :device="DeviceObject"
                :templates="templates"
                :profiles="profiles"
                :extensions="extensions"
            />
        </template>
        <template #modal-action-buttons>
            <button type="button"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    @click="handleSave" ref="saveButtonRef">Save</button>
            <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="handleCancel" ref="cancelButtonRef">Cancel</button>
        </template>
    </AddEditItemModal>
    <AddEditItemModal :show="editModalTrigger" :header="'Edit Device'">
        <template #modal-body>
            <AddEditDeviceForm
                :device="DeviceObject"
                :templates="templates"
                :profiles="profiles"
                :extensions="extensions"
                @update:show="editModalTrigger = false"
            />
        </template>
        <template #modal-action-buttons>
            <button type="button"
                    class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    @click="handleSave" ref="saveButtonRef">Save</button>
            <button type="button"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                    @click="handleCancel" ref="cancelButtonRef">Cancel</button>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import {onMounted, ref, reactive} from "vue";
import axios from 'axios';
import {router} from "@inertiajs/vue3";
import Menu from "./components/Menu.vue";
import DataTable from "./components/general/DataTable.vue";
import TableColumnHeader from "./components/general/TableColumnHeader.vue";
import TableField from "./components/general/TableField.vue";
import Paginator from "./components/general/Paginator.vue";
import NotificationSimple from "./components/notifications/Simple.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import AddEditDeviceForm from "./components/forms/AddEditDeviceForm.vue";
import {registerLicense} from '@syncfusion/ej2-base';
import {CogIcon, DocumentTextIcon, MagnifyingGlassIcon, TrashIcon,} from "@heroicons/vue/24/solid";

import {TransitionRoot,} from '@headlessui/vue'

const today = new Date();

const loading = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const restartRequestNotificationSuccessTrigger = ref(false);
const restartRequestNotificationErrorTrigger = ref(false);
const showGlobal = ref(false);
const addModalTrigger = ref(false);
const editModalTrigger = ref(false);

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
    routeDevicesCreate: String,
    routeDevices: String,
    routeSendEventNotifyAll: String,
    templates: Array,
    profiles: Array,
    extensions: Array,
});

let DeviceObject = reactive({
    device_address: '',
    extension_uuid: '',
    device_profile_uuid: '',
    device_template: ''
});

onMounted(() => {
    showGlobal.value = props.deviceGlobalView;
})

const handleSelectAll = () => {
    if (selectAll.value) {
        selectedItems.value = props.data.data.map(item => item.extension_uuid);
    } else {
        selectedItems.value = [];
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
            console.log(page)
        }
    });
}

const handleRestart = (url) => {
    axios.post(url).then((response) => {
        loading.value = false;
        restartRequestNotificationSuccessTrigger.value = true;
    }).catch((error) => {
        console.error('Failed to restart selected:', error);
    });
}

const handleRestartSelected = () => {
    if (selectedItems.value.length > 0) {
        axios.post(props.routeSendEventNotifyAll, {
            extensionIds: selectedItems.value,
        }).then((response) => {
            loading.value = false;
            restartRequestNotificationSuccessTrigger.value = true;
        }).catch((error) => {
            console.error('Failed to restart selected:', error);
        });
    } else {
        restartRequestNotificationErrorTrigger.value = true
    }
}

const handleRestartAll = () => {
    axios.post(props.routeSendEventNotifyAll).then((response) => {
        loading.value = false;
        restartRequestNotificationSuccessTrigger.value = true;
    }).catch((error) => {
        console.error('Failed to restart selected:', error);
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
    addModalTrigger.value = true;
}

const handleEdit = (url) => {
    editModalTrigger.value = true;
    router.visit(url, {
        data: {
            filterData: filterData._rawValue,
        },
        preserveScroll: true,
        preserveState: true,
        only: ["dataObject"],
        onSuccess: (device) => {
            DeviceObject.device_address = device.props.dataObject.device_address
            DeviceObject.device_profile_uuid = device.props.dataObject.device_profile_uuid
            DeviceObject.device_template = device.props.dataObject.device_template
            DeviceObject.extension_uuid = device.props.dataObject.extension_uuid
            //loadingModal.value = false
        }
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

const handleDeviceObjectReset = () => {
    DeviceObject = reactive({
        device_address: '',
        extension_uuid: '',
        device_profile_uuid: '',
        device_template: ''
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

const handleSave = () => {
    console.log("device save. The Save button clicked")
    console.log(DeviceObject)
}

const handleCancel = () => {
    addModalTrigger.value = false
    editModalTrigger.value = false
    handleDeviceObjectReset()
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";</style>
