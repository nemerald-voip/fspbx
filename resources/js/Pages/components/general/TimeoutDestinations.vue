<template>
    <div class="mt-2 grid grid-cols-5 gap-x-6 gap-y-8" v-for="(timeoutDestination, index) in timeoutDestinations" :key="index">
        <SelectBox :options="categories"
                   :search="true"
                   :allowEmpty="true"
                   :placeholder="'Choose category'"
                   :class="'col-span-2'"
                   @update:modal-value="value => updateCategory(value, index)"
        />
        <SelectBox v-if="timeoutDestination.selectedCategory"
                   :options="timeoutDestination.categoryTargets"
                   :search="true"
                   :class="'col-span-2'"
                   :placeholder="'Choose target destination'"
        />
        <MinusIcon v-if="index > 0" @click="removeTimeoutDestination"
                   class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer" />
    </div>
    <PlusIcon  v-if="timeoutDestinations.length < 21" @click="addTimeoutDestination"
               class="mt-2 h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer" />
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import {PlusIcon,MinusIcon} from "@heroicons/vue/24/solid";
import SelectBox from "./SelectBox.vue";

const props = defineProps({
    categories: [Array, null],
    targets: [Array, Object, null],
    selectedItem: [String, null],
    destination: [Array, Object, null],
});

const timeoutDestinations = ref([
    {
        selectedCategory: '',
        categoryTargets: [],
        value: ''
    }
]);

const emit = defineEmits(['update:selected-targets'])

function updateCategory(newValue, index){
    timeoutDestinations.value[index].selectedCategory = newValue.value;
    timeoutDestinations.value[index].categoryTargets = props.targets[newValue.value] || [];
    emit("update:selected-targets", timeoutDestinations.value.map(item => item.categoryTargets));
}

const addTimeoutDestination = () => {
    timeoutDestinations.value.push({
        selectedCategory: '',
        categoryTargets: [],
        value: ''
    });
};

const removeTimeoutDestination = (index) => {
    timeoutDestinations.value.splice(index, 1);
}

watch(() => props.destination, (newValue) => {
    if(newValue !== null && newValue !== undefined) {
        timeoutDestinations.value = newValue;
    }
}, { immediate: true });

watch(() => timeoutDestinations.value, (newValue) => {
    emit("update:selected-targets", newValue.map(item => item.categoryTargets));
});

/*
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
});*/

</script>
