<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                                        {{ campaign.name || "Campaign Status" }}
                                    </DialogTitle>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                                        <Badge :text="campaign.status || 'loading'" v-bind="statusBadgeProps(campaign.status)" />
                                        <span>{{ campaign.contact_list_name || "No contact list" }}</span>
                                        <span v-if="campaign.destination_label">to {{ campaign.destination_label }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button type="button"
                                        class="rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        title="Refresh" @click="fetchStatus">
                                        <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': loading }" />
                                    </button>
                                    <button type="button"
                                        class="rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        @click="emit('close')">
                                        <span class="sr-only">Close</span>
                                        <XMarkIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <div v-if="loading" class="py-12 text-center text-sm text-gray-500">
                                Loading status...
                            </div>

                            <div v-else class="mt-5 space-y-5">
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                                    <div v-for="item in summaryItems" :key="item.key"
                                        class="rounded-md border border-gray-200 px-3 py-2">
                                        <div class="text-xs font-medium text-gray-500">{{ item.label }}</div>
                                        <div class="mt-1 text-lg font-semibold text-gray-900">{{ item.value }}</div>
                                    </div>
                                </div>

                                <div class="border-b border-gray-200">
                                    <nav class="-mb-px flex gap-6" aria-label="Tabs">
                                        <button type="button" class="border-b-2 px-1 py-2 text-sm font-medium"
                                            :class="activeTab === 'recipients' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                                            @click="activeTab = 'recipients'">
                                            Recipients
                                        </button>
                                        <button type="button" class="border-b-2 px-1 py-2 text-sm font-medium"
                                            :class="activeTab === 'attempts' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                                            @click="activeTab = 'attempts'">
                                            Attempts
                                        </button>
                                    </nav>
                                </div>

                                <div v-if="activeTab === 'recipients'" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead>
                                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                <th class="py-2 pr-3">Contact</th>
                                                <th class="px-3 py-2">Phone</th>
                                                <th class="px-3 py-2">Status</th>
                                                <th class="px-3 py-2">Attempts</th>
                                                <th class="px-3 py-2">Last Attempt</th>
                                                <th class="px-3 py-2">Next Retry</th>
                                                <th class="py-2 pl-3">Outcome</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="recipient in recipients" :key="recipient.basic_dialer_campaign_recipient_uuid">
                                                <td class="whitespace-nowrap py-2 pr-3 text-gray-900">
                                                    {{ recipient.contact_name || "-" }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ recipient.phone_number }}</td>
                                                <td class="whitespace-nowrap px-3 py-2">
                                                    <Badge :text="recipient.status" v-bind="statusBadgeProps(recipient.status)" />
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ recipient.attempts_count ?? 0 }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ formatDate(recipient.last_attempt_at) }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ formatDate(recipient.next_attempt_at) }}</td>
                                                <td class="min-w-56 py-2 pl-3 text-gray-500">
                                                    <div>{{ recipient.last_outcome || "-" }}</div>
                                                    <div v-if="recipient.last_error" class="mt-1 max-w-md truncate text-xs text-red-600">
                                                        {{ recipient.last_error }}
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="recipients.length === 0">
                                                <td colspan="7" class="py-8 text-center text-gray-500">No recipients yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead>
                                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                <th class="py-2 pr-3">Queued</th>
                                                <th class="px-3 py-2">Phone</th>
                                                <th class="px-3 py-2">Attempt</th>
                                                <th class="px-3 py-2">Status</th>
                                                <th class="px-3 py-2">Outcome</th>
                                                <th class="px-3 py-2">Hangup</th>
                                                <th class="px-3 py-2">Duration</th>
                                                <th class="py-2 pl-3">Response</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="attempt in attempts" :key="attempt.basic_dialer_campaign_attempt_uuid">
                                                <td class="whitespace-nowrap py-2 pr-3 text-gray-500">{{ formatDate(attempt.queued_at) }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-900">
                                                    {{ attempt.recipient?.phone_number || "-" }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ attempt.attempt_number }}</td>
                                                <td class="whitespace-nowrap px-3 py-2">
                                                    <Badge :text="attempt.status" v-bind="statusBadgeProps(attempt.status)" />
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ attempt.outcome || "-" }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ attempt.hangup_cause || "-" }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500">{{ formatDuration(attempt.duration) }}</td>
                                                <td class="max-w-sm py-2 pl-3 text-gray-500">
                                                    <div class="truncate">{{ attempt.response || "-" }}</div>
                                                </td>
                                            </tr>
                                            <tr v-if="attempts.length === 0">
                                                <td colspan="8" class="py-8 text-center text-gray-500">No attempts yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { ArrowPathIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import Badge from "@generalComponents/Badge.vue";

const props = defineProps({
    show: Boolean,
    route: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(["close", "error"]);

const activeTab = ref("recipients");
const loading = ref(false);
const campaign = ref({});
const summary = ref({ recipients: {}, attempts: {}, total_recipients: 0, total_attempts: 0 });
const recipients = ref([]);
const attempts = ref([]);

const summaryItems = computed(() => {
    const recipientCounts = summary.value.recipients || {};

    return [
        { key: "total", label: "Total", value: summary.value.total_recipients ?? 0 },
        { key: "pending", label: "Pending", value: recipientCounts.pending ?? 0 },
        { key: "dialing", label: "Dialing", value: recipientCounts.dialing ?? 0 },
        { key: "answered", label: "Answered", value: recipientCounts.answered ?? 0 },
        { key: "retry_wait", label: "Retry Wait", value: recipientCounts.retry_wait ?? 0 },
        { key: "failed", label: "Failed", value: recipientCounts.failed ?? 0 },
        { key: "attempts", label: "Attempts", value: summary.value.total_attempts ?? 0 },
    ];
});

watch(() => [props.show, props.route], ([show]) => {
    if (show) {
        activeTab.value = "recipients";
        fetchStatus();
    }
});

function fetchStatus() {
    if (!props.route) return;

    loading.value = true;

    axios.get(props.route)
        .then((response) => {
            campaign.value = response.data.campaign || {};
            summary.value = response.data.summary || {};
            recipients.value = response.data.recipients || [];
            attempts.value = response.data.attempts || [];
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            loading.value = false;
        });
}

function formatDate(value) {
    if (!value) return "-";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "-";

    return new Intl.DateTimeFormat(undefined, {
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
    }).format(date);
}

function formatDuration(value) {
    if (value === null || value === undefined || value === "") return "-";

    return `${value}s`;
}

const statusBadgeProps = (status) => {
    if (["running", "dialing", "queued"].includes(status)) {
        return {
            backgroundColor: "bg-blue-50",
            textColor: "text-blue-700",
            ringColor: "ring-blue-600/20",
        };
    }

    if (["completed", "answered"].includes(status)) {
        return {
            backgroundColor: "bg-green-50",
            textColor: "text-green-700",
            ringColor: "ring-green-600/20",
        };
    }

    if (["paused", "retry_wait"].includes(status)) {
        return {
            backgroundColor: "bg-yellow-50",
            textColor: "text-yellow-700",
            ringColor: "ring-yellow-600/20",
        };
    }

    if (["stopped", "failed", "rejected"].includes(status)) {
        return {
            backgroundColor: "bg-red-50",
            textColor: "text-red-700",
            ringColor: "ring-red-600/20",
        };
    }

    return {
        backgroundColor: "bg-gray-50",
        textColor: "text-gray-700",
        ringColor: "ring-gray-600/20",
    };
};
</script>
