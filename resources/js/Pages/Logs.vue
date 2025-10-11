<template>
    <MainLayout>

        <div class="mt-3 px-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <div>
                        <div class="mt-3 text-lg font-semibold leading-6 text-gray-600">
                            Logs
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
                        <ul role="list" class="flex flex-1 flex-col gap-y-7 pb-5">
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
            <div class="flex-1 shadow md:rounded-md text-gray-600 bg-gray-50 px-4 py-6 md:p-6">

                <!-- EMAILS -->
                <section v-show="activeSection === 'emails'">
                    <Vueform>
                        <StaticElement name="locations_title" tag="h4" content="Emails" />
                    </Vueform>

                    <EmailLogs :trigger="emailsTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod" :timezone="timezone" :routes="routes" :permissions="permissions" />
                </section>

                <!-- WEBHOOKS -->
                <section v-show="activeSection === 'inbound_webhooks'">
                    <Vueform>
                        <StaticElement name="locations_title" tag="h4" content="Inbound Webhooks" />
                    </Vueform>

                    <InboundWebhooks :trigger="inboundWebhooksTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod" :timezone="timezone" :routes="routes" :permissions="permissions" />
                </section>

                <!-- AUTO PROVISIONING
                <section v-show="activeSection === 'auto_provisioning'">
                    <Vueform>
                        <StaticElement name="locations_title" tag="h4" content="Auto Provisioning"
                            description="Manage your auto provisioning templates." />

                        <GroupElement name="container_1" />
                    </Vueform>

                    <AutoProvisioning :trigger="emailsTrigger" :routes="routes" :permissions="permissions"
                        :domain_uuid="data.domain_uuid" />
                </section> -->



            </div>
        </main>



        <!-- </main> -->

        <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
            @update:show="hideNotification" />

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
import EmailLogs from "./components/EmailLogs.vue";
import InboundWebhooks from "./components/InboundWebhooks.vue";

import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import { ChevronRightIcon } from '@heroicons/vue/20/solid'
import {
    EnvelopeIcon,
    InboxArrowDownIcon,
    WrenchScrewdriverIcon,
    BuildingOffice2Icon,
    ChevronDoubleLeftIcon,
    KeyIcon,
    BellAlertIcon,
    ClipboardDocumentCheckIcon,
    CreditCardIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,

})

// State for collapsible navigation
const isNavCollapsed = ref(false)
const toggleNav = () => isNavCollapsed.value = !isNavCollapsed.value

const isDeleteLocationLoading = ref(false)
const showDeleteLocationConfirmationModal = ref(false)
const confirmDeleteLocationAction = ref(null);
const emailsTrigger = ref(false)
const inboundWebhooksTrigger = ref(false)
const activeSection = ref('emails')
const isActive = (key) => activeSection.value === key

const navOpen = ref(false)
const parentHasActiveChild = (item) =>
    Array.isArray(item.children) && item.children.some((c) => isActive(c.key))

watch(activeSection, (key) => {
    if (key === 'emails') emailsTrigger.value = !emailsTrigger.value
    if (key === 'inbound_webhooks') inboundWebhooksTrigger.value = !inboundWebhooksTrigger.value
})

const navigation = [
    { key: 'emails', name: 'Emails', icon: EnvelopeIcon },
    { key: 'inbound_webhooks', name: 'Inbound Webhooks', icon: InboxArrowDownIcon },


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

const handleDeleteEmailButtonClick = () => {

}

onMounted(() => {
    // Check screen size on initial load
    checkScreenSize();
    // Add event listener for window resize
    window.addEventListener('resize', checkScreenSize);

    emailsTrigger.value = !emailsTrigger.value
})

onUnmounted(() => {
    // Clean up the event listener when the component is destroyed
    window.removeEventListener('resize', checkScreenSize);
});


const notificationType = ref(null);
const notificationShow = ref(null);
const notificationMessages = ref(null);


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