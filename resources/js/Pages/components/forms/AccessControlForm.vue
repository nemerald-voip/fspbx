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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
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

                            <div v-if="loading" class="w-full h-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-blue-600"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-blue-600 m-auto">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <FormElements>
                                        <StaticElement name="settings_header" tag="h4" content="Access Control List"
                                            description="Allow or deny traffic by IP address or CIDR range." />

                                        <TextElement name="access_control_name" label="Name" :floating="false"
                                            placeholder="providers" :columns="{ sm: { container: 6 } }" />

                                        <SelectElement name="access_control_default" label="Default Action"
                                            :items="defaultOptions" :native="false" :strict="true" :floating="false"
                                            :columns="{ sm: { container: 6 } }" />

                                        <TextareaElement name="access_control_description" label="Description"
                                            :rows="2" />

                                        <GroupElement name="nodes_container" />

                                        <ListElement name="nodes" :initial="0" label="IP Rules"
                                            :controls="{ add: true, remove: true, sort: true }"
                                            :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                            <template #default="{ index }">
                                                <ObjectElement :name="index">
                                                    <SelectElement name="node_type" label="Action"
                                                        :items="nodeTypeOptions" :native="false" :strict="true"
                                                        :floating="false" :columns="{ sm: { container: 3 } }" />
                                                    <TextElement name="node_cidr" label="IP / CIDR" :floating="false"
                                                        placeholder="203.0.113.10 or 198.51.100.0/24"
                                                        :columns="{ sm: { container: 5 } }" />
                                                    <TextElement name="node_description" label="Description"
                                                        :floating="false" :columns="{ sm: { container: 4 } }" />
                                                </ObjectElement>
                                            </template>
                                        </ListElement>

                                        <GroupElement name="button_container" />

                                        <ButtonElement name="cancel" button-label="Cancel" :secondary="true"
                                            :resets="true" @click="emit('close')" :columns="{ container: 6 }" />

                                        <ButtonElement name="submit" button-label="Save" :submits="true"
                                            align="right" :columns="{ container: 6 }" />
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
        default: "Access Control",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);
const form$ = ref(null);

const defaultOptions = [
    { value: "deny", label: "Deny" },
    { value: "allow", label: "Allow" },
];

const nodeTypeOptions = [
    { value: "allow", label: "Allow" },
    { value: "deny", label: "Deny" },
];

const defaultValues = computed(() => {
    const item = props.options?.item ?? {};

    return {
        access_control_name: item.access_control_name ?? null,
        access_control_default: item.access_control_default ?? "deny",
        access_control_description: item.access_control_description ?? null,
        nodes: (item.nodes ?? []).map((node) => ({
            node_type: node.node_type ?? "allow",
            node_cidr: node.node_cidr ?? null,
            node_description: node.node_description ?? null,
        })),
    };
});

const submitForm = async (FormData, form$) => {
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(route, form$.requestData);
    }

    return await form$.$vueform.services.axios.put(route, form$.requestData);
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
