<template>
<div  class="overflow-hidden rounded-lg bg-white shadow">
    <div class="p-5">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <!-- Use the dynamic component with the computed property -->
          <component :is="iconComponent" class="h-6 w-6 text-gray-400" aria-hidden="true" />
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
}

// Computed property to get the correct icon component
const iconComponent = computed(() => iconMap[props.card.icon]);


</script>