<template>
    <div ref="dropdownRef" class="relative w-full">
        <!-- Trigger -->
        <button
            class="w-full cursor-pointer rounded-lg bg-white py-2 pl-3 pr-10 text-left shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm"
            @click.prevent="toggleDropdown">
            <span class="block truncate">
                {{ selectedOption ? selectedOption.name : placeholder }}
            </span>
            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </span>
        </button>

        <!-- Dropdown -->
        <ul v-if="dropdownOpen"
            class="absolute z-10 mt-1 w-full max-h-60 overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
            <template v-for="(options, group) in options" :key="group">
                <!-- Group Label -->
                <li class="px-4 py-2 text-sm font-medium text-gray-500">
                    {{ group }}
                </li>

                <!-- Options -->
                <li v-for="option in options" :key="option.value"
                    class="cursor-pointer select-none py-2 pl-10 pr-4 hover:bg-blue-100 hover:text-blue-900"
                    :class="{ 'bg-blue-100 text-blue-900': selectedOption?.value === option.value }"
                    @click="selectOption(option)">
                    <span class="block truncate"
                        :class="{ 'font-medium': selectedOption?.value === option.value, 'font-normal': selectedOption?.value !== option.value }">
                        {{ option.name }}
                    </span>
                </li>
            </template>
        </ul>
    </div>
</template>
  
<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { watch, toRef } from "vue";

// Props
const props = defineProps({
    options: {
        type: Object,
        required: true,
    },
    modelValue: {
        type: Object,
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
const selectedOption = ref(null);

// Bind `modelValue` to a reactive property using `toRef`
const modelValueRef = toRef(props.modelValue);

// Watch `modelValue` for updates
watch(
    modelValueRef,
    (newValue) => {
        selectedOption.value = newValue;
    },
    { immediate: true } // Ensure the initial value is set
);

// Dropdown element reference
const dropdownRef = ref(null);

// Toggle dropdown visibility
const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
};

// Select an option
const selectOption = (option) => {
    if (selectedOption.value?.value !== option.value) {
        selectedOption.value = option;
        emit("update:modelValue", option); // Emit only if value changes
    }
    dropdownOpen.value = false; // Close the dropdown
};

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
  