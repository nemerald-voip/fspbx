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

                            <Vueform ref="form$">
                                <StaticElement name="h4" tag="h4" content="Add New Holiday" />
                                <SelectElement name="holiday_type" :items="[
                                    {
                                        value: 'us_holiday',
                                        label: 'US Holiday',
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
                                        value: 'floating',
                                        label: 'Floating',
                                    },
                                ]" :search="true" :native="false" label="Holiday Type" input-type="search" autocomplete="off"
                                    placeholder="Select Holiday Type" :floating="false" />
                                <SelectElement name="select" :search="true" :native="false" label="US Holiday" :items="usHolidays"
                                    input-type="search" autocomplete="off" placeholder="Select US Holiday" :floating="false"
                                    :conditions="[
                                        [
                                            'holiday_type',
                                            'in',
                                            [
                                                'us_holiday',
                                            ],
                                        ],
                                    ]" />
                                <ButtonElement name="reset" button-label="Reset" :secondary="true" :resets="true" 
                                @click="emit('close')"

                                :columns="{
                                    container: 6,
                                }" />
                                <ButtonElement name="submit" button-label="Save" 
                                @click="submit" align="right" :columns="{
                                    container: 6,
                                }"/>
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



const emit = defineEmits(['close', 'confirm'])

const props = defineProps({
    show: Boolean,
});

const form$ = ref(null)

const usHolidays = [
    {
        label: "New Year's Day (January 1)",
        value: { mon: 1, wday: null, mweek: null, mday: 1 }
    },
    {
        label: "Martin Luther King Jr. Day (3rd Monday in January)",
        value: { mon: 1, wday: 2, mweek: 3, mday: null }
    },
    {
        label: "Presidents' Day (3rd Monday in February)",
        value: { mon: 2, wday: 2, mweek: 3, mday: null }
    },
    {
        label: "Memorial Day (last Monday in May)",
        value: { mon: 5, wday: 2, mweek: 5, mday: null }
    },
    {
        label: "Juneteenth (June 19)",
        value: { mon: 6, wday: null, mweek: null, mday: 19 }
    },
    {
        label: "Independence Day (July 4)",
        value: { mon: 7, wday: null, mweek: null, mday: 4 }
    },
    {
        label: "Labor Day (1st Monday in September)",
        value: { mon: 9, wday: 2, mweek: 1, mday: null }
    },
    {
        label: "Columbus Day (2nd Monday in October)",
        value: { mon: 10, wday: 2, mweek: 2, mday: null }
    },
    {
        label: "Veterans Day (November 11)",
        value: { mon: 11, wday: null, mweek: null, mday: 11 }
    },
    {
        label: "Thanksgiving Day (4th Thursday in November)",
        value: { mon: 11, wday: 5, mweek: 4, mday: null }
    },
    {
        label: "Christmas Day (December 25)",
        value: { mon: 12, wday: null, mweek: null, mday: 25 }
    },
];

const submit = () => {
    console.log(form$.value.data)
    emit('confirm', form$.value.data);
    // emit('close');
};

const updateGreeting = () => {
    const updatedGreeting = { ...props.greeting };

    if (props.greeting && Object.prototype.hasOwnProperty.call(props.greeting, 'name')) {
        // If the original greeting has a "name" property, update that property.
        updatedGreeting.name = greeting_name.value;
    } else if (props.greeting && Object.prototype.hasOwnProperty.call(props.greeting, 'label')) {
        // If there's no "name" but there is a "label", update the "label" property.
        updatedGreeting.label = greeting_name.value;
    }

    emit('confirm', updatedGreeting);
    emit('close');
};

</script>
