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

                            <Loading :show="true" />

                            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                @response="handleResponse" :display-errors="false" @mounted="handleFormMounted" :default="{ 
                                    // group_name: options.item.group_name,
                                    // domain_uuid: options.item?.domain_uuid ?? null,
                                }">

                                <StaticElement name="h4" tag="h4" content="Update Pemision Group" />
                                <TextElement name="group_name" label="Name" placeholder="Enter Group Name" />
                                <SelectElement name="domain_uuid" :items="options.domains" :search="true" :native="false" input-type="search" autocomplete="off" :floating="false" label="Account Name"
                                    placeholder="Select Account Name" :strict="false" />
                                <SelectElement name="group_level" :items="[
                                    {
                                        value: 0,
                                        label: 'Label',
                                    },
                                ]" :search="true" :native="false" label="Level" input-type="search" autocomplete="off" placeholder="Select Level"
                                    :floating="false" />
                                <ToggleElement name="group_protected" text="Protected" true-value="true"
                                    false-value="false" />
                                <TextElement name="group_description" label="Description" />

                                <GroupElement name="container_3" />
                                <ButtonElement name="reset" button-label="Cancel" :secondary="true" :resets="true"
                                    @click="emits('close')" :columns="{
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
import Loading from "./../general/Loading.vue";

const emits = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
});

const form$ = ref(null)

const handleFormMounted = () => {
    console.log('mounted');
}

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.data
    // console.log(requestData);

    delete requestData.us_holiday;

    requestData.business_hour_uuid = props.business_hour_uuid

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

    emits('success', response.data.messages);
    emits('close');
    emits('refresh-data');
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
            emits('error', error);
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
