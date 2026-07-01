<template>
    <PageWithSideMenu title="Logs" :navigation="navigation" :pages="pages" :header-icon="DocumentTextIcon"
        :initial-menu-option="initialMenuOption" @update-selected-menu-option="handleUpdateSelectedMenuOption">

        <template #default="{ selectedMenuOption }">
            <!-- EMAILS -->
            <section v-show="selectedMenuOption === 'emails'">
                <div class="flex items-start justify-between gap-3">
                    <Vueform class="min-w-0">
                        <StaticElement name="locations_title" tag="h4" content="Emails" />
                    </Vueform>

                    <button v-if="permissions?.email_test_send" type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-60"
                        @click="openTestEmailModal">
                        <EnvelopeIcon class="h-4 w-4" />
                        Send Test Email
                    </button>
                </div>

                <EmailLogs :trigger="emailsTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod"
                    :timezone="timezone" :routes="routes" :permissions="permissions" :domain-options="domainOptions"
                    :selected-domain-uuid="selectedDomainUuid" />
            </section>

            <!-- WEBHOOKS -->
            <section v-show="selectedMenuOption === 'inbound_webhooks'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="Inbound Webhooks" />
                </Vueform>

                <InboundWebhooks :trigger="inboundWebhooksTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod"
                    :timezone="timezone" :routes="routes" :permissions="permissions" />
            </section>

            <!-- Messages -->
            <section v-show="selectedMenuOption === 'message_logs'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="Messages" />
                </Vueform>

                <MessageLogs :trigger="messageLogsTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod"
                    :timezone="timezone" :routes="routes" :permissions="permissions" />
            </section>

            <!-- FAXES -->
            <section v-show="selectedMenuOption === 'fax_logs'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="Faxes" />
                </Vueform>

                <FaxLogs :trigger="faxLogsTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod"
                    :timezone="timezone" :routes="routes" :permissions="permissions" :domain-options="domainOptions"
                    :selected-domain-uuid="selectedDomainUuid" />
            </section>

            <!-- FREESWITCH -->
            <section v-show="selectedMenuOption === 'freeswitch_logs'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="FreeSWITCH" />
                </Vueform>

                <FreeSwitchLogs
                    :trigger="freeswitchLogsTrigger"
                    :routes="routes"
                    :permissions="permissions"
                    @success="showSuccessNotification"
                    @error="showErrorNotification"
                />
            </section>

            <!-- NGINX -->
            <section v-show="selectedMenuOption === 'nginx_logs'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="Web service" />
                </Vueform>

                <NginxLogs
                    :trigger="nginxLogsTrigger"
                    :routes="routes"
                    :permissions="permissions"
                />
            </section>

            <!-- LARAVEL -->
            <section v-show="selectedMenuOption === 'laravel_logs'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="Laravel" />
                </Vueform>

                <LaravelLogs
                    :trigger="laravelLogsTrigger"
                    :routes="routes"
                    :permissions="permissions"
                />
            </section>

            <!-- Keep TigerTMS last so new log tabs stay above the PMS integration logs. -->
            <section v-if="features?.tigertms_logs" v-show="selectedMenuOption === 'tigertms_logs'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="TigerTMS" />
                </Vueform>

                <TigerTmsLogs :trigger="tigerTmsLogsTrigger" :startPeriod="startPeriod" :endPeriod="endPeriod"
                    :timezone="timezone" :routes="routes" :permissions="permissions" :domain-options="domainOptions"
                    :selected-domain-uuid="selectedDomainUuid" />
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

            <AddEditItemModal :show="showTestEmailModal" header="Send Test Email" :loading="testEmailLoading"
                @close="closeTestEmailModal">
                <template #modal-body>
                    <form class="space-y-4" @submit.prevent="sendTestEmail">
                        <div>
                            <label for="test_email_address" class="block text-sm font-medium text-body">Email address</label>
                            <input id="test_email_address" v-model.trim="testEmailForm.email" type="email" required
                                autocomplete="email" placeholder="name@example.com"
                                class="mt-1 block w-full rounded-md border-0 py-1.5 text-sm text-heading shadow-sm ring-1 bg-surface ring-inset ring-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-focus" />
                            <p v-if="testEmailErrors.email" class="mt-2 text-xs text-danger">{{ testEmailErrors.email[0] }}</p>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button"
                                class="rounded-md bg-surface px-3 py-1.5 text-sm font-medium text-body shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2"
                                @click="closeTestEmailModal">
                                Cancel
                            </button>
                            <button type="submit" :disabled="testEmailLoading"
                                class="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-60">
                                <EnvelopeIcon class="h-4 w-4" />
                                {{ testEmailLoading ? 'Sending...' : 'Send' }}
                            </button>
                        </div>
                    </form>
                </template>
            </AddEditItemModal>
        </template>

    </PageWithSideMenu>
</template>

<script setup>
import { computed, reactive, ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import PageWithSideMenu from '../Layouts/PageWithSideMenu.vue'
import Notification from "./components/notifications/Notification.vue";
import EmailLogs from "./components/EmailLogs.vue";
import TigerTmsLogs from "./components/TigerTmsLogs.vue";
import InboundWebhooks from "./components/InboundWebhooks.vue";
import MessageLogs from "./components/MessageLogs.vue";
import FaxLogs from "./components/FaxLogs.vue";
import FreeSwitchLogs from "./components/FreeSwitchLogs.vue";
import NginxLogs from "./components/NginxLogs.vue";
import LaravelLogs from "./components/LaravelLogs.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";

import {
    EnvelopeIcon,
    InboxArrowDownIcon,
    BuildingOffice2Icon,
    DocumentTextIcon,
    ChatBubbleLeftRightIcon,
    PrinterIcon,
    ServerStackIcon,
    GlobeAltIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
    features: {
        type: Object,
        default: () => ({}),
    },
    domainOptions: {
        type: Array,
        default: () => [],
    },
    selectedDomainUuid: String,

})

const isDeleteLocationLoading = ref(false)
const showDeleteLocationConfirmationModal = ref(false)
const confirmDeleteLocationAction = ref(null);
const emailsTrigger = ref(false)
const tigerTmsLogsTrigger = ref(false)
const inboundWebhooksTrigger = ref(false)
const messageLogsTrigger = ref(false)
const faxLogsTrigger = ref(false)
const freeswitchLogsTrigger = ref(false)
const nginxLogsTrigger = ref(false)
const laravelLogsTrigger = ref(false)
const initialMenuOption = ref(null)
const showTestEmailModal = ref(false)
const testEmailLoading = ref(false)
const testEmailErrors = ref({})
const testEmailForm = reactive({
    email: '',
})
const testEmailRefreshTimers = []


const pages = [
    { name: 'Dashboard', href: props.routes.dashboard_route, current: true },
    { name: 'Logs', href: '#', current: true },
]

const handleUpdateSelectedMenuOption = (key) => {
    if (key === 'emails') emailsTrigger.value = !emailsTrigger.value
    if (key === 'tigertms_logs') tigerTmsLogsTrigger.value = !tigerTmsLogsTrigger.value
    if (key === 'inbound_webhooks') inboundWebhooksTrigger.value = !inboundWebhooksTrigger.value
    if (key === 'message_logs') messageLogsTrigger.value = !messageLogsTrigger.value
    if (key === 'fax_logs') faxLogsTrigger.value = !faxLogsTrigger.value
    if (key === 'freeswitch_logs') freeswitchLogsTrigger.value = !freeswitchLogsTrigger.value
    if (key === 'nginx_logs') nginxLogsTrigger.value = !nginxLogsTrigger.value
    if (key === 'laravel_logs') laravelLogsTrigger.value = !laravelLogsTrigger.value
}

const navigation = computed(() => {
    const items = [
        { key: 'emails', name: 'Emails', icon: EnvelopeIcon },
        { key: 'message_logs', name: 'Messages', icon: ChatBubbleLeftRightIcon },
    ]

    items.push({ key: 'inbound_webhooks', name: 'Inbound Webhooks', icon: InboxArrowDownIcon })

    if (props.permissions?.fax_log_view) {
        items.push({ key: 'fax_logs', name: 'Faxes', icon: PrinterIcon })
    }

    if (props.permissions?.log_view) {
        items.push({ key: 'freeswitch_logs', name: 'FreeSWITCH', icon: ServerStackIcon })
        items.push({ key: 'nginx_logs', name: 'Web service', icon: GlobeAltIcon })
        items.push({ key: 'laravel_logs', name: 'Laravel', icon: DocumentTextIcon })
    }

    // Keep TigerTMS as the last tab. Add future log tabs above this block.
    if (props.features?.tigertms_logs) {
        items.push({ key: 'tigertms_logs', name: 'TigerTMS', icon: BuildingOffice2Icon })
    }

    return items
})


onMounted(() => {
    if (navigation.value.length) {
        const requestedOption = new URLSearchParams(window.location.search).get('tab')
        const fallbackOption = navigation.value[0].key
        initialMenuOption.value = navigation.value.some((item) => item.key === requestedOption)
            ? requestedOption
            : fallbackOption
    }
})

onUnmounted(() => {
    testEmailRefreshTimers.forEach((timer) => clearTimeout(timer))
})


const notificationType = ref(null);
const notificationShow = ref(null);
const notificationMessages = ref(null);


const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showSuccessNotification = (messages) => {
    notificationType.value = 'success'
    notificationMessages.value = messages
    notificationShow.value = true
}

const showErrorNotification = (messages) => {
    notificationType.value = 'error'
    notificationMessages.value = messages
    notificationShow.value = true
}

const openTestEmailModal = () => {
    testEmailErrors.value = {}
    showTestEmailModal.value = true
}

const closeTestEmailModal = () => {
    showTestEmailModal.value = false
    testEmailLoading.value = false
    testEmailErrors.value = {}
    testEmailForm.email = ''
}

const sendTestEmail = () => {
    if (testEmailLoading.value) return

    testEmailLoading.value = true
    testEmailErrors.value = {}

    axios.post(props.routes.test_email_send, {
        email: testEmailForm.email,
    }).then((response) => {
        closeTestEmailModal()
        notificationType.value = 'success'
        notificationMessages.value = response.data.messages
        notificationShow.value = true
        emailsTrigger.value = !emailsTrigger.value
        scheduleTestEmailRefreshes(response.data.log_uuid)
    }).catch((error) => {
        testEmailErrors.value = error.response?.data?.errors ?? {}
        notificationType.value = 'error'
        notificationMessages.value = error.response?.data?.messages
            ?? error.response?.data?.errors
            ?? { error: ['Unable to send the test email.'] }
        notificationShow.value = true
    }).finally(() => {
        testEmailLoading.value = false
    })
}

const scheduleTestEmailRefreshes = (logUuid = null) => {
    ;[15000, 60000].forEach((delay) => {
        testEmailRefreshTimers.push(setTimeout(() => {
            refreshTestEmailLog(logUuid)
        }, delay))
    })
}

const refreshTestEmailLog = (logUuid = null) => {
    if (!logUuid || !props.routes.email_delivery_details) {
        emailsTrigger.value = !emailsTrigger.value
        return
    }

    axios.get(props.routes.email_delivery_details.replace('__UUID__', logUuid))
        .finally(() => {
            emailsTrigger.value = !emailsTrigger.value
        })
}



</script>
