<template>
    <PageWithSideMenu title="Logs" :navigation="navigation" :pages="pages" :header-icon="DocumentTextIcon"
        :initial-menu-option="initialMenuOption" @update-selected-menu-option="handleUpdateSelectedMenuOption">

        <template #default="{ selectedMenuOption }">

            <!-- EMAILS -->
            <section v-show="selectedMenuOption === 'emails'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="Emails" />
                </Vueform>

                <EmailLogs :trigger="emailsTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod"
                    :timezone="timezone" :routes="routes" :permissions="permissions" />
            </section>

            <!-- WEBHOOKS -->
            <section v-show="selectedMenuOption === 'inbound_webhooks'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="Inbound Webhooks" />
                </Vueform>

                <InboundWebhooks :trigger="inboundWebhooksTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod"
                    :timezone="timezone" :routes="routes" :permissions="permissions" />
            </section>


        </template>

        <template #overlays>
            <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
                @update:show="hideNotification" />

            <ConfirmationModal :show="showDeleteLocationConfirmationModal"
                @close="showDeleteLocationConfirmationModal = false" @confirm="confirmDeleteLocationAction"
                :header="'Confirm Deletion'" :loading="isDeleteLocationLoading"
                :text="'This action will permanently delete the selected location. Are you sure you want to proceed?'"
                confirm-button-label="Delete" cancel-button-label="Cancel" />
        </template>

    </PageWithSideMenu>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import PageWithSideMenu from '../Layouts/PageWithSideMenu.vue'
import Notification from "./components/notifications/Notification.vue";
import EmailLogs from "./components/EmailLogs.vue";
import InboundWebhooks from "./components/InboundWebhooks.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";

import {
    EnvelopeIcon,
    InboxArrowDownIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,

})

const isDeleteLocationLoading = ref(false)
const showDeleteLocationConfirmationModal = ref(false)
const confirmDeleteLocationAction = ref(null);
const emailsTrigger = ref(false)
const inboundWebhooksTrigger = ref(false)
const initialMenuOption = ref(null)


const pages = [
    { name: 'Dashboard', href: props.routes.dashboard_route, current: true },
    { name: 'Logs', href: '#', current: true },
]

const handleUpdateSelectedMenuOption = (key) => {
    if (key === 'emails') emailsTrigger.value = !emailsTrigger.value
    if (key === 'inbound_webhooks') inboundWebhooksTrigger.value = !inboundWebhooksTrigger.value
}

const navigation = [
    { key: 'emails', name: 'Emails', icon: EnvelopeIcon },
    { key: 'inbound_webhooks', name: 'Inbound Webhooks', icon: InboxArrowDownIcon },
]


onMounted(() => {
    if (navigation.length) {
        initialMenuOption.value = navigation[0].key
        // handleUpdateSelectedMenuOption(navigation.value[0].key)
    }
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



</script>