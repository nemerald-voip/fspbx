<template>
    <div
        class="group relative flex h-full overflow-hidden rounded-lg bg-white ring-1 ring-gray-200 transition duration-150 hover:-translate-y-0.5 hover:shadow-md hover:ring-cyan-200">
        <div class="grid min-h-24 flex-1 grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-3 p-4">
            <a :href="card.href || '#'" @click="handleCardClick"
                class="row-span-2 min-w-0 rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                <dl class="min-w-0">
                    <dt class="truncate text-base font-medium leading-5 text-gray-500 group-hover:text-gray-700">{{
                        card.name }}</dt>
                    <dd class="mt-3 min-h-8">
                        <div v-if="displayValue === null" class="animate-pulse">
                            <div class="h-7 w-14 rounded bg-slate-200"></div>
                        </div>
                        <div v-else class="text-3xl font-semibold leading-none tracking-tight text-gray-600">{{ displayValue }}</div>
                    </dd>
                </dl>
            </a>

            <span
                :class="[iconStyles.bgColor, iconStyles.textColor, 'inline-flex h-10 w-10 items-center justify-center justify-self-end rounded-lg ring-1 ring-inset ring-current/10 transition group-hover:scale-105']">
                <component :is="iconComponent" class="dashboard-tile-icon" aria-hidden="true" />
            </span>

            <a v-if="card.alt_href" :href="card.alt_href"
                class="self-end whitespace-nowrap rounded-md px-2 py-1 text-sm font-medium text-cyan-700 transition hover:bg-cyan-50 hover:text-cyan-900 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                {{ card.alt_link_label }}
            </a>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    UsersIcon,
    UserGroupIcon,
    CalendarDaysIcon,
    HeartIcon
} from "@heroicons/vue/24/solid";
import { ClockIcon } from "@heroicons/vue/24/outline";
import VoicemailIcon from "../icons/VoicemailIcon.vue"
import FaxIcon from "../icons/FaxIcon.vue"
import CallHistoryIcon from "../icons/CallHistoryIcon.vue"
import DevicesIcon from "../icons/DevicesIcon.vue"
import DialpadIcon from "../icons/DialpadIcon.vue"
import ContactPhoneIcon from "../icons/ContactPhoneIcon.vue"
import AlternativeRouteIcon from "../icons/AlternativeRouteIcon.vue"
import IvrIcon from "../icons/IvrIcon.vue"
import SupportAgent from "../icons/SupportAgent.vue"

const emit = defineEmits(['card-action']);

const props = defineProps({
    card: Object,
    count: {
        type: [Number, String],
        default: () => (null) // Providing an empty object as default
    }
})


// Map string keys to actual component objects
const iconMap = {
    UsersIcon: UsersIcon,
    UserGroupIcon: UserGroupIcon,
    VoicemailIcon: VoicemailIcon,
    CalendarDaysIcon: CalendarDaysIcon,
    FaxIcon: FaxIcon,
    CallHistoryIcon: CallHistoryIcon,
    DevicesIcon: DevicesIcon,
    DialpadIcon: DialpadIcon,
    ContactPhoneIcon: ContactPhoneIcon,
    AlternativeRouteIcon: AlternativeRouteIcon,
    IvrIcon: IvrIcon,
    SupportAgent: SupportAgent,
    HeartIcon: HeartIcon,
    ClockIcon: ClockIcon,
}

const styleMap = {
    UsersIcon: { bgColor: 'bg-teal-50', textColor: 'text-teal-700', hoverTextColor: 'group-hover:text-teal-900' },
    UserGroupIcon: { bgColor: 'bg-purple-50', textColor: 'text-purple-700', hoverTextColor: 'group-hover:text-purple-900' },
    ContactPhoneIcon: { bgColor: 'bg-sky-50', textColor: 'text-sky-700', hoverTextColor: 'group-hover:text-sky-900' },
    VoicemailIcon: { bgColor: 'bg-fuchsia-50', textColor: 'text-fuchsia-700', hoverTextColor: 'group-hover:text-fuchsia-900' },
    CalendarDaysIcon: { bgColor: 'bg-rose-50', textColor: 'text-rose-700', hoverTextColor: 'group-hover:text-rose-900' },
    FaxIcon: { bgColor: 'bg-indigo-50', textColor: 'text-indigo-700', hoverTextColor: 'group-hover:text-indigo-900' },
    CallHistoryIcon: { bgColor: 'bg-red-50', textColor: 'text-red-700', hoverTextColor: 'group-hover:text-red-900' },
    DevicesIcon: { bgColor: 'bg-lime-50', textColor: 'text-lime-700', hoverTextColor: 'group-hover:text-lime-900' },
    DialpadIcon: { bgColor: 'bg-green-50', textColor: 'text-green-700', hoverTextColor: 'group-hover:text-green-900' },
    AlternativeRouteIcon: { bgColor: 'bg-cyan-50', textColor: 'text-cyan-700', hoverTextColor: 'group-hover:text-cyan-900' },
    IvrIcon: { bgColor: 'bg-blue-50', textColor: 'text-blue-700', hoverTextColor: 'group-hover:text-blue-900' },
    SupportAgent: { bgColor: 'bg-orange-50', textColor: 'text-orange-700', hoverTextColor: 'group-hover:text-orange-900' },
    HeartIcon: { bgColor: 'bg-rose-50', textColor: 'text-rose-700', hoverTextColor: 'group-hover:text-rose-900' },
    ClockIcon: { bgColor: 'bg-blue-50', textColor: 'text-blue-700', hoverTextColor: 'group-hover:text-blue-900' },
};

// Computed property to get the correct icon component
const iconComponent = computed(() => iconMap[props.card.icon]);

const iconStyles = computed(() => styleMap[props.card.icon] || { bgColor: 'bg-gray-50', textColor: 'text-gray-700' });

const displayValue = computed(() => props.card.count_label ?? props.count ?? null);

const handleCardClick = (event) => {
    if (!props.card.action) {
        return;
    }

    event.preventDefault();
    emit('card-action', props.card);
};

</script>

<style scoped>
.dashboard-tile-icon,
:deep(.dashboard-tile-icon svg),
:deep(svg.dashboard-tile-icon) {
    width: 1.25rem;
    height: 1.25rem;
    display: block;
    flex-shrink: 0;
}
</style>
