<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto ">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            :class="['relative w-full transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all sm:my-8', customClass]">
                            <button type="button"
                                class="absolute right-3 top-3 rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 sm:right-5 sm:top-6"
                                @click="emit('close')">
                                <span class="sr-only">{{ $t('Close') }}</span>
                                <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                            </button>

                            <div v-if="loading" class="p-10">
                                <Loading :show="loading" :absolute="false" />
                            </div>

                            <template v-else-if="item">
                                <!-- Who, when, outcome and actions -->
                                <div class="flex flex-wrap items-start gap-4 px-4 pb-5 pr-14 pt-5 sm:px-8 sm:pr-16 sm:pt-7">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                        <component :is="directionIcon" class="h-5 w-5" aria-hidden="true" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-500">{{ headerContext }}</p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <DialogTitle as="h2"
                                                class="flex flex-wrap items-center gap-x-2.5 text-xl font-semibold tabular-nums text-gray-900">
                                                <span>{{ item.caller_id_number_formatted || item.caller_id_number || '—' }}</span>
                                                <ArrowRightIcon class="h-4 w-4 text-gray-400" aria-hidden="true" />
                                                <span>{{ item.caller_destination_formatted || item.destination_number || '—' }}</span>
                                            </DialogTitle>
                                            <span
                                                :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-sm font-medium', statusTone.badge]">
                                                <span :class="['h-1.5 w-1.5 rounded-full', statusTone.dot]" aria-hidden="true" />
                                                {{ statusLabel(item.status) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button v-if="canPlayRecording && hasRecording" type="button"
                                            class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                            @click="showRecording = true">
                                            <PlayIcon class="h-4 w-4" aria-hidden="true" />
                                            {{ $t('Play recording') }}
                                        </button>
                                        <SipTracePanel v-if="show && canViewPcap && item.xml_cdr_uuid" :key="item.xml_cdr_uuid"
                                            :cdr-uuid="item.xml_cdr_uuid" :route="pcapRoute" :call="item" />
                                    </div>
                                </div>

                                <!-- Key numbers -->
                                <dl
                                    class="mx-4 grid grid-cols-2 gap-x-6 gap-y-4 border-y border-gray-200 py-4 sm:mx-8 sm:flex sm:gap-0 sm:divide-x sm:divide-gray-200">
                                    <div v-for="stat in summaryStats" :key="stat.label" class="sm:flex-1 sm:px-5 sm:first:pl-0">
                                        <dt class="text-xs font-medium text-gray-500">{{ stat.label }}</dt>
                                        <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ stat.value }}</dd>
                                    </div>
                                    <div v-if="answeredBy" class="col-span-2 sm:flex-[1.7_1_0%] sm:px-5">
                                        <dt class="text-xs font-medium text-gray-500">{{ $t('Answered by') }}</dt>
                                        <dd class="mt-1 text-base font-semibold leading-7 text-gray-900">
                                            {{ answeredBy.name }}
                                            <span v-if="answeredBy.via" class="text-sm font-medium text-gray-500">· {{ answeredBy.via }}</span>
                                        </dd>
                                    </div>
                                </dl>

                                <!-- Where the time went -->
                                <div v-if="journey" class="px-4 pb-5 pt-5 sm:px-8">
                                    <div class="flex h-2.5 gap-[3px]" aria-hidden="true">
                                        <div v-for="(segment, index) in journey.segments" :key="index"
                                            :class="['rounded-sm first:rounded-l-full last:rounded-r-full', journeyColors[segment.kind]]"
                                            :style="{ flex: `${segment.seconds} 1 0%` }" />
                                    </div>
                                    <div class="mt-2.5 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-xs">
                                        <ul role="list" class="flex flex-wrap gap-x-4 gap-y-1 text-gray-700">
                                            <li v-for="part in journey.legend" :key="part.kind" class="inline-flex items-center gap-1.5">
                                                <span :class="['h-2 w-2 rounded-sm', journeyColors[part.kind]]" aria-hidden="true" />
                                                {{ part.label }}
                                                <span class="text-gray-500">{{ formatDuration(part.seconds) }}</span>
                                            </li>
                                        </ul>
                                        <span class="tabular-nums text-gray-500">0:00 – {{ formatOffset(journey.total) }}</span>
                                    </div>
                                </div>

                                <div :class="['grid border-gray-200 md:grid-cols-5', { 'border-t': journey }]">
                                    <!-- Timeline -->
                                    <section aria-labelledby="call-timeline-title"
                                        class="px-4 py-6 sm:px-8 md:col-span-3 md:border-r md:border-gray-200">
                                        <h3 id="call-timeline-title" class="text-sm font-semibold text-gray-900">{{ $t('Timeline') }}</h3>
                                        <ol role="list" class="mt-4">
                                            <li v-for="(step, index) in timelineSteps" :key="index"
                                                class="grid grid-cols-[2.75rem_1.75rem_minmax(0,1fr)_auto] gap-x-3">
                                                <div class="pt-1 text-right text-sm font-semibold tabular-nums text-gray-900">{{ step.offset }}</div>
                                                <div class="flex flex-col items-center">
                                                    <div :class="['flex h-7 w-7 shrink-0 items-center justify-center rounded-full', stepTones[step.tone]]">
                                                        <component :is="step.icon" class="h-4 w-4" aria-hidden="true" />
                                                    </div>
                                                    <div class="mt-1 w-0.5 flex-1 bg-gray-200" aria-hidden="true" />
                                                </div>
                                                <div class="min-w-0 pb-6 pt-0.5">
                                                    <p v-if="step.label" class="text-xs font-medium text-gray-500">{{ step.label }}</p>
                                                    <p class="break-words text-sm font-semibold text-gray-900">{{ step.title }}</p>
                                                    <p v-if="step.outcome" :class="['mt-0.5 text-sm font-semibold', step.outcome.class]">
                                                        {{ step.outcome.text }}</p>
                                                    <p v-for="(note, noteIndex) in step.notes" :key="noteIndex" class="mt-0.5 text-sm text-gray-500">
                                                        {{ note }}</p>
                                                </div>
                                                <div class="pt-1 text-sm tabular-nums text-gray-600">{{ step.duration }}</div>
                                            </li>

                                            <li class="grid grid-cols-[2.75rem_1.75rem_minmax(0,1fr)_auto] gap-x-3">
                                                <div class="pt-1 text-right text-sm font-semibold tabular-nums text-gray-900">
                                                    {{ formatOffset(item.duration > 0 ? Number(item.duration) : null) }}</div>
                                                <div class="flex flex-col items-center">
                                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-600">
                                                        <CallEndIcon class="h-4 w-4" aria-hidden="true" />
                                                    </div>
                                                </div>
                                                <div class="min-w-0 pt-1">
                                                    <p class="text-sm font-semibold text-gray-900">{{ $t('End of the call') }}</p>
                                                    <p v-if="item.call_disposition" class="mt-0.5 text-sm text-gray-500">{{ item.call_disposition }}</p>
                                                </div>
                                                <div></div>
                                            </li>
                                        </ol>
                                    </section>

                                    <!-- Details -->
                                    <section aria-labelledby="call-details-title"
                                        class="border-t border-gray-200 px-4 py-6 sm:px-8 md:col-span-2 md:border-t-0">
                                        <h3 id="call-details-title" class="text-sm font-semibold text-gray-900">{{ $t('Details') }}</h3>
                                        <dl class="mt-4 grid grid-cols-[7rem_minmax(0,1fr)] gap-x-4 gap-y-3 text-sm">
                                            <template v-for="row in detailRows" :key="row.label">
                                                <dt class="text-gray-500">{{ row.label }}</dt>
                                                <dd class="break-words text-gray-900">
                                                    <span :class="row.mono ? 'font-mono text-xs' : 'tabular-nums'">{{ row.value }}</span>
                                                    <span v-if="row.extra" class="text-gray-500"> ({{ row.extra }})</span>
                                                </dd>
                                            </template>
                                        </dl>
                                    </section>
                                </div>

                                <!-- Identifiers -->
                                <div class="flex flex-wrap items-center gap-x-8 gap-y-1 border-t border-gray-200 bg-gray-50 px-4 py-2.5 sm:px-8">
                                    <div v-for="identifier in identifiers" :key="identifier.label"
                                        class="flex min-w-0 max-w-full items-center gap-2">
                                        <span class="shrink-0 text-xs font-medium text-gray-500">{{ identifier.label }}</span>
                                        <code class="min-w-0 truncate font-mono text-xs text-gray-800" :title="identifier.value">{{ identifier.value || '—' }}</code>
                                        <button v-if="identifier.value" type="button"
                                            class="shrink-0 rounded p-1.5 text-gray-500 hover:bg-gray-200 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                            :title="$t('Copy to clipboard')" @click="handleCopyToClipboard(identifier.value)">
                                            <span class="sr-only">{{ $t('Copy to clipboard') }}</span>
                                            <ClipboardDocumentIcon class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                </div>

                                <CallRecordingModal v-if="canPlayRecording && hasRecording" :show="showRecording"
                                    :cdr_uuid="item.xml_cdr_uuid" :routes="routes" @close="showRecording = false"
                                    @error="(error) => emit('error', error)" @success="(...args) => emit('success', ...args)" />
                            </template>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { trans } from "@i18n";
import { XMarkIcon, PlayIcon, ArrowRightIcon } from "@heroicons/vue/24/solid";
import Loading from "../general/Loading.vue";

import PhoneOutgoingIcon from "../icons/PhoneOutgoingIcon.vue"
import PhoneIncomingIcon from "../icons/PhoneIncomingIcon.vue"
import SipTracePanel from "../SipTracePanel.vue"
import CallRecordingModal from "./CallRecordingModal.vue"
import PhoneLocalIcon from "../icons/PhoneLocalIcon.vue"
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";

import {
    UserGroupIcon,
    CalendarDaysIcon
} from "@heroicons/vue/24/solid";

import ContactPhoneIcon from "../icons/ContactPhoneIcon.vue"
import DialpadIcon from "../icons/DialpadIcon.vue"
import AlternativeRouteIcon from "../icons/AlternativeRouteIcon.vue"
import IvrIcon from "../icons/IvrIcon.vue"
import SupportAgent from "../icons/SupportAgent.vue"
import CallEndIcon from "../icons/CallEndIcon.vue"
import VoicemailIcon from "../icons/VoicemailIcon.vue"
import FaxIcon from "../icons/FaxIcon.vue"
import ParkIcon from "../icons/ParkIcon.vue"
import MergeIcon from "../icons/MergeIcon.vue"

const emit = defineEmits(['close', 'success', 'error'])

const props = defineProps({
    item: Object,
    canViewPcap: { type: Boolean, default: false },
    canPlayRecording: { type: Boolean, default: false },
    pcapRoute: String,
    routes: Object,
    show: Boolean,
    loading: Boolean,
    customClass: {
        type: String,
        default: 'sm:max-w-lg'
    },
});

const showRecording = ref(false);

watch(() => [props.show, props.item?.xml_cdr_uuid], () => {
    showRecording.value = false;
});

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', 'success', { message: [trans('Copied to clipboard.')] });
    }).catch((error) => {
        // Handle the error case
        emit('error', { response: { data: { errors: { request: [trans('Failed to copy to clipboard.')] } } } });
    });
}

function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

const directionHeading = (direction) => {
    switch (direction) {
        case 'outbound':
            return trans('Outbound Call');
        case 'inbound':
            return trans('Inbound Call');
        case 'local':
            return trans('Local Call');
        default:
            return capitalizeFirstLetter(direction) + ' Call';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'answered':
            return trans('Answered');
        case 'no_answer':
            return trans('No Answer');
        case 'cancelled':
            return trans('Cancelled');
        case 'voicemail':
            return trans('Voicemail');
        case 'missed call':
            return trans('Missed Call');
        case 'abandoned':
            return trans('Abandoned');
        case 'queue_exited':
            return trans('Exited queue');
        case 'queue_timeout':
            return trans('Timed out');
        case 'queue_other':
            return trans('Other queue outcome');
        case 'callback_requested':
            return trans('Callback requested');
        case 'failed':
            return trans('Failed');
        default:
            return status;
    }
};

// Lengths read "7 s" / "1 min 20 s"; positions on the timeline read "0:07".
const formatDuration = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    const seconds = Math.round(Number(value));
    if (!Number.isFinite(seconds) || seconds < 0) return '—';
    if (seconds < 60) return trans(':count s', { count: seconds });
    if (seconds < 3600) {
        return trans(':minutes min :seconds s', { minutes: Math.floor(seconds / 60), seconds: seconds % 60 });
    }
    return trans(':hours h :minutes min', { hours: Math.floor(seconds / 3600), minutes: Math.floor((seconds % 3600) / 60) });
};

const formatOffset = (seconds) => {
    if (seconds === null || seconds === undefined) return '--:--';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = String(seconds % 60).padStart(2, '0');
    return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`;
};

// time_line is the server's "MM:SS" offset from call start, or "--:--" when unknown.
const parseOffset = (value) => {
    const match = /^(\d+):(\d{2})$/.exec(String(value ?? ''));
    return match ? Number(match[1]) * 60 + Number(match[2]) : null;
};

const isBridged = (flow) => Boolean(flow.bridged_time) && flow.bridged_time != 0;

const isAnsweredStep = (flow) =>
    ['extension', 'misc_destination', 'call_intercept'].includes(flow.dialplan_app_type) && isBridged(flow);

const numberLabel = (flow) => flow.destination_number_formatted || flow.destination_number;

const nameWithNumber = (flow) => {
    const name = flow.dialplan_name;
    const number = numberLabel(flow);
    if (name && name !== flow.destination_number && name !== number) {
        return number ? `${name} (${number})` : name;
    }
    return number || name || '—';
};

const hasRecording = computed(() =>
    Boolean((props.item?.record_name && props.item?.record_path) || props.item?.record_path === 'S3'));

const directionIcon = computed(() => {
    if (props.item?.callback_timeline) return SupportAgent;
    switch (props.item?.direction) {
        case 'inbound':
            return PhoneIncomingIcon;
        case 'outbound':
            return PhoneOutgoingIcon;
        default:
            return PhoneLocalIcon;
    }
});

const headerContext = computed(() => [
    directionHeading(props.item?.direction),
    props.item?.callback_timeline ? trans('Callback') : null,
    props.item?.start_date ? trans(':date at :time', { date: props.item.start_date, time: props.item.start_time }) : null,
].filter(Boolean).join(' · '));

const statusTone = computed(() => {
    switch (props.item?.status) {
        case 'answered':
            return { badge: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' };
        case 'voicemail':
            return { badge: 'bg-violet-50 text-violet-700', dot: 'bg-violet-500' };
        case 'callback_requested':
            return { badge: 'bg-blue-50 text-blue-700', dot: 'bg-blue-500' };
        case 'missed call':
        case 'no_answer':
        case 'abandoned':
        case 'queue_timeout':
        case 'failed':
            return { badge: 'bg-red-50 text-red-700', dot: 'bg-red-500' };
        default:
            return { badge: 'bg-gray-100 text-gray-700', dot: 'bg-gray-400' };
    }
});

const summaryStats = computed(() => {
    const start = Number(props.item?.start_epoch);
    const answer = Number(props.item?.answer_epoch);
    return [
        { label: trans('Waiting time'), value: formatDuration(start > 0 && answer > 0 ? answer - start : null) },
        { label: trans('In-call duration'), value: formatDuration(props.item?.billsec) },
        { label: trans('Total duration'), value: formatDuration(props.item?.duration) },
    ];
});

const answeredBy = computed(() => {
    if (props.item?.callback_timeline) return null;
    const flow = (props.item?.call_flow ?? []).find(isAnsweredStep);
    if (!flow) return null;
    return {
        name: flow.dialplan_app_type === 'call_intercept' ? (flow.dialplan_name || numberLabel(flow)) : nameWithNumber(flow),
        via: flow.dialplan_app_type === 'call_intercept' ? flow.dialplan_app : null,
    };
});

const stepTones = {
    neutral: 'bg-gray-100 text-gray-600',
    ringing: 'bg-amber-50 text-amber-700',
    answered: 'bg-emerald-50 text-emerald-700 ring-2 ring-emerald-200',
    voicemail: 'bg-violet-50 text-violet-700',
};

const stepIcons = {
    inbound_call: DialpadIcon,
    outbound_call: DialpadIcon,
    extension: ContactPhoneIcon,
    misc_destination: ContactPhoneIcon,
    ring_group: UserGroupIcon,
    auto_receptionist: IvrIcon,
    voicemail: VoicemailIcon,
    schedule: CalendarDaysIcon,
    virtual_fax: FaxIcon,
    contact_center_queue: SupportAgent,
    call_flow: AlternativeRouteIcon,
    park: ParkIcon,
    call_intercept: MergeIcon,
};

const answeredOutcome = () => ({ text: trans('Answered'), class: 'text-emerald-700' });

const stepView = (flow) => {
    const type = flow.dialplan_app_type;
    const step = {
        icon: stepIcons[type] ?? ContactPhoneIcon,
        tone: 'neutral',
        label: flow.dialplan_app,
        title: nameWithNumber(flow),
        outcome: null,
        notes: [],
        offset: formatOffset(parseOffset(flow.time_line)),
        duration: formatDuration(flow.duration_seconds),
    };

    switch (type) {
        case 'callback_agent':
        case 'callback_customer':
            step.icon = flow.cc_callback_role === 'agent' ? SupportAgent : PhoneOutgoingIcon;
            if (flow.status === 'answered') step.tone = 'answered';
            step.notes.push(trans('Result: :disposition', { disposition: flow.status_label }));
            if (flow.waitsec > 0) step.notes.push(`${trans('Waiting time')}: ${formatDuration(flow.waitsec)}`);
            step.notes.push(`${trans('In-call duration')}: ${formatDuration(flow.billsec)}`);
            break;
        case 'inbound_call':
        case 'outbound_call':
            step.title = numberLabel(flow) || '—';
            // The phone number's own description, e.g. "Main line".
            if (type === 'inbound_call' && flow.dialplan_description) step.notes.push(flow.dialplan_description);
            if (flow.comment) step.notes.push(flow.comment);
            break;
        case 'extension':
        case 'misc_destination':
            if (isBridged(flow)) {
                step.tone = 'answered';
                step.outcome = answeredOutcome();
            } else if (type === 'extension') {
                step.tone = 'ringing';
            }
            if (flow.call_disposition) {
                step.notes.push(trans('Result: :disposition', { disposition: flow.call_disposition }));
            }
            break;
        case 'ring_group':
            step.tone = 'ringing';
            break;
        case 'voicemail':
            step.tone = 'voicemail';
            step.title = trans('Voicemail :name', { name: flow.dialplan_name });
            if (flow.voicemail_message === true) {
                step.outcome = { text: trans('The caller left a message'), class: 'text-violet-700' };
            } else if (flow.voicemail_message === false) {
                step.notes.push(trans('The caller did not leave a message'));
            } else {
                step.notes.push(trans('Message status unknown'));
            }
            break;
        case 'contact_center_queue':
            step.notes.push(trans('Queue outcome: :outcome', { outcome: flow.queue_result || trans('Unknown') }));
            if (flow.queue_reason) step.notes.push(trans('Reason: :reason', { reason: flow.queue_reason }));
            break;
        case 'call_intercept':
            step.title = flow.dialplan_name || numberLabel(flow) || '—';
            if (isBridged(flow)) {
                step.tone = 'answered';
                step.outcome = answeredOutcome();
            }
            if (flow.dialplan_description) step.notes.push(flow.dialplan_description);
            break;
    }

    return step;
};

const timelineSteps = computed(() => (props.item?.call_flow ?? []).map(stepView));

const journeyColors = {
    routing: 'bg-gray-300',
    ringing: 'bg-amber-400',
    waiting: 'bg-sky-300',
    talking: 'bg-blue-600',
    voicemail: 'bg-violet-500',
};

const journeyKind = (flow) => {
    if (isAnsweredStep(flow)) return 'talking';
    switch (flow.dialplan_app_type) {
        case 'extension':
        case 'ring_group':
            return 'ringing';
        case 'contact_center_queue':
        case 'park':
            return 'waiting';
        case 'voicemail':
            return 'voicemail';
        default:
            return 'routing';
    }
};

// Each step owns the time until the next step starts, so simultaneous legs
// (ring group members, related channels) never count the same second twice.
const journey = computed(() => {
    const item = props.item;
    if (!item || item.callback_timeline || !['inbound', 'local'].includes(item.direction)) return null;
    const total = Number(item.duration);
    if (!(total > 0)) return null;

    const steps = (item.call_flow ?? [])
        .map((flow) => ({ kind: journeyKind(flow), at: parseOffset(flow.time_line) }))
        .filter((step) => step.at !== null && step.at < total);
    if (!steps.length) return null;

    const start = Number(item.start_epoch);
    const answer = Number(item.answer_epoch);
    const answeredAt = start > 0 && answer > 0 ? answer - start : null;
    const segments = [];
    const add = (kind, from, until) => {
        if (until <= from) return;
        const last = segments[segments.length - 1];
        if (last && last.kind === kind) {
            last.seconds += until - from;
        } else {
            segments.push({ kind, seconds: until - from });
        }
    };

    add('routing', 0, steps[0].at);
    let talked = false;
    steps.forEach((step, index) => {
        const until = index + 1 < steps.length ? steps[index + 1].at : total;
        // A phone that rang and was then answered spends its first part ringing.
        if (step.kind === 'talking' && !talked && answeredAt !== null && answeredAt > step.at && answeredAt < until) {
            add('ringing', step.at, answeredAt);
            add('talking', answeredAt, until);
        } else {
            add(step.kind, step.at, until);
        }
        if (step.kind === 'talking') talked = true;
    });

    const labels = {
        routing: trans('Routing'),
        ringing: trans('Ringing'),
        waiting: trans('Waiting'),
        talking: trans('Talking'),
        voicemail: trans('Voicemail'),
    };
    const legend = [];
    segments.forEach((segment) => {
        const part = legend.find((entry) => entry.kind === segment.kind);
        if (part) {
            part.seconds += segment.seconds;
        } else {
            legend.push({ kind: segment.kind, label: labels[segment.kind], seconds: segment.seconds });
        }
    });

    return { segments, legend, total };
});

const detailRows = computed(() => {
    const item = props.item ?? {};
    const rows = [];
    const callerName = item.caller_id_name_formatted;
    if (callerName && callerName !== item.caller_id_number && callerName !== item.caller_id_number_formatted) {
        rows.push({ label: trans('Caller ID Name'), value: callerName });
    }
    if (item.start_time) rows.push({ label: trans('Started'), value: item.start_time });
    if (item.answer_time) rows.push({ label: trans('Answered'), value: item.answer_time });
    if (item.end_time) rows.push({ label: trans('Ended'), value: item.end_time });
    if (item.hangup_cause) {
        rows.push({ label: trans('Hangup cause'), value: item.hangup_cause, extra: item.hangup_cause_q850, mono: true });
    }
    return rows;
});

const identifiers = computed(() => [
    { label: trans('SIP Call-ID:'), value: props.item?.sip_call_id },
    { label: trans('Unique ID:'), value: props.item?.xml_cdr_uuid },
]);

</script>
