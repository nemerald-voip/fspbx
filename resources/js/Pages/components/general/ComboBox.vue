<template>
    <Combobox v-model="currentSelection" @update:modelValue="value => emit('update:model-value', value)"
        :multiple="multiple">
        <div class="relative w-full">
            <ComboboxButton class="w-full">
                <ComboboxInput :class="inputClass" @change="searchKeyword = $event.target.value"
                    :display-value="displayValue" :placeholder="placeholder"
                    v-bind="$attrs">

                </ComboboxInput>

            </ComboboxButton>

            <div v-if="multiple && badgeText" class="absolute inset-y-0 pl-1 flex items-center">
                <Badge :text="badgeText" backgroundColor="bg-indigo-50" textColor="text-indigo-700"
                    ringColor="ring-indigo-600/20" />
            </div>

            <div class="absolute inset-y-0 right-0 flex items-center pr-1">


                <div v-if="true">
                    <UndoIcon v-if="showUndo" @click="undoValue"
                        class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                        aria-hidden="true" />
                </div>

                <div v-if="hasCurrentSelection || showClear" class="">
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
                    class="absolute z-10 mt-1 px-2 max-h-72 w-full rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-none sm:text-sm">

                    <div class="max-h-56 overflow-auto">


                        <ComboboxOption v-if="props.allowEmpty" v-slot="{ active, selected }" as="template">
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
                                <span v-if="selected"
                                    class="absolute inset-y-0 left-0 flex items-center pl-3 text-blue-600">
                                    <CheckIcon class="h-5 w-5" aria-hidden="true" />
                                </span>
                            </li>
                        </ComboboxOption>

                    </div>
                    <div v-if="multiple" class="p-2 border-t flex justify-between items-center">
                        <div>
                            <button type="button" @click.prevent="selectAll" :disabled="allSelected"
                                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:bg-indigo-300">
                                Select All
                            </button>

                            <!-- <a class="text-indigo-600 underline cursor-pointer mx-2">Reset</a> -->
                            <button type="button" @click.prevent="resetSelection"
                                class="ml-6 rounded-md bg-white text-sm font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Reset
                            </button>
                        </div>


                        <!-- <button type="button" @click.prevent="resetSelection"
                            class="rounded-md bg-white px-2.5 py-1.5 ml-2 sm:ml-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Reset
                        </button> -->

                        <button type="button" @click.prevent="applySelection" :disabled="!hasCurrentSelection"
                            class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:bg-indigo-300">
                            Apply
                        </button>
                    </div>
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
import Badge from "@generalComponents/Badge.vue"

const props = defineProps({
    options: [Array, null],
    selectedItem: [String, Array, null],
    placeholder: [String, null],
    allowEmpty: { type: [Boolean], default: false },
    showClear: { type: [Boolean], default: false },
    showUndo: { type: [Boolean], default: false },
    error: Boolean,
    multiple: { type: Boolean, default: false },
    // disabled: { type: Boolean, default: false }, // Added disabled prop

});

const emit = defineEmits(['update:model-value', 'apply-selection'])

const currentSelection = ref(props.multiple ? [] : null);

// Initialize searchKeyword
const searchKeyword = ref('');

const clearValue = () => {
    currentSelection.value = props.multiple ? [] : null;
    searchKeyword.value = '';
    emit('update:model-value', props.multiple ? [] : { value: "NULL" });
}

const undoValue = () => {
    currentSelection.value = props.multiple ? [] : null;
    emit('update:model-value', props.multiple ? [] : { value: null });
}

const displayValue = () => {
    if (props.multiple) {
        if (currentSelection.value.length === 0) {
            // Show placeholder
            return null;
        } else {
            // return an empty line to remove placeholder
            return " ";
        }
    }
    return currentSelection.value ? currentSelection.value.name : '';
}

// Compute the text to display in the badge
const badgeText = computed(() => {
    if (props.multiple) {
        const selectionCount = currentSelection.value.length;
        if (props.options && selectionCount > 1 && selectionCount < props.options.length) {
            return `Selected - ${selectionCount}`;
        } else if (selectionCount === 1) {
            return currentSelection.value[0].name;
        } else if (props.options && selectionCount === props.options.length && props.options.length != 0) {
            return 'All Selected';
        }
    }
    return '';
});

// Watch for changes in selectedItem and update currentSelection accordingly
watch(() => props.selectedItem, (newValues) => {
    searchKeyword.value = '';
    if (props.multiple) {
        if (!newValues || newValues.length === 0 || !props.options) {
            currentSelection.value = [];
        } else {
            // Check if newValues are primitives (strings) or objects
            if (typeof newValues[0] === 'string') {
                currentSelection.value = props.options.filter(option =>
                    newValues.includes(option.value)
                );
            } else {
                const newValuesArray = newValues.map(item => item.value);
                currentSelection.value = props.options.filter(option =>
                    newValuesArray.includes(option.value)
                );
            }
        }
    } else {
        if (!newValues || newValues === "NULL" || !props.options) {
            currentSelection.value = null;
        } else {
            currentSelection.value = props.options.find(option => option.value === newValues);
        }
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
    let baseClasses = 'w-full truncate text-gray-900 border-0 cursor-default rounded-md bg-white py-1.5 pl-3 text-left ring-1 ring-inset focus:outline-none focus-visible:border-indigo-500 focus-visible:ring-2 focus-visible:ring-white/75 focus-visible:ring-offset-2 focus-visible:ring-offset-blue-600 sm:text-sm sm:leading-6';

    // Check if either `hasCurrentSelection` or `showClear` is true to adjust padding-right
    let paddingRight = (hasCurrentSelection.value || props.showClear) ? 'pr-20' : 'pr-10';

    if (props.error) {
        return `${baseClasses} ring-red-600 focus-visible:ring-offset-red-600`; // Apply red ring if there's an error
    }
    return `${baseClasses} ${paddingRight} ring-gray-300 disabled:opacity-50 disabled:bg-gray-200 disabled:cursor-not-allowed`;  // Default ring color when no error
});

// Determine if there is a current selection
const hasCurrentSelection = computed(() => {
    return props.multiple ? currentSelection.value.length > 0 : currentSelection.value !== null;
});

const allSelected = computed(() => {
    return props.options && currentSelection.value.length === props.options.length;
});

const selectAll = () => {
    if (props.options) {
        currentSelection.value = [...props.options];
        emit('update:model-value', currentSelection.value);
    }
}

const resetSelection = () => {
    undoValue();
}

const applySelection = () => {
    emit('apply-selection', currentSelection.value);
}

</script>



<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>
