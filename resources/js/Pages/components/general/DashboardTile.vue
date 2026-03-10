<template>
    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="p-5">
            <a :href="card.href" class="group">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span
                            :class="[iconStyles.bgColor, iconStyles.textColor, iconStyles.hoverTextColor, 'inline-flex rounded-lg p-3 ring-4 ring-white']">
                            <component :is="iconComponent" class="h-6 w-6" aria-hidden="true" />
                        </span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="truncate text-lg font-medium text-gray-500 group-hover:text-gray-700">{{ card.name }}</dt>
                            <dd>
                                <div v-if="count === null" class="w-full">
                                    <div class="animate-pulse flex space-x-4 pt-2 w-4/12">
                                        <div class="flex-1 space-y-6 py-1">
                                            <div class="grid grid-cols-3 gap-4">
                                                <div class="h-2 bg-slate-300 rounded col-span-2"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-md font-medium text-gray-900">{{ count }}</div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </a>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="flex text-sm justify-between">
                <a :href="card.href" class="font-medium text-cyan-700 hover:text-cyan-900">View all</a>
                <a v-if="card.alt_href" :href="card.alt_href" class="font-medium text-cyan-700 hover:text-cyan-900">{{
                    card.alt_link_label }}</a>
            </div>
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

const props = defineProps({
    card: Object,
    count: {
        type: Number,
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

const iconStyles = computed(() => styleMap[props.card.icon] || { bgColor: 'default-bg', textColor: 'default-text' });


</script>