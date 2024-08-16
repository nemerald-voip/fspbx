<template>
    <div v-for="(action, index) in actions" :key="index"
         :class="['mt-2 mb-2 grid gap-x-2', customClass]">
        <ComboBox :options="Object.entries(props.options).map(([key, value]) => {
                        return { name: value.name, value: key }
                    })"
                  :placeholder="'Choose category'"
                  :class="'col-span-2'"
                  :selectedItem="action.value"
                  @update:model-value="value => handleCategoryUpdate(value, index)"
        />
        <ComboBox v-if="action.targetOptions"
                  :options="action.targetOptions"
                  :class="'col-span-2'"
                  :placeholder="'Choose target action'"
                  :selectedItem="action.targetValue"
                  @update:model-value="value => handleTargetUpdate(value, index)"
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
    <pre>
    {{actions}}
        </pre>
</template>

<script setup>
import {ref,onMounted} from 'vue'
import {PlusIcon, MinusIcon} from "@heroicons/vue/24/solid";
import ComboBox from "../general/ComboBox.vue";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";

const props = defineProps({
    options: [Object, null],
    selectedItems: [Array, Object, null],
    maxLimit: { type: Number, default: 1 },
    customClass: {
        type: String,
        default: 'grid-cols-5'
    },
});

const emit = defineEmits(['update:model-value'])

onMounted(() => {
    if (props.selectedItems) {
        actions.value = props.selectedItems.map(item => {
            const categoryNames = Object.keys(props.options);

            let selectedItem = '';
            let selectedItemTarget = {};

            // look in each category to find the target value
            for (let category of categoryNames) {
                const categoryEntry = props.options[category];
                const foundInCategory = categoryEntry.options.find(target => target.value === item.value || target.value === item.value?.value);
                // if found, save the category and target
                if (foundInCategory) {
                    selectedItem = categoryEntry;
                    selectedItemTarget = foundInCategory;
                    break;
                }
            }

            // return a new action object
            return {
                name: selectedItem,
                value: selectedItemTarget
            }
        })
    }
})

const actions = ref([]);

function handleCategoryUpdate(newValue, index) {
    console.log(newValue)
    if (newValue !== null && newValue !== undefined && newValue.value !== 'NULL') {
        actions.value[index].name = newValue.name;
        actions.value[index].value = newValue.value;
        actions.value[index].targetOptions = props.options[newValue.value]?.options || [];
        actions.value[index].targetName = '';
        actions.value[index].targetValue = '';
    }else{
        actions.value[index].name = '';
        actions.value[index].value = '';
        actions.value[index].targetOptions = [];
        actions.value[index].targetName = '';
        actions.value[index].targetValue = '';
    }
}

function handleTargetUpdate(newValue, index) {
    console.log(newValue);
    if (newValue !== null && newValue !== undefined) {
        actions.value[index].targetName = newValue.name;
        actions.value[index].targetValue = newValue.value;
    }

    const actionsMapped = actions.value.map(({name, value, targetName, targetValue}) => {
        return { name, value, targetName, targetValue };
    });

    emit('update:model-value', actionsMapped);
}

const addAction = () => {
    if (actions.value.length < props.maxLimit) {
        actions.value.push({
            name: '',
            value: '',
            targetOptions: [],
            targetName: '',
            targetValue: '',
        });
    }
};

const removeAction = (index) => {
    actions.value.splice(index, 1);
    /*const actionsMapped = actions.value.map(action => {
        return {
            name:
                Object.entries(options).find(([key, val]) =>
                    val.label === action.selectedItem.label
                )?.[0],
            value: action.value,
        };
    });*/
    emit('update:model-value', actions);
}

</script>
