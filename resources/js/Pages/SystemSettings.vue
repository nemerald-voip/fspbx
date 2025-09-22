<template>
    <MainLayout>

        <div class="mt-3 px-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <div>
                        <div class="mt-3 text-lg font-semibold leading-6 text-gray-600">
                            System Settings
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

                <!-- PAYMENT GATEWAYS -->
                <section v-show="activeSection === 'payment_gateways'">

                    <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                        @response="handleResponse" :display-errors="false">
                        <template #empty>

                            <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">

                                <div class="lg:col-span-12">
                                    <FormElements>

                                        <!-- Payment Gateways Tab -->

                                        <StaticElement name="payment_gateways_tab_label" tag="h4"
                                            content="Payment Gateways" description="Manage Payment Providers" />
                                        <ListElement name="gateways" :controls="{
                                            add: false,
                                            remove: false,
                                        }"
                                            :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                            <template #default="{ index }">
                                                <ObjectElement :name="index">
                                                    <StaticElement name="name" tag="p" :content="(el$) => {
                                                        // console.log (el$.form$.el$('gateways').value[index].name);
                                                        return el$.form$.el$('gateways').value[index].name
                                                    }" :columns="{ container: 6, }"
                                                        :attrs="{ class: 'text-base font-semibold' }">
                                                        <template #after="{ el$ }">
                                                            <Badge
                                                                v-if="el$.form$.el$('gateways').value[index].is_enabled"
                                                                class="mt-1" :text="'Activated'"
                                                                :backgroundColor="'bg-green-50'"
                                                                :textColor="'text-green-700'"
                                                                :ringColor="'ring-green-600/20'" />

                                                            <Badge v-else class="mt-1" :text="'Disabled'"
                                                                :backgroundColor="'bg-rose-50'"
                                                                :textColor="'text-rose-700'"
                                                                :ringColor="'ring-rose-600/20'" />
                                                        </template>
                                                    </StaticElement>
                                                    <HiddenElement name="is_enabled" :meta="true" />
                                                    <ButtonElement name="gateway_activate" button-label="Configure"
                                                        @click="handlePaymentGatewaySettingsClick(index)" :columns="{
                                                            container: 6,
                                                        }" align="right" :conditions="[
                                                            ['gateways.*.is_enabled', false]
                                                        ]" />

                                                    <ButtonElement name="gwateway_deactivate" button-label="Deactivate"
                                                        @click="handlePaymentGatewayDeactivateClick(index)"
                                                        :secondary="true" :columns="{
                                                            container: 6,
                                                        }" align="right" :conditions="[
                                                            ['gateways.*.is_enabled', true]
                                                        ]" />

                                                </ObjectElement>
                                            </template>
                                        </ListElement>



                                    </FormElements>
                                </div>
                            </div>
                        </template>
                    </Vueform>
                </section>
            </div>
        </main>

        <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
            @update:show="hideNotification" />

        <UpdateStripeSettingsModal :settings="gatewaySettings" :uuid="gatewayUuid" :is-enabled="gatewayEnabled"
            :show="showStripeSettingsModal" :route="routes.payment_gateway_update"
            @refresh-data="getPaymentGatewaysData" @close="showStripeSettingsModal = false" />


    </MainLayout>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import MainLayout from '../Layouts/MainLayout.vue'

import Notification from "./components/notifications/Notification.vue";
import UpdateStripeSettingsModal from "./components/modal/UpdateStripeSettingsModal.vue";
import Badge from "@generalComponents/Badge.vue";
import { ChevronRightIcon } from '@heroicons/vue/20/solid'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import {
    Cog6ToothIcon,
    ChevronDoubleLeftIcon,
} from '@heroicons/vue/24/outline'


const props = defineProps({
    routes: Object,
})

const form$ = ref(null)
const isNavCollapsed = ref(false)
const toggleNav = () => isNavCollapsed.value = !isNavCollapsed.value
const showStripeSettingsModal = ref(false);
const gatewaySettings = ref(null);
const gatewayUuid = ref(null);
const gatewayEnabled = ref(null);
const activeSection = ref('payment_gateways')
const isActive = (key) => activeSection.value === key
const navOpen = ref(false)

const navigation = [
    { key: 'payment_gateways', name: 'Payment Gateways', icon: Cog6ToothIcon },

    // {
    //     key: 'hotel',
    //     name: 'Hotel Management',
    //     icon: BuildingOffice2Icon,
    //     children: [
    //         { key: 'room_management', name: 'Room Management', icon: KeyIcon },
    //         { key: 'room_status', name: 'Room Status', icon: ClipboardDocumentCheckIcon },
    //         { key: 'emergency_calls', name: 'Emergency Calls', icon: BellAlertIcon },

    //     ],
    // },
]

watch(activeSection, (key) => {
    if (key === 'payment_gateways') getPaymentGatewaysData()

})

const notificationType = ref(null);
const notificationShow = ref(null);
const notificationMessages = ref(null);


const getPaymentGatewaysData = async () => {
    try {
        const response = await form$.value.$vueform.services.axios.get(props.routes.payment_gateways)
        form$.value.update({
            gateways: response.data
        })
        form$.value.clean()
    }
    catch (err) {
        console.error('Failed to load gateways:', err)
        return []                    // return an empty array on error
    }
}


const handlePaymentGatewaySettingsClick = (index) => {
    if (form$.value.el$('gateways').value[index].slug == 'stripe') {
        showStripeSettingsModal.value = true;
        gatewaySettings.value = form$.value.el$('gateways').value[index].settings
        gatewayUuid.value = form$.value.el$('gateways').value[index].uuid
        gatewayEnabled.value = form$.value.el$('gateways').value[index].is_enabled
    }
}

const handlePaymentGatewayDeactivateClick = (index) => {

    axios.post(props.routes.payment_gateway_deactivate,
        { 'uuid': form$.value.el$('gateways').value[index].uuid },
    )
        .then((response) => {
            showNotification('success', response.data.messages);
        }).catch((error) => {
            handleErrorResponse(error);
        }).finally(() => {
            getPaymentGatewaysData()
        });
}

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

    getPaymentGatewaysData()

    // console.log(form$.value.data);
})

onUnmounted(() => {
    // Clean up the event listener when the component is destroyed
    window.removeEventListener('resize', checkScreenSize);
});

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