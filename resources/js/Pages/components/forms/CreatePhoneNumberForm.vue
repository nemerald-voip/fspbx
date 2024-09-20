<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in localOptions.navigation" :key="item.name" href="#"
                    :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                    @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                    <component :is="iconComponents[item.icon]"
                        :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                        aria-hidden="true" />
                    <span class="truncate">{{ item.name }}</span>
                    <ExclamationCircleIcon v-if="((errors?.voicemail_id || errors?.voicemail_password) && item.slug === 'settings') ||
                        (errors?.voicemail_alternate_greet_id && item.slug === 'advanced')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">

            <div v-if="activeTab === 'settings'">

                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Settings</h3>

                            <Toggle label="Status" v-model="form.destination_enabled" />

                            <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                        </div>

                        <div class="grid grid-cols-6 gap-6">


                            <div class="sm:col-span-1">
                                <LabelInputRequired :target="'destination_prefix'" :label="'Country Code'" />
                                <div class="mt-2">
                                    <InputField v-model="form.destination_prefix" type="text" id="destination_prefix"
                                        name="destination_prefix" placeholder="Enter country code"
                                        :error="errors?.destination_prefix && errors.destination_prefix.length > 0" />
                                </div>
                                <div v-if="errors?.destination_prefix" class="mt-2 text-sm text-red-600">
                                    {{ errors.destination_prefix[0] }}
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <LabelInputRequired :target="'destination_number'" :label="'Phone Number'" />
                                <div class="mt-2">
                                    <InputField v-model="form.destination_number" type="text" id="destination_number"
                                        name="destination_number" placeholder="Enter phone number"
                                        :error="errors?.destination_number && errors.destination_number.length > 0" />
                                </div>
                                <div v-if="errors?.destination_number" class="mt-2 text-sm text-red-600">
                                    {{ errors.destination_number[0] }}
                                </div>
                            </div>

                            <div class="col-span-6">
                                <LabelInputOptional target="destination_description" label="Description" class="truncate" />
                                <div class="mt-2">
                                    <Textarea v-model="form.destination_description" id="destination_description"
                                        name="destination_description" rows="2"
                                        :error="!!errors?.destination_description" />
                                </div>
                                <div v-if="errors?.destination_description" class="mt-2 text-xs text-red-600">
                                    {{ errors.destination_description[0] }}
                                </div>
                            </div>


                        </div>

                    </div>
                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">

                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                            ref="saveButtonRef" :disabled="isSubmitting">
                            <Spinner :show="isSubmitting" />
                            Save
                        </button>
                    </div>
                </div>

                <div class="mt-6 shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Call routing</h3>
                            <p class="mt-1 text-sm text-gray-500">Ensure calls are routed to the right team every time.
                                Select a routing option below to fit your business needs.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                            <div class="sm:col-span-full space-y-3">
                                <LabelInputOptional :target="'destination_actions'" :label="'Send calls to'" />
                                <CallRouting v-model="form.routing_options" :routingTypes="options.routing_types"
                                    :selectedItems="form.routing_options" :maxRouteLimit="6"
                                    :optionsUrl="options.routes.get_routing_options"
                                    @update:model-value="handleDestinationActionsUpdate" />
                            </div>

                        </div>
                    </div>
                </div>

                


            </div>


            <div v-if="activeTab === 'advanced'">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Advanced</h3>
                        </div>

                        <div class="divide-y divide-gray-200 col-span-6">
                            <div class="col-span-6">
                                <Toggle :target="'destination_record'" :label="'Record Inbound Calls'"
                                    description="Enable this setting to automatically record all inbound calls for this phone number. Once activated, every incoming call will be captured and stored for future reference, ensuring that no important conversation is missed. Note: Ensure compliance with local call recording laws before enabling."
                                    v-model="form.destination_record" customClass="py-4" />
                            </div>

                            <div class="col-span-6">
                                <Toggle :target="'destination_record'" :label="'Enable Fax Machine'"
                                    description="Activate this setting if calls will be routed direclty to a physical fax machine. This ensures proper handling of fax transmissions."
                                    v-model="form.destination_type_fax" customClass="py-4" />
                            </div>

                        </div>

                        <div class="grid grid-cols-6 gap-6">

                            <div class="col-span-6">
                                <LabelInputOptional :target="'fax_uuid'" :label="'Fax detection'" />
                                <div class="mt-2">
                                    <ComboBox :options="options.faxes" :allowEmpty="true" :selectedItem="form.fax_uuid"
                                        :placeholder="'Choose fax'" @update:model-value="handleFaxUpdate" />
                                </div>
                            </div>
                            <div class="col-span-6">
                                <LabelInputOptional :target="'destination_cid_name_prefix'"
                                    :label="'Caller ID name prefix'" />
                                <div class="mt-2">
                                    <InputField v-model="form.destination_cid_name_prefix" type="text"
                                        id="destination_cid_name_prefix" name="destination_cid_name_prefix"
                                        placeholder="Enter caller prefix"
                                        :error="errors?.destination_cid_name_prefix && errors.destination_cid_name_prefix.length > 0" />
                                </div>
                            </div>

                            <!-- Conditions -->
                            <!-- <div class="col-span-6">
                                <LabelInputOptional :target="'destination_conditions'"
                                    :label="'If the condition matches, perform action'" />
                                <div class="border rounded-md pl-4 pr-4 pb-2">
                                    <div v-for="(condition, index) in conditions" :key="condition.id">
                                        <div class="mt-4 grid grid-cols-3 gap-x-2">
                                            <div>
                                                <ComboBox :options="page.props.conditions" :selectedItem="condition.condition_field"
                                                    :placeholder="'Choose condition'"
                                                    @update:model-value="value => handleConditionUpdate(value, index)" />
                                            </div>
                                            <div v-if="condition.condition_field">
                                                <InputField v-model="condition.condition_expression" type="text"
                                                    placeholder="Enter phone number" />
                                            </div>
                                            <div v-else />
                                            <div class="relative">
                                                <div class="absolute right-0">
                                                    <div id="delete_condition_tooltip" class="relative">
                                                        <div class="absolute right-0">
                                                            <ejs-tooltip :content="'Remove condition'" position='RightTop'
                                                                :target="'#delete_condition_tooltip' + index">
                                                                <MinusIcon @click="() => removeCondition(condition.id)"
                                                                    class="h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer" />
                                                            </ejs-tooltip>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="condition.condition_expression" class="grid grid-cols-3 gap-x-2 border-b pb-4">
                                            <ArrowCurvedRightIcon class="mt-2 h-10 w-10" />
                                            <ConditionDestinations :options="options.actions"
                                                :selectedItems="condition.condition_target" :initWith="1"
                                                :customClass="'grid-cols-4 col-span-2'"
                                                @update:model-value="value => handleConditionActionsUpdate(value, index)" />
                                        </div>
                                    </div>
                                    <div class="w-fit">
                                        <ejs-tooltip v-if="conditions.length < conditionsMaxLimit" :content="'Add condition'"
                                            position='RightTop' target="#add_condition_tooltip">
                                            <div id="add_condition_tooltip">
                                                <PlusIcon @click="addCondition"
                                                    class="mt-2 h-8 w-8 border text-black-500 hover:text-black-900 active:h-8 active:w-8 cursor-pointer" />
                                            </div>
                                        </ejs-tooltip>
                                    </div>
                                </div>
                            </div> -->
                            <div class="col-span-6">
                                <LabelInputOptional :target="'destination_accountcode'" :label="'Account code'" />
                                <div class="mt-2">
                                    <InputField v-model="form.destination_accountcode" type="text"
                                        id="destination_accountcode" name="destination_accountcode"
                                        placeholder="Enter account code"
                                        :error="errors?.destination_accountcode && errors.destination_accountcode.length > 0" />
                                </div>
                            </div>

                            <div class="col-span-6">
                                <LabelInputOptional :target="'destination_distinctive_ring'" :label="'Distinctive ring'" />
                                <div class="mt-2">
                                    <InputField v-model="form.destination_distinctive_ring" type="text"
                                        id="destination_distinctive_ring" name="destination_distinctive_ring"
                                        placeholder="Enter distinctive ring"
                                        :error="errors?.destination_distinctive_ring && errors.destination_distinctive_ring.length > 0" />
                                </div>
                            </div>

                            <div v-if="page.props.auth.can.domain_select && page.props.auth.can.destination_edit_domain"
                                class="col-span-6">
                                <LabelInputRequired :target="'domain_uuid'" :label="'Owned By (Company Name)'" />
                                <div class="mt-2">
                                    <ComboBox :options="options.domains" :selectedItem="form.domain_uuid"
                                        :placeholder="'Choose company'" @update:model-value="handleDomainUpdate"
                                        :error="errors?.domain_uuid && errors.domain_uuid.length > 0" />
                                </div>
                                <div v-if="errors?.domain_uuid" class="mt-2 text-sm text-red-600">
                                    {{ errors.domain_uuid[0] }}
                                </div>
                            </div>
                        </div>


                    </div>
                    <div class="bg-gray-100 px-4 py-3 text-right sm:px-6">

                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                            ref="saveButtonRef" :disabled="isSubmitting">
                            <Spinner :show="isSubmitting" />
                            Save
                        </button>
                    </div>
                </div>
            </div>


        </form>
    </div>
</template>

<script setup>
import { defineProps, reactive, ref } from 'vue'
import LabelInputRequired from "../general/LabelInputRequired.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import Toggle from "../general/Toggle.vue";
import ComboBoxGroup from "../general/ComboBoxGroup.vue";
import MainDestinations from "../general/ActionSelect.vue";
import ConditionDestinations from "../general/ActionSelect.vue";
import InputField from "../general/InputField.vue";
import Textarea from "../general/Textarea.vue";
import { usePage } from "@inertiajs/vue3";
import Spinner from "../general/Spinner.vue";
import SelectBox from "../general/SelectBox.vue";
import ComboBox from "../general/ComboBox.vue";
import { MinusIcon, PlusIcon } from "@heroicons/vue/24/solid/index.js";
import ArrowCurvedRightIcon from "../icons/ArrowCurvedRightIcon.vue";
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import { Cog6ToothIcon, MusicalNoteIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import CallRouting from "../general/ActionSelect.vue";




const props = defineProps({
    item: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const localOptions = reactive({ ...props.options });

const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);

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
    destination_type_fax: false,
    destination_cid_name_prefix: null,
    destination_accountcode: null,
    destination_distinctive_ring: null,
    destination_conditions: null,
    destination_context: 'public',
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);

const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'MusicalNoteIcon': MusicalNoteIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
};

const submitForm = () => {
    // Transform conditions before submit
    form.destination_conditions = conditions.value.map(condition => {
        return {
            "condition_field": condition.condition_field,
            "condition_expression": condition.condition_expression,
            "condition_target": {
                "targetValue": condition.condition_target[0]?.targetValue ?? null
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

const handleConditionUpdate = (newValue, index) => {
    if (newValue !== null && newValue !== undefined) {
        conditions.value[index].condition_field = newValue.value;
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
        condition_target: []
    };
    conditions.value.push(newCondition);
}

const handleConditionActionsUpdate = (newValue, index) => {
    if (newValue !== null && newValue !== undefined) {
        conditions.value[index].condition_target.push(newValue[0]);
    }
}

const removeCondition = (id) => {
    conditions.value = conditions.value.filter(el => el.id !== id);
}

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};


</script>


<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>
