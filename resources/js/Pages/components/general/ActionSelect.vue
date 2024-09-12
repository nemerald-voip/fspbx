<template>
    <div class="grid grid-cols-12 gap-6">
        <template v-for="(option, index) in routingOptions" :key="index">
            <div class="pt-2 text-sm font-medium leading-6 text-gray-900">
                {{ index + 1 }}
            </div>

            <div class="col-span-10 flex flex-col sm:flex-row gap-x-2 gap-y-1 justify-between flex-auto">
                <div class=" basis-2/4 text-sm font-medium leading-6 text-gray-900">
                    <ComboBox
                        :options="routingTypes"
                        :search="true"
                        :placeholder="'Choose type'"
                        @update:model-value="(value) => fetchRoutingTypeOptions(value, index)" />
                </div>

                <div class=" basis-2/4 text-sm font-medium leading-6 text-gray-900">
                    <ComboBox :options="routingTypeOptions" :selectedItem="null" :search="true" :placeholder="'Choose option'"
                        @update:model-value="(value) => handleOptionUpdate(value, index)" />
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
    </div>

    <div v-if="routingOptions.length < maxRouteLimit"
        class="flex justify-center bg-gray-100 px-4 py-4 text-center text-sm font-medium text-indigo-500 hover:text-indigo-700 sm:rounded-b-lg">
        <button href="#" @click.prevent="addRoutingOption" class="flex items-center gap-2">
            <PlusIcon class="h-6 w-6 text-black-500 hover:text-black-900 active:h-8 active:w-8 " />
            <span>
                Add new routing option
            </span>
        </button>
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

});

const emit = defineEmits(['update:model-value'])


const routingOptions = ref([]);

const routingTypeOptions = ref([]);

function handleCategoryUpdate(newValue, index) {
    console.log(newValue);
}

// Fetch new options for the selected type using Axios
function fetchRoutingTypeOptions(newValue, index) {

    // console.log(index);
    // console.log(routingOptions);

    routingOptions[index].type = newValue.value;
    console.log(routingOptions.value);
    // axios.get(`/api/routing-options/${type.value}`).then((response) => {
    //     routingOptions.value[index].typeOptions = response.data;
    // });
}

function handleOptionUpdate(newValue, index) {
    if (newValue !== null && newValue !== undefined) {
        routingOptions.value[index].targetName = newValue.name;
        routingOptions.value[index].targetValue = newValue.value;
    }

    const routingOptionsMapped = routingOptions.value.map(({ name, value, targetName, targetValue }) => {
        return { name, value, targetName, targetValue };
    });

    emit('update:model-value', routingOptionsMapped);
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
    console.log(index);
    routingOptions.value.splice(index, 1);
    emit('update:model-value', routingOptions);
}

</script>
