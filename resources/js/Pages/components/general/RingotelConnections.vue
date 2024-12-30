<template>
    <div class="grid grid-cols-12 gap-6">

        <div class="col-span-12">

            <div class="overflow-visible">
                <div class="inline-block min-w-full py-2 align-middle">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead>
                            <tr>
                                <th scope="col"
                                    class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6 lg:pl-8">
                                    Name</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                                    Domain/Port
                                </th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 lg:pr-8">
                                    <span class="sr-only">Edit</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <!-- Check if connections array is empty -->
                            <tr v-if="connections.length === 0">
                                <td colspan="6" class="py-4 text-center text-sm italic text-gray-500">
                                    No connections available.
                                </td>
                            </tr>
                            <tr v-for="(option, index) in connections" :key="index">
                                <td class=" py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8">
                                    {{ option.connection_name }}</td>
                                <td class="px-3 py-4 text-sm text-gray-500">{{ option.domain }}</td>
                                <td class="relative py-2 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 lg:pr-8">
                                    <Menu as="div" class="relative inline-block text-left">
                                        <div>
                                            <MenuButton
                                                class="flex items-center rounded-full bg-gray-100 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-100">
                                                <span class="sr-only">Open options</span>
                                                <EllipsisVerticalIcon
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-500 hover:bg-gray-200 hover:text-gray-900 active:bg-gray-300 active:duration-150 cursor-pointer"
                                                    aria-hidden="true" />
                                            </MenuButton>
                                        </div>

                                        <transition enter-active-class="transition ease-out duration-100"
                                            enter-from-class="transform opacity-0 scale-95"
                                            enter-to-class="transform opacity-100 scale-100"
                                            leave-active-class="transition ease-in duration-75"
                                            leave-from-class="transform opacity-100 scale-100"
                                            leave-to-class="transform opacity-0 scale-95">
                                            <MenuItems
                                                class="absolute right-0 z-10 mt-2 w-36 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                                <div class="py-1">
                                                    <MenuItem v-slot="{ active }">
                                                    <a href="#" @click.prevent="handleEdit(option)"
                                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block px-4 py-2 text-sm']">Edit</a>
                                                    </MenuItem>
                                                    <MenuItem v-slot="{ active }">
                                                    <a href="#" @click.prevent="handleDelete(option)"
                                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'flex px-4 py-2 text-sm']">
                                                        Delete
                                                        <Spinner class="ml-1" :show="isDeleting" />
                                                    </a>
                                                    </MenuItem>

                                                </div>
                                            </MenuItems>
                                        </transition>
                                    </Menu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div
            class="col-span-full flex justify-center bg-gray-100 px-4 py-4 text-center text-sm font-medium text-indigo-500 hover:text-indigo-700 sm:rounded-b-lg">
            <button href="#" @click.prevent="handleAddConnection" class="flex items-center gap-2">
                <PlusIcon class="h-6 w-6 text-black-500 hover:text-black-900 active:h-8 active:w-8 " />
                <span>
                    Add new connection
                </span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { PlusIcon } from "@heroicons/vue/24/solid";
import Toggle from "@generalComponents/Toggle.vue";

import ComboBox from "../general/ComboBox.vue";
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { EllipsisVerticalIcon } from '@heroicons/vue/24/outline';
import InputField from "../general/InputField.vue";
import Spinner from "../general/Spinner.vue";


const props = defineProps({
    modelValue: [Object, null],
    routingTypes: [Object, null],
    optionsUrl: String,
    isDeleting: Boolean,
});


const emit = defineEmits(['update:model-value', 'add-connection', 'edit-connection', 'delete-connection'])

// Create a local reactive copy of the modelValue
const connections = ref([...props.modelValue]);


const loading = ref(false)

// Watch for changes to the modelValue from the parent and update local state
watch(
    () => props.modelValue,
    (newVal) => {
        connections.value = newVal; // Directly bind to the new value
    },
    { deep: true } // Ensure it watches for nested changes
);

const handleAddConnection = () => emit('add-connection');

const handleEdit = (connection) => {
    emit('edit-connection', connection); // Emit the edit event with
};

const handleDelete = (connection) => {
    emit('delete-connection', connection); // Emit the delete event
};


// Emit updates to the parent whenever routingOptions changes
const updateParent = () => {
    emit('update:modelValue', routingOptions.value);
};


</script>
