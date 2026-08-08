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
                                    <span class="sr-only">{{ $t('Close') }}</span>
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
                                    <div class="text-lg text-blue-600 m-auto">{{ $t('Loading...') }}</div>
                                </div>
                            </div>


                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false" :default="{
                                    send_confirmation: options.send_confirmation_default ?? false,
                                }">


                                <SelectElement name="sender_fax_number" :label="$t('Select Your Fax Number')"
                                    :items="options.phone_numbers" :search="true" :native="false" input-type="search"
                                    autocomplete="off" />
                                <!-- Kept flat (no GroupElement): form$.el$() only resolves top-level names. -->
                                <TextElement name="recipient" :label="$t('Fax recipient')" :floating="false"
                                    input-type="tel" autocomplete="off" :rules="['required']"
                                    :placeholder="$t('Enter a fax number')"
                                    :columns="{ default: { container: 12 }, sm: { container: 9 } }"
                                    @change="handleRecipientChange" />

                                <!-- The blank label keeps the button aligned with the input beside it. -->
                                <ButtonElement name="open_phone_book" label="&nbsp;" :secondary="true" :full="true"
                                    :columns="{ default: { container: 12 }, sm: { container: 3 } }"
                                    @click="showContactPicker = true">
                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                        <BookOpenIcon class="h-4 w-4" aria-hidden="true" />
                                        {{ $t('Phone book') }}
                                    </span>
                                </ButtonElement>

                                <StaticElement name="recipient_status" :columns="{ default: { container: 12 } }">
                                    <!-- Negative margins pull this under the input and reclaim the space the
                                         element's own box would otherwise leave below it. -->
                                    <div
                                        class="-mt-4 -mb-2 flex min-h-[1.25rem] flex-wrap items-center gap-x-2 gap-y-1">
                                        <template v-if="matchedContact">
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 ring-1 ring-inset ring-teal-600/20">
                                                <CheckCircleIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                                {{ matchedContact.name }}
                                            </span>
                                            <span v-if="matchedContact.organization" class="text-xs text-gray-500">
                                                {{ matchedContact.organization }}
                                            </span>
                                        </template>

                                        <span v-else-if="lookupState === 'checking'" class="text-xs text-gray-400">
                                            {{ $t('Checking your phone book...') }}
                                        </span>

                                        <template v-else-if="lookupState === 'unknown'">
                                            <span class="text-xs text-gray-500">
                                                {{ $t('Not in your phone book.') }}
                                            </span>
                                            <button type="button"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 transition hover:text-indigo-500"
                                                @click="openAddContact()">
                                                <UserPlusIcon class="h-3.5 w-3.5" aria-hidden="true" />
                                                {{ $t('Save to phone book') }}
                                            </button>
                                        </template>
                                    </div>

                                    <!--
                                        Stays inside this dialog so Headless UI portals it into the same
                                        PortalGroup and the parent focus trap includes it - at the template
                                        root its search box becomes unfocusable.
                                    -->
                                    <ContactPickerModal :show="showContactPicker"
                                        :route="options?.routes?.contact_options_route"
                                        :destroy-route="options?.routes?.contact_destroy_route" channel="fax"
                                        @close="showContactPicker = false" @select="applyContact"
                                        @create="handleCreateFromPicker" @deleted="handleContactDeleted"
                                        @error="emit('error', $event)" />
                                </StaticElement>

                                <!--
                                    Add-to-phone-book lives in a FormChildModal holding THIS form's elements
                                    (all :submit="false", so they never reach the fax endpoint) - the same
                                    shape as the device forms' advanced line settings. A nested <Vueform>
                                    here sends Headless UI's Dialog into a recursive update loop.
                                -->
                                <GroupElement name="new_contact">
                                    <FormChildModal :show="showAddContact" :header="$t('Add to phone book')"
                                        :loading="false" @close="showAddContact = false">
                                        <p class="mb-4 text-sm text-gray-500">
                                            {{ $t('Save this recipient to reuse it on your next fax') }}
                                        </p>

                                        <!--
                                            FormChildModal's slot is a plain div, so this group re-establishes
                                            Vueform's row grid - without it every element renders full width.
                                        -->
                                        <GroupElement name="new_contact_fields">
                                            <!-- Defaults apply on mount, and the modal remounts on every open. -->
                                            <TextElement name="new_contact_number" :label="$t('Fax number')"
                                                :floating="false" :submit="false" autocomplete="off" input-type="tel"
                                                :default="newContact.number" @change="newContact.number = $event" />

                                            <TextElement name="new_contact_first_name" :label="$t('First name')"
                                                :floating="false" :submit="false" autocomplete="off"
                                                :columns="{ default: { container: 12 }, sm: { container: 6 } }"
                                                @change="newContact.first_name = $event" />

                                            <TextElement name="new_contact_last_name" :label="$t('Last name')"
                                                :floating="false" :submit="false" autocomplete="off"
                                                :columns="{ default: { container: 12 }, sm: { container: 6 } }"
                                                @change="newContact.last_name = $event" />

                                            <SelectElement name="new_contact_company" :label="$t('Company')"
                                                :items="searchOrganizations" :search="true" :native="false"
                                                :create="true" input-type="search" autocomplete="off"
                                                :floating="false" :strict="false" allow-absent :submit="false"
                                                :placeholder="$t('Search or add a company (optional)')"
                                                :description="canSaveContact ? null : $t('Enter a first name or choose a company.')"
                                                @change="newContact.company = $event" />

                                            <ButtonElement name="new_contact_cancel" :button-label="$t('Cancel')"
                                                :secondary="true" :columns="{ container: 6 }" :full="true"
                                                @click="showAddContact = false" />

                                            <ButtonElement name="new_contact_save" :button-label="$t('Save contact')"
                                                :columns="{ container: 6 }" align="right" :full="true"
                                                :disabled="!canSaveContact" :loading="savingContact"
                                                @click="saveNewContact" />
                                        </GroupElement>
                                    </FormChildModal>
                                </GroupElement>
                                <CheckboxElement name="cover_letter_checkbox" :text="$t('Cover Letter')" :submit="false" />
                                <TextareaElement name="fax_message" :label="$t('Cover Letter Text')" :conditions="[
                                    [
                                        'cover_letter_checkbox',
                                        '==',
                                        true,
                                    ],
                                ]" />
                                <MultifileElement name="files" :label="$t('Upload files')" :upload-temp-endpoint="false"
                                    :description="$t('Supported file types: .pdf, .doc, .docx, .rtf, .xls, .xlsx, .csv, .txt, .jpg')"
                                    :urls="{}" :drop="true"
                                    accept=".pdf,.doc,.docx,.rtf,.xls,.xlsx,.csv,.txt,.jpeg,.jpg" :rules="[
                                        'max:5',
                                    ]" :add-classes="{
                                        MultifileElement: {
                                            listItem: '!bg-teal-50 !border !border-teal-200 rounded-md !p-2 mt-2 shadow-sm !text-teal-700 font-semibold [&_.form-bg-passive]:!bg-teal-200 hover:[&_.form-bg-passive]:!bg-teal-300 [&_.form-bg-passive>span]:!bg-teal-800'
                                        }
                                    }" />
                                <ToggleElement name="send_confirmation" :text="$t('Send fax confirmation to my email')"
                                    :description="$t('You will receive a fax confirmation either when it is successfully sent or if it fails to send.')" />
                                <ButtonElement name="cancel" :button-label="$t('Cancel')" :secondary="true" :columns="{
                                    container: 6,
                                }" :resets="true" :full="true" @click="resetRecipientState" />
                                <ButtonElement name="submit" :button-label="$t('Send Fax')" :columns="{
                                    container: 6,
                                }" align="right" :submits="true" :full="true" />
                            </Vueform>
                        </DialogPanel>


                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import { BookOpenIcon, CheckCircleIcon, UserPlusIcon } from "@heroicons/vue/24/outline";
import { trans } from "@i18n";
import axios from "axios";
import ContactPickerModal from "../modal/ContactPickerModal.vue";
import FormChildModal from "../FormChildModal.vue";


const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
});

const form$ = ref(null)

const showContactPicker = ref(false)
const showAddContact = ref(false)
const recipient = ref('')
const matchedContact = ref(null)
// idle | checking | found | unknown
const lookupState = ref('idle')

// Mirrors the new_contact_* elements shown inside the add-to-phone-book modal.
const emptyNewContact = () => ({ number: '', first_name: '', last_name: '', company: null })
const newContact = ref(emptyNewContact())
const savingContact = ref(false)

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

/**
 * Digit count only, to decide when a number is worth looking up. Anything to do
 * with the shape of a number - E.164 on save, formatting on display - belongs to
 * the server, which knows the domain's country.
 */
const digitCount = (value) => String(value ?? '').replace(/\D+/g, '').length

const canSaveContact = computed(() => {
    const hasNumber = digitCount(newContact.value.number) >= 7
    const hasIdentity = !!String(newContact.value.first_name ?? '').trim() || !!newContact.value.company

    return hasNumber && hasIdentity
})

let lookupTimer = null
let lookupRequest = 0

/**
 * Look the typed number up in the phone book so the user gets confirmation
 * of who they are faxing - or the chance to save a new recipient. The server
 * resolves the number against the domain's country, so no shape is assumed here.
 */
const handleRecipientChange = (newValue) => {
    recipient.value = newValue ?? ''

    // Still the contact we already resolved - leave the confirmation in place.
    if (matchedContact.value && matchedContact.value.typed === recipient.value) {
        lookupState.value = 'found'
        return
    }

    // Invalidate anything already in flight - it describes an older number.
    clearTimeout(lookupTimer)
    lookupRequest++
    matchedContact.value = null

    if (digitCount(recipient.value) < 7) {
        lookupState.value = 'idle'
        return
    }

    lookupState.value = 'checking'
    lookupTimer = setTimeout(() => lookupRecipient(recipient.value), 400)
}

const lookupRecipient = async (number) => {
    const route = props.options?.routes?.contact_show_route

    if (!route) {
        lookupState.value = 'idle'
        return
    }

    const requestId = ++lookupRequest

    try {
        const response = await axios.get(route.replace(':phoneNumber', encodeURIComponent(number)))

        if (requestId !== lookupRequest) return

        const contact = response.data?.contact ?? null

        matchedContact.value = contact
            ? {
                contact_uuid: contact.contact_uuid,
                name: contact.name,
                organization: contact.organization,
                value: contact.phone_number_formatted || contact.phone_number,
                typed: number,
            }
            : null
        lookupState.value = contact ? 'found' : 'unknown'
    } catch (error) {
        if (requestId !== lookupRequest) return

        // A failed lookup should never block sending a fax.
        lookupState.value = 'idle'
    }
}

const setRecipient = (value) => {
    recipient.value = value
    // Form-level update merges by field name and works regardless of layout nesting.
    form$.value?.update({ recipient: value })
}

const applyContact = (contact) => {
    // The server already formatted the number for this domain's country.
    const display = contact.number_formatted || contact.value

    matchedContact.value = { ...contact, value: display, typed: display }
    lookupState.value = 'found'
    setRecipient(display)
    showContactPicker.value = false
}

const openAddContact = (number = null) => {
    // Set the state first: the modal's elements read it as their default on mount.
    // Whatever shape it is in, the server stores it as E.164.
    newContact.value = { ...emptyNewContact(), number: String(number ?? recipient.value ?? '').trim() }

    showContactPicker.value = false
    showAddContact.value = true
}

const handleCreateFromPicker = (query) => {
    // Whatever they typed in the search box is a fax number if it looks like one.
    if (digitCount(query) >= 7) {
        openAddContact(query)
        return
    }

    // Otherwise carry over the number being faxed - but only if it isn't already a
    // saved contact, since then they are adding a different, new one.
    openAddContact(matchedContact.value ? '' : recipient.value)
}

const searchOrganizations = async (query) => {
    const route = props.options?.routes?.organization_options_route

    if (!route) return []

    const response = await axios.get(route, { params: { query: query ?? '' } })

    return Array.isArray(response.data) ? response.data : []
}

const saveNewContact = async () => {
    const route = props.options?.routes?.contact_store_route

    if (!route || !canSaveContact.value || savingContact.value) return

    // The company select holds a uuid when an existing company was picked, and the
    // raw text when a new one was typed - the server creates the latter.
    const company = newContact.value.company
    const isExisting = typeof company === 'string' && UUID_PATTERN.test(company)

    savingContact.value = true

    try {
        const response = await axios.post(route, {
            phone_number: newContact.value.number,
            first_name: String(newContact.value.first_name ?? '').trim() || null,
            last_name: String(newContact.value.last_name ?? '').trim() || null,
            organization_uuid: isExisting ? company : null,
            organization_name: !isExisting && company ? String(company).trim() : null,
            phone_label: 'fax',
        })

        showAddContact.value = false
        handleContactSaved(response.data?.contact ?? null)
    } catch (error) {
        emit('error', error)
    } finally {
        savingContact.value = false
    }
}

const handleContactSaved = (contact) => {
    if (!contact) return

    // The store response carries the number formatted for this domain's country.
    const display = contact.phone_number_formatted || contact.phone_number

    matchedContact.value = {
        contact_uuid: contact.contact_uuid,
        name: contact.name,
        organization: contact.organization,
        value: display,
        typed: display,
    }
    lookupState.value = 'found'
    setRecipient(display)

    // Notification renders message[0] of each entry, so each must be an array.
    emit('success', 'success', { success: [trans('Contact saved to your phone book.')] })
}

const handleContactDeleted = (contact) => {
    // If the number in the field belonged to that contact, it is no longer saved.
    if (matchedContact.value?.contact_uuid === contact.contact_uuid) {
        matchedContact.value = null
        lookupState.value = digitCount(recipient.value) >= 7 ? 'unknown' : 'idle'
    }

    emit('success', 'success', { success: [trans('Contact removed from your phone book.')] })
}

const resetRecipientState = () => {
    clearTimeout(lookupTimer)
    lookupRequest++
    recipient.value = ''
    matchedContact.value = null
    lookupState.value = 'idle'
    newContact.value = emptyNewContact()
    savingContact.value = false
    showContactPicker.value = false
    showAddContact.value = false
}

watch(() => props.show, (show) => {
    if (!show) resetRecipientState()
})

const submitForm = async (FormData, form$) => {
    // Using FormData will EXCLUDE conditional elements and it
    // will submit the form as "Content-Type: multipart/form-data".
    const formData = FormData
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    // const requestData = form$.requestData
    // console.log(requestData);

    return await form$.$vueform.services.axios.post(props.options.routes.send_fax_route, formData)
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

            form$.messageBag.append(trans('Could not prepare form'))
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

            form$.messageBag.append(trans('Request cancelled'))
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append(trans('Couldn\'t submit form'))
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
