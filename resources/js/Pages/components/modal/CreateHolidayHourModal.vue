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

                            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                @response="handleResponse" :display-errors="false">
                                <HiddenElement name="business_hour_uuid" :meta="true" />
                                <StaticElement name="h4" tag="h4" content="Add New Holiday" />
                                <SelectElement name="holiday_type" :items="[
                                    {
                                        value: 'us_holiday',
                                        label: 'US Holiday',
                                    },
                                    {
                                        value: 'ca_holiday',
                                        label: 'Canadian Holiday',
                                    },
                                    {
                                        value: 'single_date',
                                        label: 'Single Date',
                                    },
                                    {
                                        value: 'date_range',
                                        label: 'Date Range',
                                    },
                                    {
                                        value: 'recurring_pattern',
                                        label: 'Recurring Pattern',
                                    },
                                ]" :search="true" :native="false" label="Holiday Type" input-type="search"
                                    @change="handleHolidayTypeChange" autocomplete="off" placeholder="Select Holiday Type"
                                    :floating="false" />


                                <StaticElement name="p1" tag="p" :conditions="[
                                    [
                                        'holiday_type',
                                        'in',
                                        [
                                            'us_holiday',
                                            'ca_holiday',
                                        ],
                                    ],
                                ]">

                                    <div class="rounded-md bg-blue-50 p-4">
                                        <div class="flex">
                                            <div class="shrink-0">
                                                <InformationCircleIcon class="size-5 text-blue-400" aria-hidden="true" />
                                            </div>

                                            <div class="ml-3">
                                                Choose from the list of holidays. Each selection automatically
                                                applies the exception on that exact holiday date.

                                            </div>
                                        </div>
                                    </div>

                                </StaticElement>

                                <StaticElement name="p2" tag="p" :conditions="[
                                    [
                                        'holiday_type',
                                        'in',
                                        [
                                            'single_date',
                                        ],
                                    ],
                                ]">

                                    <div class="rounded-md bg-blue-50 p-4">
                                        <div class="flex">
                                            <div class="shrink-0">
                                                <InformationCircleIcon class="size-5 text-blue-400" aria-hidden="true" />
                                            </div>

                                            <div class="ml-3">

                                                Define a holiday for one specific calendar date.
                                                Pick your date and, if needed, enter a start and/or end time.
                                                Leaving the time fields blank will cover the entire day from 00:00 to 23:59.
                                            </div>
                                        </div>
                                    </div>

                                </StaticElement>

                                <StaticElement name="p3" tag="p" :conditions="[
                                    [
                                        'holiday_type',
                                        'in',
                                        [
                                            'date_range',
                                        ],
                                    ],
                                ]">

                                    <div class="rounded-md bg-blue-50 p-4">
                                        <div class="flex">
                                            <div class="shrink-0">
                                                <InformationCircleIcon class="size-5 text-blue-400" aria-hidden="true" />
                                            </div>

                                            <div class="ml-3">
                                                Create an exception spanning multiple days.
                                                Select a “From” date and a “To” date, and optionally specify start/end times
                                                for each day.
                                                If you leave the time fields blank, each day in the range will default to a
                                                full-day exception.
                                            </div>
                                        </div>
                                    </div>

                                </StaticElement>

                                <StaticElement name="p" tag="p" :conditions="[
                                    [
                                        'holiday_type',
                                        'in',
                                        [
                                            'recurring_pattern',
                                        ],
                                    ],
                                ]">

                                    <div class="rounded-md bg-blue-50 p-4">
                                        <div class="flex">
                                            <div class="shrink-0">
                                                <InformationCircleIcon class="size-5 text-blue-400" aria-hidden="true" />
                                            </div>

                                            <div class="ml-3">
                                                <strong>Understanding Recurring Patterns:</strong>
                                                This form helps you define specific time periods based on a
                                                <strong>recurring
                                                    pattern</strong>.
                                                If you set multiple options (like a specific month and a specific day of
                                                the week), the condition will only be met when <strong>all</strong> selected
                                                criteria are true simultaneously.
                                                Any field you leave blank will not restrict the condition (for example,
                                                leaving <strong>Month</strong> blank means “every month”).
                                            </div>
                                        </div>
                                    </div>

                                </StaticElement>

                                <SelectElement name="us_holiday" :search="true" :native="false" label="US Holiday"
                                    :submit="false" :items="usHolidays" input-type="search" autocomplete="off" :object="true"
                                    @change="handleUSHolidayUpdate" placeholder="Select US Holiday" :floating="false"
                                    :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'us_holiday',
                                            ],
                                        ],
                                    ]" />

                                <SelectElement name="ca_holiday" :search="true" :native="false" label="Canadian Holiday"
                                    :submit="false" :items="caHolidays" input-type="search" autocomplete="off" :object="true"
                                    @change="handleCAHolidayUpdate" placeholder="Select Canadian Holiday" :floating="false"
                                    :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'ca_holiday',
                                            ],
                                        ],
                                    ]" />

                                <TextElement name="description" label="Holiday Description"
                                    description="Enter a clear, descriptive name for this holiday (e.g. ‘Company Annual Picnic’)."
                                    :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'single_date',
                                                'date_range',
                                                'recurring_pattern',
                                            ],
                                        ],
                                    ]" />

                                <DateElement name="start_date" display-format="MMMM DD, YYYY" :label="(el$) => {
                                    if (el$.form$.el$('holiday_type').value == 'single_date') {
                                        return 'Date'
                                    }

                                    if (el$.form$.el$('holiday_type').value == 'date_range') {
                                        return 'Start Date'
                                    }

                                }" :columns="{
    default: {
        container: 6,
    },
    sm: {
        container: 4,
    },
}" :conditions="[
    [
        'holiday_type',
        'in',
        [
            'single_date',
            'date_range',
        ],
    ],
]" />
                                <DateElement name="start_time" label="Start Time" :date="false" :time="true" :hour24="false"
                                    value-format="HH:mm" :columns="{
                                        default: {
                                            container: 6,
                                        },
                                        sm: {
                                            container: 4,
                                        },
                                    }" :conditions="[
    [
        'holiday_type',
        'in',
        [
            'single_date',
            'date_range',
        ],
    ],
]" />
                                <GroupElement name="container" :conditions="[
                                    [
                                        'holiday_type',
                                        'in',
                                        [
                                            'date_range',
                                        ],
                                    ],
                                ]" />
                                <DateElement name="end_date" display-format="MMMM DD, YYYY" label="End Date" :columns="{
                                    default: {
                                        container: 6,
                                    },
                                    sm: {
                                        container: 4,
                                    },
                                }" :conditions="[
    [
        'holiday_type',
        'in',
        [
            'date_range',
        ],
    ],
]" />
                                <DateElement name="end_time" label="End Time" :date="false" :time="true" :hour24="false"
                                    value-format="HH:mm" :columns="{
                                        default: {
                                            container: 6,
                                        },
                                        sm: {
                                            container: 4,
                                        },
                                    }" :conditions="[
    [
        'holiday_type',
        'in',
        [
            'single_date',
            'date_range',
        ],
    ],
]" />
                                <GroupElement name="container_1" />

                                <SelectElement name="mon" :items="monthOptions" :search="true" :native="false"
                                    input-type="search" autocomplete="off" label="Month" :strict="false"
                                    description="Select the month of the year. Leave blank for any month." :floating="false"
                                    :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'recurring_pattern',
                                            ],
                                        ],
                                    ]" />
                                <SelectElement name="mday" :items="dayOfMonthOptions" :search="true" :native="false"
                                    input-type="search" autocomplete="off" label="Day of Month"
                                    description="Select the day of the month (1-31). For example, choose '15' for the 15th day of the month. Leave blank for any day."
                                    :floating="false" :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'recurring_pattern',
                                            ],
                                        ],
                                    ]" />
                                <SelectElement name="week" :items="weekOfYearOptions" :search="true" :native="false"
                                    input-type="search" autocomplete="off" label="Week of Year" :strict="false"
                                    description="Select the week of the year (1-53). Week 1 is the week containing January 1st. Leave blank for any week."
                                    :floating="false" :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'recurring_pattern',
                                            ],
                                        ],
                                    ]" />
                                <SelectElement name="mweek" :items="weekOfMonthOptions" :search="true" :native="false"
                                    input-type="search" autocomplete="off" label="Week of Month" :strict="false"
                                    description="Select the occurrence of a weekday within the month. For example, to specify the 2nd Friday of the month, select '2' here and 'Friday' in the 'Day of Week' field. 
                                    '6' specifically means the last occurrence of the chosen weekday in the month. Leave blank for any week of the month."
                                    :floating="false" :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'recurring_pattern',
                                            ],
                                        ],
                                    ]" />
                                <SelectElement name="wday" :items="dayOfWeekOptions" :search="true" :native="false"
                                    input-type="search" autocomplete="off" label="Day of Week" :strict="false"
                                    description="Select the day of the week. This is often used in conjunction with 'Week of Month'. Leave blank for any day of the week."
                                    :floating="false" :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'recurring_pattern',
                                            ],
                                        ],
                                    ]" />


                                <StaticElement name="action_header" tag="p"
                                    content="Define how incoming calls are handled during this holiday."
                                    :conditions="[['holiday_type', '!=', null],]" />

                                <SelectElement name="action" :items="options.routing_types" label-prop="name" :search="true"
                                    :native="false" label="Choose Action" input-type="search" autocomplete="off"
                                    placeholder="Choose Action" :floating="false" :strict="false"
                                    :columns="{ sm: { container: 6, }, }" @change="(newValue, oldValue, el$) => {
                                        let target = el$.form$.el$('target')

                                        // only clear when this isn’t the very first time (i.e. oldValue was set)
                                        if (oldValue !== null && oldValue !== undefined) {
                                            target.clear();
                                        }

                                        // target.clear()
                                        target.updateItems()
                                    }" :conditions="[['holiday_type', '!=', null],]" />

                                <SelectElement name="target" :items="async (query, input) => {
                                    let action = input.$parent.el$.form$.el$('action');

                                    try {
                                        let response = await action.$vueform.services.axios.post(
                                            options.routes.get_routing_options,
                                            { category: action.value }
                                        );

                                        if (input.externalValue) {
                                            const opts = response.data.options;
                                            // extract the raw value (in case externalValue might be a string or object)
                                            const lookupValue = typeof input.externalValue === 'string'
                                                ? input.externalValue
                                                : input.externalValue?.value;

                                            const selectedOption = opts.find(o => o.value === lookupValue);

                                            // console.log(selectedOption);
                                            input.update(selectedOption)
                                        }
                                        // console.log(response.data.options);
                                        return response.data.options;
                                    } catch (error) {
                                        // emits('error', error);
                                        return [];  // Return an empty array in case of error
                                    }
                                }" :search="true" label-prop="name" :native="false" label="Target" input-type="search"
                                    allow-absent :object="true" autocomplete="off" placeholder="Choose Target"
                                    :floating="false" :strict="false" :columns="{ sm: { container: 6, }, }" :conditions="[
                                        ['action', 'not_empty'],
                                        ['action', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                    ]" />


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
import { ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { InformationCircleIcon } from '@heroicons/vue/20/solid'

const emits = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
    business_hour_uuid: String,
});

const form$ = ref(null)

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.data

    delete requestData.us_holiday;

    requestData.business_hour_uuid = props.business_hour_uuid

    // console.log(requestData);
    return await form$.$vueform.services.axios.post(props.options.routes.store_route, requestData)
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

const handleCAHolidayUpdate = (newValue, oldValue, el$) => {
    if (newValue != oldValue) {
        // find the holiday whose value matches newValue
        const match = caHolidays.find(h =>
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
  { value: '1',  label: 'January' },
  { value: '2',  label: 'February' },
  { value: '3',  label: 'March' },
  { value: '4',  label: 'April' },
  { value: '5',  label: 'May' },
  { value: '6',  label: 'June' },
  { value: '7',  label: 'July' },
  { value: '8',  label: 'August' },
  { value: '9',  label: 'September' },
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
    value: { mon: "1",  wday: "",   mday: "1",      mweek: "" }
  },
  {
    label: "Martin Luther King Jr. Day (3rd Monday in January)",
    value: { mon: "1",  wday: "2",  mday: "15-21",  mweek: "" }
  },
  {
    label: "Valentine's Day (February 14)",
    value: { mon: "2",  wday: "",   mday: "14",     mweek: "" }
  },
  {
    label: "Presidents' Day (3rd Monday in February)",
    value: { mon: "2",  wday: "2",  mday: "15-21",  mweek: "" }
  },
  {
    label: "St. Patrick's Day (March 17)",
    value: { mon: "3",  wday: "",   mday: "17",     mweek: "" }
  },
  {
    label: "Memorial Day (last Monday in May)",
    value: { mon: "5",  wday: "2",  mday: "25-31",  mweek: "" }
  },
  {
    label: "Juneteenth (June 19)",
    value: { mon: "6",  wday: "",   mday: "19",     mweek: "" }
  },
  {
    label: "Independence Day (July 4)",
    value: { mon: "7",  wday: "",   mday: "4",      mweek: "" }
  },
  {
    label: "Labor Day (1st Monday in September)",
    value: { mon: "9",  wday: "2",  mday: "1-7",    mweek: "" }
  },
  {
    label: "Columbus Day (2nd Monday in October)",
    value: { mon: "10", wday: "2",  mday: "8-14",   mweek: "" }
  },
  {
    label: "Halloween (October 31)",
    value: { mon: "10", wday: "",   mday: "31",     mweek: "" }
  },
  {
    label: "Veterans Day (November 11)",
    value: { mon: "11", wday: "",   mday: "11",     mweek: "" }
  },
  {
    label: "Thanksgiving Day (4th Thursday in November)",
    value: { mon: "11", wday: "5",  mday: "22-28",  mweek: "" }
  },
  {
    label: "Christmas Day (December 25)",
    value: { mon: "12", wday: "",   mday: "25",     mweek: "" }
  },
  {
    label: "Mother's Day (2nd Sunday in May)",
    value: { mon: "5",  wday: "1",  mday: "8-14",   mweek: "" }
  },
  {
    label: "Father's Day (3rd Sunday in June)",
    value: { mon: "6",  wday: "1",  mday: "15-21",  mweek: "" }
  }
];

const caHolidays = [
    {
        label: "New Year's Day (January 1)",
        value: { mon: "1", wday: "", mday: "1", mweek: "" }
    },
    {
        label: "Family Day (3rd Monday in February)",
        value: { mon: "2", wday: "2", mday: "15-21", mweek: "" }
    },
    {
        label: "Good Friday (Friday before Easter Sunday)",
        value: { mon: "4", wday: "6", mday: "2-8", mweek: "" }
    },
    {
        label: "Easter Monday (Monday after Easter Sunday)",
        value: { mon: "4", wday: "2", mday: "1-7", mweek: "" }
    },
    {
        label: "Victoria Day (Last Monday before May 25)",
        value: { mon: "5", wday: "2", mday: "18-24", mweek: "" }
    },
    {
        label: "Canada Day (July 1)",
        value: { mon: "7", wday: "", mday: "1", mweek: "" }
    },
    {
        label: "Civic Holiday (First Monday in August)",
        value: { mon: "8", wday: "2", mday: "1-7", mweek: "" }
    },
    {
        label: "Labour Day (First Monday in September)",
        value: { mon: "9", wday: "2", mday: "1-7", mweek: "" }
    },
    {
        label: "National Day for Truth and Reconciliation (September 30)",
        value: { mon: "9", wday: "", mday: "30", mweek: "" }
    },
    {
        label: "Thanksgiving Day (Second Monday in October)",
        value: { mon: "10", wday: "2", mday: "8-14", mweek: "" }
    },
    {
        label: "Remembrance Day (November 11)",
        value: { mon: "11", wday: "", mday: "11", mweek: "" }
    },
    {
        label: "Christmas Day (December 25)",
        value: { mon: "12", wday: "", mday: "25", mweek: "" }
    },
    {
        label: "Boxing Day (December 26)",
        value: { mon: "12", wday: "", mday: "26", mweek: "" }
    },
    // Additional observances
    {
        label: "St. Patrick's Day (March 17)",
        value: { mon: "3", wday: "", mday: "17", mweek: "" }
    },
    {
        label: "Mother's Day (Second Sunday in May)",
        value: { mon: "5", wday: "1", mday: "8-14", mweek: "" }
    },
    {
        label: "Father's Day (Third Sunday in June)",
        value: { mon: "6", wday: "1", mday: "15-21", mweek: "" }
    },
    {
        label: "Halloween (October 31)",
        value: { mon: "10", wday: "", mday: "31", mweek: "" }
    }
];

</script>
