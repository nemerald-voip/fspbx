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
                    <ExclamationCircleIcon v-if="((errors?.extension || errors?.wake_up_time || errors?.status) && item.slug === 'settings')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">

            <div v-if="activeTab === 'settings'">

                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Settings</h3>
                            <p class="mt-1 text-sm text-gray-500">Update wakeup call settings.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                            <div class="sm:col-span-3 space-y-3">
                                <LabelInputRequired label="Date" class="truncate" />
                                <VueDatePicker v-model="date" :range="false" :enable-time-picker="false"
                                    :min-date="new Date()" auto-apply @update:model-value="handleDate"
                                    :timezone="options.timezone" placeholder="Select Date">
                                </VueDatePicker>
                            </div>

                            <div class="sm:col-span-3 space-y-3">
                                <LabelInputRequired label="Time" class="truncate" />
                                <VueDatePicker v-model="time" time-picker auto-apply :is-24="false"
                                    @update:model-value="handleTime" :timezone="options.timezone" placeholder="Select Time">
                                </VueDatePicker>
                            </div>

                            <div class="col-span-6 -mt-5">
                                <div v-if="errors?.wake_up_time" class="mt-2 text-xs text-red-600">
                                    {{ errors.wake_up_time[0] }}
                                </div>
                            </div>


                            <div class="col-span-6 sm:col-span-3 space-y-3">
                                <LabelInputRequired target="" label="Extension" />
                                <ComboBox :options="options.extensions" :selectedItem="options.wakeup_call.extension_uuid"
                                    :search="true" placeholder="Choose Extension"
                                    @update:model-value="handleExtensionUpdate"
                                    :error="errors?.extension && errors.extension.length > 0" />
                                <div v-if="errors?.extension" class="mt-2 text-xs text-red-600">
                                    {{ errors.extension[0] }}
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3 space-y-3">
                                <LabelInputRequired target="" label="Status" />
                                <ComboBox :options="options.status_options" :selectedItem="options.wakeup_call.status"
                                    :search="true" placeholder="Choose Status" @update:model-value="handleStatusUpdate"
                                    :error="errors?.status && errors.status.length > 0" />
                                <div v-if="errors?.status" class="mt-2 text-xs text-red-600">
                                    {{ errors.status[0] }}
                                </div>
                            </div>

                            <div class="divide-y divide-gray-200 col-span-6">

                                <Toggle label="Daily Repeat"
                                    description="Enable this option to automatically repeat the wake-up call every day at the scheduled time. If unchecked, the call will only be made once. Snoozes and missed calls will be handled separately and do not affect the daily repeat setting."
                                    v-model="form.recurring" customClass="py-4" />

                            </div>

                        </div>
                    </div>
                </div>
                <div class="bg-gray-100 px-4 py-3 text-right sm:px-6">
                    <button type="submit"
                        class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                </div>


            </div>


        </form>
    </div>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import LabelInputRequired from "../general/LabelInputRequired.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import Toggle from "../general/Toggle.vue";
import CallRouting from "../general/ActionSelect.vue";
import InputField from "../general/InputField.vue";
import Textarea from "../general/Textarea.vue";
import { usePage } from "@inertiajs/vue3";
import Spinner from "../general/Spinner.vue";
import ComboBox from "../general/ComboBox.vue";
import { Cog6ToothIcon } from '@heroicons/vue/24/outline';
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import VueDatePicker from '@vuepic/vue-datepicker';
import { zonedTimeToUtc, utcToZonedTime } from 'date-fns-tz';
import { parseISO } from 'date-fns';
import Popover from "@generalComponents/Popover.vue";


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

const conditionsMaxLimit = 6;

const form = reactive({
    uuid: props.options.wakeup_call.uuid,
    wake_up_time: props.options.wakeup_call.wake_up_time,
    extension: props.options.wakeup_call.extension_uuid,
    recurring: props.options.wakeup_call.recurring,
    status: props.options.wakeup_call.status,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);

// Map icon names to their respective components
const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
};

const handleExtensionUpdate = (newSelectedItem) => {
    form.extension = newSelectedItem ? newSelectedItem.value : null;
};

const handleStatusUpdate = (newSelectedItem) => {
    form.status = newSelectedItem ? newSelectedItem.value : null;
};

// Convert `wake_up_time` to the user's timezone
const wakeUpDateTime = props.options.wakeup_call.wake_up_time
    ? utcToZonedTime(parseISO(props.options.wakeup_call.wake_up_time), props.options.timezone)
    : new Date();

// VueDatePicker expects **Date object** for the date picker
const date = ref(wakeUpDateTime);
// VueDatePicker expects time as an **object** `{ hours, minutes }`
const time = ref({
    hours: wakeUpDateTime.getHours(),
    minutes: wakeUpDateTime.getMinutes()
});


// Update `wake_up_time` when date changes
const handleDate = (newDate) => {
    if (!newDate) return;
    updateWakeUpTime(newDate, time.value);
};

// Update `wake_up_time` when time changes
const handleTime = (newTime) => {
    if (!newTime) return;
    updateWakeUpTime(date.value, newTime);
};

// Merge date and time, then convert to UTC before saving
const updateWakeUpTime = (newDate, newTime) => {
    // Ensure newDate is a valid Date object
    const validDate = new Date(newDate);

    // Ensure newTime is an object with hours and minutes
    if (!newTime || typeof newTime.hours === 'undefined' || typeof newTime.minutes === 'undefined') {
        console.error("Invalid time object:", newTime);
        return;
    }

    // Set the correct hours and minutes
    validDate.setHours(newTime.hours, newTime.minutes, 0, 0); // Ensure no milliseconds

    // Convert to UTC before storing
    form.wake_up_time = zonedTimeToUtc(validDate, props.options.timezone).toISOString();
};

const submitForm = () => {
    // console.log(form);
    emits('submit', form); // Emit the event with the form data
}


const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

</script>

<style>
@import '@vuepic/vue-datepicker/dist/main.css';

div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>
