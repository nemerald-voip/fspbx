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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">{{ $t('Close') }}</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600 m-auto">{{ $t('Loading...') }}</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <div class="shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                        <FormElements>
                                            <StaticElement name="settings_header" tag="h4" :content="$t('Flag Settings')"
                                                :description="$t('A flag is either switched on and off by people, or follows a schedule. Ring groups and virtual receptionists check it on every call.')" />

                                            <TextElement name="name" :label="$t('Name')" :placeholder="$t('Example: Morning Meeting')"
                                                :floating="false" :columns="{ sm: { container: 6 } }" />

                                            <TextElement name="flag_number" :label="$t('Flag Number')" :floating="false"
                                                :columns="{ sm: { container: 3 } }"
                                                :info="$t('Program a BLF key with flag followed by this number, for example flag1.')" />

                                            <ToggleElement name="enabled" :text="$t('Enabled')" :labels="{ on: $t('On'), off: $t('Off') }"
                                                label="&nbsp;" :columns="{ sm: { container: 3 } }" />

                                            <RadiogroupElement name="type" :label="$t('How is this flag controlled?')" view="tabs"
                                                :items="[
                                                    { value: 'manual', label: $t('Manually') },
                                                    { value: 'scheduled', label: $t('On a schedule') },
                                                ]" :columns="{ sm: { wrapper: 8 } }" />

                                            <!-- Manual -->
                                            <StaticElement name="manual_header" tag="h4" :content="$t('Manual Control')"
                                                :description="$t('Switch the flag from this page or by pressing its BLF key. The key lights while the flag is active.')"
                                                :conditions="[['type', 'manual']]"
                                                :add-classes="{ StaticElement: { container: 'border-t border-gray-200 pt-6' } }" />

                                            <ToggleElement name="active" :text="$t('Flag is active now')"
                                                :labels="{ on: $t('On'), off: $t('Off') }" :conditions="[['type', 'manual']]"
                                                :columns="{ sm: { container: 6 } }" />

                                            <ToggleElement name="blf_enabled" :text="$t('Use as a BLF key')"
                                                :labels="{ on: $t('On'), off: $t('Off') }" :columns="{ sm: { container: 6 } }" />

                                            <TagsElement name="allowed_extensions" :label="$t('Extensions allowed to switch it')"
                                                :items="extensionOptions" :search="true" :close-on-select="false"
                                                :native="false" :floating="false" :placeholder="$t('Any extension in this account')"
                                                :conditions="[['type', 'manual']]" :columns="{ sm: { container: 8 } }"
                                                :info="$t('Leave empty to let any extension in this account switch the flag.')" />

                                            <TextElement name="pin_number" :label="$t('PIN Number')" :placeholder="$t('Optional PIN')"
                                                :floating="false" :conditions="[['type', 'manual']]"
                                                :columns="{ sm: { container: 4 } }"
                                                :info="$t('Callers must enter this PIN before the flag switches.')" />

                                            <!-- Scheduled -->
                                            <StaticElement name="schedule_header" tag="h4" :content="$t('Schedule')"
                                                :description="$t('The flag is active during any of these times and inactive the rest of the time.')"
                                                :conditions="[['type', 'scheduled']]"
                                                :add-classes="{ StaticElement: { container: 'border-t border-gray-200 pt-6' } }" />

                                            <SelectElement name="timezone" :groups="true" :items="timezones" :search="true"
                                                :native="false" :label="$t('Time Zone')" input-type="search" autocomplete="off"
                                                :placeholder="$t('Choose time zone')" :floating="false" :strict="false"
                                                :conditions="[['type', 'scheduled']]" :columns="{ sm: { container: 6 } }" />

                                            <ListElement name="weekly_rules" :sort="true" :label="$t('Weekly Times')"
                                                :conditions="[['type', 'scheduled']]" :add-text="$t('+ Add weekly time')"
                                                :add-classes="{ ListElement: { listItem: 'bg-white p-3 sm:p-4 mb-4 rounded-lg shadow-md' } }">
                                                <template #default="{ index }">
                                                    <ObjectElement :name="index">
                                                        <CheckboxgroupElement name="weekdays" view="tabs" :label="$t('Days')"
                                                            :items="weekdayOptions" size="sm"
                                                            :columns="{ default: { container: 12 }, sm: { container: 6 } }" />
                                                        <DateElement name="time_from" :label="$t('From')" :time="true" :date="false"
                                                            :hour24="false" value-format="HH:mm" size="sm" :floating="false"
                                                            :columns="{ default: { container: 12 }, sm: { container: 3 } }" />
                                                        <DateElement name="time_to" :label="$t('To')" :time="true" :date="false"
                                                            :hour24="false" value-format="HH:mm" size="sm" :floating="false"
                                                            :columns="{ default: { container: 12 }, sm: { container: 3 } }" />
                                                    </ObjectElement>
                                                </template>
                                            </ListElement>

                                            <ListElement name="date_rules" :sort="true" :label="$t('Date Ranges')"
                                                :conditions="[['type', 'scheduled']]" :add-text="$t('+ Add date range')"
                                                :add-classes="{ ListElement: { listItem: 'bg-white p-3 sm:p-4 mb-4 rounded-lg shadow-md' } }">
                                                <template #default="{ index }">
                                                    <ObjectElement :name="index">
                                                        <DateElement name="start_date" :label="$t('Start Date')" :time="false"
                                                            value-format="YYYY-MM-DD" size="sm" :floating="false"
                                                            :columns="{ default: { container: 6 }, sm: { container: 3 } }" />
                                                        <DateElement name="time_from" :label="$t('Start Time')" :time="true" :date="false"
                                                            :hour24="false" value-format="HH:mm" size="sm" :floating="false"
                                                            :columns="{ default: { container: 6 }, sm: { container: 3 } }" />
                                                        <DateElement name="end_date" :label="$t('End Date')" :time="false"
                                                            value-format="YYYY-MM-DD" size="sm" :floating="false"
                                                            :columns="{ default: { container: 6 }, sm: { container: 3 } }" />
                                                        <DateElement name="time_to" :label="$t('End Time')" :time="true" :date="false"
                                                            :hour24="false" value-format="HH:mm" size="sm" :floating="false"
                                                            :columns="{ default: { container: 6 }, sm: { container: 3 } }" />
                                                    </ObjectElement>
                                                </template>
                                            </ListElement>

                                            <StaticElement name="date_rules_hint" :conditions="[['type', 'scheduled']]">
                                                <p class="text-sm text-gray-500">
                                                    {{ $t('A date range runs continuously from its start to its end. Leave the times empty to cover whole days.') }}
                                                </p>
                                            </StaticElement>

                                            <TextareaElement name="description" :label="$t('Description')" :rows="2"
                                                :add-classes="{ TextareaElement: { container: 'border-t border-gray-200 pt-6' } }" />

                                            <StaticElement name="usages" :conditions="[() => usages.length > 0]">
                                                <div class="text-sm">
                                                    <div class="font-medium text-gray-700 mb-1">{{ $t('Used by') }}</div>
                                                    <ul class="list-disc pl-5 text-gray-600">
                                                        <li v-for="usage in usages" :key="usage">{{ usage }}</li>
                                                    </ul>
                                                </div>
                                            </StaticElement>

                                            <ButtonElement name="submit" :button-label="$t('Save')" :submits="true" align="right" />
                                        </FormElements>
                                    </div>
                                </template>
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, ref } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import { trans } from "@i18n";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: () => trans("Flag"),
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const weekdayOptions = [
    { value: "1", label: trans("Sun") },
    { value: "2", label: trans("Mon") },
    { value: "3", label: trans("Tue") },
    { value: "4", label: trans("Wed") },
    { value: "5", label: trans("Thu") },
    { value: "6", label: trans("Fri") },
    { value: "7", label: trans("Sat") },
];

const extensionOptions = computed(() => props.options?.extension_options ?? []);
const timezones = computed(() => props.options?.timezones ?? []);
const usages = computed(() => props.options?.item?.usages ?? []);

const defaultValues = computed(() => {
    const item = props.options?.item ?? {};

    return {
        name: item.name ?? null,
        flag_number: item.flag_number ?? null,
        type: item.type ?? "manual",
        enabled: item.enabled ?? true,
        active: item.active ?? false,
        blf_enabled: item.blf_enabled ?? true,
        allowed_extensions: item.allowed_extensions ?? [],
        pin_number: item.pin_number ?? null,
        timezone: item.timezone ?? null,
        weekly_rules: item.weekly_rules ?? [],
        date_rules: item.date_rules ?? [],
        description: item.description ?? null,
    };
});

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(props.options.routes.store_route, requestData);
    }

    return await form$.$vueform.services.axios.put(props.options.routes.update_route, requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();

    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => {
            clearErrorsRecursive(childEl$);
        });
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => {
        clearErrorsRecursive(el$);
    });

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append(trans("Could not submit form"));
};
</script>
