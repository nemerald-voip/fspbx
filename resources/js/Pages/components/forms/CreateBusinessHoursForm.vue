<template>
    <div>
        <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError" @response="handleResponse"
            :default="{
                extension: options.item.extension,
                timezone: options.timezone,
                custom_hours: false,
            }" :display-errors="false">
            <template #empty>

                <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                    <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                        <FormTabs view="vertical">
                            <FormTab name="page0" label="Business Hours" :elements="[
                                'business_hours_header',
                                'name',
                                'extension',
                                'timezone',
                                'description',
                                'container',
                                'custom_hours',
                                'time_slots',
                                'ring_group_extension',
                                'ring_group_description',
                                'container_3',
                                'container_4',
                                'divider1',
                                'closed_hours_header',
                                '247_header',
                                'after_hours_action',
                                'after_hours_target',
                                'submit',

                            ]" />

                        </FormTabs>
                    </div>

                    <div
                        class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <StaticElement name="business_hours_header" tag="h4" content="Business Hours" />
                            <TextElement name="name" label="Name" :columns="{
                                sm: {
                                    container: 6,
                                },
                            }" placeholder="Enter Name" :floating="false" />

                            <TextElement name="extension" label="Extension" :columns="{
                                sm: {
                                    container: 6,
                                },
                            }" placeholder="Enter Extension" :floating="false" />

                            <SelectElement name="timezone" :groups="true" :items="options.timezones" :search="true"
                                :native="false" label="Time Zone" input-type="search" autocomplete="off"
                                placeholder="Choose time zone" :floating="false" :strict="false" :columns="{
                                    sm: {
                                        container: 6,
                                    },
                                }" />

                            <TextElement name="description" label="Description" :columns="{
                                sm: {
                                    container: 6,
                                },
                            }" placeholder="Enter Description" :floating="false" />

                            <GroupElement name="container" />

                            <RadiogroupElement name="custom_hours" :items="[
                                {
                                    value: false,
                                    label: 'Always take calls (24/7)',
                                },
                                {
                                    value: true,
                                    label: 'Only during specific hours',
                                },
                            ]" label="When do you want to receive calls?" default="false"
                                @change="handleCustomHoursUpdate" />
                            <ListElement name="time_slots" :sort="true" label="Time Slots"
                                :conditions="[['custom_hours', true]]"
                                :add-classes="{ ListElement: { listItem: 'bg-white p-3 sm:p-4 mb-4 rounded-lg shadow-md' } }">
                                <template #default="{ index }">
                                    <ObjectElement :name="index">
                                        <CheckboxgroupElement name="weekdays" view="tabs" label="Weekdays" :items="[
                                            { value: '1', label: 'S' },
                                            { value: '2', label: 'M' },
                                            { value: '3', label: 'T' },
                                            { value: '4', label: 'W' },
                                            { value: '5', label: 'T' },
                                            { value: '6', label: 'F' },
                                            { value: '7', label: 'S' },
                                        ]" size="sm" :columns="{
    default: { container: 12 },
    sm: { container: 6 },
}" />
                                        <DateElement name="time_from" label="From" :time="true" :date="false"
                                            :hour24="false" :columns="{
                                                default: {
                                                    container: 12,
                                                },
                                                sm: {
                                                    container: 3,
                                                },
                                            }" size="sm" />
                                        <DateElement name="time_to" :time="true" :date="false" :hour24="false" :columns="{
                                            default: {
                                                container: 12,
                                            },
                                            sm: {
                                                container: 3,
                                            },
                                        }" size="sm" label="To" />

                                        <SelectElement name="action" :items="options.routing_types" label-prop="name"
                                            :search="true" :native="false" label="Choose Action" input-type="search"
                                            autocomplete="off" placeholder="Choose Action" :floating="false" :strict="false"
                                            :columns="{ default: { container: 12 }, sm: { container: 6 } }" @change="(newValue, oldValue, el$) => {
                                                let target = el$.form$.el$('time_slots').children$[index].children$['target']

                                                // console.log(el$.form$.el$('time_slots').children$[index].children$['target']);

                                                // only clear when this isn’t the very first time (i.e. oldValue was set)
                                                if (oldValue !== null && oldValue !== undefined) {
                                                    target.clear();
                                                }

                                                target.updateItems()
                                            }" size="sm" />

                                        <SelectElement name="target" :items="async (query, input) => {
                                            let action = input.$parent.el$.form$.el$('time_slots').children$[index].children$['action']

                                            try {
                                                let response = await action.$vueform.services.axios.post(
                                                    options.routes.get_routing_options,
                                                    { category: action.value }
                                                );
                                                // console.log(response.data.options);
                                                return response.data.options;
                                            } catch (error) {
                                                emit('error', error);
                                                return [];  // Return an empty array in case of error
                                            }
                                        }" :search="true" label-prop="name" :native="false" label="Target"
                                            input-type="search" allow-absent :object="true"
                                            autocomplete="off" placeholder="Choose Target" :floating="false" :strict="false"
                                            :columns="{ default: { container: 12 }, sm: { container: 6 } }" :conditions="[
                                                ['time_slots.*.action', 'not_empty'],
                                                ['time_slots.*.action', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                            ]" size="sm" />

                                    </ObjectElement>
                                </template>
                            </ListElement>

                            <GroupElement name="container_3" :conditions="[['custom_hours', true]]" />
                            <StaticElement name="divider1" tag="hr" :conditions="[['custom_hours', true]]" />
                            <GroupElement name="container_4" :conditions="[['custom_hours', true]]" />

                            <StaticElement name="closed_hours_header" tag="h4" content="Closed Hours"
                                description="Define how incoming calls are handled outside of your business hours."
                                :conditions="[['custom_hours', true]]" />

                            <StaticElement name="247_header" tag="h4" content=""
                                description="Define how incoming calls are handled."
                                :conditions="[['custom_hours', false]]" />

                            <SelectElement name="after_hours_action" :items="options.routing_types" label-prop="name"
                                :search="true" :native="false" label="Choose Action" input-type="search" autocomplete="off"
                                placeholder="Choose Action" :floating="false" :strict="false"
                                :columns="{ default: { container: 12 }, sm: { container: 6 } }" @change="(newValue, oldValue, el$) => {
                                    let after_hours_target = el$.form$.el$('after_hours_target')

                                    // only clear when this isn’t the very first time (i.e. oldValue was set)
                                    if (oldValue !== null && oldValue !== undefined) {
                                        after_hours_target.clear();
                                    }

                                    // after_hours_target.clear()
                                    after_hours_target.updateItems()
                                }" />

                            <SelectElement name="after_hours_target" :items="async (query, input) => {
                                let after_hours_action = input.$parent.el$.form$.el$('after_hours_action');

                                try {
                                    let response = await after_hours_action.$vueform.services.axios.post(
                                        options.routes.get_routing_options,
                                        { category: after_hours_action.value }
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
                                    emit('error', error);
                                    return [];  // Return an empty array in case of error
                                }
                            }" :search="true" label-prop="name" :native="false" label="Target" input-type="search"
                                allow-absent :object="true" autocomplete="off" placeholder="Choose Target" :floating="false"
                                :strict="false" :columns="{ default: { container: 12 }, sm: { container: 6 } }" :conditions="[
                                    ['after_hours_action', 'not_empty'],
                                    ['after_hours_action', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                ]" />


                            <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />

                        </FormElements>
                    </div>
                </div>
            </template>
        </Vueform>
    </div>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
    options: Object,
});

const form$ = ref(null)

const emit = defineEmits(['close', 'error', 'success', 'refresh-data', 'open-edit-form']);

const handleCustomHoursUpdate = (newValue, oldValue, el$) => {
    // only when toggling from false → true
    if (!oldValue && newValue) {
        const slotsField = el$.form$.el$('time_slots');
        const currentSlots = slotsField.value || [];

        // if there are no slots yet, seed one
        if (currentSlots.length === 0) {
            const defaultSlot = {
                weekdays: [],
                time_from: '',
                time_to: '',
                action: ''
            };
            slotsField.update([defaultSlot]);
        }
    }
}

const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

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

    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
    emit('open-edit-form', response.data.business_hours_uuid);
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

</script>

<style scoped>
/* This will mask the text input to behave like a password field */
.password-field {
    -webkit-text-security: disc;
    /* For Chrome and Safari */
    -moz-text-security: disc;
    /* For Firefox */
}
</style>