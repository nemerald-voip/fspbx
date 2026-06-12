<template>
    <PageWithSideMenu title="Logs" :navigation="navigation" :pages="pages" :header-icon="DocumentTextIcon"
        :initial-menu-option="initialMenuOption" @update-selected-menu-option="handleUpdateSelectedMenuOption">

        <template #default="{ selectedMenuOption }">
            <div class="mb-4 flex justify-end">
                <button v-if="permissions?.email_test_send" type="button"
                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="openTestEmailModal">
                    <EnvelopeIcon class="h-4 w-4" />
                    Send Test Email
                </button>
            </div>

            <!-- EMAILS -->
            <section v-show="selectedMenuOption === 'emails'">
                <Vueform>
                    <StaticElement name="locations_title" tag="h4" content="Emails" />
                </Vueform>

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

                <FreeSwitchLogs :trigger="freeswitchLogsTrigger" :routes="routes" />
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
                            <label for="test_email_address" class="block text-sm font-medium text-gray-700">Email address</label>
                            <input id="test_email_address" v-model.trim="testEmailForm.email" type="email" required
                                autocomplete="email" placeholder="name@example.com"
                                class="mt-1 block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600" />
                            <p v-if="testEmailErrors.email" class="mt-2 text-xs text-red-600">{{ testEmailErrors.email[0] }}</p>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button"
                                class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                @click="closeTestEmailModal">
                                Cancel
                            </button>
                            <button type="submit" :disabled="testEmailLoading"
                                class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60">
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
import InboundWebhooks from "./components/InboundWebhooks.vue";
import MessageLogs from "./components/MessageLogs.vue";
import FaxLogs from "./components/FaxLogs.vue";
import FreeSwitchLogs from "./components/FreeSwitchLogs.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";

import {
    EnvelopeIcon,
    InboxArrowDownIcon,
    DocumentTextIcon,
    ChatBubbleLeftRightIcon,
    PrinterIcon,
    ServerStackIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    startPeriod: String,
    endPeriod: String,
    timezone: String,
    routes: Object,
    permissions: Object,
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
const inboundWebhooksTrigger = ref(false)
const messageLogsTrigger = ref(false)
const faxLogsTrigger = ref(false)
const freeswitchLogsTrigger = ref(false)
const initialMenuOption = ref(null)
const selectedMenuOption = ref(null)
const showTestEmailModal = ref(false)
const testEmailLoading = ref(false)
const testEmailErrors = ref({})
const testEmailForm = reactive({
    email: '',
})


const pages = [
    { name: 'Dashboard', href: props.routes.dashboard_route, current: true },
    { name: 'Logs', href: '#', current: true },
]

const handleUpdateSelectedMenuOption = (key) => {
    selectedMenuOption.value = key
    if (key === 'emails') emailsTrigger.value = !emailsTrigger.value
    if (key === 'inbound_webhooks') inboundWebhooksTrigger.value = !inboundWebhooksTrigger.value
    if (key === 'message_logs') messageLogsTrigger.value = !messageLogsTrigger.value
    if (key === 'fax_logs') faxLogsTrigger.value = !faxLogsTrigger.value
    if (key === 'freeswitch_logs') freeswitchLogsTrigger.value = !freeswitchLogsTrigger.value
}

const refreshEmailLogsIfVisible = () => {
    if (selectedMenuOption.value === 'emails') {
        emailsTrigger.value = !emailsTrigger.value
    }
}

const navigation = computed(() => {
    const items = [
        { key: 'emails', name: 'Emails', icon: EnvelopeIcon },
        { key: 'inbound_webhooks', name: 'Inbound Webhooks', icon: InboxArrowDownIcon },
        { key: 'message_logs', name: 'Messages', icon: ChatBubbleLeftRightIcon },
    ]

    if (props.permissions?.fax_log_view) {
        items.push({ key: 'fax_logs', name: 'Faxes', icon: PrinterIcon })
    }

    if (props.permissions?.log_view) {
        items.push({ key: 'freeswitch_logs', name: 'FreeSWITCH', icon: ServerStackIcon })
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

        handleUpdateSelectedMenuOption(initialMenuOption.value)
    }
})


const notificationType = ref(null);
const notificationShow = ref(null);
const notificationMessages = ref(null);


const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
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
        refreshEmailLogsIfVisible()
    }).catch((error) => {
        testEmailErrors.value = error.response?.data?.errors ?? {}
        notificationType.value = 'error'
        notificationMessages.value = error.response?.data?.messages
            ?? error.response?.data?.errors
            ?? { error: ['Unable to send the test email.'] }
        notificationShow.value = true
        refreshEmailLogsIfVisible()
    }).finally(() => {
        testEmailLoading.value = false
    })
}



</script>
