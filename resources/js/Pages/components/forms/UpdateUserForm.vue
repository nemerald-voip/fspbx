<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform  rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:p-6">

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full">
                                <div class="flex justify-center items-center space-x-3">
                                    <div>
                                        <svg class="animate-spin  h-10 w-10 text-blue-600"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4">
                                            </circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>


                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    timezone:options.item.timezone,
                                    user_email:options.item.user_email,
                                    // group_name: options.item.group_name ?? null,
                                    // members: options.item.domain_group_relations
                                    //     ? options.item.domain_group_relations.map(r => r.domain_uuid)
                                    //     : []

                                }">

                                <StaticElement name="h4" tag="h4" content="Update User" />

                                <TextElement name="first_name" label="First Name" placeholder="Enter First Name"
                                    :floating="false" />
                                <TextElement name="last_name" label="Last Name" placeholder="Enter Last Name"
                                    :floating="false" />
                                <TextElement name="user_email" label="Email" placeholder="Enter Email" :floating="false" />
                                <TagsElement name="groups" :close-on-select="false" :search="true" :items="null" label="Roles" input-type="search" autocomplete="off" placeholder="Select Roles" :floating="false"
                                    :strict="false" />
                                <SelectElement name="timezone" :groups="true" :items="options.timezones" :search="true"
                                :native="false" label="Time Zone" input-type="search" autocomplete="off"
                                 :floating="false" :strict="false" placeholder="Select Time Zone" />
                                <ToggleElement name="user_enabled" text="Status" true-value="true" false-value="false" />

                                <GroupElement name="container_3" />
                                <ButtonElement name="reset" button-label="Cancel" :secondary="true" :resets="true"
                                    @click="emit('close')" :columns="{
                                        container: 6,
                                    }" />

                                <ButtonElement name="submit" button-label="Save" :submits="true" align="right" :columns="{
                                    container: 6,
                                }" />
                            </Vueform>
                        </DialogPanel>


                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";


const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
});

const form$ = ref(null)

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData
    // console.log(requestData);

    return await form$.$vueform.services.axios.put(props.options.routes.update_route, requestData)
};

function clearErrorsRecursive(el$) {
    // clear this element’s errors
    el$.messageBag?.clear()

    // if it has child elements, recurse into each
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}

const handleResponse = (response, form$) => {
    // Clear form including nested elements 
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    // Display custom errors for elements
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append('Could not prepare form')
            break

        // Error occured because response status is outside of 2xx
        case 'submit':
            emit('error', error);
            console.log(error) // AxiosError object
            // console.log(error.response) // axios response
            // console.log(error.response.status) // HTTP status code
            // console.log(error.response.data) // response data

            // console.log(error.response.data.errors)


            break

        // Request cancelled (no response object)
        case 'cancel':
            console.log(error) // Error object

            form$.messageBag.append('Request cancelled')
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append('Couldn\'t submit form')
            break
    }
}

const handleHolidayTypeChange = (newValue, oldValue, el$) => {

    if (newValue != oldValue) {
        el$.form$.clear()
        el$.form$.update({
            holiday_type: newValue
        })
    }

}

const handleUSHolidayUpdate = (newValue, oldValue, el$) => {

    if (newValue != oldValue) {

        // find the holiday whose value matches newValue
        const match = usHolidays.find(h =>
            h.value.mon === newValue.value.mon
            && h.value.mday === newValue.value.mday
            && h.value.mweek === newValue.value.mweek
            && h.value.wday === newValue.value.wday
        );

        // pull its label (or fall back to an empty string)
        const label = match?.label ?? '';

        el$.form$.update({
            mday: newValue.value.mday,
            mon: newValue.value.mon,
            mweek: newValue.value.mweek,
            wday: newValue.value.wday,
            description: label,
        })
    }

}



// Month (1=Jan … 12=Dec)
const monthOptions = [
    { value: '1', label: 'January' },
    { value: '2', label: 'February' },
    { value: '3', label: 'March' },
    { value: '4', label: 'April' },
    { value: '5', label: 'May' },
    { value: '6', label: 'June' },
    { value: '7', label: 'July' },
    { value: '8', label: 'August' },
    { value: '9', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

// Day of Month (1–31)
const dayOfMonthOptions = Array.from({ length: 31 }, (_, i) => ({
    value: String(i + 1),
    label: String(i + 1),
}));

// Week of Year (1–53)
const weekOfYearOptions = Array.from({ length: 53 }, (_, i) => ({
    value: String(i + 1),
    label: String(i + 1),
}));

// Week of Month (1=first … 5=fifth, 6=last)
const weekOfMonthOptions = [
    { value: '1', label: '1 (First)' },
    { value: '2', label: '2 (Second)' },
    { value: '3', label: '3 (Third)' },
    { value: '4', label: '4 (Fourth)' },
    { value: '5', label: '5 (Fifth)' },
    { value: '6', label: '6 (Last)' },
];

// Day of Week (1=Sunday … 7=Saturday)
const dayOfWeekOptions = [
    { value: '1', label: 'Sunday' },
    { value: '2', label: 'Monday' },
    { value: '3', label: 'Tuesday' },
    { value: '4', label: 'Wednesday' },
    { value: '5', label: 'Thursday' },
    { value: '6', label: 'Friday' },
    { value: '7', label: 'Saturday' },
];

const usHolidays = [
    {
        label: "New Year's Day (January 1)",
        value: { mon: "1", wday: "", mday: "1", mweek: "" }
    },
    {
        label: "Martin Luther King Jr. Day (3rd Monday in January)",
        value: { mon: "1", wday: "2", mday: "15-21", mweek: "" }
    },
    {
        label: "Valentine's Day (February 14)",
        value: { mon: "2", wday: "", mday: "14", mweek: "" }
    },
    {
        label: "Presidents' Day (3rd Monday in February)",
        value: { mon: "2", wday: "2", mday: "15-21", mweek: "" }
    },
    {
        label: "St. Patrick's Day (March 17)",
        value: { mon: "3", wday: "", mday: "17", mweek: "" }
    },
    {
        label: "Memorial Day (last Monday in May)",
        value: { mon: "5", wday: "2", mday: "25-31", mweek: "" }
    },
    {
        label: "Juneteenth (June 19)",
        value: { mon: "6", wday: "", mday: "19", mweek: "" }
    },
    {
        label: "Independence Day (July 4)",
        value: { mon: "7", wday: "", mday: "4", mweek: "" }
    },
    {
        label: "Labor Day (1st Monday in September)",
        value: { mon: "9", wday: "2", mday: "1-7", mweek: "" }
    },
    {
        label: "Columbus Day (2nd Monday in October)",
        value: { mon: "10", wday: "2", mday: "8-14", mweek: "" }
    },
    {
        label: "Halloween (October 31)",
        value: { mon: "10", wday: "", mday: "31", mweek: "" }
    },
    {
        label: "Veterans Day (November 11)",
        value: { mon: "11", wday: "", mday: "11", mweek: "" }
    },
    {
        label: "Thanksgiving Day (4th Thursday in November)",
        value: { mon: "11", wday: "5", mday: "22-28", mweek: "" }
    },
    {
        label: "Christmas Day (December 25)",
        value: { mon: "12", wday: "", mday: "25", mweek: "" }
    },
    {
        label: "Mother's Day (2nd Sunday in May)",
        value: { mon: "5", wday: "1", mday: "8-14", mweek: "" }
    },
    {
        label: "Father's Day (3rd Sunday in June)",
        value: { mon: "6", wday: "1", mday: "15-21", mweek: "" }
    }
];

</script>
