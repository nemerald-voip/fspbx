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
                            <!-- <div class="col-span-3 sm:col-span-2">
                                <LabelInputRequired target="voicemail_id" label="Voicemail Extension" class="truncate" />
                                <InputField v-model="form.voicemail_id" type="text" name="voicemail_id" id="voicemail_id"
                                    class="mt-2" :error="!!errors?.voicemail_id" />
                                <div v-if="errors?.voicemail_id" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_id[0] }}
                                </div>
                            </div>

                            <div class="col-span-3 sm:col-span-2">
                                <LabelInputOptional target="voicemail_password" label="Password" class="truncate" />
                                <InputFieldWithIcon v-model="form.voicemail_password" id="voicemail_password"
                                    name="voicemail_password" type="text" autocomplete="shut-up-google"
                                    :error="!!errors?.voicemail_password" class="password-field">
                                    <template #icon>
                                        <VisibilityIcon @click="togglePasswordVisibility"
                                            class="h-8 w-8 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer"
                                            aria-hidden="true" />
                                    </template>
                                </InputFieldWithIcon>
                                <div v-if="errors?.voicemail_password" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_password[0] }}
                                </div>
                            </div> -->

                            <!-- <div class="col-span-6 sm:col-span-3">
                                <LabelInputOptional target="voicemail_mail_to" label="Email address" class="truncate" />
                                <InputField v-model="form.voicemail_mail_to" type="text" name="voicemail_mail_to"
                                    id="voicemail_mail_to" class="mt-2" :error="!!errors?.voicemail_mail_to" />
                                <div v-if="errors?.voicemail_mail_to" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_mail_to[0] }}
                                </div>
                            </div> -->

                            <div class="col-span-3 sm:col-span-2">
                                <LabelInputOptional :target="'destination_hold_music'" :label="'Music on Hold'" />
                                <div class="mt-2">
                                    <ComboBoxGroup :options="options.music_on_hold" :allowEmpty="true"
                                        :selectedItem="form.destination_hold_music" :placeholder="'Choose music on hold'"
                                        @update:model-value="handleMusicOnHoldUpdate" />
                                </div>
                            </div>

                            <div class="col-span-6">
                                <LabelInputOptional target="destination_description" label="Description" class="truncate" />
                                <div class="mt-2">
                                    <Textarea v-model="form.destination_description" id="destination_description"
                                        name="destination_description" rows="2" :error="!!errors?.destination_description" />
                                </div>
                                <div v-if="errors?.destination_description" class="mt-2 text-xs text-red-600">
                                    {{ errors.destination_description[0] }}
                                </div>
                            </div>


                            <!-- <div class="divide-y divide-gray-200 col-span-6">

                                <Toggle v-if="localOptions.permissions.manage_voicemail_transcription"
                                    label="Voicemail Transcription"
                                    description="Convert voicemail messages to text using AI-powered transcription."
                                    v-model="form.voicemail_transcription_enabled" customClass="py-4" />

                                <Toggle label="Attach File to Email Notifications"
                                    description="Attach voicemail recording file to the email notification."
                                    v-model="form.voicemail_email_attachment" customClass="py-4" />

                                <Toggle v-if="localOptions.permissions.manage_voicemail_auto_delete"
                                    label="Automatically Delete Voicemail After Email"
                                    description="Remove voicemail from the cloud once the email is sent."
                                    v-model="form.voicemail_delete" customClass="py-4" />

                            </div> -->



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
            </div>

            <div v-if="activeTab === 'call_routing'">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Call routing</h3>
                            <p class="mt-1 text-sm text-gray-500">Ensure calls are routed to the right team every time. Select a routing fucntin below to fit your business needs.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                            <!-- <div class="sm:col-span-1">
                                <LabelInputRequired :target="'destination_prefix'" :label="'Country Code'" />
                                <div class="mt-2">
                                    <InputField v-model="form.destination_prefix" type="text" id="destination_prefix"
                                        name="destination_prefix" placeholder="Enter country code" disabled="disabled" />
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <LabelInputRequired :target="'destination_number'" :label="'Phone Number'" />
                                <div class="mt-2">
                                    <InputField v-model="form.destination_number" type="text" id="destination_number"
                                        name="destination_number" placeholder="Enter phone number" disabled="disabled" />
                                </div>
                            </div> -->
                            <div class="sm:col-span-full">
                                <LabelInputOptional :target="'destination_actions'" :label="'Call Routing'" />
                                <div class="border rounded-md pl-4 pr-4 pt-2 pb-2">
                                    <CallRouting :routingTypes="options.routing_types" :selectedItems="form.destination_actions"
                                        :customClass="'grid-cols-5'" :maxLimit="6"
                                        @update:model-value="handleDestinationActionsUpdate" />
                                </div>
                            </div>

                            <div class="sm:col-span-full">
                                <LabelInputOptional :target="'destination_description'" :label="'Description'" />
                                <div class="mt-2">
                                    <Textarea v-model="form.destination_description" name="destination_description" rows="2" />
                                </div>
                            </div>
                            <div class="sm:col-span-full">
                                <Toggle :target="'destination_enabled'" :label="'Enable'" :enabled="form.destination_enabled"
                                    @update:status="handleDestinationEnabled" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'advanced'">
                <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                    <div class="sm:col-span-12">
                        <Toggle :target="'destination_record'" :label="'Check to save recordings'"
                            :enabled="form.destination_record" @update:status="handleDestinationRecordEnabled" />
                    </div>
                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'fax_uuid'" :label="'Fax detection'" />
                        <div class="mt-2">
                            <ComboBox :options="options.faxes" :allowEmpty="true" :selectedItem="form.fax_uuid"
                                :placeholder="'Choose fax'" @update:model-value="handleFaxUpdate" />
                        </div>
                    </div>
                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'destination_cid_name_prefix'" :label="'Caller ID name prefix'" />
                        <div class="mt-2">
                            <InputField v-model="form.destination_cid_name_prefix" type="text"
                                id="destination_cid_name_prefix" name="destination_cid_name_prefix"
                                placeholder="Enter caller prefix"
                                :error="errors?.destination_cid_name_prefix && errors.destination_cid_name_prefix.length > 0" />
                        </div>
                    </div>
                    <div class="sm:col-span-12">
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
                    </div>
                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'destination_accountcode'" :label="'Account code'" />
                        <div class="mt-2">
                            <InputField v-model="form.destination_accountcode" type="text" id="destination_accountcode"
                                name="destination_accountcode" placeholder="Enter account code"
                                :error="errors?.destination_accountcode && errors.destination_accountcode.length > 0" />
                        </div>
                    </div>

                    <div class="sm:col-span-12">
                        <LabelInputOptional :target="'destination_distinctive_ring'" :label="'Distinctive ring'" />
                        <div class="mt-2">
                            <InputField v-model="form.destination_distinctive_ring" type="text"
                                id="destination_distinctive_ring" name="destination_distinctive_ring"
                                placeholder="Enter distinctive ring"
                                :error="errors?.destination_distinctive_ring && errors.destination_distinctive_ring.length > 0" />
                        </div>
                    </div>

                    <div v-if="page.props.auth.can.domain_select && page.props.auth.can.destination_edit_domain"
                        class="sm:col-span-12">
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

        </form>
    </div>
</template>

<script setup>
import { defineProps, reactive, ref, onBeforeMount } from 'vue'
import LabelInputRequired from "../general/LabelInputRequired.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import Toggle from "../general/Toggle.vue";
import ComboBoxGroup from "../general/ComboBoxGroup.vue";
import CallRouting from "../general/ActionSelect.vue";
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
import { PhoneIcon } from "@heroicons/vue/24/outline";



const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

// Make a local reactive copy of options to manipulate in this component
const localOptions = reactive({ ...props.options });

const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);

const conditions = ref([])

const actions = ref([]);

const conditionsMaxLimit = 6;

const selectedTab = ref(0)

const form = reactive({
    fax_uuid: props.options.phone_number.fax_uuid,
    destination_prefix: props.options.phone_number.destination_prefix,
    destination_number: props.options.phone_number.destination_number,
    destination_actions: props.options.phone_number.destination_actions,
    destination_hold_music: props.options.phone_number.destination_hold_music,
    destination_description: props.options.phone_number.destination_description,
    destination_enabled: props.options.phone_number.destination_enabled,
    destination_record: props.options.phone_number.destination_record,
    destination_cid_name_prefix: props.options.phone_number.destination_cid_name_prefix,
    destination_accountcode: props.options.phone_number.destination_accountcode,
    destination_distinctive_ring: props.options.phone_number.destination_distinctive_ring,
    destination_conditions: props.options.phone_number.destination_conditions,
    destination_context: props.options.phone_number.destination_context,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);

// Map icon names to their respective components
const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'MusicalNoteIcon': MusicalNoteIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
    'PhoneIcon': PhoneIcon
};

// onBeforeMount(() => {
//     if (form.destination_actions) {
//         form.destination_actions = form.destination_actions.map(action => {
//             const targetValue = action.targetValue.toLowerCase();
//             let matchedAction = action;
//             for (const [key, actionGroup] of Object.entries(props.options.actions)) {
//                 const option = actionGroup.options.find(opt => opt.value.toLowerCase().includes(targetValue));
//                 if (option) {
//                     matchedAction = {
//                         name: actionGroup.name,
//                         value: key,
//                         targetName: option.name,
//                         targetValue: option.value,
//                     };
//                     break;
//                 }
//             }
//             return matchedAction;
//         });
//     }
//     if (form.destination_conditions) {
//         conditions.value = form.destination_conditions.map(condition => {
//             const targetValue = condition.condition_target.targetValue.toLowerCase();
//             let targetMatched = null;
//             for (const [key, actionGroup] of Object.entries(props.options.actions)) {
//                 const option = actionGroup.options.find(opt => opt.value.toLowerCase().includes(targetValue));
//                 if (option) {
//                     targetMatched = {
//                         name: actionGroup.name,
//                         value: key,
//                         targetName: option.name,
//                         targetValue: option.value,
//                     };
//                     break;
//                 }
//             }
//             return {
//                 ...condition,
//                 id: Math.random().toString(36).slice(2, 7),  // Assuming `generateUniqueId()` is a function to generate unique IDs
//                 condition_target: targetMatched ? [targetMatched] : condition.condition_target,
//             };
//         });
//     }
// });

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
        conditions.value[index].condition_target = [];
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
