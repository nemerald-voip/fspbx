<template>
    <Menu as="div" class="">
        <div>
            <MenuButton
                class="flex items-center rounded py-2 hover:bg-gray-200 text-gray-400  hover:text-gray-600 focus:outline-none focus:ring-1 focus:bg-gray-200 focus:ring-gray-300 transition duration-500 ease-in-out">
                <span class="sr-only">Open options</span>
                <ArrowDropDown class="h-5 w-5" aria-hidden="true" />
            </MenuButton>
        </div>

        <transition enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95">
            <MenuItems
                class="absolute z-20 shadow-2xl mt-1 -ml-4 origin-top-right divide-y divide-gray-100 rounded-md bg-white font-normal ring-1 ring-black ring-opacity-15 focus:outline-none">
                <div class="px-4 py-3">
                    <p class="text-sm font-semibold">Bulk Actions</p>
                </div>
                <div v-if="hasSelectedItems" class="py-1">
                    <MenuItem v-for="action in actions" :key="action.id" v-slot="{ active }">
                        <button @click="$emit('bulkAction', action.id)"
                            :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-500', getIconStyles(action.icon).bgColor, getIconStyles(action.icon).textColor, 'group flex items-center px-4 py-2 text-sm min-w-full']">
                            <component :is="getIconComponent(action.icon)" class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-500" aria-hidden="true" />
                            {{ action.label }}
                        </button>
                    </MenuItem>
                </div>
                <div v-else class="text-gray-500 italic group flex items-center px-4 py-2 text-sm min-w-full">
                    No items selected
                </div>
            </MenuItems>
        </transition>
    </Menu>
</template>

<script setup>
import { defineAsyncComponent,computed,onMounted } from 'vue'

import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import ArrowDropDown from "../icons/ArrowDropDown.vue"


const RestartIcon = defineAsyncComponent(() => import('../icons/RestartIcon.vue'));
const PencilSquareIcon = defineAsyncComponent(() => import('@heroicons/vue/20/solid/PencilSquareIcon'));
const TrashIcon = defineAsyncComponent(() => import('@heroicons/vue/20/solid/TrashIcon'));

// Define props to accept actions from the parent component
const props = defineProps({
    actions: Array,
    hasSelectedItems: Boolean,
});

// Map 
const iconMap = {
  RestartIcon,
  PencilSquareIcon,
  TrashIcon
};

const getIconComponent = (iconKey) => {
  return iconMap[iconKey];
};

const styleMap = {
    // ArrowDropDown: { bgColor: 'bg-teal-50', textColor: 'text-teal-700' },
};


const getIconStyles = (iconKey) => {
  return styleMap[iconKey] || { bgColor: 'default-bg', textColor: 'default-text' };
};
</script>