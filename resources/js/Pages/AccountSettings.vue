<template>
    <MainLayout>

        <main class="mx-auto max-w-full pb-10 lg:px-8 lg:py-12">
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                @response="handleResponse" :display-errors="false">
                <template #empty>

                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                        <div class="px-2 py-6 sm:px-6 lg:col-span-2 lg:px-0 lg:py-0">
                            <FormTabs view="vertical">
                                <FormTab name="page0" label="General" :elements="[
                                    'general_tab_label',
                                    'domain_enabled',
                                    'domain_description',
                                    'domain_name',
                                    'time_zone',
                                    'general_submit',

                                ]" :conditions="[() => true]" />
                                <FormTab name="page1" label="Emergency Calls" :elements="[
                                    'emergency_calls',
                                ]" :conditions="[() => true]" />

                            </FormTabs>
                        </div>

                        <div
                            class="sm:px-6 lg:col-span-10 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                            <FormElements>

                                <!-- General Tab -->

                                <StaticElement name="general_tab_label" tag="h4" content="General" />
                                <HiddenElement name="domain_uuid" :meta="true" />
                                <ToggleElement name="domain_enabled" text="Account Status" />
                                <TextElement name="domain_description" label="Account Name" placeholder="Enter Account Name"
                                    :floating="false" :columns="{
                                        sm: {
                                            container: 6,
                                        },
                                    }" />
                                <TextElement name="domain_name" label="Domain" :readonly="true" :columns="{
                                    sm: {
                                        container: 6,
                                    },
                                }" />
                                <SelectElement name="time_zone" :groups="true" :items="timezones" :search="true"
                                    :native="false" label="Time Zone" input-type="search" autocomplete="off"
                                    placeholder="Select Time Zone" :floating="false" :strict="false" :columns="{
                                        sm: {
                                            container: 6,
                                        },
                                    }" />

                                <ButtonElement name="general_submit" button-label="Save" :submits="true" align="right" />


                                <!-- Emergency Calls -->

                                <StaticElement name="emergency_calls">
                                    <template #default="{ el$ }">
                                        <EmergencyCalls :routes="routes" />
                                    </template>
                                </StaticElement>


                            </FormElements>
                        </div>
                    </div>
                </template>
            </Vueform>
        </main>

        <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
            @update:show="hideNotification" />

    </MainLayout>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import MainLayout from '../Layouts/MainLayout.vue'
import { Cog6ToothIcon, BellIcon } from '@heroicons/vue/24/outline';
import LabelInputOptional from "@generalComponents/LabelInputOptional.vue";
import InputField from "@generalComponents/InputField.vue";
import Toggle from "@generalComponents/Toggle.vue";
import Spinner from "@generalComponents/Spinner.vue";
import Notification from "./components/notifications/Notification.vue";
import ListboxGroup from "@generalComponents/ListboxGroup.vue";
import EmergencyCalls from "./components/EmergencyCalls.vue";
import EmergencyServiceStatus from "./components/EmergencyServiceStatus.vue";
import { CheckCircleIcon, QuestionMarkCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { CreditCardIcon } from '@heroicons/vue/24/outline'
import { ChevronDownIcon } from '@heroicons/vue/16/solid'




const props = defineProps({
    data: {
        type: Object,
        default: () => ({}) // Providing an empty object as default
    },
    timezones: Object,
    routes: Object,
    errors: Object,

})

const form$ = ref(null)

// const localData = ref(JSON.parse(JSON.stringify(props.data || {})));

onMounted(() => {
    form$.value.update({ // updates form data
        domain_uuid: props.data.domain_uuid ?? null,
        domain_enabled: props.data.domain_enabled ?? false,
        domain_name: props.data.domain_name ?? '',
        domain_description: props.data.domain_description ?? '',
        time_zone: props.data.named_settings.time_zone?.value ?? null,

    })

    form$.value.clean()
    // console.log(form$.value.data);
})

console.log(props.data)


const notificationType = ref(null);
const notificationShow = ref(null);
const notificationMessages = ref(null);



const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    // Build a lookup of original settings by subcategory
    const originalMap = props.data.settings.reduce((map, s) => {
        map[s.domain_setting_subcategory] = {
            value: s.domain_setting_value,
            category: s.domain_setting_category,
            uuid: s.domain_setting_uuid,
            enabled: s.domain_setting_enabled,
        }
        return map
    }, {})

    const updatedSettings = []
    const newSettings = []

    // Meta‐fields that are NOT “settings”
    const metaKeys = [
        'domain_uuid',
        'domain_enabled',
        'domain_description',
        'domain_name',
        // plus anything else your form has at top‐level
    ]

    // Handle updates to EXISTING settings
    Object.entries(originalMap).forEach(([subcat, orig]) => {
        // if the form actually sent us this subcat...
        if (requestData.hasOwnProperty(subcat)) {
            const newValue = requestData[subcat]
            if (newValue !== orig.value) {
                updatedSettings.push({
                    domain_uuid: props.data.domain_uuid,
                    domain_setting_uuid: orig.uuid,
                    domain_setting_category: orig.category,
                    domain_setting_subcategory: subcat,
                    domain_setting_value: newValue,
                    domain_setting_enabled: true,
                })
            }
        }
    })

    // Handle brand-new settings
    Object.keys(requestData).forEach(key => {
        // if it’s not one of the meta-fields AND not in originalMap
        if (!metaKeys.includes(key) && !originalMap.hasOwnProperty(key)) {
            newSettings.push({
                domain_uuid: props.data.domain_uuid,
                domain_setting_subcategory: key,
                domain_setting_value: requestData[key],
                domain_setting_enabled: true,
            })
        }
    })

    // Overwrite the “settings” payload and add “newSettings”
    const payload = {
        ...requestData,
        updatedSettings,
        newSettings
    }

    // console.log(requestData);
    return await form$.$vueform.services.axios.put(props.routes.settings_update, payload)
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
    if (response?.data?.errors) {
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

    showNotification('success', response.data.messages);

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
            handleErrorResponse(error);
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



const plans = [
    { name: 'Startup', priceMonthly: '$29', priceYearly: '$290', limit: 'Up to 5 active job postings', selected: true },
    {
        name: 'Business',
        priceMonthly: '$99',
        priceYearly: '$990',
        limit: 'Up to 25 active job postings',
        selected: false,
    },
    {
        name: 'Enterprise',
        priceMonthly: '$249',
        priceYearly: '$2490',
        limit: 'Unlimited active job postings',
        selected: false,
    },
]
const payments = [
    {
        id: 1,
        date: '1/1/2020',
        datetime: '2020-01-01',
        description: 'Business Plan - Annual Billing',
        amount: 'CA$109.00',
        href: '#',
    },
    // More payments...
]



const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}


</script>