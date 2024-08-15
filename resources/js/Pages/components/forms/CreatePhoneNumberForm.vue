<template>
    <form @submit.prevent="submitForm">
        <div>
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="#"
                       @click.prevent="selectedTab = 0"
                       :class="[selectedTab === 0 ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700', 'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium']"
                       :aria-current="selectedTab === 0 ? 'page' : undefined">
                        Basic
                    </a>
                    <a href="#"
                       @click.prevent="selectedTab = 1"
                       :class="[selectedTab === 1 ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700', 'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium']"
                       :aria-current="selectedTab === 1 ? 'page' : undefined">
                        Advanced
                    </a>
                </nav>
            </div>

            <div v-if="selectedTab === 0">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-1">
                        <LabelInputRequired :target="'destination_prefix'" :label="'Country Code'"/>
                        <div class="mt-2">
                            <InputField
                                v-model="form.destination_prefix"
                                type="text"
                                id="destination_prefix"
                                name="destination_prefix"
                                placeholder="Enter country code"
                                :error="errors?.destination_prefix && errors.destination_prefix.length > 0"/>
                        </div>
                        <div v-if="errors?.destination_prefix" class="mt-2 text-sm text-red-600">
                            {{ errors.destination_prefix[0] }}
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <LabelInputRequired :target="'destination_number'" :label="'Phone Number'"/>
                        <div class="mt-2">
                            <InputField
                                v-model="form.destination_number"
                                type="text"
                                id="destination_number"
                                name="destination_number"
                                placeholder="Enter phone number"
                                :error="errors?.destination_number && errors.destination_number.length > 0"/>
                        </div>
                        <div v-if="errors?.destination_number" class="mt-2 text-sm text-red-600">
                            {{ errors.destination_number[0] }}
                        </div>
                    </div>
                    <div class="sm:col-span-full">
                        <LabelInputOptional :target="'destination_actions'" :label="'Routing'"/>
                        <div class="border rounded-md pl-4 pr-4 pt-2 pb-2">
                            <MainDestinations
                                :options="options.timeout_destinations_categories"
                                :optionTargets="options.timeout_destinations_targets"
                                :selectedItems="form.destination_actions"
                                :customClass="'grid-cols-5'"
                                :maxLimit="6"
                                @update:model-value="handleDestinationActionsUpdate"
                            />
                        </div>
                    </div>
                    <div class="sm:col-span-full">
                        <LabelInputOptional :target="'destination_hold_music'" :label="'Music on Hold'"/>
                        <div class="mt-2">
                            <ComboBoxGroup :options="options.music_on_hold"
                                            :allowEmpty="true"
                                            :selectedItem="null"
                                            :placeholder="'Choose music on hold'"
                                            @update:model-value="handleMusicOnHoldUpdate"
                            />
                        </div>
                    </div>
                    <div class="sm:col-span-full">
                        <LabelInputOptional :target="'destination_description'" :label="'Description'"/>
                        <div class="mt-2">
                            <Textarea v-model="form.destination_description" name="destination_description" rows="2" />
                        </div>
                    </div>
                    <div class="sm:col-span-full">
                        <Toggle
                            :target="'destination_enabled'"
                            :label="'Enable'"
                            :enabled="form.destination_enabled"
                            @update:status="handleDestinationEnabled"
                        />
                    </div>
                </div>
            </div>

            <div v-if="selectedTab === 1">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-12">
                        <Toggle
                            :target="'destination_record'"
                            :label="'Check to save recordings'"
                            :enabled="form.destination_record"
                            @update:status="handleDestinationRecordEnabled"
                        />
                    </div>
                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'fax_uuid'" :label="'Fax detection'"/>
                        <div class="mt-2">
                            <ComboBox :options="options.faxes"
                                       :allowEmpty="true"
                                       :selectedItem="null"
                                       :placeholder="'Choose fax'"
                                       @update:model-value="handleFaxUpdate"
                            />
                        </div>
                    </div>
                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'destination_cid_name_prefix'" :label="'Caller ID name prefix'" />
                        <div class="mt-2">
                            <InputField
                                v-model="form.destination_cid_name_prefix"
                                type="text"
                                id="destination_cid_name_prefix"
                                name="destination_cid_name_prefix"
                                placeholder="Enter caller prefix"
                                :error="errors?.destination_cid_name_prefix && errors.destination_cid_name_prefix.length > 0"/>
                        </div>
                    </div>
                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'destination_conditions'" :label="'If the condition matches, perform action'"/>
                        <div class="border rounded-md pl-4 pr-4 pb-2">
                            <div v-for="(condition, index) in conditions" :key="condition.id">
                                <div class="mt-4 grid grid-cols-3 gap-x-2">
                                    <div>
                                        <SelectBox :options="page.props.conditions"
                                                   :selectedItem="condition.condition_field"
                                                   :placeholder="'Choose condition'"
                                                   @update:model-value="value => handleConditionUpdate(value, index)"
                                        />
                                    </div>
                                    <div v-if="condition.condition_field">
                                        <InputField
                                            v-model="condition.condition_expression"
                                            type="text"
                                            placeholder="Enter phone number"/>
                                    </div>
                                    <div v-else />
                                    <div class="relative">
                                        <div class="absolute right-0">
                                            <div id="delete_condition_tooltip" class="relative">
                                                <div class="absolute right-0">
                                                    <ejs-tooltip :content="'Remove condition'"
                                                                 position='RightTop' :target="'#delete_condition_tooltip'+index">
                                                        <MinusIcon @click="() => removeCondition(condition.id)"
                                                                   class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
                                                    </ejs-tooltip>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="condition.condition_field" class="grid grid-cols-3 gap-x-2 border-b pb-4">
                                    <ArrowCurvedRightIcon class="mt-2 h-10 w-10"/>
                                    <ConditionDestinations
                                        :options="options.timeout_destinations_categories"
                                        :optionTargets="options.timeout_destinations_targets"
                                        :selectedItems="[condition]"
                                        :customClass="'grid-cols-4 col-span-2'"
                                        @update:model-value="value => handleConditionActionsUpdate(value, index)"
                                    />
                                </div>
                            </div>
                            <div class="w-fit">
                                <ejs-tooltip v-if="conditions.length < conditionsMaxLimit" :content="'Add condition'"
                                             position='RightTop' target="#add_condition_tooltip">
                                    <div id="add_condition_tooltip">
                                        <PlusIcon @click="addCondition"
                                                  class="mt-2 h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer"/>
                                    </div>
                                </ejs-tooltip>
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'destination_accountcode'" :label="'Account code'" />
                        <div class="mt-2">
                            <InputField
                                v-model="form.destination_accountcode"
                                type="text"
                                id="destination_accountcode"
                                name="destination_accountcode"
                                placeholder="Enter account code"
                                :error="errors?.destination_accountcode && errors.destination_accountcode.length > 0"/>
                        </div>
                    </div>

                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'destination_distinctive_ring'" :label="'Distinctive ring'" />
                        <div class="mt-2">
                            <InputField
                                v-model="form.destination_distinctive_ring"
                                type="text"
                                id="destination_distinctive_ring"
                                name="destination_distinctive_ring"
                                placeholder="Enter distinctive ring"
                                :error="errors?.destination_distinctive_ring && errors.destination_distinctive_ring.length > 0"/>
                        </div>
                    </div>

                    <div v-if="page.props.auth.can.domain_select && page.props.auth.can.destination_edit_domain" class="sm:col-span-12">
                        <LabelInputRequired :target="'domain_uuid'" :label="'Owned By (Company Name)'"/>
                        <div class="mt-2">
                            <ComboBox :options="options.domains"
                                       :selectedItem="form.domain_uuid"
                                       :placeholder="'Choose company'"
                                       @update:model-value="handleDomainUpdate"
                                       :error="errors?.domain_uuid && errors.domain_uuid.length > 0"
                            />
                        </div>
                        <div v-if="errors?.domain_uuid" class="mt-2 text-sm text-red-600">
                            {{ errors.domain_uuid[0] }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="border-t mt-4 sm:mt-4 ">
            <div class="mt-4 sm:mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit"
                        class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                        ref="saveButtonRef" :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Save
                </button>
                <button type="button"
                        class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                        @click="emits('cancel')" ref="cancelButtonRef">Cancel
                </button>
            </div>
        </div>
    </form>
</template>

<script setup>
import {defineProps, reactive, ref} from 'vue'
import LabelInputRequired from "../general/LabelInputRequired.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import Toggle from "../general/Toggle.vue";
import ComboBoxGroup from "../general/ComboBoxGroup.vue";
import MainDestinations from "../general/ActionSelect.vue";
import ConditionDestinations from "../general/ActionSelect.vue";
import InputField from "../general/InputField.vue";
import Textarea from "../general/Textarea.vue";
import {usePage} from "@inertiajs/vue3";
import Spinner from "../general/Spinner.vue";
import SelectBox from "../general/SelectBox.vue";
import ComboBox from "../general/ComboBox.vue";
import {MinusIcon, PlusIcon} from "@heroicons/vue/24/solid/index.js";
import ArrowCurvedRightIcon from "../icons/ArrowCurvedRightIcon.vue";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";

const props = defineProps({
    item: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const conditions = ref([])

const conditionsMaxLimit = 6;

const selectedTab = ref(0)

const form = reactive({
    domain_uuid: page.props.domain,
    fax_uuid: null,
    destination_prefix: null,
    destination_number: null,
    destination_actions: null,
    destination_hold_music: null,
    destination_description: null,
    destination_enabled: true,
    destination_record: false,
    destination_cid_name_prefix: null,
    destination_accountcode: null,
    destination_distinctive_ring: null,
    destination_conditions: [],
    destination_context: 'public',
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);

const submitForm = () => {
    // Transform conditions before submit
    form.destination_conditions = conditions.value.map(condition => {
        return {
            "condition_field": condition.condition_field,
            "condition_expression": condition.condition_expression,
            "value": {
                "value": condition.value
            }
        }
    })
    emits('submit', form); // Emit the event with the form data
}

const handleMusicOnHoldUpdate = (newSelectedItem) => {
    if (newSelectedItem !== null && newSelectedItem.value !== undefined) {
        form.destination_hold_music = newSelectedItem.value;
    } else {
        form.destination_hold_music = null;
    }
}

const handleDestinationEnabled = (newSelectedItem) => {
    form.destination_enabled = newSelectedItem;
}

const handleDestinationRecordEnabled = (newSelectedItem) => {
    form.destination_record = newSelectedItem;
}

const handleDomainUpdate = (newSelectedItem) => {
    form.domain_uuid = newSelectedItem.value;
    emits('domain-selected', newSelectedItem.value); // Emit 'domain-selected' event when the domain is updated
}

const handleFaxUpdate = (newSelectedItem) => {
    if (newSelectedItem !== null && newSelectedItem.value !== undefined) {
        form.fax_uuid = newSelectedItem.value;
    } else {
        form.fax_uuid = null;
    }
}

const handleConditionUpdate = (newSelectedItem, index) => {
    if (newSelectedItem !== null && newSelectedItem !== undefined) {
        conditions.value[index].condition_field = newSelectedItem.value;
    }
}

const handleDestinationActionsUpdate = (newSelectedItem) => {
    form.destination_actions = newSelectedItem;
}

const addCondition = () => {
    const newCondition = {
        id: Math.random().toString(36).slice(2, 7),
        condition_field: null,
        condition_expression: "",
        selectedCategory: "",
        categoryTargets: [],
        value: null
    };
    conditions.value.push(newCondition);
}

const handleConditionActionsUpdate = (newSelectedItem, index) => {
    if (newSelectedItem !== null && newSelectedItem !== undefined) {
        conditions.value[index].value = newSelectedItem[0].value.value;
    }
}

const removeCondition = (id) => {
    conditions.value = conditions.value.filter(el => el.id !== id);
}

</script>


<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>
