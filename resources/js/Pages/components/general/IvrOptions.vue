<template>
    <div class="grid grid-cols-12 gap-6">

        <div class="col-span-12">

            <div class=" overflow-x-auto ">
                <div class="inline-block min-w-full py-2 align-middle">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead>
                            <tr>
                                <th scope="col"
                                    class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6 lg:pl-8">
                                    Key</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Action
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Ext./Number
                                </th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Desc
                                </th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 lg:pr-8">
                                    <span class="sr-only">Edit</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(option, index) in keys" :key="index">
                                <td class=" py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 lg:pl-8">
                                    {{ keys[index].ivr_menu_option_digits }}</td>
                                <td class="px-3 py-4 text-sm text-gray-500">{{ keys[index].key_type }}</td>
                                <td class="px-3 py-4 text-sm text-gray-500">{{ keys[index].key_name }}</td>
                                <td class="px-3 py-4 text-sm text-gray-500">{{ keys[index].ivr_menu_option_description }}</td>
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
                                                    <a href="#" @click.prevent="removeRoutingOption(index)"
                                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block px-4 py-2 text-sm']">Delete</a>
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

        <!-- <div class="col-span-12 flex flex-col sm:flex-row gap-x-2 gap-y-1 flex-auto">

                <div class=" basis-1/12 text-sm font-medium leading-6 text-gray-900">
                    <InputField v-model="keys[index].ivr_menu_option_digits" type="text" name="ivr_menu_option_digits"
                        id="ivr_menu_option_digits" :error="!!errors?.ivr_menu_option_digits" />
                </div>

                <div class=" basis-4/12 text-sm font-medium leading-6 text-gray-900">
                    <ComboBox :options="routingTypes" :search="true" :placeholder="'Choose type'"
                        :selectedItem="keys[index].type"
                        @update:model-value="(value) => fetchRoutingTypeOptions(value, index)" />
                </div>

                <div v-if="keys[index].typeOptions" class=" basis-4/12 text-sm font-medium leading-6 text-gray-900">
                    <ComboBox :options="keys[index].typeOptions" :selectedItem="keys[index].option" :search="true"
                        :placeholder="'Choose option'" :key="keys[index].typeOptions.length + keys[index].option"
                        @update:model-value="(value) => updatekeys(value, index)" />
                </div>

                <div class=" basis-1/12 text-sm font-medium leading-6 text-gray-900">
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
                            enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
                            leave-active-class="transition ease-in duration-75"
                            leave-from-class="transform opacity-100 scale-100"
                            leave-to-class="transform opacity-0 scale-95">
                            <MenuItems
                                class="absolute right-0 z-10 mt-2 w-36 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <div class="py-1">
                                    <MenuItem v-slot="{ active }">
                                    <a href="#" @click.prevent="removeRoutingOption(index)"
                                        :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block px-4 py-2 text-sm']">Delete</a>
                                    </MenuItem>

                                </div>
                            </MenuItems>
                        </transition>
                    </Menu>

                </div>

            </div> -->





        <div
            class="col-span-full flex justify-center bg-gray-50 px-4 py-4 text-center text-sm font-medium text-indigo-500 hover:text-indigo-700 sm:rounded-b-lg">
            <button href="#" @click.prevent="addRoutingOption" class="flex items-center gap-2">
                <PlusIcon class="h-6 w-6 text-black-500 hover:text-black-900 active:h-8 active:w-8 " />
                <span>
                    Add new key
                </span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { PlusIcon } from "@heroicons/vue/24/solid";
import DataTable from "../general/DataTable.vue";
import TableColumnHeader from "../general/TableColumnHeader.vue";
import TableField from "../general/TableField.vue";
import Loading from "../general/Loading.vue";

import ComboBox from "../general/ComboBox.vue";
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { EllipsisVerticalIcon } from '@heroicons/vue/24/outline';
import InputField from "../general/InputField.vue";


const props = defineProps({
    modelValue: [Object, null],
    routingTypes: [Object, null],
    optionsUrl: String,
});


const people = [
    { name: 'Lindsay Walton', title: 'Front-end Developer', email: 'lindsay.walton@example.com', role: 'Member' },
    // More people...
]

const emit = defineEmits(['update:model-value'])

// Create a local reactive copy of the modelValue
const keys = ref([...props.modelValue]);


console.log(keys.value);

const loading = ref(false)

// Watch for changes to the modelValue from the parent and update local state
watch(() => props.modelValue, (newVal) => {
    keys.value = [...newVal];
});

// Initialize keys and fetch typeOptions
// if (props.selectedItems) {
//     props.selectedItems.forEach((item, index) => {
//         keys.value.push({
//             type: item.type || null,
//             typeOptions: [],  // Initially empty
//             option: item.option || null,
//             extension: item.extension || null,
//         });

//         // If type is available, fetch the options for that type
//         if (item.type) {
//             fetchTypeOptionsForItem(item.type, index);
//         }
//     });
// }


// Fetch new options for the selected type using Axios
function fetchRoutingTypeOptions(newValue, index) {

    keys.value[index].type = newValue.value;

    // Reset the selected option when type changes
    keys.value[index].option = null;

    axios.post(props.optionsUrl, { 'category': newValue.value })
        .then((response) => {
            // console.log(response.data);
            keys.value[index].typeOptions = response.data.options;
        }).catch((error) => {
            keys.value[index].typeOptions = null;
        });
}

function fetchTypeOptionsForItem(type, index) {
    axios.post(props.optionsUrl, { 'category': type })
        .then((response) => {
            keys.value[index].typeOptions = response.data.options;

            // Automatically set the selected option if the option exists in the fetched options
            const selectedOption = keys.value[index].option;
            if (selectedOption) {
                const match = response.data.options.find(option => option.value === selectedOption);
                if (match) {
                    keys.value[index].option = match.value;
                } else {
                    keys.value[index].option = null; // Reset if no match found
                }
            }
        }).catch(() => {
            keys.value[index].typeOptions = null;
            keys.value[index].option = null;  // Reset option in case of an error
        });
}

// Emit updates to the parent whenever routingOptions changes
const updateParent = () => {
    emit('update:modelValue', routingOptions.value);
};

// Function to modify routing options
const addRoutingOption = () => {
    routingOptions.value.push({ type: null, typeOptions: [], option: null });
    updateParent(); // Notify the parent about the changes
};

const removeRoutingOption = (index) => {
    routingOptions.value.splice(index, 1);
    updateParent();
};

const updateRoutingOption = (value, index) => {
    routingOptions.value[index] = value;
    updateParent();
};


// // Update keys and emit updated model value
// function updatekeys(newValue, index) {
//     keys.value[index].option = newValue.value;
//     keys.value[index].extension = newValue.extension;

//     // Prepare the updated options
//     const updatedOptions = keys.value.map(({ type, option, extension }) => {
//         return { type, option, extension };
//     });

//     emit('update:model-value', updatedOptions);
// }


// // Add a new routing option
// const addRoutingOption = () => {
//     keys.value.push({
//         type: null,
//         typeOptions: [],
//         option: null,
//     });
// };

// const removeRoutingOption = (index) => {
//     // console.log(keys.value);
//     keys.value.splice(index, 1);

//     // Reassign the array to force Vue to track reactivity properly
//     keys.value = [...keys.value];

//     const updatedOptions = keys.value.map(({ type, option }) => {
//         return { type, option };
//     });
//     // console.log(updatedOptions);
//     emit('update:model-value', updatedOptions);
// }

</script>
