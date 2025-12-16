<template>
    <th :class="computedClass" :style="style" @click="handleSortClick">
        <div class="flex items-center">
            <slot>{{ header }}</slot>
            <!-- Sorting icons -->
            <ChevronUpIcon v-if="isSorted && sortOrder === 'asc'" class="ml-2 h-5 w-5 text-indigo-500" />
            <ChevronDownIcon v-if="isSorted && sortOrder === 'desc'" class="ml-2 h-5 w-5 text-indigo-500" />
        </div>
    </th>
</template>


<script setup>
import { computed } from 'vue';
import { ChevronUpIcon, ChevronDownIcon } from "@heroicons/vue/24/solid";

// Define the props for the Column component
const props = defineProps({
    header: {
        type: null,
        default: null
    },
    style: {
        type: null,
        default: null
    },
    class: {
        type: [String, Object, Array],  // Ensure class can handle various types
        default: () => ''
    },
    hidden: {
        type: Boolean,
        default: false
    },
    sortOrder: String,
    sortedField: String,
    sortable: {
        type: Boolean,
        default: true,
    },
    field: String,
});

// Define the emit function to emit events from this component
const emit = defineEmits(['sort']);

const isSorted = computed(() => props.field === props.sortedField);

const computedClass = computed(() => {
    let baseClass = props.class || ''; // Default class
    let sortableClass = props.sortable ? 'cursor-pointer' : ''; // Add cursor-pointer if sortable
    return [baseClass, sortableClass].filter(Boolean).join(' '); // Combine classes, filter out empty strings
});


const handleSortClick = () => {
    if (!props.sortable) {
        return;
    }
    // If a new column is clicked, start with 'asc'
    let newOrder;
    if (props.field !== props.sortedField) {
        newOrder = 'asc';  // Always start with 'asc' when a new header is clicked
    } else {
        // Toggle between 'asc' and 'desc' if the same column is clicked again
        newOrder = props.sortOrder === 'asc' ? 'desc' : 'asc';
    }
    emit('sort', { field: props.field, order: newOrder });
};


</script>
