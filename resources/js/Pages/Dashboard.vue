<template>
    <MainLayout>
        <TopBanner :show="showTopBanner" @close="showTopBanner = false" color="bg-rose-600" :text="topBannerText" />

        <main class="bg-slate-50/60">
            <div class="mx-auto max-w-none px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-cyan-700">{{ $t('Account dashboard') }}</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">{{
                            company_data.company_name }}</h1>
                    </div>

                    <a v-if="permissions.account_settings_index" type="button" :href="routes.account_settings_page"
                        class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400">
                        <CogIcon class="-ml-0.5 size-5 text-gray-400" aria-hidden="true" />
                        {{ $t('Settings') }}
                    </a>
                </div>

                <div
                    class="mx-auto grid max-w-2xl grid-cols-1 grid-rows-1 items-start gap-6 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                    <!-- Right column: Account summary + My Extension -->
                    <div class="space-y-6 lg:col-start-3 lg:row-end-1">
                        <div v-if="permissions.extension_view" class="rounded-lg bg-white p-6 ring-1 ring-gray-200">
                            <dl class="flex flex-wrap">
                                <div class="flex-auto truncate border-b border-gray-100 pb-5">
                                    <dt class="text-sm font-medium leading-6 text-gray-500">{{ $t('Account name') }}</dt>
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
                                                <span class="font-medium text-gray-700">{{ $t('Extensions') }}</span>
                                                <span class="font-semibold text-gray-950">{{ counts.extensions }}</span>
                                            </div>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <span
                                                    class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                                    {{ $t('Online: :count', { count: onlineExtensions }) }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                                    {{ $t('Offline: :count', { count: offlineExtensions }) }}
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
                                        <dd class="text-sm leading-6 text-gray-500">{{ $t('Phone Numbers') }}: <span
                                                class="font-semibold text-gray-900">{{ counts.phone_numbers }}</span>
                                        </dd>
                                    </div>

                                    <div v-if="counts.faxes !== undefined && counts.faxes >= 0"
                                        class="mt-4 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <FaxIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                        </dt>
                                        <dd class="text-sm leading-6 text-gray-500">{{ $t('Virtual Faxes') }}: <span
                                                class="font-semibold text-gray-900">{{ counts.faxes }}</span></dd>
                                    </div>

                                    <div class="mt-4 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <ClockIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                        </dt>
                                        <dd class="text-sm font-medium leading-6 text-gray-500">{{ $t('Time Zone') }}: {{
                                            company_data.time_zone }}</dd>
                                    </div>
                                </template>
                            </dl>
                        </div>

                        <section v-if="customerNotes.visible" role="button" tabindex="0" @click="showCustomerNotesModal = true"
                            @keydown.enter="showCustomerNotesModal = true"
                            @keydown.space.prevent="showCustomerNotesModal = true"
                            :class="[
                                'group relative w-full overflow-hidden rounded-lg p-5 text-left shadow-sm ring-1 transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2',
                                hasVisibleCustomerNotesContent
                                    ? 'border border-amber-200 bg-amber-100/90 ring-amber-300/30'
                                    : 'border border-gray-200 bg-white ring-gray-200',
                            ]">
                            <div class="relative flex items-start justify-between gap-4">
                                <div>
                                    <p :class="[
                                        'text-xs font-semibold uppercase tracking-wide',
                                        hasVisibleCustomerNotesContent ? 'text-amber-800' : 'text-cyan-700',
                                    ]">{{ $t('Customer Notes') }}</p>
                                    <h3 :class="[
                                        'mt-1 text-base font-semibold',
                                        hasVisibleCustomerNotesContent ? 'text-amber-950' : 'text-gray-950',
                                    ]">{{ $t('Technician notes') }}</h3>
                                </div>
                            </div>

                            <div class="relative mt-4 space-y-3">
                                <div v-for="note in visibleCustomerNotes" :key="note.key"
                                    :class="[
                                        'rounded-md px-3 py-2 ring-1 ring-inset',
                                        hasVisibleCustomerNotesContent ? 'bg-white/55 ring-amber-700/10' : 'bg-gray-50 ring-gray-200',
                                        note.borderClass,
                                    ]">
                                    <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide"
                                        :class="note.labelClass">
                                        <span :class="['h-2 w-2 rounded-full', note.dotClass]"></span>
                                        {{ note.label }}
                                    </div>
                                    <div v-if="note.content" class="relative">
                                        <div :ref="(el) => setCustomerNotesPreviewRef(note.key, el)"
                                            class="customer-notes-preview max-h-28 overflow-hidden text-sm leading-5 text-amber-950"
                                            v-html="note.content"></div>
                                        <div v-if="overflowingCustomerNotes[note.key]"
                                            :class="[
                                                'pointer-events-none absolute inset-x-0 bottom-0 flex h-10 items-end justify-center bg-gradient-to-t pb-0.5 text-lg font-semibold leading-none',
                                                hasVisibleCustomerNotesContent
                                                    ? 'from-amber-50 via-amber-50/95 text-amber-900'
                                                    : 'from-gray-50 via-gray-50/95 text-gray-700',
                                            ]">
                                            ...
                                        </div>
                                    </div>
                                    <p v-else class="text-sm italic leading-5 text-amber-900/75">{{ $t('No notes yet.') }}</p>
                                </div>
                            </div>
                        </section>

                        <div v-if="my_extension_status"
                            class="rounded-lg bg-white ring-1 ring-gray-200">
                            <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-cyan-700">{{ $t('My Extension') }}</p>
                                    <h3 class="mt-1 text-sm font-semibold text-gray-950">{{ my_extension_status.name }}</h3>
                                </div>

                                <button type="button" @click="openExtensionModal(my_extension_status.extension_uuid)"
                                    class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400">
                                    <CogIcon class="-ml-0.5 size-5 text-gray-400" aria-hidden="true" />
                                    {{ $t('Manage') }}
                                </button>
                            </div>

                            <div class="px-5 py-4">
                                <p class="text-sm font-medium text-gray-700">{{ $t('Active call handling') }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span v-if="my_extension_status.do_not_disturb"
                                        class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-800 ring-1 ring-inset ring-rose-400/20">
                                        {{ $t('DND') }}
                                    </span>
                                    <span v-for="forward in activeForwarding" :key="forward.key"
                                        class="inline-flex max-w-full items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 ring-1 ring-inset ring-blue-400/20">
                                        <span>{{ forward.badge }}</span>
                                        <span v-if="forward.target" class="ml-1 max-w-48 truncate text-blue-700">- {{ forward.target }}</span>
                                    </span>
                                    <span v-if="my_extension_status.call_sequence_enabled"
                                        class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-800 ring-1 ring-inset ring-blue-400/20">
                                        {{ $t('Sequence') }}
                                    </span>
                                    <span v-if="!hasActiveCallHandling"
                                        class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        {{ $t('Normal routing') }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="my_extension_status.agent && routes.agent_status_update"
                                class="border-t border-gray-100 px-5 py-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <SupportAgentIcon class="h-5 w-5 flex-none text-gray-400" aria-hidden="true" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-gray-700">{{ $t('Contact Center') }}</p>
                                            <p class="text-xs text-gray-500">{{ $t('Status') }}</p>
                                        </div>
                                    </div>

                                    <Menu as="div" class="relative flex-none">
                                        <MenuButton type="button" :disabled="isAgentStatusUpdating"
                                            class="inline-flex items-center rounded-md px-2.5 py-1.5 text-sm font-medium ring-1 ring-inset transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
                                            :class="agentStatusStyles.button">
                                            <span :class="[agentStatusStyles.dot, 'mr-2 h-2 w-2 rounded-full']"
                                                aria-hidden="true"></span>
                                            {{ agentStatusLabel(my_extension_status.agent.status) }}
                                            <Spinner v-if="isAgentStatusUpdating" class="ml-2" :show="true" />
                                            <ChevronDownIcon v-else class="ml-2 h-4 w-4" aria-hidden="true" />
                                        </MenuButton>

                                        <transition enter-active-class="transition ease-out duration-100"
                                            enter-from-class="transform opacity-0 scale-95"
                                            enter-to-class="transform opacity-100 scale-100"
                                            leave-active-class="transition ease-in duration-75"
                                            leave-from-class="transform opacity-100 scale-100"
                                            leave-to-class="transform opacity-0 scale-95">
                                            <MenuItems
                                                class="absolute right-0 z-20 mt-2 w-40 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-none">
                                                <MenuItem v-for="option in agentStatusOptions" :key="option" v-slot="{ active }">
                                                    <button type="button"
                                                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700"
                                                        :class="active ? 'bg-gray-50' : ''"
                                                        @click="updateAgentStatus(option)">
                                                        <span :class="[agentStatusStyle(option).dot, 'h-2 w-2 rounded-full']"
                                                            aria-hidden="true"></span>
                                                        {{ agentStatusLabel(option) }}
                                                    </button>
                                                </MenuItem>
                                            </MenuItems>
                                        </transition>
                                    </Menu>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Access -->
                    <div class="lg:col-span-2 lg:row-span-2 lg:row-end-2 space-y-6">
                      <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold leading-6 text-gray-950">{{ $t('Quick Access') }}</h2>
                            <span v-if="cards.length" class="text-sm text-gray-500">
                                {{ $t(':count shortcut(s)', { count: cards.length }) }}
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
                            <p class="text-sm font-medium text-gray-700">{{ $t('No shortcuts available') }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $t('Shortcuts will appear here once you have access to features.') }}</p>
                        </div>
                      </div>

                      <GlobalInfoPanel v-if="data.superadmin" :data="data" :counts="counts" />
                    </div>
                </div>
            </div>
        </main>
    </MainLayout>

    <UpdateExtensionForm :show="showExtensionModal" :options="extensionItemOptions" :loading="isExtensionModalLoading"
        :header="$t('Update Extension - :name', { name: extensionItemOptions?.item?.name_formatted ?? 'loading' })"
        @close="showExtensionModal = false" @error="handleErrorResponse" @success="showNotification"
        @refresh-data="getCounts" />

    <CustomerNotesModal :show="showCustomerNotesModal" :customer-notes="customerNotes"
        :can-edit="permissions.customer_notes_edit" :route="routes.customer_notes_route"
        @close="showCustomerNotesModal = false"
        @error="handleErrorResponse" @success="handleCustomerNotesSuccess"
        @updated="handleCustomerNotesUpdated" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />
</template>

<script setup>
import { computed, ref, onBeforeUnmount, onMounted, nextTick } from 'vue'
import axios from 'axios';
import { trans } from '@i18n';
import MainLayout from '../Layouts/MainLayout.vue'
import DashboardTile from './components/general/DashboardTile.vue'
import GlobalInfoPanel from './components/general/GlobalInfoPanel.vue'
import SkeletonRows from './components/general/Skeleton.vue'
import UpdateExtensionForm from './components/forms/UpdateExtensionForm.vue'
import CustomerNotesModal from './components/modal/CustomerNotesModal.vue'
import Notification from './components/notifications/Notification.vue'
import ContactPhoneIcon from "./components/icons/ContactPhoneIcon.vue"
import DialpadIcon from "./components/icons/DialpadIcon.vue"
import FaxIcon from "./components/icons/FaxIcon.vue"
import { ChevronDownIcon, ClockIcon } from '@heroicons/vue/20/solid'
import { CogIcon } from '@heroicons/vue/24/outline'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import TopBanner from './components/notifications/TopBanner.vue';
import SupportAgentIcon from './components/icons/SupportAgent.vue'
import Spinner from '@generalComponents/Spinner.vue'


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
    customer_notes: {
        type: Object,
        default: () => ({ visible: false, levels: [], notes: {} }),
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
const customerNotes = ref(props.customer_notes ?? { visible: false, levels: [], notes: {} });
const customerNotesPreviewRefs = ref({});
const overflowingCustomerNotes = ref({});
const showExtensionModal = ref(false);
const showCustomerNotesModal = ref(false);
const isExtensionModalLoading = ref(false);
const extensionItemOptions = ref({});
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(false);
const isAgentStatusUpdating = ref(false);

const showTopBanner = ref(Boolean(props.company_data.billing_suspension));
const topBannerText = ref(trans('Your account has been suspended. Reactivation requires payment for past-due invoice(s).'));

const countsLoaded = computed(() => Object.keys(counts.value).length !== 0);

const onlineExtensions = computed(() => Number(counts.value.local_reg_count || 0));
const offlineExtensions = computed(() => Math.max((counts.value.extensions || 0) - onlineExtensions.value, 0));
const registrationPercent = computed(() => {
    const totalExtensions = Number(counts.value.extensions || 0);
    if (!totalExtensions) return 0;
    return Math.min(Math.round((onlineExtensions.value / totalExtensions) * 100), 100);
});

const forwardBadgeLabels = {
    forward_all: trans('FWD All'),
    forward_busy: trans('FWD Busy'),
    forward_no_answer: trans('FWD no Ans'),
    forward_user_not_registered: trans('FWD no Reg'),
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

const agentStatusOptions = ['Available', 'On Break', 'Logged Out'];
const agentStatusLabels = {
    'Available': trans('Available'),
    'On Break': trans('On Break'),
    'Logged Out': trans('Logged Out'),
};
const agentStatusStyleMap = {
    'Available': {
        button: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 hover:bg-emerald-100',
        dot: 'bg-emerald-500',
    },
    'On Break': {
        button: 'bg-amber-50 text-amber-700 ring-amber-600/20 hover:bg-amber-100',
        dot: 'bg-amber-500',
    },
    'Logged Out': {
        button: 'bg-gray-50 text-gray-700 ring-gray-600/20 hover:bg-gray-100',
        dot: 'bg-gray-400',
    },
};
const fallbackAgentStatusStyle = {
    button: 'bg-gray-50 text-gray-700 ring-gray-600/20 hover:bg-gray-100',
    dot: 'bg-gray-400',
};

const agentStatusLabel = (status) => agentStatusLabels[status] || status;
const agentStatusStyle = (status) => agentStatusStyleMap[status] || fallbackAgentStatusStyle;
const agentStatusStyles = computed(() => agentStatusStyle(my_extension_status.value?.agent?.status));

const customerNoteLayers = [
    {
        key: 'level_1',
        level: 1,
        label: trans('Level 1'),
        borderClass: 'border-l-4 border-l-amber-600',
        labelClass: 'text-amber-800',
        dotClass: 'bg-amber-600',
    },
    {
        key: 'level_2',
        level: 2,
        label: trans('Level 2'),
        borderClass: 'border-l-4 border-l-sky-600',
        labelClass: 'text-sky-800',
        dotClass: 'bg-sky-600',
    },
    {
        key: 'level_3',
        level: 3,
        label: trans('Level 3'),
        borderClass: 'border-l-4 border-l-rose-600',
        labelClass: 'text-rose-800',
        dotClass: 'bg-rose-600',
    },
];

const visibleCustomerNotes = computed(() => {
    const levels = customerNotes.value?.levels || [];
    const notes = customerNotes.value?.notes || {};

    return customerNoteLayers
        .filter((layer) => levels.includes(layer.level))
        .map((layer) => ({
            ...layer,
            content: notes[layer.key] || null,
        }));
});

const hasVisibleCustomerNotesContent = computed(() => {
    return visibleCustomerNotes.value.some((note) => {
        const content = note.content || '';
        return content.replace(/<[^>]*>/g, '').trim() !== '';
    });
});

onMounted(() => {
    getCounts();
    window.addEventListener('resize', measureCustomerNotesPreviews);
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', measureCustomerNotesPreviews);
})

const getCounts = () => {
    axios.get(props.routes.counts_route)
        .then((response) => {
            counts.value = response.data || {};
            return getMyExtensionStatus();
        })
        .then(() => {
            return getData();
        })
        .then(() => {
            return getCustomerNotes();
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

const handleCustomerNotesUpdated = (payload) => {
    customerNotes.value = payload || { visible: false, levels: [], notes: {} };
    measureCustomerNotesPreviews();
}

const handleCustomerNotesSuccess = (messages = null) => {
    showNotification('success', messages);
}

const setCustomerNotesPreviewRef = (key, el) => {
    if (el) {
        customerNotesPreviewRefs.value[key] = el;
    } else {
        delete customerNotesPreviewRefs.value[key];
    }

    measureCustomerNotesPreviews();
}

const measureCustomerNotesPreviews = async () => {
    await nextTick();

    const overflowState = {};
    Object.entries(customerNotesPreviewRefs.value).forEach(([key, el]) => {
        overflowState[key] = el.scrollHeight > el.clientHeight + 1;
    });

    if (JSON.stringify(overflowingCustomerNotes.value) !== JSON.stringify(overflowState)) {
        overflowingCustomerNotes.value = overflowState;
    }
}

const getCustomerNotes = () => {
    const hasCustomerNotesAccess = props.permissions.customer_notes_level_1
        || props.permissions.customer_notes_level_2
        || props.permissions.customer_notes_level_3;

    if (!hasCustomerNotesAccess || !props.routes.customer_notes_route) {
        customerNotes.value = { visible: false, levels: [], notes: {} };
        return Promise.resolve();
    }

    return axios.get(props.routes.customer_notes_route)
        .then((response) => {
            customerNotes.value = response.data || { visible: false, levels: [], notes: {} };
            measureCustomerNotesPreviews();
        });
}

const getData = () => {
    return axios.get(props.routes.data_route)
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
};

const updateAgentStatus = async (status) => {
    const agent = my_extension_status.value?.agent;
    if (!agent || !props.routes.agent_status_update || status === agent.status || isAgentStatusUpdating.value) {
        return;
    }

    isAgentStatusUpdating.value = true;

    try {
        const response = await axios.post(props.routes.agent_status_update, {
            agentUuid: agent.call_center_agent_uuid,
            status,
        });

        agent.status = response.data.status || status;
        showNotification('success', response.data.messages || {
            success: [trans('Status updated successfully.')],
        });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        isAgentStatusUpdating.value = false;
    }
};
</script>

<style scoped>
.customer-notes-preview :deep(p),
.customer-notes-preview :deep(div),
.customer-notes-preview :deep(ul),
.customer-notes-preview :deep(ol),
.customer-notes-preview :deep(blockquote),
.customer-notes-preview :deep(pre) {
    margin-bottom: 0.5rem;
}

.customer-notes-preview :deep(ul),
.customer-notes-preview :deep(ol) {
    padding-left: 1.25rem;
}

.customer-notes-preview :deep(ul) {
    list-style: disc;
}

.customer-notes-preview :deep(ol) {
    list-style: decimal;
}

.customer-notes-preview :deep(a) {
    color: #0e7490;
    text-decoration: underline;
}
</style>
