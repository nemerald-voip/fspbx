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
                                Create Outbound Route
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
                                    <svg class="h-10 w-10 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
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
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="route" label="Route" :elements="[
                                                    'route_header',
                                                    'dialplan_name',
                                                    'gateway_group',
                                                    'gateway',
                                                    'gateway_2',
                                                    'gateway_3',
                                                    'pattern_picker',
                                                    'dialplan_expression',
                                                    'options_header',
                                                    'prefix_number',
                                                    'toll_allow',
                                                    'accountcode',
                                                    'dialplan_description',
                                                    'button_container',
                                                    'cancel',
                                                    'submit',
                                                ]" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_header',
                                                    'domain_uuid',
                                                    'dialplan_context',
                                                    'dialplan_order',
                                                    'limit',
                                                    'pin_numbers_enabled',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="dialplan_enabled" :meta="true" />

                                                <StaticElement name="route_header" tag="h4" content="Route Pattern"
                                                    description="Choose the gateway and number pattern this outbound route should match." />

                                                <TextElement name="dialplan_name" label="Name"
                                                    placeholder="Enter outbound route name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="gateway_group" :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="gateway" :items="gatewayOptions" :groups="true"
                                                    :search="true" :native="false" label="Gateway" input-type="search"
                                                    autocomplete="off" placeholder="Select gateway" :floating="false"
                                                    allow-absent :strict="false"
                                                    :columns="{ sm: { container: 4 } }" />

                                                <SelectElement name="gateway_2" :items="gatewayOptions" :groups="true"
                                                    :search="true" :native="false" label="Alternate Gateway 1"
                                                    input-type="search" autocomplete="off" placeholder="Optional"
                                                    :floating="false" allow-absent :strict="false"
                                                    :columns="{ sm: { container: 4 } }" />

                                                <SelectElement name="gateway_3" :items="gatewayOptions" :groups="true"
                                                    :search="true" :native="false" label="Alternate Gateway 2"
                                                    input-type="search" autocomplete="off" placeholder="Optional"
                                                    :floating="false" allow-absent :strict="false"
                                                    :columns="{ sm: { container: 4 } }" />

                                                <SelectElement name="pattern_picker" :items="patternOptions"
                                                    :search="true" :native="false" label="Common Pattern"
                                                    input-type="search" autocomplete="off"
                                                    placeholder="Insert a common pattern" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }"
                                                    @change="insertPattern" />

                                                <TextareaElement name="dialplan_expression" label="Dialplan Expression"
                                                    :rows="4"
                                                    info="One expression per line. Multiple lines create one outbound route pair per expression." />

                                                <StaticElement name="options_header" tag="h4" content="Route Options"
                                                    description="Fine tune prefixing, permissions, and how this route appears in the dialplan list." />

                                                <TextElement name="prefix_number" label="Prefix Number"
                                                    placeholder="Optional digits to prepend" :floating="false"
                                                    :columns="{ sm: { container: 4 } }" />

                                                <TextElement name="toll_allow" label="Toll Allow"
                                                    placeholder="Optional toll allow value" :floating="false"
                                                    :columns="{ sm: { container: 4 } }" />

                                                <TextElement name="accountcode" label="Account Code"
                                                    placeholder="Optional account code" :floating="false"
                                                    :columns="{ sm: { container: 4 } }" />

                                                <TextareaElement name="dialplan_description" label="Description"
                                                    :rows="2" />

                                                <GroupElement name="button_container" />

                                                <ButtonElement name="cancel" button-label="Cancel" :secondary="true"
                                                    :resets="true" @click="emit('close')"
                                                    :columns="{ container: 6 }" />

                                                <ButtonElement name="submit" button-label="Create" :submits="true"
                                                    align="right" :columns="{ container: 6 }" />

                                                <StaticElement name="advanced_header" tag="h4"
                                                    content="Advanced Settings"
                                                    description="Set the domain, context, dialplan order, and optional call limit." />

                                                <SelectElement name="domain_uuid" :items="domainOptions" :search="true"
                                                    :native="false" label="Domain" input-type="search"
                                                    autocomplete="off" placeholder="Select domain" :floating="false"
                                                    :strict="false" :columns="{ sm: { container: 6 } }"
                                                    :conditions="[() => domainOptions.length > 0]" />

                                                <SelectElement name="dialplan_context" :items="contextOptions"
                                                    :search="true" :native="false" label="Context"
                                                    input-type="search" allow-absent autocomplete="off" :strict="false"
                                                    placeholder="Select context" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="dialplan_order" input-type="number" label="Order"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="limit" label="Call Limit" placeholder="Optional limit"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <!-- <ToggleElement name="pin_numbers_enabled" text="Require PIN"
                                                    true-value="true" false-value="false"
                                                    :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => props.options?.permissions?.pin_numbers]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" /> -->
                                            </FormElements>
                                        </div>
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

const props = defineProps({
    show: Boolean,
    loading: Boolean,
    options: Object,
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const gatewayOptions = computed(() => props.options?.gateway_options ?? []);
const patternOptions = computed(() => props.options?.pattern_options ?? []);
const domainOptions = computed(() => props.options?.domain_options ?? []);
const contextOptions = computed(() => props.options?.context_options ?? []);

const defaultValues = computed(() => ({
    dialplan_name: null,
    domain_uuid: props.options?.defaults?.domain_uuid ?? "",
    dialplan_context: props.options?.defaults?.dialplan_context ?? "global",
    gateway: null,
    gateway_2: null,
    gateway_3: null,
    pattern_picker: null,
    dialplan_expression: null,
    prefix_number: null,
    toll_allow: null,
    accountcode: null,
    limit: null,
    pin_numbers_enabled: props.options?.defaults?.pin_numbers_enabled ?? "false",
    dialplan_order: props.options?.defaults?.dialplan_order ?? "100",
    dialplan_enabled: props.options?.defaults?.dialplan_enabled ?? "true",
    dialplan_description: null,
}));

const insertPattern = (pattern) => {
    if (!pattern) {
        return;
    }

    const expression = form$.value?.el$("dialplan_expression");
    const current = String(expression?.value ?? "").trim();
    expression?.update(current ? `${current}\n${pattern}` : pattern);
    form$.value?.el$("pattern_picker")?.update(null);
};

const submitForm = async (FormData, form$) => {
    return await form$.$vueform.services.axios.post(
        props.options.routes.store_route,
        form$.requestData,
    );
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

    form$.messageBag.append("Could not submit form");
};
</script>
