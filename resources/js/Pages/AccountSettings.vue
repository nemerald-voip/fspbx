<template>
    <MainLayout>

        <div class="mt-3 px-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <div>
                        <div class="mt-3 text-lg font-semibold leading-6 text-gray-600">
                            Account Settings
                        </div>
                    </div>

                </div>
            </div>


        </div>

        <main class="mx-auto max-w-full pb-10 sm:px-6 md:py-12 flex gap-2 md:gap-8 relative">
            <aside :class="isNavCollapsed ? 'w-15' : 'w-64'"
                class="flex flex-col flex-none transition-all duration-300">
                <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-4">
                    <nav class="flex flex-1 flex-col mt-12">
                        <ul role="list" class="flex flex-1 flex-col gap-y-7">
                            <li>
                                <ul role="list" class="-mx-2 space-y-1">
                                    <li v-for="item in navigation" :key="item.key">
                                        <div v-if="!item.children" class="relative">
                                            <button type="button" @click="navigateTo(item.key)"
                                                :class="[isActive(item.key) ? 'bg-gray-100' : 'hover:bg-gray-100', 'group flex items-center gap-x-3 w-full text-left rounded-md p-2 text-sm/6 font-semibold text-gray-700']">
                                                <component :is="item.icon" class="size-6 shrink-0"
                                                    :class="isActive(item.key) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600'" />
                                                <span class="truncate" v-show="!isNavCollapsed">{{ item.name }}</span>
                                            </button>
                                            <span v-if="isNavCollapsed"
                                                class="absolute left-full top-1/2 -translate-y-1/2 ml-4 w-auto min-w-max scale-0 rounded bg-gray-900 p-2 text-xs font-bold text-white transition-all group-hover:scale-100 origin-left z-10">
                                                {{ item.name }}
                                            </span>
                                        </div>

                                        <Disclosure as="div" v-else v-slot="{ open }"
                                            :default-open="parentHasActiveChild(item)">
                                            <div class="relative">
                                                <DisclosureButton
                                                    :class="[parentHasActiveChild(item) ? 'bg-gray-100' : 'hover:bg-gray-100', 'group flex w-full items-center gap-x-3 rounded-md p-2 text-left text-sm/6 font-semibold text-gray-700']">
                                                    <component :is="item.icon" class="size-6 shrink-0"
                                                        :class="parentHasActiveChild(item) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600'" />
                                                    <span class="truncate" v-show="!isNavCollapsed">{{ item.name
                                                    }}</span>
                                                    <ChevronRightIcon v-show="!isNavCollapsed"
                                                        :class="[open ? 'rotate-90 text-gray-500' : 'text-gray-400', 'ml-auto size-5 shrink-0']" />
                                                </DisclosureButton>
                                                <span v-if="isNavCollapsed"
                                                    class="absolute left-full top-1/2 -translate-y-1/2 ml-4 w-auto min-w-max scale-0 rounded bg-gray-900 p-2 text-xs font-bold text-white transition-all group-hover:scale-100 origin-left z-10">
                                                    {{ item.name }}
                                                </span>
                                            </div>
                                            <DisclosurePanel as="ul" class="mt-1"
                                                :class="isNavCollapsed ? 'pl-0' : 'pl-6'">
                                    <li v-for="subItem in item.children" :key="subItem.key" class="relative">
                                        <button type="button" @click="navigateTo(subItem.key)" :class="[
                                            isActive(subItem.key) ? 'bg-gray-100' : 'hover:bg-gray-100',
                                            'group flex w-full items-center gap-x-3 rounded-md py-2 pr-2 text-sm/6 text-gray-700',
                                            isNavCollapsed ? 'justify-center' : 'pl-1'
                                        ]">

                                            <component v-if="subItem.icon" :is="subItem.icon" class="size-5 shrink-0"
                                                :class="isActive(subItem.key) ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600'" />

                                            <!-- label (hidden when collapsed) -->
                                            <span v-show="!isNavCollapsed" class="truncate">{{ subItem.name }}</span>

                                            <!-- fallback initial only if no icon and collapsed -->
                                            <span v-if="isNavCollapsed && !subItem.icon" class="font-bold">
                                                {{ subItem.name.charAt(0) }}
                                            </span>
                                        </button>

                                        <!-- tooltip when collapsed -->
                                        <span v-if="isNavCollapsed"
                                            class="absolute left-full top-1/2 -translate-y-1/2 ml-4 w-auto min-w-max scale-0 rounded bg-gray-900 p-2 text-xs font-bold text-white transition-all group-hover:scale-100 origin-left z-10">
                                            {{ subItem.name }}
                                        </span>
                                    </li>
                                    </DisclosurePanel>
                                    </Disclosure>
                            </li>
                        </ul>
                        </li>
                        </ul>
                    </nav>

                </div>
            </aside>

            <button @click="toggleNav" :class="isNavCollapsed ? 'left-10 sm:left-14' : 'left-64'"
                class="absolute top-1 md:top-14 -translate-x-1/2 bg-white rounded-full p-1.5 border shadow-md text-gray-500 hover:text-indigo-600 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all duration-300 z-10">
                <ChevronDoubleLeftIcon class="size-5 transition-transform duration-300"
                    :class="{ 'rotate-180': isNavCollapsed }" />
            </button>

            <!-- MAIN content -->
            <div class="flex-1 shadow md:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 md:p-6">

                <!-- GENERAL -->
                <section v-show="activeSection === 'general'">
                    <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                        @response="handleResponse" :display-errors="false">
                        <template #empty>

                            <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                <!-- <div class="px-2 py-6 sm:px-6 lg:col-span-2 lg:px-0 lg:py-0">
                                <FormTabs view="vertical" @select="handleTabSelected">
                                    <FormTab name="page0" label="General" :elements="[
                                        'general_tab_label',
                                        'domain_enabled',
                                        'domain_description',
                                        'domain_name',
                                        'time_zone',
                                        'general_submit',

                                    ]" :conditions="[() => true]" />

                                    <FormTab name="locations" label="Locations" :elements="[
                                        'locations_title',
                                        'add_location',
                                        'locations_table',

                                    ]" :conditions="[() => permissions?.location_view]" />

                                    <FormTab name="auto_provisioning" label="Auto Provisioning" :elements="[
                                        'auto_provisioning',
                                        // 'add_location',
                                        // 'locations_table',

                                    ]" :conditions="[() => permissions?.location_view]" />

                                    <FormTab name="page2" label="Emergency Calls" :elements="[
                                        'emergency_calls',
                                        'container_1',
                                        'container_2',
                                        'divider',
                                        'emergency_calls_service_status'
                                    ]" :conditions="[() => true]" />

                                </FormTabs>
                            </div> -->

                                <div class="lg:col-span-12">
                                    <FormElements>

                                        <!-- General Tab -->

                                        <StaticElement name="general_tab_label" tag="h4" content="General" />
                                        <HiddenElement name="domain_uuid" :meta="true" />
                                        <ToggleElement name="domain_enabled" text="Account Status" />
                                        <TextElement name="domain_description" label="Account Name"
                                            placeholder="Enter Account Name" :floating="false" :columns="{
                                                sm: {
                                                    container: 6,
                                                },
                                            }" />
                                        <TextElement name="domain_name" label="Domain" :readonly="true" :columns="{
                                            sm: {
                                                container: 6,
                                            },
                                        }" />
                                        <SelectElement name="time_zone" :groups="true" :items="timezones" :search="true"
                                            :native="false" label="Time Zone" input-type="search" autocomplete="off"
                                            placeholder="Select Time Zone" :floating="false" :strict="false" :columns="{
                                                sm: {
                                                    container: 6,
                                                },
                                            }" />

                                        <ButtonElement name="general_submit" button-label="Save" :submits="true"
                                            align="right" />



                                    </FormElements>
                                </div>
                            </div>
                        </template>
                    </Vueform>
                </section>

                <!-- LOCATIONS -->
                <section v-show="activeSection === 'locations'">
                    <Vueform>
                        <StaticElement name="locations_title" tag="h4" content="Locations"
                            description="Locations help you group your users and resources within your organization. When you assign users to specific locations, they will only be able to see the resources that belong to those locations." />

                        <ButtonElement name="add_location" button-label="Add Location" align="right"
                            @click="handleAddLocationButtonClick" :loading="addLocationButtonLoading"
                            :conditions="[() => permissions?.location_create]" />
                        <GroupElement name="container_1" />
                    </Vueform>

                    <Locations :locations="locations" :loading="isLocationsLoading" :permissions="permissions"
                        @edit-item="handleUpdateLocationButtonClick" @delete-item="handleDeleteLocationButtonClick" />
                </section>

                <!-- AUTO PROVISIONING -->
                <section v-show="activeSection === 'auto_provisioning'">
                    <Vueform>
                        <StaticElement name="locations_title" tag="h4" content="Auto Provisioning"
                            description="Manage your auto provisioning templates." />

                        <GroupElement name="container_1" />
                    </Vueform>

                    <AutoProvisioning :trigger="autoProvisioningTrigger" :routes="routes" :permissions="permissions"
                        :domain_uuid="data.domain_uuid" />
                </section>

                <!-- ROOM MANAGEMENT -->
                <section v-show="activeSection === 'room_management'">
                    <Vueform>
                        <StaticElement name="room_management_title" tag="h4" content="Room Management" description="" />
                        <GroupElement name="container_1" />
                    </Vueform>

                    <RoomManagement :trigger="roomManagementTrigger" :routes="routes" :permissions="permissions"
                        :domain_uuid="data.domain_uuid" />
                </section>

                <!-- ROOM STATUS -->
                <section v-show="activeSection === 'room_status'">
                    <Vueform>
                        <StaticElement name="room_management_title" tag="h4" content="Room Status" description="" />
                        <GroupElement name="container_1" />
                    </Vueform>

                    <RoomStatus :trigger="roomStatusTrigger" :routes="routes" :permissions="permissions"
                        :domain_uuid="data.domain_uuid" />
                </section>

                <!-- EMERGENCY CALLS -->
                <section v-show="activeSection === 'emergency_calls'">

                    <Vueform>
                        <StaticElement name="locations_title" tag="h4" content="Emergency Calls" description="" />


                        <GroupElement name="container_1" />
                    </Vueform>

                    <EmergencyCalls :routes="routes" />
                    <div class="flex p-5 items-center">
                        <div class="w-full border-t border-gray-300" aria-hidden="true" />

                        <div class="w-full border-t border-gray-300" aria-hidden="true" />
                    </div>

                    <EmergencyServiceStatus :routes="routes" />
                </section>

            </div>
        </main>



        <!-- </main> -->

        <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
            @update:show="hideNotification" />

        <CreateLocationModal :show="showCreateLocationModal" :route="routes.locations_store"
            @close="showCreateLocationModal = false" @success="val => showNotification('success', val)"
            @error="handleErrorResponse" @refresh-data="getLocations" />

        <UpdateLocationModal :show="showUpdateLocationModal" :route="locationUpdateRoute" :location="selectedLocation"
            @close="showUpdateLocationModal = false" @success="val => showNotification('success', val)"
            @error="handleErrorResponse" @refresh-data="getLocations" />

        <ConfirmationModal :show="showDeleteLocationConfirmationModal"
            @close="showDeleteLocationConfirmationModal = false" @confirm="confirmDeleteLocationAction"
            :header="'Confirm Deletion'" :loading="isDeleteLocationLoading"
            :text="'This action will permanently delete the selected location. Are you sure you want to proceed?'"
            confirm-button-label="Delete" cancel-button-label="Cancel" />
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import MainLayout from '../Layouts/MainLayout.vue'
import Notification from "./components/notifications/Notification.vue";
import EmergencyCalls from "./components/EmergencyCalls.vue";
import AutoProvisioning from "./components/AutoProvisioning.vue";
import RoomManagement from "./components/RoomManagement.vue";
import RoomStatus from "./components/RoomStatus.vue";
import EmergencyServiceStatus from "./components/EmergencyServiceStatus.vue";
import Locations from "./components/Locations.vue";
import CreateLocationModal from "./components/modal/CreateLocationModal.vue"
import UpdateLocationModal from "./components/modal/UpdateLocationModal.vue"
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronRightIcon } from '@heroicons/vue/20/solid'
import {
    Cog6ToothIcon,
    MapPinIcon,
    WrenchScrewdriverIcon,
    BuildingOffice2Icon,
    ChevronDoubleLeftIcon,
    KeyIcon,
    BellAlertIcon,
    ClipboardDocumentCheckIcon,
    CreditCardIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    data: {
        type: Object,
        default: () => ({}) // Providing an empty object as default
    },
    timezones: Object,
    routes: Object,
    permissions: Object,

})

// State for collapsible navigation
const isNavCollapsed = ref(false)
const toggleNav = () => isNavCollapsed.value = !isNavCollapsed.value

const form$ = ref(null)
const isLocationsLoading = ref(false)
const isDeleteLocationLoading = ref(false)
const locations = ref([])
const addLocationButtonLoading = ref(false)
const showCreateLocationModal = ref(false)
const showUpdateLocationModal = ref(false)
const selectedLocation = ref(null);
const locationUpdateRoute = ref(null);
const showDeleteLocationConfirmationModal = ref(false)
const confirmDeleteLocationAction = ref(null);
const autoProvisioningTrigger = ref(false)
const roomManagementTrigger = ref(false)
const roomStatusTrigger = ref(false)
const activeSection = ref('general')
const isActive = (key) => activeSection.value === key

const navOpen = ref(false)
const parentHasActiveChild = (item) =>
    Array.isArray(item.children) && item.children.some((c) => isActive(c.key))

watch(activeSection, (key) => {
    if (key === 'locations') getLocations()
    if (key === 'auto_provisioning') autoProvisioningTrigger.value = !autoProvisioningTrigger.value
    if (key === 'room_management') roomManagementTrigger.value = !roomManagementTrigger.value
    if (key === 'room_status') roomStatusTrigger.value = !roomStatusTrigger.value
})

const navigation = [
    { key: 'general', name: 'General', icon: Cog6ToothIcon },
    { key: 'locations', name: 'Locations', icon: MapPinIcon },
    { key: 'auto_provisioning', name: 'Auto Provisioning', icon: WrenchScrewdriverIcon },
    { key: 'billing', name: 'Billing', icon: CreditCardIcon },
    {
        key: 'hotel',
        name: 'Hotel Management',
        icon: BuildingOffice2Icon,
        children: [
            { key: 'room_management', name: 'Room Management', icon: KeyIcon },
            { key: 'room_status', name: 'Room Status', icon: ClipboardDocumentCheckIcon },
            { key: 'emergency_calls', name: 'Emergency Calls', icon: BellAlertIcon },

        ],
    },
]

const navigateTo = (key) => {
    activeSection.value = key
    navOpen.value = false // close mobile drawer if open
}

// --- Responsive Collapse Logic ---
const checkScreenSize = () => {
    // Tailwind's `md` breakpoint is 768px.
    // If the window is smaller, collapse the navigation.
    isNavCollapsed.value = window.innerWidth < 768;
};

onMounted(() => {
    // Check screen size on initial load
    checkScreenSize();
    // Add event listener for window resize
    window.addEventListener('resize', checkScreenSize);

    form$.value.update({ // updates form data
        domain_uuid: props.data.domain_uuid ?? null,
        domain_enabled: props.data.domain_enabled ?? false,
        domain_name: props.data.domain_name ?? '',
        domain_description: props.data.domain_description ?? '',
        time_zone: props.data.named_settings.time_zone?.value ?? null,

    })

    form$.value.clean()
    // console.log(form$.value.data);
})

onUnmounted(() => {
    // Clean up the event listener when the component is destroyed
    window.removeEventListener('resize', checkScreenSize);
});

// console.log(props.data)


const notificationType = ref(null);
const notificationShow = ref(null);
const notificationMessages = ref(null);

// const handleTabSelected = (activeTab, previousTab) => {
//     if (activeTab.name == 'locations') {
//         getLocations()
//     }
//     if (activeTab.name == 'auto_provisioning') {
//         autoProvisioningTrigger.value = !autoProvisioningTrigger.value
//     }

// }

const getLocations = async () => {
    isLocationsLoading.value = true
    axios.get(props.routes.locations, {
        params: {
            domain_uuid: props.data.domain_uuid
        }
    })
        .then((response) => {
            locations.value = response.data;
            // console.log(locations.value);

        }).catch((error) => {
            handleErrorResponse(error)
        }).finally(() => {
            isLocationsLoading.value = false
        });
}

const handleAddLocationButtonClick = () => {
    showCreateLocationModal.value = true
}

const handleUpdateLocationButtonClick = (location) => {
    selectedLocation.value = location;
    // Dynamically build the update route
    locationUpdateRoute.value = `/api/locations/${location.location_uuid}`; // or use your route helper if available
    showUpdateLocationModal.value = true;
}

const handleDeleteLocationButtonClick = (uuid) => {
    showDeleteLocationConfirmationModal.value = true;
    confirmDeleteLocationAction.value = () => executeLocationBulkDelete([uuid]);
};

const executeLocationBulkDelete = async (items) => {
    isDeleteLocationLoading.value = true;

    try {
        const response = await axios.post(
            props.routes.locations_bulk_delete,
            { items }
        );
        showNotification('success', response.data.messages);
        getLocations();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        showDeleteLocationConfirmationModal.value = false;
        isDeleteLocationLoading.value = false;
    }
};

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    // Build a lookup of original settings by subcategory
    const originalMap = props.data.settings.reduce((map, s) => {
        map[s.domain_setting_subcategory] = {
            value: s.domain_setting_value,
            category: s.domain_setting_category,
            uuid: s.domain_setting_uuid,
            enabled: s.domain_setting_enabled,
        }
        return map
    }, {})

    const updatedSettings = []
    const newSettings = []

    // Meta‐fields that are NOT “settings”
    const metaKeys = [
        'domain_uuid',
        'domain_enabled',
        'domain_description',
        'domain_name',
        // plus anything else your form has at top‐level
    ]

    // Handle updates to EXISTING settings
    Object.entries(originalMap).forEach(([subcat, orig]) => {
        // if the form actually sent us this subcat...
        if (requestData.hasOwnProperty(subcat)) {
            const newValue = requestData[subcat]
            if (newValue !== orig.value) {
                updatedSettings.push({
                    domain_uuid: props.data.domain_uuid,
                    domain_setting_uuid: orig.uuid,
                    domain_setting_category: orig.category,
                    domain_setting_subcategory: subcat,
                    domain_setting_value: newValue,
                    domain_setting_enabled: true,
                })
            }
        }
    })

    // Handle brand-new settings
    Object.keys(requestData).forEach(key => {
        // if it’s not one of the meta-fields AND not in originalMap
        if (!metaKeys.includes(key) && !originalMap.hasOwnProperty(key)) {
            newSettings.push({
                domain_uuid: props.data.domain_uuid,
                domain_setting_subcategory: key,
                domain_setting_value: requestData[key],
                domain_setting_enabled: true,
            })
        }
    })

    // Overwrite the “settings” payload and add “newSettings”
    const payload = {
        ...requestData,
        updatedSettings,
        newSettings
    }

    // console.log(requestData);
    return await form$.$vueform.services.axios.put(props.routes.settings_update, payload)
};

function clearErrorsRecursive(el$) {
    // clear this element’s errors
    el$.messageBag?.clear()

    // if it has child elements, recurse into each
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    // Clear form including nested elements 
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    // Display custom errors for elements
    if (response?.data?.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    showNotification('success', response.data.messages);

}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append('Could not prepare form')
            break

        // Error occured because response status is outside of 2xx
        case 'submit':
            handleErrorResponse(error);
            console.log(error) // AxiosError object
            // console.log(error.response) // axios response
            // console.log(error.response.status) // HTTP status code
            // console.log(error.response.data) // response data

            // console.log(error.response.data.errors)


            break

        // Request cancelled (no response object)
        case 'cancel':
            console.log(error) // Error object

            form$.messageBag.append('Request cancelled')
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}



const plans = [
    { name: 'Startup', priceMonthly: '$29', priceYearly: '$290', limit: 'Up to 5 active job postings', selected: true },
    {
        name: 'Business',
        priceMonthly: '$99',
        priceYearly: '$990',
        limit: 'Up to 25 active job postings',
        selected: false,
    },
    {
        name: 'Enterprise',
        priceMonthly: '$249',
        priceYearly: '$2490',
        limit: 'Unlimited active job postings',
        selected: false,
    },
]
const payments = [
    {
        id: 1,
        date: '1/1/2020',
        datetime: '2020-01-01',
        description: 'Business Plan - Annual Billing',
        amount: 'CA$109.00',
        href: '#',
    },
    // More payments...
]



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


</script>