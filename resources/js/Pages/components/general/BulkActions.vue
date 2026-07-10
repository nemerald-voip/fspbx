<template>
    <Menu as="div" class="">
        <div>
            <MenuButton
                class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-xs ring-1 ring-strong ring-inset hover:bg-surface-2">
                Bulk Actions
                <ChevronDownIcon class="-mr-1 size-5 text-subtle" aria-hidden="true" />
            </MenuButton>
        </div>

        <transition enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95">
            <MenuItems
                class="absolute z-20 shadow-2xl mt-1 -ml-4 origin-top-right divide-y divide-default rounded-md bg-surface font-normal ring-1 ring-black/[0.15] dark:ring-white/10 focus:outline-none">
                <div class="px-4 py-3">
                    <p class="text-sm font-semibold">Bulk Actions</p>
                </div>
                <div v-if="hasSelectedItems" class="py-1">
                    <MenuItem v-for="action in actions" :key="action.id" v-slot="{ active }">
                    <button @click="$emit('bulk-action', action.id)"
                        :class="[active ? 'bg-surface-3 text-heading' : 'text-muted', getIconStyles(action.icon).bgColor, getIconStyles(action.icon).textColor, 'group flex items-center px-4 py-2 text-sm min-w-full']">
                        <component :is="getIconComponent(action.icon)"
                            class="mr-3 h-5 w-5 text-subtle group-hover:text-muted" aria-hidden="true" />
                        {{ action.label }}
                    </button>
                    </MenuItem>
                </div>
                <div v-else class="text-muted italic group flex items-center px-4 py-2 text-sm min-w-full">
                    No items selected
                </div>
            </MenuItems>
        </transition>
    </Menu>
</template>

<script setup>
import { defineAsyncComponent } from 'vue'

import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import {ChevronDownIcon} from '@heroicons/vue/20/solid'


const RestartIcon = defineAsyncComponent(() => import('../icons/RestartIcon.vue'));
const LinkOffIcon = defineAsyncComponent(() => import('../icons/LinkOffIcon.vue'));
const SyncIcon = defineAsyncComponent(() => import('../icons/SyncIcon.vue'));
const PencilSquareIcon = defineAsyncComponent(() => import('@heroicons/vue/20/solid/PencilSquareIcon'));
const PlayIcon = defineAsyncComponent(() => import('@heroicons/vue/20/solid/PlayIcon'));
const StopIcon = defineAsyncComponent(() => import('@heroicons/vue/20/solid/StopIcon'));
const TrashIcon = defineAsyncComponent(() => import('@heroicons/vue/24/solid/TrashIcon'));
const EnvelopeIcon = defineAsyncComponent(() => import('@heroicons/vue/24/solid/EnvelopeIcon'));
const EnvelopeOpenIcon = defineAsyncComponent(() => import('@heroicons/vue/24/solid/EnvelopeOpenIcon'));
const DocumentDuplicateIcon = defineAsyncComponent(() => import('@heroicons/vue/24/outline/DocumentDuplicateIcon'));

// Define props to accept actions from the parent component
const props = defineProps({
    actions: Array,
    hasSelectedItems: Boolean,
});

// Map 
const iconMap = {
    RestartIcon,
    PencilSquareIcon,
    PlayIcon,
    StopIcon,
    TrashIcon,
    LinkOffIcon,
    SyncIcon,
    EnvelopeIcon,
    EnvelopeOpenIcon,
    DocumentDuplicateIcon,
};

const getIconComponent = (iconKey) => {
    return iconMap[iconKey];
};

const styleMap = {
    // ArrowDropDown: { bgColor: 'bg-teal-50 dark:bg-teal-900/40', textColor: 'text-teal-700 dark:text-teal-300' },
};


const getIconStyles = (iconKey) => {
    return styleMap[iconKey] || { bgColor: 'default-bg', textColor: 'default-text' };
};
</script>
