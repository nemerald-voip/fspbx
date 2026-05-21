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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl sm:p-6">
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
                                        <HiddenElement name="basic_dialer_contact_list_uuid" :meta="true" />

                                        <TextElement name="name" label="Name" :floating="false"
                                            :columns="{ sm: { container: 8 } }" />

                                        <ToggleElement name="enabled" text="Enabled" :labels="{ on: 'On', off: 'Off' }"
                                            :columns="{ sm: { container: 4 } }" label="&nbsp;" />

                                        <TextareaElement name="description" label="Description" :rows="2"
                                            :floating="false" />

                                        <TextareaElement name="contacts" label="Import Contacts" :rows="8"
                                            :floating="false"
                                            placeholder="15551234567, Jane Smith, Acme&#10;15557654321, Bob Jones, Acme" />

                                        <StaticElement name="existing_contacts"
                                            :conditions="[() => contacts.length > 0]">
                                            <div class="mt-2 overflow-hidden rounded-md border border-gray-200">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <tbody class="divide-y divide-gray-100 bg-white">
                                                        <tr v-for="contact in contacts" :key="contact.basic_dialer_contact_uuid">
                                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                                {{ contact.phone_number }}
                                                            </td>
                                                            <td class="px-3 py-2 text-sm text-gray-500">
                                                                {{ contact.contact_name }}
                                                            </td>
                                                            <td class="px-3 py-2 text-sm text-gray-500">
                                                                {{ contact.company }}
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </StaticElement>

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
        default: "Contact List",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const contacts = computed(() => props.options?.contacts ?? []);

const defaultValues = computed(() => ({
    basic_dialer_contact_list_uuid: props.options?.item?.basic_dialer_contact_list_uuid ?? null,
    name: props.options?.item?.name ?? null,
    enabled: props.options?.item?.enabled ?? true,
    description: props.options?.item?.description ?? null,
    contacts: null,
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
