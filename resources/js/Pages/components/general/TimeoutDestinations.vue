<template>
    <div class="mt-2 grid grid-cols-2 gap-x-6 gap-y-8" v-for="(timeoutDestination, index) in timeoutDestinations" :key="index">
        <SelectBox :options="categories"
                   :search="true"
                   :allowEmpty="true"
                   :placeholder="'Choose category'"
                   @update:modal-value='updateCategory'
        />
        <SelectBox v-if="selectedCategory" :options="categoryTargets"
                   :search="true"
                   :placeholder="'Choose target destination'"
        />
        <div>
            <MinusIcon @click="removeTimeoutDestination"
                       class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-5 active:w-5 cursor-pointer" />
            <PlusIcon @click="addTimeoutDestination"
                      class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-5 active:w-5 cursor-pointer" />
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import {PlusIcon,MinusIcon} from "@heroicons/vue/24/solid";
import SelectBox from "./SelectBox.vue";

let selectedCategory = ref('');
let categoryTargets = ref([]);

const props = defineProps({
    categories: [Array, null],
    targets: [Array, Object, null],
    selectedItem: [String, null],
    search: [Boolean, null],
    customClass: {
        type: String
    },
});

const timeoutDestinations = ref([{value: ''}]);

const emit = defineEmits(['update:modal-value'])

function updateCategory(newValue){
    selectedCategory.value = newValue.value;
    categoryTargets.value = props.targets[newValue.value] || [];
}

const addTimeoutDestination = () => {
    timeoutDestinations.value.push({value: ''});
}

const removeTimeoutDestination = () => {

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
