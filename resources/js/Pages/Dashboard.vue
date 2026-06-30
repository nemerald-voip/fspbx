<template>
    <MainLayout>
        <TopBanner :show="showTopBanner" @close="showTopBanner = false" color="bg-danger-solid" :text="topBannerText" />

        <main class="bg-surface-2/60">
            <div class="mx-auto max-w-none px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-info">Account dashboard</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-heading sm:text-3xl">{{
                            company_data.company_name }}</h1>
                    </div>

                    <a v-if="permissions.account_settings_index" type="button" :href="routes.account_settings_page"
                        class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading ring-1 ring-inset ring-strong transition hover:bg-surface-2 hover:ring-strong">
                        <CogIcon class="-ml-0.5 size-5 text-subtle" aria-hidden="true" />
                        Settings
                    </a>
                </div>

                <div
                    class="mx-auto grid max-w-2xl grid-cols-1 grid-rows-1 items-start gap-6 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                    <!-- Right column: Account summary + My Extension -->
                    <div class="space-y-6 lg:col-start-3 lg:row-end-1">
                        <div v-if="permissions.extension_view" class="rounded-lg bg-surface p-6 ring-1 ring-strong">
                            <dl class="flex flex-wrap">
                                <div class="flex-auto truncate border-b border-default pb-5">
                                    <dt class="text-sm font-medium leading-6 text-muted">Account name</dt>
                                    <div class="mt-1 text-lg font-semibold leading-6 text-heading">{{
                                        company_data.company_name }}</div>
                                </div>

                                <div v-if="!countsLoaded" class="w-full">
                                    <SkeletonRows :rows="3" class="pt-6" />
                                </div>

                                <template v-else>
                                    <div v-if="counts.extensions !== undefined && counts.extensions >= 0"
                                        class="mt-6 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <ContactPhoneIcon class="h-6 w-5 text-subtle" aria-hidden="true" />
                                        </dt>
                                        <dd class="min-w-0 flex-1 text-sm leading-6 text-muted">
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="font-medium text-body">Extensions</span>
                                                <span class="font-semibold text-heading">{{ counts.extensions }}</span>
                                            </div>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <span
                                                    class="inline-flex items-center rounded-full bg-success-subtle px-2.5 py-1 text-xs font-medium text-success ring-1 ring-inset ring-success/20">
                                                    Online: {{ onlineExtensions }}
                                                </span>
                                                <span
                                                    class="inline-flex items-center rounded-full bg-danger-subtle px-2.5 py-1 text-xs font-medium text-danger ring-1 ring-inset ring-danger/20">
                                                    Offline: {{ offlineExtensions }}
                                                </span>
                                            </div>
                                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-3">
                                                <div class="h-full rounded-full bg-success"
                                                    :style="{ width: registrationPercent + '%' }"></div>
                                            </div>
                                        </dd>
                                    </div>

                                    <div v-if="counts.phone_numbers !== undefined && counts.phone_numbers >= 0"
                                        class="mt-5 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <DialpadIcon class="h-6 w-5 text-subtle" aria-hidden="true" />
                                        </dt>
                                        <dd class="text-sm leading-6 text-muted">Phone Numbers: <span
                                                class="font-semibold text-heading">{{ counts.phone_numbers }}</span>
                                        </dd>
                                    </div>

                                    <div v-if="counts.faxes !== undefined && counts.faxes >= 0"
                                        class="mt-4 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <FaxIcon class="h-6 w-5 text-subtle" aria-hidden="true" />
                                        </dt>
                                        <dd class="text-sm leading-6 text-muted">Virtual Faxes: <span
                                                class="font-semibold text-heading">{{ counts.faxes }}</span></dd>
                                    </div>

                                    <div class="mt-4 flex w-full flex-none gap-x-4">
                                        <dt class="flex-none">
                                            <ClockIcon class="h-6 w-5 text-subtle" aria-hidden="true" />
                                        </dt>
                                        <dd class="text-sm font-medium leading-6 text-muted">Time Zone: {{
                                            company_data.time_zone }}</dd>
                                    </div>
                                </template>
                            </dl>
                        </div>

                        <section v-if="customerNotes.visible" role="button" tabindex="0" @click="showCustomerNotesModal = true"
                            @keydown.enter="showCustomerNotesModal = true"
                            @keydown.space.prevent="showCustomerNotesModal = true"
                            :class="[
                                'group relative w-full overflow-hidden rounded-lg p-5 text-left shadow-sm ring-1 transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2',
                                hasVisibleCustomerNotesContent
                                    ? 'border border-warning bg-warning/90 ring-warning/30'
                                    : 'border border-default bg-surface ring-strong',
                            ]">
                            <div class="relative flex items-start justify-between gap-4">
                                <div>
                                    <p :class="[
                                        'text-xs font-semibold uppercase tracking-wide',
                                        hasVisibleCustomerNotesContent ? 'text-warning' : 'text-info',
                                    ]">Customer Notes</p>
                                    <h3 :class="[
                                        'mt-1 text-base font-semibold',
                                        hasVisibleCustomerNotesContent ? 'text-warning' : 'text-heading',
                                    ]">Technician notes</h3>
                                </div>
                            </div>

                            <div class="relative mt-4 space-y-3">
                                <div v-for="note in visibleCustomerNotes" :key="note.key"
                                    :class="[
                                        'rounded-md px-3 py-2 ring-1 ring-inset',
                                        hasVisibleCustomerNotesContent ? 'bg-white/55 ring-warning/10' : 'bg-surface-2 ring-strong',
                                        note.borderClass,
                                    ]">
                                    <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide"
                                        :class="note.labelClass">
                                        <span :class="['h-2 w-2 rounded-full', note.dotClass]"></span>
                                        {{ note.label }}
                                    </div>
                                    <div v-if="note.content" class="relative">
                                        <div :ref="(el) => setCustomerNotesPreviewRef(note.key, el)"
                                            class="customer-notes-preview max-h-28 overflow-hidden text-sm leading-5 text-warning"
                                            v-html="note.content"></div>
                                        <div v-if="overflowingCustomerNotes[note.key]"
                                            :class="[
                                                'pointer-events-none absolute inset-x-0 bottom-0 flex h-10 items-end justify-center bg-gradient-to-t pb-0.5 text-lg font-semibold leading-none',
                                                hasVisibleCustomerNotesContent
                                                    ? 'from-warning via-warning/95 text-warning'
                                                    : 'from-surface-2 via-surface-2/95 text-body',
                                            ]">
                                            ...
                                        </div>
                                    </div>
                                    <p v-else class="text-sm italic leading-5 text-warning/75">No notes yet.</p>
                                </div>
                            </div>
                        </section>

                        <div v-if="my_extension_status"
                            class="overflow-hidden rounded-lg bg-surface ring-1 ring-strong">
                            <div class="flex flex-col gap-4 border-b border-default px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-info">My Extension</p>
                                    <h3 class="mt-1 text-sm font-semibold text-heading">{{ my_extension_status.name }}</h3>
                                </div>

                                <button type="button" @click="openExtensionModal(my_extension_status.extension_uuid)"
                                    class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading ring-1 ring-inset ring-strong transition hover:bg-surface-2 hover:ring-strong">
                                    <CogIcon class="-ml-0.5 size-5 text-subtle" aria-hidden="true" />
                                    Manage
                                </button>
                            </div>

                            <div class="px-5 py-4">
                                <p class="text-sm font-medium text-body">Active call handling</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span v-if="my_extension_status.do_not_disturb"
                                        class="inline-flex items-center rounded-full bg-danger-subtle px-2.5 py-1 text-xs font-medium text-danger ring-1 ring-inset ring-danger/20">
                                        DND
                                    </span>
                                    <span v-for="forward in activeForwarding" :key="forward.key"
                                        class="inline-flex max-w-full items-center rounded-full bg-info-subtle px-2.5 py-1 text-xs font-medium text-info ring-1 ring-inset ring-info/20">
                                        <span>{{ forward.badge }}</span>
                                        <span v-if="forward.target" class="ml-1 max-w-48 truncate text-info">- {{ forward.target }}</span>
                                    </span>
                                    <span v-if="my_extension_status.call_sequence_enabled"
                                        class="inline-flex items-center rounded-full bg-info-subtle px-2.5 py-1 text-xs font-medium text-info ring-1 ring-inset ring-info/20">
                                        Sequence
                                    </span>
                                    <span v-if="!hasActiveCallHandling"
                                        class="inline-flex items-center rounded-full bg-success-subtle px-2.5 py-1 text-xs font-medium text-success ring-1 ring-inset ring-success/20">
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
                            <h2 class="text-base font-semibold leading-6 text-heading">Quick Access</h2>
                            <span v-if="cards.length" class="text-sm text-muted">
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
                            class="mt-4 rounded-lg border-2 border-dashed border-default bg-surface p-8 text-center">
                            <p class="text-sm font-medium text-body">No shortcuts available</p>
                            <p class="mt-1 text-sm text-muted">Shortcuts will appear here once you have access to features.</p>
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

const customerNoteLayers = [
    {
        key: 'level_1',
        level: 1,
        label: 'Level 1',
        borderClass: 'border-l-4 border-l-amber-600',
        labelClass: 'text-warning',
        dotClass: 'bg-warning',
    },
    {
        key: 'level_2',
        level: 2,
        label: 'Level 2',
        borderClass: 'border-l-4 border-l-sky-600',
        labelClass: 'text-info',
        dotClass: 'bg-info',
    },
    {
        key: 'level_3',
        level: 3,
        label: 'Level 3',
        borderClass: 'border-l-4 border-l-rose-600',
        labelClass: 'text-danger',
        dotClass: 'bg-danger',
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
}
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
    color: rgb(var(--color-info));
    text-decoration: underline;
}
</style>
