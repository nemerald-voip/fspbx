<template>
    <div class="space-y-6">
        <div v-if="loading && !loaded" class="rounded-lg bg-white p-12 text-center text-sm text-gray-500 ring-1 ring-gray-200">
            {{ $t("Loading dashboard...") }}
        </div>

        <template v-else>
            <div class="flex items-center justify-end gap-2">
                <span class="text-xs text-gray-400">{{ lastUpdatedLabel }}</span>
                <button type="button" @click="fetchOverview"
                    class="rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    :title="$t(&quot;Refresh&quot;)">
                    <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': loading }" />
                </button>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <KpiCard :label="$t(&quot;Running&quot;)" :value="kpis.running_campaigns" :sub="$tChoice('of :count campaign|of :count campaigns', kpis.total_campaigns)"
                    accent="bg-blue-50 text-blue-700 ring-blue-600/20" />
                <KpiCard :label="$t(&quot;Paused&quot;)" :value="kpis.paused_campaigns" :sub="$t('Campaigns')"
                    accent="bg-yellow-50 text-yellow-700 ring-yellow-600/20" />
                <KpiCard :label="$t(&quot;Calls Today&quot;)" :value="kpis.attempts_today"
                    :sub="$t(':count answered', { count: kpis.answered_today })"
                    accent="bg-indigo-50 text-indigo-700 ring-indigo-600/20" />
                <KpiCard :label="$t(&quot;Answer Rate (Today)&quot;)" :value="`${kpis.answer_rate_today}%`"
                    :sub="$t(':rate% all-time', { rate: kpis.answer_rate })"
                    accent="bg-emerald-50 text-emerald-700 ring-emerald-600/20" />
                <KpiCard :label="$t(&quot;Total Attempts&quot;)" :value="kpis.total_attempts"
                    :sub="$t(':count answered', { count: kpis.total_answered })"
                    accent="bg-gray-50 text-gray-700 ring-gray-600/20" />
                <KpiCard :label="$t(&quot;Talk Time&quot;)" :value="formatDuration(kpis.total_talk_seconds)" :sub="$t('cumulative')"
                    accent="bg-violet-50 text-violet-700 ring-violet-600/20" />
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200 lg:col-span-1">
                    <div class="mb-3 flex items-baseline justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $t("Outcome Breakdown") }}</h3>
                        <span class="text-xs text-gray-400">{{ $tChoice(':count attempt|:count attempts', outcomeTotal) }}</span>
                    </div>
                    <div v-if="outcomeBreakdown.length === 0" class="py-12 text-center text-sm text-gray-500">
                        {{ $t("No attempts yet.") }}
                    </div>
                    <div v-else class="flex flex-col items-center gap-4">
                        <div class="relative h-48 w-48">
                            <Doughnut :data="outcomeChartData" :options="doughnutOptions" />
                        </div>
                        <div class="w-full space-y-1">
                            <div v-for="item in outcomeBreakdown" :key="item.label"
                                class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full"
                                        :style="{ backgroundColor: outcomeColor(item.label) }"></span>
                                    <span class="capitalize text-gray-700">{{ formatLabel(item.label) }}</span>
                                </div>
                                <div class="font-medium text-gray-900">
                                    {{ item.count }}
                                    <span class="ml-1 text-gray-400">{{ percent(item.count, outcomeTotal) }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 ring-1 ring-gray-200 lg:col-span-2">
                    <div class="mb-3 flex items-baseline justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">{{ $t("Hangup Causes") }}</h3>
                        <span class="text-xs text-gray-400">{{ $t('Top :count', { count: hangupBreakdown.length }) }}</span>
                    </div>
                    <div v-if="hangupBreakdown.length === 0" class="py-12 text-center text-sm text-gray-500">
                        {{ $t("No hangup cause data yet.") }}
                    </div>
                    <div v-else class="h-64">
                        <Bar :data="hangupChartData" :options="barOptions" />
                    </div>
                </div>
            </div>

            <div v-if="activeCampaigns.length > 0" class="rounded-lg bg-white p-5 ring-1 ring-gray-200">
                <h3 class="mb-3 text-sm font-semibold text-gray-900">{{ $t("Active Campaigns") }}</h3>
                <div class="space-y-3">
                    <div v-for="campaign in activeCampaigns" :key="campaign.basic_dialer_campaign_uuid"
                        class="rounded-md border border-gray-100 px-4 py-3 hover:bg-gray-50 cursor-pointer"
                        @click="$emit('open-campaign', campaign.basic_dialer_campaign_uuid)">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-medium text-gray-900">{{ campaign.name }}</span>
                                    <Badge :text="dialerLabel(campaign.status)" v-bind="statusBadgeProps(campaign.status)" />
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $t(':answered answered · :failed failed · :pending pending', { answered: campaign.answered_recipients_count, failed: campaign.failed_recipients_count, pending: campaign.pending_recipients_count }) }}
                                </div>
                            </div>
                            <div class="w-40 sm:w-56">
                                <div class="flex items-center justify-between text-xs text-gray-500">
                                    <span>{{ campaignProgress(campaign) }}%</span>
                                    <span>{{ $tChoice(':count contact|:count contacts', campaign.recipients_count) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full bg-indigo-500"
                                        :style="{ width: campaignProgress(campaign) + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white ring-1 ring-gray-200">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-900">{{ $t("Recent Activity") }}</h3>
                    <span class="text-xs text-gray-400">{{ $tChoice('Last :count attempt|Last :count attempts', recentActivity.length) }}</span>
                </div>
                <div v-if="recentActivity.length === 0" class="py-12 text-center text-sm text-gray-500">
                    {{ $t("No call activity yet.") }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2">{{ $t("When") }}</th>
                                <th class="px-4 py-2">{{ $t("Campaign") }}</th>
                                <th class="px-4 py-2">{{ $t("Phone") }}</th>
                                <th class="px-4 py-2">{{ $t("Status") }}</th>
                                <th class="px-4 py-2">{{ $t("Outcome") }}</th>
                                <th class="px-4 py-2">{{ $t("Hangup") }}</th>
                                <th class="px-4 py-2">{{ $t("Duration") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in recentActivity" :key="item.basic_dialer_campaign_attempt_uuid"
                                class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-2 text-gray-500">
                                    {{ formatRelative(item.created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <button type="button" class="text-indigo-600 hover:underline"
                                        @click="$emit('open-campaign', item.basic_dialer_campaign_uuid)">
                                        {{ item.campaign?.name || '-' }}
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-900">
                                    <div>{{ item.recipient?.phone_number || '-' }}</div>
                                    <div v-if="item.recipient?.contact_name" class="text-xs text-gray-400">
                                        {{ item.recipient.contact_name }}
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <Badge :text="dialerLabel(item.status)" v-bind="statusBadgeProps(item.status)" />
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-500">{{ formatLabel(item.outcome) || '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-500">{{ item.hangup_cause || '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-gray-500">{{ formatDuration(item.duration) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { dialerLabel } from "../../data/basicDialerLabels";
import { formatDuration, relativeTime as formatRelative } from "../../data/localizedTime";
import { trans, currentLocale } from "@i18n";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import axios from "axios";
import { ArrowPathIcon } from "@heroicons/vue/24/solid";
import { Bar, Doughnut } from "vue-chartjs";
import {
    ArcElement,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
} from "chart.js";
import Badge from "@generalComponents/Badge.vue";
import KpiCard from "./BasicDialerKpiCard.vue";

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, Tooltip, Title, Legend);

const props = defineProps({
    route: {
        type: String,
        required: true,
    },
});

defineEmits(["open-campaign", "error"]);

const loading = ref(false);
const loaded = ref(false);
const lastUpdated = ref(null);
const kpis = ref({
    total_campaigns: 0,
    running_campaigns: 0,
    paused_campaigns: 0,
    draft_campaigns: 0,
    completed_campaigns: 0,
    stopped_campaigns: 0,
    total_recipients: 0,
    pending_recipients: 0,
    answered_recipients: 0,
    failed_recipients: 0,
    total_attempts: 0,
    attempts_today: 0,
    answered_today: 0,
    total_answered: 0,
    answer_rate: 0,
    answer_rate_today: 0,
    total_talk_seconds: 0,
});
const outcomeBreakdown = ref([]);
const hangupBreakdown = ref([]);
const recentActivity = ref([]);
const activeCampaigns = ref([]);

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

const outcomeTotal = computed(() =>
    outcomeBreakdown.value.reduce((sum, item) => sum + (item.count || 0), 0)
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

const barOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: "y",
    plugins: {
        legend: { display: false },
        tooltip: { enabled: true },
    },
    datasets: {
        bar: { maxBarThickness: 24, categoryPercentage: 0.7, barPercentage: 0.8 },
    },
    scales: {
        x: { beginAtZero: true, ticks: { precision: 0 } },
        y: { grid: { display: false }, ticks: { autoSkip: false } },
    },
};

const lastUpdatedLabel = computed(() => {
    if (!lastUpdated.value) return "";
    return trans("Updated :time", { time: formatRelative(lastUpdated.value) });
});

let pollHandle = null;

onMounted(() => {
    fetchOverview();
    pollHandle = setInterval(fetchOverview, 15000);
});

onBeforeUnmount(() => {
    if (pollHandle) clearInterval(pollHandle);
});

function fetchOverview() {
    if (!props.route) return;
    loading.value = true;
    axios.get(props.route)
        .then((response) => {
            kpis.value = { ...kpis.value, ...(response.data.kpis || {}) };
            outcomeBreakdown.value = response.data.outcome_breakdown || [];
            hangupBreakdown.value = response.data.hangup_breakdown || [];
            recentActivity.value = response.data.recent_activity || [];
            activeCampaigns.value = response.data.active_campaigns || [];
            lastUpdated.value = new Date().toISOString();
            loaded.value = true;
        })
        .catch(() => {})
        .finally(() => {
            loading.value = false;
        });
}

function campaignProgress(campaign) {
    const total = campaign.recipients_count || 0;
    if (total === 0) return 0;
    const done = total - (campaign.pending_recipients_count || 0);
    return Math.max(0, Math.min(100, Math.round((done / total) * 100)));
}

function percent(part, whole) {
    if (!whole) return 0;
    return Math.round((part / whole) * 100);
}

const formatLabel = dialerLabel;





function statusBadgeProps(status) {
    if (["running", "dialing", "queued"].includes(status)) {
        return { backgroundColor: "bg-blue-50", textColor: "text-blue-700", ringColor: "ring-blue-600/20" };
    }
    if (["completed", "answered"].includes(status)) {
        return { backgroundColor: "bg-green-50", textColor: "text-green-700", ringColor: "ring-green-600/20" };
    }
    if (["paused", "retry_wait"].includes(status)) {
        return { backgroundColor: "bg-yellow-50", textColor: "text-yellow-700", ringColor: "ring-yellow-600/20" };
    }
    if (["stopped", "failed", "rejected"].includes(status)) {
        return { backgroundColor: "bg-red-50", textColor: "text-red-700", ringColor: "ring-red-600/20" };
    }
    return { backgroundColor: "bg-gray-50", textColor: "text-gray-700", ringColor: "ring-gray-600/20" };
}
</script>
