<template>
    <Menu as="div" class="">
        <div>
            <MenuButton
                class="flex items-center rounded py-2 hover:bg-gray-200 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-1 focus:bg-gray-200 focus:ring-gray-300 transition duration-500 ease-in-out">
                <span class="sr-only">Open options</span>
                <EllipsisVerticalIcon class="h-5 w-5" aria-hidden="true" />
            </MenuButton>
        </div>
        <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95">
            <MenuItems
                class="absolute right-0 z-10 mt-2.5 mr-2 origin-top-right rounded-md bg-white py-2 shadow-xl ring-1 ring-gray-900/5 focus:outline-none">
                <div v-for="(group, idx) in actions" :key="group.category">
                    <!-- Dynamic category name -->
                    <div class="px-4 py-3">
                        <p class="text-sm font-semibold">{{ group.category }}</p>
                    </div>
                    <!-- Actions -->
                    <div>
                        <MenuItem v-for="action in group.actions" :key="action.id" v-slot="{ active }">
                            <button
                                @click="$emit('advancedAction', action.id)"
                                :class="[
                                    'group flex items-center px-8 py-2 text-sm min-w-full transition',
                                    active ? 'bg-gray-100 text-gray-900' : 'text-gray-500'
                                ]"
                            >
                                <component :is="getIconComponent(action.icon)"
                                    class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-500"
                                    aria-hidden="true" />
                                {{ action.label }}
                            </button>
                        </MenuItem>
                    </div>
                    <div v-if="idx !== actions.length - 1" class="border-t border-gray-100 my-1"></div>
                </div>
            </MenuItems>
        </transition>
    </Menu>
</template>


<script setup>
import { defineAsyncComponent } from 'vue'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { EllipsisVerticalIcon } from "@heroicons/vue/24/outline";

const RestartIcon = defineAsyncComponent(() => import('../icons/RestartIcon.vue'));
const LinkOffIcon = defineAsyncComponent(() => import('../icons/LinkOffIcon.vue'));
const SyncIcon = defineAsyncComponent(() => import('../icons/SyncIcon.vue'));
const SupportAgent = defineAsyncComponent(() => import('../icons/SupportAgent.vue'));
const PencilSquareIcon = defineAsyncComponent(() => import('@heroicons/vue/20/solid/PencilSquareIcon'));
const UserPlusIcon = defineAsyncComponent(() => import('@heroicons/vue/24/outline/UserPlusIcon'));
const KeyIcon = defineAsyncComponent(() => import('@heroicons/vue/24/outline/KeyIcon'));
const TrashIcon = defineAsyncComponent(() => import('@heroicons/vue/20/solid/TrashIcon'));
const DocumentDuplicateIcon = defineAsyncComponent(() => import('@heroicons/vue/24/outline/DocumentDuplicateIcon'));


const props = defineProps({
    actions: Array, // array of {category, actions:[...]}
    hasSelectedItems: Boolean,
});

const iconMap = {
  RestartIcon,
  PencilSquareIcon,
  TrashIcon,
  LinkOffIcon,
  SyncIcon,
  UserPlusIcon,
  KeyIcon,
  SupportAgent,
  DocumentDuplicateIcon
};

const getIconComponent = (iconKey) => {
  return iconMap[iconKey];
};
</script>
