<template>
    <MainLayout>
        <TopBanner :show="showTopBanner" @close="showTopBanner = false" color="bg-rose-600" :text="topBannerText" />

        <main class="bg-slate-50/60">
            <div class="mx-auto max-w-none px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-cyan-700">Account dashboard</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">{{
                            company_data.company_name }}</h1>
                    </div>

                    <a v-if="permissions.account_settings_index" type="button" :href="routes.account_settings_page"
                        class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400">
                        <CogIcon class="-ml-0.5 size-5 text-gray-400" aria-hidden="true" />
                        Settings
                    </a>
                </div>

                <div
                    class="mx-auto grid max-w-2xl grid-cols-1 grid-rows-1 items-start gap-6 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                    <!-- Right column: Account summary + My Extension -->
                    <div class="space-y-6 lg:col-start-3 lg:row-end-1">
                        <div v-if="permissions.extension_view" class="rounded-lg bg-white p-6 ring-1 ring-gray-200">
                            <dl class="flex flex-wrap">
                                <div class="flex-auto truncate border-b border-gray-100 pb-5">
                                    <dt class="text-sm font-medium leading-6 text-gray-500">Account name</dt>
                                    <div class="mt-1 text-lg font-semibold leading-6 text-gray-950">{{
                                        company_data.company_name }}</div>
                                </div>

                                <div v-if="!countsLoaded" class="w-full">
                                    <SkeletonRows :rows="3" class="pt-6" />
                                </div>

                                <template v-else>
                                    <div v-if="counts.extensions !== undefined && counts.extensions >= 0"
                                        class="mt-6 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <ContactPhoneIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                        </dt>
                                        <dd class="min-w-0 flex-1 text-sm leading-6 text-gray-500">
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="font-medium text-gray-700">Extensions</span>
                                                <span class="font-semibold text-gray-950">{{ counts.extensions }}</span>
                                            </div>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <span
                                                    class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                                    Online: {{ onlineExtensions }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                                    Offline: {{ offlineExtensions }}
                                                </span>
                                            </div>
                                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                                <div class="h-full rounded-full bg-emerald-500"
                                                    :style="{ width: registrationPercent + '%' }"></div>
                                            </div>
                                        </dd>
                                    </div>

                                    <div v-if="counts.phone_numbers !== undefined && counts.phone_numbers >= 0"
                                        class="mt-5 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <DialpadIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                        </dt>
                                        <dd class="text-sm leading-6 text-gray-500">Phone Numbers: <span
                                                class="font-semibold text-gray-900">{{ counts.phone_numbers }}</span>
                                        </dd>
                                    </div>

                                    <div v-if="counts.faxes !== undefined && counts.faxes >= 0"
                                        class="mt-4 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <FaxIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                        </dt>
                                        <dd class="text-sm leading-6 text-gray-500">Virtual Faxes: <span
                                                class="font-semibold text-gray-900">{{ counts.faxes }}</span></dd>
                                    </div>

                                    <div class="mt-4 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <ClockIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                        </dt>
                                        <dd class="text-sm font-medium leading-6 text-gray-500">Time Zone: {{
                                            company_data.time_zone }}</dd>
                                    </div>
                                </template>
                            </dl>
                        </div>

                        <div v-if="my_extension_status"
                            class="overflow-hidden rounded-lg bg-white ring-1 ring-gray-200">
                            <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-cyan-700">My Extension</p>
                                    <h3 class="mt-1 text-sm font-semibold text-gray-950">{{ my_extension_status.name }}</h3>
                                </div>

                                <button type="button" @click="openExtensionModal(my_extension_status.extension_uuid)"
                                    class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400">
                                    <CogIcon class="-ml-0.5 size-5 text-gray-400" aria-hidden="true" />
                                    Manage
                                </button>
                            </div>

                            <div class="px-5 py-4">
                                <p class="text-sm font-medium text-gray-700">Active call handling</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span v-if="my_extension_status.do_not_disturb"
                                        class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-400/20">
                                        DND
                                    </span>
                                    <span v-for="forward in activeForwarding" :key="forward.key"
                                        class="inline-flex max-w-full items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 ring-1 ring-inset ring-blue-400/20">
                                        <span>{{ forward.badge }}</span>
                                        <span v-if="forward.target" class="ml-1 max-w-48 truncate text-blue-700">- {{ forward.target }}</span>
                                    </span>
                                    <span v-if="my_extension_status.call_sequence_enabled"
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 ring-1 ring-inset ring-blue-400/20">
                                        Sequence
                                    </span>
                                    <span v-if="!hasActiveCallHandling"
                                        class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        Normal routing
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Access -->
                    <div class="lg:col-span-2 lg:row-span-2 lg:row-end-2 space-y-6">
                      <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold leading-6 text-gray-950">Quick Access</h2>
                            <span v-if="cards.length" class="text-sm text-gray-500">
                                {{ cards.length }} shortcut{{ cards.length === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <div v-if="cards.length"
                            class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <div v-for="card in cards" :key="card.slug" class="h-full">
                                <DashboardTile :card="card" :count="counts[card.slug]" @card-action="handleCardAction" />
                            </div>
                        </div>

                        <div v-else
                            class="mt-4 rounded-lg border-2 border-dashed border-gray-200 bg-white p-8 text-center">
                            <p class="text-sm font-medium text-gray-700">No shortcuts available</p>
                            <p class="mt-1 text-sm text-gray-500">Shortcuts will appear here once you have access to features.</p>
                        </div>
                      </div>

                      <GlobalInfoPanel v-if="data.superadmin" :data="data" :counts="counts" />
                    </div>
                </div>
            </div>
        </main>
    </MainLayout>

    <UpdateExtensionForm :show="showExtensionModal" :options="extensionItemOptions" :loading="isExtensionModalLoading"
        :header="'Update Extension - ' + (extensionItemOptions?.item?.name_formatted ?? 'loading')"
        @close="showExtensionModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="getCounts" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import axios from 'axios';
import MainLayout from '../Layouts/MainLayout.vue'
import DashboardTile from './components/general/DashboardTile.vue'
import GlobalInfoPanel from './components/general/GlobalInfoPanel.vue'
import SkeletonRows from './components/general/Skeleton.vue'
import UpdateExtensionForm from './components/forms/UpdateExtensionForm.vue'
import Notification from './components/notifications/Notification.vue'
import ContactPhoneIcon from "./components/icons/ContactPhoneIcon.vue"
import DialpadIcon from "./components/icons/DialpadIcon.vue"
import FaxIcon from "./components/icons/FaxIcon.vue"
import { ClockIcon } from '@heroicons/vue/20/solid'
import { CogIcon } from '@heroicons/vue/24/outline'
import TopBanner from './components/notifications/TopBanner.vue';


const props = defineProps({
    data: {
        type: Object,
        default: () => ({})
    },
    company_data: Object,
    cards: Array,
    counts: {
        type: Object,
        default: () => ({})
    },
    my_extension_status: {
        type: Object,
        default: null,
    },
    permissions: {
        type: Object,
        default: () => ({})
    },
    routes: Object,
})

const data = ref(props.data ?? {});
const counts = ref(props.counts ?? {});
const my_extension_status = ref(props.my_extension_status ?? null);
const showExtensionModal = ref(false);
const isExtensionModalLoading = ref(false);
const extensionItemOptions = ref({});
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);

const showTopBanner = ref(Boolean(props.company_data.billing_suspension));
const topBannerText = ref('Your account has been suspended. Reactivation requires payment for past-due invoice(s).');

const countsLoaded = computed(() => Object.keys(counts.value).length !== 0);

const onlineExtensions = computed(() => Number(counts.value.local_reg_count || 0));
const offlineExtensions = computed(() => Math.max((counts.value.extensions || 0) - onlineExtensions.value, 0));
const registrationPercent = computed(() => {
    const totalExtensions = Number(counts.value.extensions || 0);
    if (!totalExtensions) return 0;
    return Math.min(Math.round((onlineExtensions.value / totalExtensions) * 100), 100);
});

const forwardBadgeLabels = {
    forward_all: 'FWD All',
    forward_busy: 'FWD Busy',
    forward_no_answer: 'FWD no Ans',
    forward_user_not_registered: 'FWD no Reg',
};

const activeForwarding = computed(() => {
    return (my_extension_status.value?.forwarding || [])
        .filter((forward) => forward.enabled)
        .map((forward) => ({
            ...forward,
            badge: forwardBadgeLabels[forward.key] || forward.label,
        }));
});

const hasActiveCallHandling = computed(() => {
    return Boolean(
        my_extension_status.value?.do_not_disturb
        || my_extension_status.value?.call_sequence_enabled
        || activeForwarding.value.length
    );
});

onMounted(() => {
    getCounts();
})

const getCounts = () => {
    axios.get(props.routes.counts_route)
        .then((response) => {
            counts.value = response.data || {};
            return getMyExtensionStatus();
        })
        .then(() => {
            getData();
        })
        .catch((error) => {
            handleErrorResponse(error);
        });
}

const handleCardAction = (card) => {
    if (card.action === 'open_extension_modal') {
        openExtensionModal(card.extension_uuid);
    }
}

const openExtensionModal = (extensionUuid) => {
    if (!extensionUuid) return;

    showExtensionModal.value = true;
    extensionItemOptions.value = {};
    isExtensionModalLoading.value = true;

    axios.post(props.routes.extension_item_options, { item_uuid: extensionUuid })
        .then((response) => {
            extensionItemOptions.value = response.data;
        })
        .catch((error) => {
            showExtensionModal.value = false;
            handleErrorResponse(error);
        })
        .finally(() => {
            isExtensionModalLoading.value = false;
        });
}

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        showNotification('error', { request: [error.request] });
    } else {
        showNotification('error', { request: [error.message] });
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

const getData = () => {
    axios.get(props.routes.data_route)
        .then((response) => {
            data.value = response.data || {};
        })
        .catch((error) => {
            handleErrorResponse(error);
        });
}

const getMyExtensionStatus = () => {
    if (!props.routes.my_extension_status_route) {
        return Promise.resolve();
    }

    return axios.get(props.routes.my_extension_status_route)
        .then((response) => {
            my_extension_status.value = response.data || null;
        });
}
</script>
