<template>
    <div>
        <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError" @response="handleResponse"
            :default="{
                uuid: options.item.uuid,
                extension: options.item.extension,
                name: options.item.name,
                timezone: options.timezone,
                description: options.item.description ?? null,
                custom_hours: options.custom_hours,
                time_slots: options.time_slots,
                after_hours_action: options.item.after_hours_action,
                after_hours_target: options.item.after_hours_target,
                exceptions: options.item.exceptions,
            }" :display-errors="false">
            <template #empty>

                <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                    <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                        <FormTabs view="vertical" @select="handleTabSelected">
                            <FormTab name="business_hours" label="Business Hours" :elements="[
                                'business_hours_header',
                                'uuid_clean',
                                'name',
                                'extension',
                                'uuid',
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

                            <FormTab name="holidays" label="Holidays" :elements="[
                                'holidays_header',
                                'add_holiday',
                                'holiday_table',
                                'submit',

                            ]" :conditions="[() => options.permissions.holidays_list_view]" />

                        </FormTabs>
                    </div>

                    <div
                        class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <HiddenElement name="uuid" :meta="true" />

                            <StaticElement name="uuid_clean"
                                :conditions="[() => options.permissions.is_superadmin]">

                                <div class="mb-1">
                                    <div class="text-sm font-medium text-gray-600 mb-1">
                                        Unique ID
                                    </div>

                                    <div class="flex items-center group">
                                        <span class="text-sm text-gray-900 select-all font-normal">
                                            {{ options.item.uuid }}
                                        </span>

                                        <button type="button" @click="handleCopyToClipboard(options.item.uuid)"
                                            class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                            title="Copy to clipboard">
                                            <!-- Small Copy Icon -->
                                            <ClipboardDocumentIcon
                                                class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                        </button>
                                    </div>
                                </div>

                            </StaticElement>

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
                            <ListElement name="time_slots" :sort="true" label="Time Slots" :initial="1"
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

                                                return response.data.options;
                                            } catch (error) {
                                                emit('error', error);
                                                return [];  // Return an empty array in case of error
                                            }
                                        }" :search="true" label-prop="name" :native="false" label="Target"
                                            input-type="search" allow-absent :object="true" autocomplete="off"
                                            placeholder="Choose Target" :floating="false" :strict="false"
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
                                }"  />

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


                            <!-- Holidays -->

                            <StaticElement name="holidays_header" tag="h4" content="Holidays"
                                description="Configure how incoming calls are routed on holidays and other special dates outside your normal business hours." />

                            <ButtonElement name="add_holiday" button-label="Add Holiday" align="right"
                                @click="handleAddHolidayButtonClick" :loading="addHolidayButtonLoading"
                                :conditions="[() => options.permissions.holidays_create]" />

                            <StaticElement name="holiday_table">
                                <HolidayTable :holidays="holidays" :loading="isHolidaysLoading"
                                    :permissions="options.permissions" @edit-item="handleUpdateHolidayButtonClick"
                                    @delete-item="handleDeleteHolidayButtonClick" />
                            </StaticElement>
                            <HiddenElement name="exceptions" :meta="true" />




                            <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />

                        </FormElements>
                    </div>
                </div>
            </template>
        </Vueform>

        <UpdateHolidayHourModal :show="showUpdateHolidayModal" :options="holidayItemOptions"
            :business_hour_uuid="options.item.uuid" @close="showUpdateHolidayModal = false"
            @error="emitErrorToParentFromChild" @success="emitSuccessToParentFromChild" @refresh-data="getHolidays" />

        <CreateHolidayHourModal :show="showAddHolidayModal" :options="holidayItemOptions"
            :business_hour_uuid="options.item.uuid" @close="showAddHolidayModal = false" @error="emitErrorToParentFromChild"
            @success="emitSuccessToParentFromChild" @refresh-data="getHolidays" />

        <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
            @confirm="confirmDeleteAction" :header="'Confirm Deletion'" :loading="isDeleteHolidayLoading"
            :text="'This action will permanently delete the selected holiday. Are you sure you want to proceed?'"
            :confirm-button-label="'Delete'" cancel-button-label="Cancel" />

    </div>
</template>

<script setup>
import { ref } from "vue";
import HolidayTable from "./../HolidayTable.vue";
import CreateHolidayHourModal from "./../modal/CreateHolidayHourModal.vue"
import UpdateHolidayHourModal from "./../modal/UpdateHolidayHourModal.vue"
import ConfirmationModal from "./../modal/ConfirmationModal.vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
    options: Object,
});

const form$ = ref(null)
const showAddHolidayModal = ref(false)
const showUpdateHolidayModal = ref(false)
const holidayItemOptions = ref(null)
const addHolidayButtonLoading = ref(false)
const updateHolidayButtonLoading = ref(false)
const holidays = ref([])
const isHolidaysLoading = ref(false)
const isDeleteHolidayLoading = ref(false)
const showDeleteConfirmationModal = ref(false)
const confirmDeleteAction = ref(null);

const emit = defineEmits(['close', 'error', 'success', 'refresh-data']);

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch((error) => {
        // Handle the error case
        emit('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}

const getHolidayItemOptions = (itemUuid = null) => {
    const payload = itemUuid ? { item_uuid: itemUuid } : {};
    return axios
        .post(props.options.routes.holiday_item_options, payload)
        .then(res => {
            holidayItemOptions.value = res.data;
            // console.log(holidayItemOptions.value);
            return res;
        });
};

const handleAddHolidayButtonClick = async () => {
    addHolidayButtonLoading.value = true;
    try {
        await getHolidayItemOptions();
        showAddHolidayModal.value = true;
    } catch (err) {
        handleModalClose();
        emit('error', err);
    } finally {
        addHolidayButtonLoading.value = false;
    }
};


const handleUpdateHolidayButtonClick = async uuid => {
    updateHolidayButtonLoading.value = true;
    try {
        await getHolidayItemOptions(uuid);
        showUpdateHolidayModal.value = true;
    } catch (err) {
        handleModalClose();
        emit('error', err);
    } finally {
        updateHolidayButtonLoading.value = false;
    }
};


const handleDeleteHolidayButtonClick = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};


const executeBulkDelete = async (items) => {
    isDeleteHolidayLoading.value = true;

    try {
        const response = await axios.post(
            props.options.routes.holiday_bulk_delete,
            { items }
        );
        emit('success', 'success', response.data.messages);
        getHolidays();
    } catch (error) {
        emit('error', error);
    } finally {
        // hide both the delete and the confirmation modals
        handleModalClose();
        isDeleteHolidayLoading.value = false;
    }
};

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

const handleTabSelected = (activeTab, previousTab) => {
    if (activeTab.name == 'holidays') {
        getHolidays()
    }
}

const getHolidays = async () => {
    isHolidaysLoading.value = true
    axios.get(props.options.routes.holidays, {
        params: {
            uuid: props.options.item.uuid
        }
    })
        .then((response) => {
            holidays.value = response.data.data;
            // console.log(holidays.value);

        }).catch((error) => {
            handleModalClose();
            emit('error', error)
        }).finally(() => {
            isHolidaysLoading.value = false
        });
}

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


const handleModalClose = () => {
    showAddHolidayModal.value = false;
    showUpdateHolidayModal.value = false
    showDeleteConfirmationModal.value = false;

}

const emitErrorToParentFromChild = (error) => {
    emit('error', error);
}

const emitSuccessToParentFromChild = (message) => {
    emit('success', 'success', message);
}



</script>
