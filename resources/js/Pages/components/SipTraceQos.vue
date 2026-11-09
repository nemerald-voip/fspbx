<template>
    <section class="space-y-4 p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-semibold">{{ $t('Media quality') }}</h3>
            <span v-if="result" class="text-xs text-gray-500">{{ $t(':count RTCP reports', { count: result.report_count }) }}</span>
        </div>
        <p v-if="busy" role="status">{{ $t('Loading QoS reports…') }}</p>
        <div v-else-if="error" role="alert" class="flex items-center gap-3 text-red-700">
            {{ error }} <button type="button" class="qos-button" @click="load">{{ $t('Retry') }}</button>
        </div>
        <p v-else-if="!stream" role="status" class="rounded-lg border bg-white p-4 text-gray-600">
            {{ $t('No usable RTCP reports were found for this call.') }}
        </p>
        <template v-else>
            <label v-if="result.streams.length > 1" class="block text-xs text-gray-600">
                {{ $t('Stream') }}
                <select v-model="streamIndex" :aria-label="$t('Stream')" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option v-for="(item, index) in result.streams" :key="index" :value="index">
                        {{ index + 1 }} · {{ item.source }} → {{ item.destination }} · {{ item.call_id }}
                    </option>
                </select>
            </label>
            <div class="break-all text-xs text-gray-600">
                <p class="font-medium text-gray-900">{{ stream.source }} → {{ stream.destination }}</p>
                <p>{{ $t('SIP Call-ID:') }} {{ stream.call_id }}</p>
                <p v-if="stream.sender_ssrc || stream.report_ssrc">SSRC: {{ stream.sender_ssrc || '—' }} → {{ stream.report_ssrc || '—' }}</p>
            </div>
            <dl class="grid gap-3 sm:grid-cols-3">
                <div v-for="key in headlineMetrics" :key="key" class="rounded-lg border bg-white px-4 py-3">
                    <dt class="text-xs text-gray-600">{{ labels[key] }}</dt>
                    <dd class="my-1 text-2xl font-semibold tabular-nums">{{ format(stats[key]?.avg, key) }}</dd>
                    <dd class="text-xs text-gray-500">{{ $t('Average') }}</dd>
                </div>
            </dl>
            <div class="space-y-3 rounded-lg border bg-white p-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <label class="text-xs text-gray-600">
                        {{ $t('Chart metric') }}
                        <select :value="metric" :aria-label="$t('Chart metric')" class="mt-1 block max-w-full rounded-md border-gray-300 text-sm"
                            @change="preferredMetric = $event.target.value">
                            <option v-for="key in availableMetrics" :key="key" :value="key">{{ labels[key] }}</option>
                        </select>
                    </label>
                    <div class="flex gap-1">
                        <button type="button" class="qos-button" :aria-pressed="mode === 'line'" @click="mode = 'line'">{{ $t('Line') }}</button>
                        <button type="button" class="qos-button" :aria-pressed="mode === 'bar'" @click="mode = 'bar'">{{ $t('Bar') }}</button>
                    </div>
                </div>
                <div class="relative h-56">
                    <component :is="mode === 'line' ? Line : Bar" :data="chartData" :options="chartOptions" :aria-label="labels[metric]" />
                </div>
            </div>
            <div class="rounded-lg border bg-white p-4">
                <h3 class="mb-2 font-semibold">{{ $t('Stream statistics') }}</h3>
                <table class="w-full text-xs">
                    <thead><tr>
                        <th class="text-left">{{ $t('Metric') }}</th><th>{{ $t('Min') }}</th>
                        <th>{{ $t('Average') }}</th><th>{{ $t('Max') }}</th>
                    </tr></thead>
                    <tbody><tr v-for="key in availableMetrics" :key="key" class="border-t">
                        <th scope="row" class="text-left font-normal">{{ labels[key] }}</th>
                        <td v-for="field in ['min', 'avg', 'max']" :key="field">{{ format(stats[key][field], key) }}</td>
                    </tr></tbody>
                </table>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { Chart, LinearScale, BarElement, LineElement, PointElement, Tooltip } from 'chart.js';
import axios from 'axios';
import { trans } from '@i18n';

Chart.register(LinearScale, BarElement, LineElement, PointElement, Tooltip);
const props = defineProps({ cdrUuid: { type: String, required: true }, route: { type: String, required: true } });
const emit = defineEmits(['loaded']);
const result = ref(null);
const error = ref('');
const busy = ref(false);
const streamIndex = ref(0);
const preferredMetric = ref('mos');
const mode = ref('line');
const controller = new AbortController();
const stream = computed(() => result.value?.streams[streamIndex.value]);
const headlineMetrics = ['mos', 'loss', 'jitter'];
const labels = computed(() => ({
    mos: trans('Estimated MOS'), loss: trans('Interval packet loss (%)'), jitter: trans('Jitter (RTP timestamp units)'),
    packets_lost: trans('Cumulative packets lost'), packets: trans('Sender packets'), octets: trans('Sender octets'),
    highest_seq_no: trans('Highest sequence number'), lsr: trans('Last sender report'),
}));
const points = computed(() => [...(stream.value?.points ?? [])].sort((a, b) => a.time - b.time));
const stats = computed(() => {
    const summary = {};
    for (const key of Object.keys(labels.value)) {
        const values = points.value.map(point => point[key]).filter(Number.isFinite);
        if (values.length) {
            summary[key] = {
                min: Math.min(...values), max: Math.max(...values),
                avg: values.reduce((sum, value) => sum + value, 0) / values.length,
            };
        }
    }
    return summary;
});
const availableMetrics = computed(() => Object.keys(stats.value));
const metric = computed(() => stats.value[preferredMetric.value] ? preferredMetric.value : availableMetrics.value[0]);
function format(value, key) {
    if (!Number.isFinite(value)) return '—';
    return value.toLocaleString(undefined, { minimumFractionDigits: key === 'mos' ? 2 : 0, maximumFractionDigits: 2 });
}
const chartData = computed(() => ({
    datasets: [{
        label: labels.value[metric.value],
        data: points.value.map(point => ({ x: (point.time - result.value.start_ms) / 1000, y: point[metric.value] ?? null })),
        borderColor: '#4f46e5', backgroundColor: '#6366f1', borderWidth: 2,
        maxBarThickness: 24, pointRadius: points.value.length > 150 ? 1 : 3,
    }],
}));
const chartOptions = computed(() => ({
    maintainAspectRatio: false, animation: false,
    scales: {
        x: { type: 'linear', title: { display: true, text: trans('Seconds from call start') } },
        y: { type: 'linear', beginAtZero: mode.value === 'bar', title: { display: true, text: labels.value[metric.value] } },
    },
    plugins: { legend: { display: false }, tooltip: { callbacks: {
        title: items => items.length ? new Date(result.value.start_ms + items[0].parsed.x * 1000).toISOString() : '',
    } } },
}));
async function load() {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        const { data } = await axios.post(props.route, { cdr_uuid: props.cdrUuid, view: 'qos' }, { signal: controller.signal });
        result.value = data;
        emit('loaded', data.report_count);
    } catch (exception) {
        if (!controller.signal.aborted) error.value = exception.response?.data?.errors?.capture?.[0] || trans('Unable to load QoS reports.');
    } finally {
        busy.value = false;
    }
}
onMounted(load);
onBeforeUnmount(() => controller.abort());
</script>

<style scoped>
.qos-button { border: 1px solid #d1d5db; border-radius: .375rem; color: #374151; background: white; padding: .375rem .75rem; font-size: .75rem; }
.qos-button[aria-pressed=true] { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
th, td { padding: .5rem .25rem; }
td, thead th:not(:first-child) { text-align: right; font-variant-numeric: tabular-nums; }
</style>
