<template>
    <div
        class="group relative flex h-full overflow-hidden rounded-lg bg-surface ring-1 ring-strong transition duration-150 hover:-translate-y-0.5 hover:shadow-md hover:ring-info/40">
        <div class="grid min-h-24 flex-1 grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-3 p-4">
            <a :href="card.href || '#'" @click="handleCardClick"
                class="row-span-2 min-w-0 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2 before:absolute before:inset-0 before:content-['']">
                <dl class="min-w-0">
                    <dt class="truncate text-base font-medium leading-5 text-muted group-hover:text-body">{{
                        card.name }}</dt>
                    <dd class="mt-3 min-h-8">
                        <div v-if="displayValue === null" class="animate-pulse">
                            <div class="h-7 w-14 rounded bg-surface-3"></div>
                        </div>
                        <div v-else class="text-3xl font-semibold leading-none tracking-tight text-body">{{ displayValue }}</div>
                    </dd>
                </dl>
            </a>

            <span
                :class="[iconStyles.bgColor, iconStyles.textColor, 'pointer-events-none inline-flex h-10 w-10 items-center justify-center justify-self-end rounded-lg ring-1 ring-inset ring-current/10 transition group-hover:scale-105']">
                <component :is="iconComponent" class="dashboard-tile-icon" aria-hidden="true" />
            </span>

            <a v-if="card.alt_href" :href="card.alt_href"
                class="relative z-10 self-end whitespace-nowrap rounded-md px-2 py-1 text-sm font-medium text-info transition hover:bg-info-subtle hover:underline focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2">
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
    UsersIcon: { bgColor: 'bg-teal-50 dark:bg-teal-900/40', textColor: 'text-teal-700 dark:text-teal-300', hoverTextColor: 'group-hover:text-teal-900 dark:group-hover:text-teal-300' },
    UserGroupIcon: { bgColor: 'bg-purple-50 dark:bg-purple-900/40', textColor: 'text-purple-700 dark:text-purple-300', hoverTextColor: 'group-hover:text-purple-900 dark:group-hover:text-purple-300' },
    ContactPhoneIcon: { bgColor: 'bg-sky-50 dark:bg-sky-900/40', textColor: 'text-sky-700 dark:text-sky-300', hoverTextColor: 'group-hover:text-sky-900 dark:group-hover:text-sky-300' },
    VoicemailIcon: { bgColor: 'bg-fuchsia-50 dark:bg-fuchsia-900/40', textColor: 'text-fuchsia-700 dark:text-fuchsia-300', hoverTextColor: 'group-hover:text-fuchsia-900 dark:group-hover:text-fuchsia-300' },
    CalendarDaysIcon: { bgColor: 'bg-rose-50 dark:bg-rose-900/40', textColor: 'text-rose-700 dark:text-rose-300', hoverTextColor: 'group-hover:text-rose-900 dark:group-hover:text-rose-300' },
    FaxIcon: { bgColor: 'bg-violet-50 dark:bg-violet-900/40', textColor: 'text-violet-700 dark:text-violet-300', hoverTextColor: 'group-hover:text-violet-900 dark:group-hover:text-violet-300' },
    CallHistoryIcon: { bgColor: 'bg-orange-50 dark:bg-orange-900/40', textColor: 'text-orange-700 dark:text-orange-300', hoverTextColor: 'group-hover:text-orange-900 dark:group-hover:text-orange-300' },
    DevicesIcon: { bgColor: 'bg-lime-50 dark:bg-lime-900/40', textColor: 'text-lime-700 dark:text-lime-300', hoverTextColor: 'group-hover:text-lime-900 dark:group-hover:text-lime-300' },
    DialpadIcon: { bgColor: 'bg-emerald-50 dark:bg-emerald-900/40', textColor: 'text-emerald-700 dark:text-emerald-300', hoverTextColor: 'group-hover:text-emerald-900 dark:group-hover:text-emerald-300' },
    AlternativeRouteIcon: { bgColor: 'bg-cyan-50 dark:bg-cyan-900/40', textColor: 'text-cyan-700 dark:text-cyan-300', hoverTextColor: 'group-hover:text-cyan-900 dark:group-hover:text-cyan-300' },
    IvrIcon: { bgColor: 'bg-blue-50 dark:bg-blue-900/40', textColor: 'text-blue-700 dark:text-blue-300', hoverTextColor: 'group-hover:text-blue-900 dark:group-hover:text-blue-300' },
    SupportAgent: { bgColor: 'bg-amber-50 dark:bg-amber-900/40', textColor: 'text-amber-700 dark:text-amber-300', hoverTextColor: 'group-hover:text-amber-900 dark:group-hover:text-amber-300' },
    HeartIcon: { bgColor: 'bg-pink-50 dark:bg-pink-900/40', textColor: 'text-pink-700 dark:text-pink-300', hoverTextColor: 'group-hover:text-pink-900 dark:group-hover:text-pink-300' },
    ClockIcon: { bgColor: 'bg-indigo-50 dark:bg-indigo-900/40', textColor: 'text-indigo-700 dark:text-indigo-300', hoverTextColor: 'group-hover:text-indigo-900 dark:group-hover:text-indigo-300' },
};

// Computed property to get the correct icon component
const iconComponent = computed(() => iconMap[props.card.icon]);

const iconStyles = computed(() => styleMap[props.card.icon] || { bgColor: 'bg-surface-2', textColor: 'text-body' });

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
