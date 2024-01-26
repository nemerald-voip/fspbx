<template>
    <Listbox v-model="selectedItem" @update:modelValue="$emit('update:selected-item',selectedItem.value)">
        <div class="relative">
            <ListboxButton
                class="relative w-full cursor-default rounded-md bg-white py-2 pl-3 pr-10 text-left ring-1 ring-inset ring-gray-300 focus:outline-none focus-visible:border-indigo-500 focus-visible:ring-2 focus-visible:ring-white/75 focus-visible:ring-offset-2 focus-visible:ring-offset-blue-600 sm:text-sm">
                <span :class="{ 'text-gray-400': !selectedItem }" class="block truncate">
                    {{ selectedItem ? selectedItem.name : 'Call Direction' }}
                </span> 
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                    <ChevronUpDownIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                </span>
            </ListboxButton>

            <transition leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <ListboxOptions
                    class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-none sm:text-sm">
                    <ListboxOption v-slot="{ active, selected }" v-for="item in data" :key="item.value"
                        :value="item" as="template">
                        <li :class="[
                            active ? 'bg-blue-100 text-blue-800' : 'text-gray-900',
                            'relative cursor-default select-none py-2 pl-10 pr-4',
                        ]">
                            <span :class="[
                                selected ? 'font-medium' : 'font-normal',
                                'block truncate',
                            ]">{{ item.name }}</span>
                            <span v-if="selected" class="absolute inset-y-0 left-0 flex items-center pl-3 text-blue-600">
                                <CheckIcon class="h-5 w-5" aria-hidden="true" />
                            </span>
                        </li>
                    </ListboxOption>
                </ListboxOptions>
            </transition>
        </div>
    </Listbox>
</template>
  
<script setup>
import { ref } from 'vue'
import {
    Listbox,
    ListboxLabel,
    ListboxButton,
    ListboxOptions,
    ListboxOption,
} from '@headlessui/vue'
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
    data: Object,
});

const selectedItem = ref()

// const handleSelectBoxUpdate = (newValue) => {
//     console.log(selectedDirection.value);
// }
</script>
  