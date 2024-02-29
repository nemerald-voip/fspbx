<template>
    <div class="overflow-hidden rounded-lg bg-white shadow">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <span
                        :class="[iconStyles.bgColor, iconStyles.textColor, 'inline-flex rounded-lg p-3 ring-4 ring-white']">
                        <component :is="iconComponent" class="h-6 w-6" aria-hidden="true" />
                    </span>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="truncate text-lg font-medium text-gray-500">{{ card.name }}</dt>
                        <dd>
                            <div class="text-md font-medium text-gray-900">{{ card.amount }}</div>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                <a :href="card.href" class="font-medium text-cyan-700 hover:text-cyan-900">View all</a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    UsersIcon,
    UserGroupIcon,
    CalendarDaysIcon
} from "@heroicons/vue/24/solid";
import VoicemailIcon from "../icons/VoicemailIcon.vue"
import FaxIcon from "../icons/FaxIcon.vue"
import CallHistoryIcon from "../icons/CallHistoryIcon.vue"
import DevicesIcon from "../icons/DevicesIcon.vue"
import DialpadIcon from "../icons/DialpadIcon.vue"
import ContactPhoneIcon from "../icons/ContactPhoneIcon.vue"
import AlternativeRouteIcon from "../icons/AlternativeRouteIcon.vue"
import IvrIcon from "../icons/IvrIcon.vue"

const props = defineProps({
    card: Object,
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
}

const styleMap = {
    UsersIcon: { bgColor: 'bg-teal-50', textColor: 'text-teal-700' },
    UserGroupIcon: { bgColor: 'bg-purple-50', textColor: 'text-purple-700' },
    ContactPhoneIcon: { bgColor: 'bg-sky-50', textColor: 'text-sky-700' },
    VoicemailIcon: { bgColor: 'bg-fuchsia-50', textColor: 'text-fuchsia-700' },
    CalendarDaysIcon: { bgColor: 'bg-rose-50', textColor: 'text-rose-700' },
    FaxIcon: { bgColor: 'bg-indigo-50', textColor: 'text-indigo-700' },
    CallHistoryIcon: { bgColor: 'bg-red-50', textColor: 'text-red-700' },
    DevicesIcon: { bgColor: 'bg-lime-50', textColor: 'text-lime-700' },
    DialpadIcon: { bgColor: 'bg-green-50', textColor: 'text-green-700' },
    AlternativeRouteIcon: { bgColor: 'bg-cyan-50', textColor: 'text-cyan-700' },
    IvrIcon: { bgColor: 'bg-blue-50', textColor: 'text-blue-700' },
};

// Computed property to get the correct icon component
const iconComponent = computed(() => iconMap[props.card.icon]);

const iconStyles = computed(() => styleMap[props.card.icon] || { bgColor: 'default-bg', textColor: 'default-text' });


</script>