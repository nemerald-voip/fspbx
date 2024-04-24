<template>
    <div class="mt-2 grid grid-cols-5 gap-x-6 gap-y-8" v-for="(timeoutDestination, index) in timeoutDestinations"
         :key="index">
        <SelectBox :options="categories"
                   :search="true"
                   :allowEmpty="true"
                   :placeholder="'Choose category'"
                   :class="'col-span-2'"
                   @update:modal-value="value => handleUpdateCategory(value, index)"
        />
        <SelectBox v-if="timeoutDestination.selectedCategory"
                   :options="timeoutDestination.categoryTargets"
                   :search="true"
                   :class="'col-span-2'"
                   :placeholder="'Choose target destination'"
                   @update:modal-value="value => handleUpdateTarget(value, index)"
        />
        <MinusIcon v-if="index > 0" @click="() => removeTimeoutDestination(index)"
                   class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
    </div>
    <PlusIcon v-if="timeoutDestinations.length < 21" @click="addTimeoutDestination"
              class="mt-2 h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
</template>

<script setup>
import {ref, watch, computed} from 'vue'
import {PlusIcon, MinusIcon} from "@heroicons/vue/24/solid";
import SelectBox from "./SelectBox.vue";

const props = defineProps({
    itemOptions: Object,
    categories: [Array, null],
    targets: [Array, Object, null],
    selectedItem: [String, null],
   // destinations: [Array, Object, null],
});

const timeoutDestinations = ref([
    {
        selectedCategory: '',
        categoryTargets: [],
        value: ''
    }
]);

function handleUpdateCategory(newValue, index) {
    if (newValue !== null && newValue !== undefined) {
        timeoutDestinations.value[index].selectedCategory = newValue.value;
        timeoutDestinations.value[index].categoryTargets = props.targets[newValue.value] || [];
    } else {
        timeoutDestinations.value[index].categoryTargets = [];
        timeoutDestinations.selectedCategory.value = '';
    }
}

function handleUpdateTarget(newValue, index) {
    if (newValue !== null && newValue !== undefined) {
        props.itemOptions.destination_actions[index] = newValue.value;
    }
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
    props.itemOptions.destination_actions.splice(index, 1);
}

</script>
