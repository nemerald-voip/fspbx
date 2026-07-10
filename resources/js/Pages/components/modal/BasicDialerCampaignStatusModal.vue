<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative w-full max-w-6xl transform overflow-hidden rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <DialogTitle as="h3" class="truncate text-base font-semibold leading-6 text-heading">
                                        {{ campaign.name || "Campaign Status" }}
                                    </DialogTitle>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                                        <Badge :text="campaign.status || 'loading'" v-bind="statusBadgeProps(campaign.status)" />
                                        <span class="truncate">{{ campaign.contact_list_name || "No contact list" }}</span>
                                        <span v-if="campaign.destination_label" class="truncate">to {{ campaign.destination_label }}</span>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <button type="button"
                                        class="rounded-md p-2 text-subtle hover:bg-surface-3 hover:text-body focus:outline-none focus:ring-2 focus:ring-focus"
                                        title="Refresh" @click="fetchStatus">
                                        <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': loading }" />
                                    </button>
                                    <button type="button"
                                        class="rounded-md p-2 text-subtle hover:bg-surface-3 hover:text-body focus:outline-none focus:ring-2 focus:ring-focus"
                                        @click="emit('close')">
                                        <span class="sr-only">Close</span>
                                        <XMarkIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <div v-if="loading" class="py-12 text-center text-sm text-muted">
                                Loading status...
                            </div>

                            <div v-else class="mt-5 space-y-5">
                                <div class="rounded-md border border-default p-4">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <div class="text-sm font-medium text-body">Progress</div>
                                        <div class="text-sm text-muted">
                                            {{ completedRecipients }} / {{ summary.total_recipients ?? 0 }} contacts
                                            <span class="ml-2 font-semibold text-heading">{{ summary.completion_percent ?? 0 }}%</span>
                                        </div>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-3">
                                        <div class="h-full rounded-full bg-accent transition-all duration-500"
                                            :style="{ width: (summary.completion_percent ?? 0) + '%' }"></div>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-muted">
                                        <span><span class="font-semibold text-heading">{{ summary.answer_rate ?? 0 }}%</span> answer rate</span>
                                        <span>{{ summary.answered_attempts ?? 0 }} answered / {{ summary.total_attempts ?? 0 }} attempts</span>
                                        <span>{{ formatDuration(summary.talk_seconds) }} talk time</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                                    <div v-for="item in summaryItems" :key="item.key"
                                        class="rounded-md border border-default px-3 py-2">
                                        <div class="text-xs font-medium text-muted">{{ item.label }}</div>
                                        <div class="mt-1 text-lg font-semibold text-heading">{{ item.value }}</div>
                                    </div>
                                </div>

                                <div v-if="hasBreakdowns" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <div class="rounded-md border border-default p-4">
                                        <div class="mb-2 flex items-baseline justify-between">
                                            <h4 class="text-sm font-semibold text-heading">Outcomes</h4>
                                            <span class="text-xs text-subtle">{{ outcomeTotal }} attempts</span>
                                        </div>
                                        <div v-if="outcomeBreakdown.length === 0"
                                            class="py-8 text-center text-xs text-muted">
                                            No outcomes recorded yet.
                                        </div>
                                        <div v-else class="flex flex-col items-center gap-4 sm:flex-row">
                                            <div class="relative h-32 w-32 shrink-0 sm:h-36 sm:w-36">
                                                <Doughnut :data="outcomeChartData" :options="doughnutOptions" />
                                            </div>
                                            <div class="w-full min-w-0 flex-1 space-y-1">
                                                <div v-for="item in outcomeBreakdown" :key="item.label"
                                                    class="flex items-center justify-between gap-3 text-xs">
                                                    <div class="flex min-w-0 items-center gap-2">
                                                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                                            :style="{ backgroundColor: outcomeColor(item.label) }"></span>
                                                        <span class="truncate capitalize text-body">{{ formatLabel(item.label) }}</span>
                                                    </div>
                                                    <div class="shrink-0 font-medium text-heading">
                                                        {{ item.count }}
                                                        <span class="ml-1 text-subtle">{{ percent(item.count, outcomeTotal) }}%</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rounded-md border border-default p-4">
                                        <h4 class="mb-2 text-sm font-semibold text-heading">Hangup Causes</h4>
                                        <div v-if="hangupBreakdown.length === 0"
                                            class="py-8 text-center text-xs text-muted">
                                            No hangup cause data yet.
                                        </div>
                                        <div v-else class="h-40">
                                            <Bar :data="hangupChartData" :options="barOptions" />
                                        </div>
                                    </div>
                                </div>

                                <div class="border-b border-default">
                                    <nav class="-mb-px flex gap-6" aria-label="Tabs">
                                        <button type="button" class="border-b-2 px-1 py-2 text-sm font-medium"
                                            :class="activeTab === 'recipients' ? 'border-accent text-accent-fg' : 'border-transparent text-muted hover:border-strong hover:text-body'"
                                            @click="activeTab = 'recipients'">
                                            Recipients
                                        </button>
                                        <button type="button" class="border-b-2 px-1 py-2 text-sm font-medium"
                                            :class="activeTab === 'attempts' ? 'border-accent text-accent-fg' : 'border-transparent text-muted hover:border-strong hover:text-body'"
                                            @click="activeTab = 'attempts'">
                                            Attempts
                                        </button>
                                    </nav>
                                </div>

                                <div v-if="activeTab === 'recipients'" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-default text-sm">
                                        <thead>
                                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted">
                                                <th class="py-2 pr-3">Contact</th>
                                                <th class="px-3 py-2">Phone</th>
                                                <th class="px-3 py-2">Status</th>
                                                <th class="px-3 py-2">Attempts</th>
                                                <th class="px-3 py-2">Last Attempt</th>
                                                <th class="px-3 py-2">Next Retry</th>
                                                <th class="py-2 pl-3">Outcome</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-default">
                                            <tr v-for="recipient in recipients" :key="recipient.basic_dialer_campaign_recipient_uuid">
                                                <td class="whitespace-nowrap py-2 pr-3 text-heading">
                                                    {{ recipient.contact_name || "-" }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted">{{ recipient.phone_number }}</td>
                                                <td class="whitespace-nowrap px-3 py-2">
                                                    <Badge :text="recipient.status" v-bind="statusBadgeProps(recipient.status)" />
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted">{{ recipient.attempts_count ?? 0 }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted">{{ formatDate(recipient.last_attempt_at) }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted">{{ formatDate(recipient.next_attempt_at) }}</td>
                                                <td class="min-w-56 py-2 pl-3 text-muted">
                                                    <div>{{ recipient.last_outcome || "-" }}</div>
                                                    <div v-if="recipient.last_error" class="mt-1 max-w-md truncate text-xs text-danger">
                                                        {{ recipient.last_error }}
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="recipients.length === 0">
                                                <td colspan="7" class="py-8 text-center text-muted">No recipients yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-else class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-default text-sm">
                                        <thead>
                                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted">
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
                                        <tbody class="divide-y divide-default">
                                            <tr v-for="attempt in attempts" :key="attempt.basic_dialer_campaign_attempt_uuid">
                                                <td class="whitespace-nowrap py-2 pr-3 text-muted">{{ formatDate(attempt.queued_at) }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-heading">
                                                    {{ attempt.recipient?.phone_number || "-" }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted">{{ attempt.attempt_number }}</td>
                                                <td class="whitespace-nowrap px-3 py-2">
                                                    <Badge :text="attempt.status" v-bind="statusBadgeProps(attempt.status)" />
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted">{{ attempt.outcome || "-" }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted">{{ attempt.hangup_cause || "-" }}</td>
                                                <td class="whitespace-nowrap px-3 py-2 text-muted">{{ formatDuration(attempt.duration) }}</td>
                                                <td class="max-w-sm py-2 pl-3 text-muted">
                                                    <div class="truncate">{{ attempt.response || "-" }}</div>
                                                </td>
                                            </tr>
                                            <tr v-if="attempts.length === 0">
                                                <td colspan="8" class="py-8 text-center text-muted">No attempts yet.</td>
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
import { Bar, Doughnut } from "vue-chartjs";
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from "chart.js";
import Badge from "@generalComponents/Badge.vue";
import { useTheme } from "../../../composables/useTheme";

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const { isDark } = useTheme();
const chartTickColor = computed(() => (isDark.value ? "#9ca3af" : "#6b7280"));
const chartGridColor = computed(() => (isDark.value ? "rgba(255,255,255,0.08)" : "rgba(0,0,0,0.08)"));

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
const outcomeBreakdown = ref([]);
const hangupBreakdown = ref([]);

const OUTCOME_GREEN = "#10b981";
const OUTCOME_RED = "#ef4444";
const OUTCOME_GRAY = "#9ca3af";

function outcomeColor(label) {
    const norm = String(label ?? "").toLowerCase().replace(/[^a-z]/g, "_");
    if (norm === "answered") return OUTCOME_GREEN;
    if (norm === "" || norm === "unknown") return OUTCOME_GRAY;
    return OUTCOME_RED;
}

function hangupCauseColor(label) {
    const norm = String(label ?? "").toUpperCase().replace(/[^A-Z_]/g, "");
    if (norm === "NORMAL_CLEARING") return OUTCOME_GREEN;
    if (norm === "" || norm === "NONE" || norm === "UNKNOWN" || norm === "ORIGINATOR_CANCEL") return OUTCOME_GRAY;
    return OUTCOME_RED;
}

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

const completedRecipients = computed(() => {
    const total = summary.value.total_recipients ?? 0;
    const recipientCounts = summary.value.recipients || {};
    const pending = recipientCounts.pending ?? 0;
    const retryWait = recipientCounts.retry_wait ?? 0;
    return Math.max(0, total - pending - retryWait);
});

const outcomeTotal = computed(() =>
    outcomeBreakdown.value.reduce((sum, item) => sum + (item.count || 0), 0)
);

const hasBreakdowns = computed(() =>
    outcomeBreakdown.value.length > 0 || hangupBreakdown.value.length > 0
);

const outcomeChartData = computed(() => ({
    labels: outcomeBreakdown.value.map((item) => formatLabel(item.label)),
    datasets: [{
        data: outcomeBreakdown.value.map((item) => item.count),
        backgroundColor: outcomeBreakdown.value.map((item) => outcomeColor(item.label)),
        borderWidth: 0,
    }],
}));

const hangupChartData = computed(() => ({
    labels: hangupBreakdown.value.map((item) => item.label),
    datasets: [{
        data: hangupBreakdown.value.map((item) => item.count),
        backgroundColor: hangupBreakdown.value.map((item) => hangupCauseColor(item.label)),
        borderRadius: 4,
    }],
}));

const doughnutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: "65%",
    plugins: {
        legend: { display: false },
        tooltip: { enabled: true },
    },
};

const barOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: "y",
    plugins: {
        legend: { display: false },
        tooltip: { enabled: true },
    },
    elements: {
        bar: { borderSkipped: false },
    },
    datasets: {
        bar: { maxBarThickness: 24, categoryPercentage: 0.7, barPercentage: 0.8 },
    },
    scales: {
        x: { beginAtZero: true, ticks: { precision: 0, color: chartTickColor.value }, grid: { color: chartGridColor.value } },
        y: { grid: { display: false }, ticks: { autoSkip: false, color: chartTickColor.value } },
    },
}));

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
            outcomeBreakdown.value = response.data.outcome_breakdown || [];
            hangupBreakdown.value = response.data.hangup_breakdown || [];
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            loading.value = false;
        });
}

function percent(part, whole) {
    if (!whole) return 0;
    return Math.round((part / whole) * 100);
}

function formatLabel(value) {
    if (!value) return "";
    return String(value).replace(/_/g, " ");
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

    const total = Math.max(0, parseInt(value, 10) || 0);
    if (total < 60) return `${total}s`;
    const mins = Math.floor(total / 60);
    const secs = total % 60;
    if (mins < 60) return `${mins}m ${secs}s`;
    const hours = Math.floor(mins / 60);
    return `${hours}h ${mins % 60}m`;
}

const statusBadgeProps = (status) => {
    if (["running", "dialing", "queued"].includes(status)) {
        return {
            backgroundColor: "bg-info-subtle",
            textColor: "text-info",
            ringColor: "ring-info/20",
        };
    }

    if (["completed", "answered"].includes(status)) {
        return {
            backgroundColor: "bg-success-subtle",
            textColor: "text-success",
            ringColor: "ring-success/20",
        };
    }

    if (["paused", "retry_wait"].includes(status)) {
        return {
            backgroundColor: "bg-warning-subtle",
            textColor: "text-warning",
            ringColor: "ring-warning/20",
        };
    }

    if (["stopped", "failed", "rejected"].includes(status)) {
        return {
            backgroundColor: "bg-danger-subtle",
            textColor: "text-danger",
            ringColor: "ring-danger/20",
        };
    }

    return {
        backgroundColor: "bg-surface-2",
        textColor: "text-body",
        ringColor: "ring-strong/20",
    };
};
</script>
