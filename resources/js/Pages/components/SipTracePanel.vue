<template>
    <button type="button" class="sip-button sip-primary" @click="openWorkspace">
        <ChartBarIcon class="h-4 w-4" aria-hidden="true" />{{ $t('SIP Trace') }}
    </button>
    <Dialog :open="open" class="relative z-50" @close="closeWorkspace">
        <div class="fixed inset-0 bg-gray-900/50" aria-hidden="true" />
        <div class="fixed inset-0 p-2 sm:p-4">
            <DialogPanel class="sip-workspace">
                <header class="sip-header">
                    <button type="button" class="absolute right-4 top-4 rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" @click="closeWorkspace">
                        <span class="sr-only">{{ $t('Close') }}</span>
                        <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                    </button>
                    <button type="button" class="sip-back" @click="closeWorkspace">
                        <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Call details') }}
                    </button>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <ChartBarIcon class="h-6 w-6 text-indigo-600" aria-hidden="true" />
                        <DialogTitle class="text-xl font-semibold text-gray-900">{{ $t('SIP Trace') }}</DialogTitle>
                    </div>
                    <p class="mt-1 break-words text-sm text-gray-500">
                        {{ call.caller_id_number_formatted || call.caller_id_number || '—' }} →
                        {{ call.caller_destination_formatted || call.destination_number || '—' }}
                        <span v-if="call.start_date"> · {{ $t('On :date at :time', { date: call.start_date, time: call.start_time }) }}</span>
                    </p>
                </header>
                <div class="sip-toolbar">
                    <div class="text-xs text-gray-500" role="status">
                        <span v-if="busy">{{ $t('Loading SIP capture…') }}</span>
                        <span v-else-if="messages.length">{{ $t(':count SIP messages', { count: messages.length }) }} ·
                            {{ $t(':count call legs', { count: legCount }) }} · {{ $t(':count endpoints', { count: endpoints.length }) }}</span>
                        <span v-else>{{ $t('SIP signaling') }}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="sip-button" :disabled="busy" @click="load('pcap')">
                            <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Download PCAP') }}
                        </button>
                        <button type="button" class="sip-button" :disabled="busy" @click="load('txt')">
                            <DocumentTextIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Download TXT') }}
                        </button>
                    </div>
                </div>
                <div v-if="error" role="alert" class="mx-4 mb-3 flex flex-wrap items-center gap-3 text-sm text-red-700">
                    <span>{{ error }}</span>
                    <button v-if="!messages.length" type="button" class="sip-button" :disabled="busy" @click="load()">{{ $t('Retry') }}</button>
                </div>
                <template v-if="messages.length">
                    <label v-if="!compact && detailVisible" class="sip-resize">
                        {{ $t('Flow width') }}
                        <input v-model.number="flowPercent" type="range" min="40" max="75" :aria-label="$t('Flow width')" />
                        <span>{{ flowPercent }}%</span>
                    </label>
                    <div class="sip-content" :class="{ 'sip-split': !compact && detailVisible }" :style="{ '--flow-width': flowPercent + '%' }">
                        <section class="sip-flow-panel" :aria-label="$t('SIP call flow')">
                            <div class="sip-panel-heading">
                                <h3 class="text-sm font-semibold">{{ $t('All legs · Combined flow') }}</h3>
                                <button ref="showButton" type="button" class="sip-button" @click="toggleDetails">
                                    {{ detailVisible && !compact ? $t('Hide message') : $t('Show message') }}
                                </button>
                            </div>
                            <div ref="flowViewport" class="sip-flow-scroll">
                                <div class="sip-ladder" :style="{ width: width + 'px' }">
                                    <div class="sip-endpoints">
                                        <div v-for="(address, index) in endpoints" :key="address" class="sip-endpoint"
                                            :style="{ left: laneX(index) + 'px' }">{{ address }}</div>
                                    </div>
                                    <div class="sip-lanes" aria-hidden="true">
                                        <span v-for="(address, index) in endpoints" :key="address" :style="{ left: laneX(index) + 'px' }" />
                                    </div>
                                    <button v-for="(message, index) in pageMessages" :key="page * pageSize + index" type="button"
                                        class="sip-message" :class="{ 'sip-selected': selected === message }"
                                        :aria-pressed="selected === message" :aria-label="messageLabel(message)" @click="selectMessage(message)">
                                        <span class="sip-elapsed">{{ elapsed(message) }}</span>
                                        <span class="sip-arrow" :class="{ 'sip-reverse': sourceX(message) > targetX(message), 'sip-failure': isFailure(message) }"
                                            :style="{ left: Math.min(sourceX(message), targetX(message)) + 'px', width: Math.abs(targetX(message) - sourceX(message)) + 'px' }">
                                            <span :title="firstLine(message)">{{ summary(message) }}</span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="messages.length > pageSize" class="sip-pagination">
                                <button type="button" class="sip-button" :disabled="page === 0" @click="page--">{{ $t('Previous') }}</button>
                                <span>{{ $t('Page :page of :total', { page: page + 1, total: Math.ceil(messages.length / pageSize) }) }}</span>
                                <button type="button" class="sip-button" :disabled="(page + 1) * pageSize >= messages.length" @click="page++">{{ $t('Next') }}</button>
                            </div>
                        </section>
                        <section v-if="selected && detailVisible && !compact" class="sip-detail-panel" :aria-label="$t('Selected SIP message')">
                            <div class="sip-panel-heading">
                                <h3 class="min-w-0 break-words text-sm font-semibold">{{ summary(selected) }}</h3>
                                <button ref="expandButton" type="button" class="sip-button" @click="overlayOpen = true">
                                    <ArrowsPointingOutIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Expand') }}
                                </button>
                            </div>
                            <div class="sip-metadata">
                                <p>{{ selected.timestamp }} UTC</p>
                                <p>{{ endpoint(selected, 'src') }} → {{ endpoint(selected, 'dst') }}</p>
                                <p>{{ $t('SIP Call-ID:') }} {{ selected.session_id }}</p>
                            </div>
                            <pre class="sip-payload">{{ selected.payload }}</pre>
                        </section>
                    </div>
                    <footer class="sip-footer">
                        <span>{{ $t('Downloads include all captured legs in this call’s lookup window, across all pages.') }}</span>
                        <span>{{ $t('SIP only · No RTP audio · UTC') }}</span>
                    </footer>
                </template>
                <div v-else-if="busy" class="p-8 text-center text-sm text-gray-500">{{ $t('Loading SIP capture…') }}</div>
            </DialogPanel>
        </div>
        <Dialog :open="overlayOpen && open" :initial-focus="overlayCloseButton" class="relative z-[60]" @close="closeOverlay">
            <div class="fixed inset-0 bg-gray-900/40" aria-hidden="true" />
            <div class="fixed inset-0 flex items-center justify-center p-2 sm:p-6">
                <DialogPanel class="sip-message-overlay">
                    <div class="sip-panel-heading">
                        <DialogTitle class="min-w-0 break-words text-base font-semibold">{{ selected ? summary(selected) : $t('SIP message') }}</DialogTitle>
                        <button ref="overlayCloseButton" type="button" class="sip-button" @click="closeOverlay">
                            <XMarkIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Close') }}
                        </button>
                    </div>
                    <div v-if="selected" class="sip-metadata">
                        <p>{{ selected.timestamp }} UTC · {{ endpoint(selected, 'src') }} → {{ endpoint(selected, 'dst') }}</p>
                        <p>{{ $t('SIP Call-ID:') }} {{ selected.session_id }}</p>
                    </div>
                    <pre class="sip-payload">{{ selected?.payload }}</pre>
                </DialogPanel>
            </div>
        </Dialog>
    </Dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { ArrowDownTrayIcon, ArrowLeftIcon, ArrowsPointingOutIcon, ChartBarIcon, DocumentTextIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';
import { trans } from '@i18n';

const props = defineProps({
    cdrUuid: { type: String, required: true }, route: { type: String, required: true },
    call: { type: Object, default: () => ({}) },
});
const open = ref(false);
const busy = ref(false);
const error = ref('');
const messages = ref([]);
const selected = ref(null);
const page = ref(0);
const detailVisible = ref(true);
const overlayOpen = ref(false);
const flowPercent = ref(62);
const flowViewport = ref(null);
const viewportWidth = ref(0);
const showButton = ref(null);
const expandButton = ref(null);
const overlayCloseButton = ref(null);
const media = window.matchMedia('(max-width: 899px)');
const compact = ref(media.matches);
const onResize = () => { compact.value = media.matches; };
media.addEventListener('change', onResize);
const pageSize = 100;
let controller;
let resizeObserver;
const endpoint = (message, side) => {
    const ip = message[`${side}_ip`];
    return `${ip.includes(':') ? `[${ip}]` : ip}:${message[`${side}_port`]}`;
};
// Keep endpoint lanes stable when changing pages, including correlated call legs.
const endpoints = computed(() => [...new Set(messages.value.flatMap(message => [endpoint(message, 'src'), endpoint(message, 'dst')]))]);
const legCount = computed(() => new Set(messages.value.map(message => message.session_id)).size);
const width = computed(() => Math.max(viewportWidth.value, 640, endpoints.value.length * 180 + 110));
const laneX = index => 80 + (width.value - 110) / Math.max(1, endpoints.value.length) * (index + 0.5);
const sourceX = message => laneX(endpoints.value.indexOf(endpoint(message, 'src')));
const targetX = message => {
    const target = laneX(endpoints.value.indexOf(endpoint(message, 'dst')));
    return target === sourceX(message) ? target + 45 : target;
};
const pageMessages = computed(() => messages.value.slice(page.value * pageSize, (page.value + 1) * pageSize));
const firstLine = message => message.payload.split(/\r?\n/, 1)[0] || trans('SIP message');
const summary = message => {
    const line = firstLine(message);
    return (line.startsWith('SIP/2.0 ') ? line.slice(8) : line.split(' ', 1)[0]).slice(0, 60);
};
const isFailure = message => /^SIP\/2\.0 [456]\d\d/.test(firstLine(message)) && !/^SIP\/2\.0 (401|407)/.test(firstLine(message));
const timestamp = message => {
    let value = message.timestamp.replace(' ', 'T');
    if (! /(?:Z|[+-]\d{2}:?\d{2})$/i.test(value)) value += 'Z';
    return Date.parse(value);
};
const elapsed = message => {
    const difference = (timestamp(message) - timestamp(messages.value[0])) / 1000;
    return Number.isFinite(difference) ? `${difference.toFixed(3)}s` : '—';
};
const messageLabel = message => `${elapsed(message)} · ${endpoint(message, 'src')} → ${endpoint(message, 'dst')} · ${firstLine(message)}`;
const selectMessage = message => {
    selected.value = message;
    detailVisible.value = true;
    if (compact.value) overlayOpen.value = true;
};
const toggleDetails = () => {
    if (compact.value) overlayOpen.value = true;
    else detailVisible.value = !detailVisible.value;
};
const closeOverlay = async () => {
    overlayOpen.value = false;
    await nextTick();
    (compact.value ? showButton.value : expandButton.value)?.focus();
};
const openWorkspace = () => {
    open.value = true;
    if (!messages.value.length) load();
};
const closeWorkspace = () => {
    if (overlayOpen.value) { closeOverlay(); return; }
    controller?.abort();
    controller = null;
    busy.value = false;
    open.value = false;
};
watch(page, async () => {
    selected.value = pageMessages.value[0] ?? null;
    await nextTick();
    if (flowViewport.value) flowViewport.value.scrollTop = 0;
});
watch(flowViewport, element => {
    resizeObserver?.disconnect();
    if (!element) return;
    resizeObserver = new ResizeObserver(entries => { viewportWidth.value = entries[0].contentRect.width; });
    resizeObserver.observe(element);
});

const load = async (format = null) => {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    const requestController = new AbortController();
    controller = requestController;
    try {
        const response = await axios.post(props.route, {
            cdr_uuid: props.cdrUuid, download: !!format, ...(format ? { format } : {}),
        }, { responseType: format ? 'blob' : 'json', signal: requestController.signal });
        if (requestController.signal.aborted) return;
        if (format) {
            const url = URL.createObjectURL(response.data);
            const link = document.createElement('a');
            link.href = url;
            link.download = `call-${props.cdrUuid}.${format}`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        } else {
            messages.value = response.data.messages;
            selected.value = messages.value[0] ?? null;
            page.value = 0;
        }
    } catch (exception) {
        if (axios.isCancel(exception)) return;
        let data = exception.response?.data;
        if (data instanceof Blob) {
            try { data = JSON.parse(await data.text()); } catch { data = null; }
        }
        error.value = Object.values(data?.errors ?? {}).flat()[0] || trans('Unable to retrieve the SIP capture.');
    } finally {
        if (controller === requestController) busy.value = false;
    }
};
onBeforeUnmount(() => {
    controller?.abort();
    resizeObserver?.disconnect();
    media.removeEventListener('change', onResize);
});
</script>

<style scoped>
.sip-button { display: inline-flex; align-items: center; justify-content: center; gap: .375rem; flex-shrink: 0; padding: .5rem .75rem; border: 1px solid #d1d5db; border-radius: .375rem; background: white; color: #111827; font-size: .75rem; font-weight: 600; }
.sip-button:hover { background: #f9fafb; }
.sip-button:disabled { opacity: .5; cursor: default; }
.sip-button:focus-visible, .sip-message:focus-visible, .sip-back:focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
.sip-primary { background: #4f46e5; color: white; border-color: #4f46e5; }
.sip-primary:hover { background: #4338ca; }
.sip-workspace { display: flex; flex-direction: column; width: 100%; height: calc(100vh - 2rem); height: calc(100dvh - 2rem); margin: auto; overflow: auto; background: #f3f4f6; border-radius: .75rem; color: #111827; box-shadow: 0 20px 50px #0003; }
.sip-header { position: relative; padding: 1rem 3.5rem 1rem 1.25rem; background: white; border-bottom: 1px solid #e5e7eb; }
.sip-back { display: inline-flex; align-items: center; gap: .375rem; color: #4f46e5; font-size: .75rem; }
.sip-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .875rem 1rem; }
.sip-resize { display: flex; align-items: center; gap: .625rem; padding: 0 1rem .75rem; font-size: .75rem; color: #6b7280; }
.sip-resize input { width: 9rem; accent-color: #4f46e5; }
.sip-content { display: grid; min-height: 12rem; flex: 1; gap: .75rem; padding: 0 1rem; }
.sip-split { grid-template-columns: minmax(0, var(--flow-width)) minmax(0, 1fr); }
.sip-flow-panel, .sip-detail-panel { display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: hidden; background: white; border: 1px solid #e5e7eb; border-radius: .5rem; }
.sip-panel-heading { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; flex-shrink: 0; gap: .5rem; padding: .75rem; border-bottom: 1px solid #e5e7eb; }
.sip-flow-scroll { flex: 1; min-height: 0; overflow: auto; }
.sip-ladder { position: relative; padding-bottom: 1rem; }
.sip-endpoints { height: 56px; position: sticky; top: 0; z-index: 2; background: white; border-bottom: 1px solid #f3f4f6; }
.sip-endpoint { position: absolute; top: 18px; transform: translateX(-50%); max-width: 165px; overflow-wrap: anywhere; text-align: center; font-size: .75rem; color: #4b5563; }
.sip-lanes { position: absolute; inset: 56px 0 0; pointer-events: none; }
.sip-lanes span { position: absolute; top: 0; bottom: 0; border-left: 1px dashed #d1d5db; }
.sip-message { display: block; position: relative; height: 44px; width: 100%; text-align: left; font-size: .75rem; }
.sip-message:hover { background: #f9fafb; }
.sip-selected, .sip-selected:hover { background: #eef2ff; }
.sip-elapsed { position: absolute; top: 15px; left: 8px; font-size: 11px; color: #6b7280; font-variant-numeric: tabular-nums; }
.sip-arrow { position: absolute; top: 7px; height: 26px; text-align: center; border-bottom: 1px solid #6366f1; color: #6366f1; }
.sip-arrow:after { content: ''; position: absolute; right: -1px; bottom: -4px; border-left: 7px solid currentColor; border-top: 3px solid transparent; border-bottom: 3px solid transparent; }
.sip-reverse:after { right: auto; left: -1px; border-left: 0; border-right: 7px solid currentColor; }
.sip-arrow span { display: inline-block; max-width: 100%; padding: 0 4px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; background: white; color: #374151; }
.sip-selected .sip-arrow span { background: #eef2ff; color: #4338ca; }
.sip-failure { border-color: #b91c1c; color: #b91c1c; }
.sip-failure span { color: #b91c1c; }
.sip-pagination { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .5rem; border-top: 1px solid #e5e7eb; font-size: .75rem; }
.sip-metadata { flex-shrink: 0; padding: .75rem; overflow-wrap: anywhere; border-bottom: 1px solid #e5e7eb; color: #6b7280; font-size: .75rem; max-height: 9rem; overflow-y: auto; }
.sip-payload { flex: 1; min-height: 0; margin: 0; padding: 1rem; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; font: 12px/1.7 ui-monospace, SFMono-Regular, Consolas, monospace; color: #111827; background: white; }
.sip-footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; padding: .75rem 1rem; color: #6b7280; font-size: 11px; }
.sip-message-overlay { display: flex; flex-direction: column; width: 100%; max-width: 90rem; height: calc(100vh - 3rem); height: calc(100dvh - 3rem); min-height: 0; overflow: hidden; border: 1px solid #c7d2fe; border-radius: .5rem; background: white; color: #111827; box-shadow: 0 20px 50px #0004; }
@media (max-width: 639px) { .sip-workspace, .sip-message-overlay { height: calc(100vh - 1rem); height: calc(100dvh - 1rem); } .sip-header { padding: .75rem 3.5rem .75rem .75rem; } .sip-content { padding: 0 .5rem; } .sip-toolbar { padding: .75rem .5rem; } .sip-button { min-height: 40px; } }
</style>
