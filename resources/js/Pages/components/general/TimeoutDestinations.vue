<template>
    <div :class="['mt-2 grid grid-cols-5 gap-x-2', customClass]" v-for="(timeoutDestination, index) in timeoutDestinations"
         :key="index">
        <SelectBox :options="categories"
                   :search="true"
                   :allowEmpty="true"
                   :placeholder="'Choose category'"
                   :class="'col-span-2'"
                   :selectedItem="timeoutDestination.selectedCategory"
                   @update:modal-value="value => handleCategoryUpdate(value, index)"
        />
        <SelectBox v-if="timeoutDestination.selectedCategory"
                   :options="timeoutDestination.categoryTargets"
                   :search="true"
                   :class="'col-span-2'"
                   :placeholder="'Choose target destination'"
                   :selectedItem="timeoutDestination.value.value"
                   @update:modal-value="value => handleTargetUpdate(value, index)"
        />
        <MinusIcon v-if="index > 0" @click="() => removeTimeoutDestination(index)"
                   class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
    </div>
    <PlusIcon v-if="timeoutDestinations.length < maxLimit" @click="addTimeoutDestination"
              class="mt-2 h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
</template>

<script setup>
import {ref,onMounted} from 'vue'
import {PlusIcon, MinusIcon} from "@heroicons/vue/24/solid";
import SelectBox from "./SelectBox.vue";

const props = defineProps({
    itemOptions: Object,
    categories: [Array, null],
    targets: [Array, Object, null],
    selectedItems: [Array, Object, null],
    maxLimit: { type: Number, default: 21 },
    customClass: {
        type: String,
        default: ''
    },
});

const emit = defineEmits(['update:modal-value'])

onMounted(() => {
    if (props.selectedItems) {
        timeoutDestinations.value = props.selectedItems.map(item => {
            const categoryNames = Object.keys(props.targets);

            let selectedCategory = '';
            let selectedCategoryTarget = {};

            // look in each category to find the target value
            for (let category of categoryNames) {
                const foundInCategory = props.targets[category].find(target =>
                    target.value === item.destination_data
                );

                // if found, save the category and target
                if (foundInCategory) {
                    selectedCategory = category;
                    selectedCategoryTarget = foundInCategory;
                    break;
                }
            }

            // return a new timeoutDestination object
            return {
                selectedCategory: selectedCategory,
                categoryTargets: props.targets[selectedCategory] || [],
                value: selectedCategoryTarget
            }
        })
    }
})

const timeoutDestinations = ref([
    {
        selectedCategory: '',
        categoryTargets: [],
        value: ''
    }
]);

function handleCategoryUpdate(newValue, index) {
    if (newValue !== null && newValue !== undefined) {
        timeoutDestinations.value[index].selectedCategory = newValue.value;
        timeoutDestinations.value[index].categoryTargets = props.targets[newValue.value] || [];
    } else {
        timeoutDestinations.value[index].categoryTargets = [];
        timeoutDestinations.value[index].selectedCategory = '';
    }
}

function handleTargetUpdate(newValue, index) {
    if (newValue !== null && newValue !== undefined) {
        timeoutDestinations.value[index].value = newValue;
    }
    emit('update:modal-value', timeoutDestinations.value); // emit the current state on target update
}

const addTimeoutDestination = () => {
    if (timeoutDestinations.value.length < props.maxLimit) {
        timeoutDestinations.value.push({
            selectedCategory: '',
            categoryTargets: [],
            value: ''
        });
    }
};

const removeTimeoutDestination = (index) => {
    timeoutDestinations.value.splice(index, 1);
    props.itemOptions.destination_actions.splice(index, 1);
}

</script>
