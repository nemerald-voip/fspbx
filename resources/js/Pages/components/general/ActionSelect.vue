<template>
    <div :class="['mt-2 mb-2 grid gap-x-2', customClass]" v-for="(action, index) in actions"
         :key="index">
        <SelectBox :options="options"
                   :search="true"
                   :allowEmpty="true"
                   :placeholder="'Choose category'"
                   :class="'col-span-2'"
                   :selectedItem="action.selectedCategory"
                   @update:modal-value="value => handleCategoryUpdate(value, index)"
        />
        <SelectBox v-if="action.selectedCategory"
                   :options="action.categoryTargets"
                   :search="true"
                   :class="'col-span-2'"
                   :placeholder="'Choose target action'"
                   :selectedItem="action.value.value"
                   @update:modal-value="value => handleTargetUpdate(value, index)"
        />
        <template v-else>
            <div></div>
            <div></div>
        </template>
        <div v-if="maxLimit > 1" class="relative">
            <div class="absolute right-0">
                <ejs-tooltip :content="'Remove action'"
                             position='RightTop' :target="'#delete_action_tooltip'+index">
                    <div :id="'delete_action_tooltip'+index">
                        <MinusIcon @click="() => removeAction(index)"
                                   class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
                    </div>
                </ejs-tooltip>
            </div>
        </div>

    </div>
    <div v-if="maxLimit > 1" class="w-fit">
        <ejs-tooltip v-if="actions.length < maxLimit" :content="'Add action'"
                     position='RightTop' target="#add_action_tooltip">
            <div id="add_action_tooltip">
                <PlusIcon @click="addAction"
                          class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
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
    //itemOptions: Object,
    options: [Array, null],
    optionTargets: [Array, Object, null],
    selectedItems: [Array, Object, null],
    maxLimit: { type: Number, default: 1 },
    customClass: {
        type: String,
        default: 'grid-cols-5'
    },
});

const emit = defineEmits(['update:modal-value'])

onMounted(() => {
    if (props.selectedItems) {
        actions.value = props.selectedItems.map(item => {
            const categoryNames = Object.keys(props.optionTargets);

            let selectedCategory = '';
            let selectedCategoryTarget = {};

            // look in each category to find the target value
            for (let category of categoryNames) {
                const foundInCategory = props.optionTargets[category].find(target => target.value === item.value || target.value === item.value?.value);
                // if found, save the category and target
                if (foundInCategory) {
                    selectedCategory = category;
                    selectedCategoryTarget = foundInCategory;
                    break;
                }
            }

            // return a new action object
            return {
                selectedCategory: selectedCategory,
                categoryTargets: props.optionTargets[selectedCategory] || [],
                value: selectedCategoryTarget
            }
        })
    }
})

const actions = ref([
    {
        selectedCategory: '',
        categoryTargets: [],
        value: ''
    }
]);

function handleCategoryUpdate(newValue, index) {
    if (newValue !== null && newValue !== undefined) {
        actions.value[index].selectedCategory = newValue.value;
        actions.value[index].categoryTargets = props.optionTargets[newValue.value] || [];
    } else {
        actions.value[index].categoryTargets = [];
        actions.value[index].selectedCategory = '';
    }
}

function handleTargetUpdate(newValue, index) {
    console.log(newValue)
    if (newValue !== null && newValue !== undefined) {
        actions.value[index].value = newValue;
    }
    const actionsMapped = actions.value.map(action => {
        return {
            selectedCategory: action.selectedCategory,
            value: action.value
        }
    });
    emit('update:modal-value', actionsMapped);
}

const addAction = () => {
    if (actions.value.length < props.maxLimit) {
        actions.value.push({
            selectedCategory: '',
            categoryTargets: [],
            value: ''
        });
    }
};

const removeAction = (index) => {
    actions.value.splice(index, 1);
    const actionsMapped = actions.value.map(action => {
        return {
            selectedCategory: action.selectedCategory,
            value: action.value
        }
    });
    emit('update:modal-value', actionsMapped);
}

</script>
