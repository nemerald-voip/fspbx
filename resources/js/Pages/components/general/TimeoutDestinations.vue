<template>
    <div class="grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-2">
        <div>
            <div class="mt-2">
                <SelectBox :options="categories"
                           :search="true"
                           :allowEmpty="true"
                           :placeholder="'Choose category'"
                           @update:modal-value='updateCategory'
                />
            </div>
        </div>

        <div v-if="categoryTargets.value.length > 0">
            <div class="mt-2">
                <SelectBox :options="categoryTargets"
                           :search="true"
                           :placeholder="'Choose target destination'"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import {
    Listbox,
    ListboxLabel,
    ListboxButton,
    ListboxOptions,
    ListboxOption,
} from '@headlessui/vue'
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid'
import SelectBox from "./SelectBox.vue";
import LabelInputOptional from "./LabelInputOptional.vue";

let selectedCategory = ref('');
let categoryTargets = ref([]);

const props = defineProps({
    categories: [Array, null],
    targets: [Array, null],
    selectedItem: [String, null],
    search: [Boolean, null]
});

const emit = defineEmits(['update:modal-value'])

function updateCategory(newValue){
    selectedCategory.value = newValue;
    categoryTargets.value = props.targets[newValue] || [];
}

// let currentItem = ref(props.selectedItem === null ? null : props.options.find(option => option.value === props.selectedItem));
let currentItem = ref(null);

// Initialize searchKeyword
let searchKeyword = ref('');

// Watch for changes in selectedItem and update currentItem accordingly
watch(() => props.selectedItem, (newValue) => {
    if (newValue === null || newValue === undefined || props.options === null || props.options === undefined) {
        currentItem.value = null;
    } else {
        currentItem.value = props.options.find(option => option.value === newValue);
    }

}, { immediate: true });

// Computed property to filter options based on search keyword
const filteredOptions = computed(() => {
    if (!searchKeyword.value) return props.options;

    // Need to handle the fact that options are now an object of arrays.
    // This creates a new object with the same keys, but filtered arrays.
    const filtered = {};
    for (const [group, items] of Object.entries(props.options)) {
        // Only include items that match the search.
        filtered[group] = items.filter(item =>
            item.name.toLowerCase().includes(searchKeyword.value.toLowerCase())
        );
    }
    return filtered;
});

</script>
