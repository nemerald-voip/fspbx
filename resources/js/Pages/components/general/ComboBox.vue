<template>
    <Combobox v-model="currentItem" @update:modelValue="value => emit('update:modal-value', value)">
        <div class="relative">
            <ComboboxButton class="w-full">
                <ComboboxInput :class="inputClass" @change="searchKeyword = $event.target.value"
                    :display-value="displayValue" :placeholder="placeholder" />
            </ComboboxButton>

            <div class="absolute inset-y-0 right-0 flex items-center pr-1">


                <div v-if="true">
                    <UndoIcon v-if="showUndo" @click="undoValue"
                        class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                        aria-hidden="true" />
                </div>

                <div v-if="(currentItem != '' && currentItem != null) || showClear" class="">
                    <XMarkIcon @click="clearValue"
                        class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                        aria-hidden="true" />
                </div>

                <div v-if="true">
                    <ComboboxButton class=" flex items-center  focus:outline-none">
                        <ChevronUpDownIcon
                            class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                            aria-hidden="true" />
                    </ComboboxButton>
                </div>

            </div>

            <transition leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <ComboboxOptions
                    class="absolute z-10 mt-1 px-2 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-none sm:text-sm">

                    <ComboboxOption v-if="props.allowEmpty" v-slot="{ active, selected }" :value="null" as="template">
                        <li :class="[
                            active ? 'bg-blue-100 text-blue-800' : 'text-gray-900',
                            'relative cursor-default select-none py-2 pl-10 pr-4',
                        ]">
                            <span :class="[
                                selected ? 'font-medium' : 'font-normal',
                                'block truncate',
                            ]">None</span>
                        </li>
                    </ComboboxOption>

                    <ComboboxOption v-slot="{ active, selected }" v-for="item in filteredOptions" :key="item.value"
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
                    </ComboboxOption>
                </ComboboxOptions>
            </transition>
        </div>
    </Combobox>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxLabel,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/vue'
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import UndoIcon from "../icons/UndoIcon.vue"

const props = defineProps({
    options: [Array, null],
    selectedItem: [String, null],
    placeholder: [String, null],
    search: [Boolean, null],
    allowEmpty: { type: [Boolean], default: false },
    showClear: { type: [Boolean], default: false },
    showUndo: { type: [Boolean], default: false },
    error: Boolean,
});

const emit = defineEmits(['update:modal-value'])

const currentItem = ref(null);

// Initialize searchKeyword
const searchKeyword = ref('');

const clearValue = () => {
    currentItem.value = null;
    emit('update:modal-value', { value: "NULL" });
}

const undoValue = () => {
    currentItem.value = null;
    emit('update:modal-value', { value: null });
}

const displayValue = (currentItem) => {
    return currentItem ? currentItem.name : "";
}

// Watch for changes in selectedItem and update currentItem accordingly
watch(() => props.selectedItem, (newValue) => {
    searchKeyword.value = '';
    if (newValue === null || newValue === "NULL" || newValue === undefined || props.options === null || props.options === undefined) {
        currentItem.value = null;
    } else {
        currentItem.value = props.options.find(option => option.value === newValue);
    }

}, { immediate: true });

// Computed property to filter options based on search keyword
const filteredOptions = computed(() => {
    if (!searchKeyword.value) return props.options;
    return props.options.filter(item =>
        item.name.toLowerCase().includes(searchKeyword.value.toLowerCase())
    );
});

// Compute the classes based on the error state
const inputClass = computed(() => {
    let baseClasses = 'w-full text-gray-900 border-0 cursor-default rounded-md bg-white py-2 pl-3 pr-10 text-left ring-1 ring-inset focus:outline-none focus-visible:border-indigo-500 focus-visible:ring-2 focus-visible:ring-white/75 focus-visible:ring-offset-2 focus-visible:ring-offset-blue-600 sm:text-sm sm:leading-6';
    if (props.error) {
        return `${baseClasses} ring-red-600 focus-visible:ring-offset-red-600`; // Apply red ring if there's an error
    }
    if (props.disabled) {
        return `${baseClasses} disabled:opacity-50`; // Apply disabled class
    }
    return `${baseClasses} ring-gray-300`;  // Default ring color when no error
});

</script>



<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>