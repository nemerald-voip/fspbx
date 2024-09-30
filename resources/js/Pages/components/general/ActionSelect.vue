<template>
    <div class="grid grid-cols-12 gap-6">
        <template v-for="(option, index) in routingOptions" :key="index">
            <div class="pt-2 text-sm font-medium leading-6 text-gray-900">
                {{ index + 1 }}
            </div>

            <div class="col-span-10 flex flex-col sm:flex-row gap-x-2 gap-y-1 justify-between flex-auto">
                <div class=" basis-2/4 text-sm font-medium leading-6 text-gray-900">
                    <ComboBox :options="routingTypes" :search="true" :placeholder="'Choose type'"
                        :selectedItem="routingOptions[index].type"
                        @update:model-value="(value) => fetchRoutingTypeOptions(value, index)" />
                </div>

                <div v-if="routingOptions[index].typeOptions" 
                    class=" basis-2/4 text-sm font-medium leading-6 text-gray-900">
                    <ComboBox :options="routingOptions[index].typeOptions" :selectedItem="routingOptions[index].option"
                        :search="true" :placeholder="'Choose option'"   :key="routingOptions[index].typeOptions.length + routingOptions[index].option" 
                        @update:model-value="(value) => updateRoutingOptions(value, index)" />
                </div>

            </div>


            <div class="text-sm font-medium leading-6 text-gray-900">
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
                        leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
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

        </template>

        <div v-if="routingOptions.length < maxRouteLimit"
            class="col-span-full flex justify-center bg-gray-100 px-4 py-4 text-center text-sm font-medium text-indigo-500 hover:text-indigo-700 sm:rounded-b-lg">
            <button href="#" @click.prevent="addRoutingOption" class="flex items-center gap-2">
                <PlusIcon class="h-6 w-6 text-black-500 hover:text-black-900 active:h-8 active:w-8 " />
                <span>
                    Add new routing option
                </span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { PlusIcon } from "@heroicons/vue/24/solid";
import ComboBox from "../general/ComboBox.vue";
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { EllipsisVerticalIcon } from '@heroicons/vue/24/outline';



const props = defineProps({
    routingTypes: [Object, null],
    selectedItems: [Array, Object, null],
    maxRouteLimit: { type: Number, default: 1 },
    optionsUrl: String,
});

const emit = defineEmits(['update:model-value'])


const routingOptions = ref([]);

// Initialize routingOptions and fetch typeOptions
if (props.selectedItems) {
    console.log(props.selectedItems)
    props.selectedItems.forEach((item, index) => {
        routingOptions.value.push({
            type: item.type || null,
            typeOptions: [],  // Initially empty
            option: item.option || null,
            extension: item.extension || null,
        });

        // If type is available, fetch the options for that type
        if (item.type) {
            fetchTypeOptionsForItem(item.type, index);
        }
    });
}


// Fetch new options for the selected type using Axios
function fetchRoutingTypeOptions(newValue, index) {

    routingOptions.value[index].type = newValue.value;

    // Reset the selected option when type changes
    routingOptions.value[index].option = null;

    axios.post(props.optionsUrl, { 'category': newValue.value })
        .then((response) => {
            // console.log(response.data);
            routingOptions.value[index].typeOptions = response.data.options;
            // createFormSubmiting.value = false;
            // showNotification('success', response.data.messages);
            // handleSearchButtonClick();
            // handleModalClose();
            // handleClearSelection();
        }).catch((error) => {
            // createFormSubmiting.value = false;
            // handleClearSelection();
            // handleFormErrorResponse(error);
            routingOptions.value[index].typeOptions = null;
        });
}

function fetchTypeOptionsForItem(type, index) {
    axios.post(props.optionsUrl, { 'category': type })
        .then((response) => {
            routingOptions.value[index].typeOptions = response.data.options;

            // Automatically set the selected option if the option exists in the fetched options
            const selectedOption = routingOptions.value[index].option;
            if (selectedOption) {
                const match = response.data.options.find(option => option.value === selectedOption);
                if (match) {
                    routingOptions.value[index].option = match.value;
                } else {
                    routingOptions.value[index].option = null; // Reset if no match found
                }
            }
        }).catch(() => {
            routingOptions.value[index].typeOptions = null;
            routingOptions.value[index].option = null;  // Reset option in case of an error
        });
}

// Update routingOptions and emit updated model value
function updateRoutingOptions(newValue, index) {
    routingOptions.value[index].option = newValue.value;
    routingOptions.value[index].extension = newValue.extension;

    // Prepare the updated options
    const updatedOptions = routingOptions.value.map(({ type, option, extension }) => {
        return { type, option, extension };
    });

    emit('update:model-value', updatedOptions);
}


// Add a new routing option
const addRoutingOption = () => {
    if (routingOptions.value.length < props.maxRouteLimit) {
        routingOptions.value.push({
            type: null,
            typeOptions: [],
            option: null,
        });
    }
};

const removeRoutingOption = (index) => {
    // console.log(routingOptions.value);
    routingOptions.value.splice(index, 1);

    // Reassign the array to force Vue to track reactivity properly
    routingOptions.value = [...routingOptions.value];
    
    const updatedOptions = routingOptions.value.map(({ type, option }) => {
        return { type, option };
    });
    // console.log(updatedOptions);
    emit('update:model-value', updatedOptions);
}

</script>
