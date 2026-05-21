<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full py-10">
                                <div class="flex items-center justify-center space-x-3">
                                    <svg class="h-10 w-10 animate-spin text-blue-600"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <FormElements>
                                        <HiddenElement name="basic_dialer_campaign_uuid" :meta="true" />

                                        <TextElement name="name" label="Name" :floating="false"
                                            :columns="{ sm: { container: 8 } }" />

                                        <ToggleElement name="enabled" text="Enabled" :labels="{ on: 'On', off: 'Off' }"
                                            :columns="{ sm: { container: 4 } }" label="&nbsp;" />

                                        <SelectElement name="basic_dialer_contact_list_uuid" label="Contact List"
                                            :items="contactLists" :search="true" :native="false" input-type="search"
                                            allow-absent :strict="false" :floating="false"
                                            :columns="{ sm: { container: 6 } }" />

                                        <SelectElement name="destination_type" :items="routingTypes" label-prop="name"
                                            label="Destination Action" :search="true" :native="false"
                                            input-type="search" autocomplete="off" placeholder="Choose Action"
                                            :floating="false" :strict="false" :columns="{ sm: { container: 6 } }"
                                            @change="(newValue, oldValue, el$) => {
                                                const target = el$.form$.el$('destination_target');

                                                if (oldValue !== null && oldValue !== undefined) {
                                                    target.clear();
                                                }

                                                target.updateItems();
                                            }" />

                                        <SelectElement name="destination_target" :items="async (query, input) => {
                                            const action = input.$parent.el$.form$.el$('destination_type');

                                            try {
                                                const response = await action.$vueform.services.axios.post(
                                                    props.options.routes.get_routing_options,
                                                    { category: action.value },
                                                );

                                                return response.data.options;
                                            } catch (error) {
                                                emit('error', error);
                                                return [];
                                            }
                                        }" :search="true" label-prop="name" :native="false" label="Target"
                                            input-type="search" allow-absent :object="true" autocomplete="off"
                                            placeholder="Choose Target" :floating="false" :strict="false"
                                            :columns="{ sm: { container: 6 } }" :conditions="[
                                                ['destination_type', 'not_empty'],
                                                ['destination_type', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                            ]" />

                                        <TextElement name="caller_id_name" label="Caller ID Name" :floating="false"
                                            :columns="{ sm: { container: 6 } }" />

                                        <SelectElement name="caller_id_number" label="Caller ID Number"
                                            :items="phoneNumbers" :search="true" :native="false" input-type="search"
                                            autocomplete="off" allow-absent :strict="false" :floating="false"
                                            :rules="['required']" :columns="{ sm: { container: 6 } }" />

                                        <TextElement name="max_concurrent_calls" label="Concurrent Calls"
                                            input-type="number" :floating="false"
                                            :columns="{ sm: { container: 4 } }" />

                                        <TextElement name="seconds_between_calls" label="Seconds Between Calls"
                                            input-type="number" :floating="false"
                                            :columns="{ sm: { container: 4 } }" />

                                        <TextElement name="originate_timeout" label="Originate Timeout"
                                            input-type="number" :floating="false"
                                            :columns="{ sm: { container: 4 } }" />

                                        <TextElement name="retry_limit" label="Retry Limit" input-type="number"
                                            :floating="false" :columns="{ sm: { container: 6 } }" />

                                        <TextElement name="retry_delay_minutes" label="Retry Delay Minutes"
                                            input-type="number" :floating="false"
                                            :columns="{ sm: { container: 6 } }" />

                                        <TextareaElement name="description" label="Description" :rows="2"
                                            :floating="false" />

                                        <ButtonElement name="submit" button-label="Save" :submits="true"
                                            align="right" />
                                    </FormElements>
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

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Campaign",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const contactLists = computed(() => props.options?.contact_lists ?? []);
const phoneNumbers = computed(() => props.options?.phone_numbers ?? []);
const routingTypes = computed(() => props.options?.routing_types ?? []);

const defaultValues = computed(() => ({
    basic_dialer_campaign_uuid: props.options?.item?.basic_dialer_campaign_uuid ?? null,
    name: props.options?.item?.name ?? null,
    basic_dialer_contact_list_uuid: props.options?.item?.basic_dialer_contact_list_uuid ?? null,
    enabled: props.options?.item?.enabled ?? true,
    caller_id_name: props.options?.item?.caller_id_name ?? null,
    caller_id_number: props.options?.item?.caller_id_number ?? null,
    destination_type: props.options?.item?.destination_type ?? null,
    destination_target: props.options?.destination_target ?? null,
    max_concurrent_calls: props.options?.item?.max_concurrent_calls ?? 1,
    seconds_between_calls: props.options?.item?.seconds_between_calls ?? 5,
    retry_limit: props.options?.item?.retry_limit ?? 0,
    retry_delay_minutes: props.options?.item?.retry_delay_minutes ?? 60,
    originate_timeout: props.options?.item?.originate_timeout ?? 30,
    description: props.options?.item?.description ?? null,
}));

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    return props.mode === "create"
        ? await form$.$vueform.services.axios.post(route, requestData)
        : await form$.$vueform.services.axios.put(route, requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();
    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => clearErrorsRecursive(el$));

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

    form$.messageBag.append("Could not submit form");
};
</script>
