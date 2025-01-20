<template>
    <div ref="dropdownRef" class="relative w-full">
        <!-- Search/Select Input -->
        <input type="text" :value="dropdownOpen ? searchQuery : selectedOption?.name || ''" @focus="openDropdown"
            @input="onSearch" placeholder="Search or select..."
            class="w-full border-0 cursor-pointer rounded-lg bg-white py-2 pl-3 pr-10 text-left shadow-md ring-1 ring-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm" />
        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </span>

        <!-- Dropdown -->
        <ul v-if="dropdownOpen"
            class="absolute z-10 mt-1 w-full max-h-60 overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
            <template v-for="(options, group) in filteredOptions" :key="group">
                <!-- Group Label -->
                <li class="px-4 py-2 text-sm font-medium text-gray-500">
                    {{ group }}
                </li>

                <!-- Options -->
                <li v-for="option in options" :key="option.value"
                    class="relative cursor-pointer select-none py-2 pl-10 pr-4 hover:bg-blue-100 hover:text-blue-900"
                    :class="{ 'bg-blue-100 text-blue-900': modelValue === option.value }"
                    @click="selectOption(option)">
                    <span class="block truncate"
                        :class="{ 'font-meduim': modelValue === option.value, 'font-normal': modelValue !== option.value }">
                        {{ option.name }}
                    </span>
                    <!-- Add the CheckIcon for the selected item -->
                    <span v-if="modelValue === option.value"
                        class="absolute inset-y-0 left-0 flex items-center pl-3 text-blue-600">
                        <CheckIcon class="h-5 w-5" aria-hidden="true" />
                    </span>
                </li>
            </template>
        </ul>
    </div>
</template>
  
<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { watch } from "vue";
import { CheckIcon } from "@heroicons/vue/20/solid";


// Props
const props = defineProps({
    options: {
        type: Object,
        required: true,
    },
    modelValue: {
        type: String,
        default: null,
    },
    placeholder: {
        type: String,
        default: "Select an option",
    },
});

// Emit event
const emit = defineEmits(["update:modelValue"]);

// Reactive state for dropdown
const dropdownOpen = ref(false);
const searchQuery = ref("");
const selectedOption = ref(props.modelValue);


watch(() => props.modelValue, (newValue) => {
    selectedOption.value = Object.values(props.options)
        .flatMap(group => group)
        .find(option => option.value === newValue) || null;
}, { immediate: true });

// Dropdown element reference
const dropdownRef = ref(null);

// Toggle dropdown visibility
const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
    if (!dropdownOpen.value) {
        closeDropdown();
    }
};

const openDropdown = () => {
    dropdownOpen.value = true;
    searchQuery.value = ""; // Start search with the selected option name
};

const closeDropdown = () => {
    dropdownOpen.value = false;
    searchQuery.value = selectedOption.value?.name || ""; // Revert to selected option when dropdown closes
};

// Select an option
const selectOption = (option) => {
    emit("update:modelValue", option.value); // Emit the selected value
    searchQuery.value = option.name;
    closeDropdown();
};


// Handle search input
const onSearch = (event) => {
    searchQuery.value = event.target.value;
    if (!dropdownOpen.value) {
        dropdownOpen.value = true; // Open dropdown if closed
    }
};

// Computed property for filtered options
const filteredOptions = computed(() => {
    const query = searchQuery.value.toLowerCase();
    const result = {};
    for (const [group, items] of Object.entries(props.options)) {
        const filteredItems = items.filter((item) =>
            item.name.toLowerCase().includes(query)
        );
        if (filteredItems.length > 0) {
            result[group] = filteredItems;
        }
    }
    return result;
});

// Handle click outside
const handleClickOutside = (event) => {
    if (!dropdownRef.value || !dropdownRef.value.contains(event.target)) {
        dropdownOpen.value = false; // Close the dropdown
    }
};

// Setup global event listener
onMounted(() => {
    document.addEventListener("mousedown", handleClickOutside); // Use mousedown for better responsiveness
});

// Cleanup global event listener
onUnmounted(() => {
    document.removeEventListener("mousedown", handleClickOutside);
});
</script>
  
<style scoped>
/* Optional styling for better user experience */
</style>
  