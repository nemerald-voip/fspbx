<template>
    <div :class="['mt-2 grid gap-x-2', customClass]" v-for="(timeoutDestination, index) in timeoutDestinations"
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
        <div class="relative">
            <div class="absolute right-0">
                <ejs-tooltip :content="'Remove destination'"
                             position='RightTop' :target="'#delete_destination_tooltip'+index">
                    <div :id="'delete_destination_tooltip'+index">
                        <MinusIcon @click="() => removeTimeoutDestination(index)"
                                   class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
                    </div>
                </ejs-tooltip>
            </div>
        </div>

    </div>
    <div class="w-fit">
        <ejs-tooltip v-if="timeoutDestinations.length < maxLimit" :content="'Add destination'"
                     position='RightTop' target="#add_destination_tooltip">
            <div id="add_destination_tooltip">
                <PlusIcon @click="addTimeoutDestination"
                          class="mt-2 h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
            </div>
        </ejs-tooltip>
    </div>
</template>

<script setup>
import {ref,onMounted} from 'vue'
import {PlusIcon, MinusIcon} from "@heroicons/vue/24/solid";
import SelectBox from "./SelectBox.vue";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";

const props = defineProps({
    itemOptions: Object,
    categories: [Array, null],
    targets: [Array, Object, null],
    selectedItems: [Array, Object, null],
    maxLimit: { type: Number, default: 21 },
    customClass: {
        type: String,
        default: 'grid-cols-5'
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
