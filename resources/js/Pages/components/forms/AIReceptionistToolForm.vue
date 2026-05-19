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
                        leave-from="opacity-100 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                HTTP Tool
                            </DialogTitle>

                            <button type="button"
                                class="absolute right-4 top-4 rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="emit('close')">
                                <span class="sr-only">Close</span>
                                <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                            </button>

                            <Vueform :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                :display-errors="false" :float-placeholders="false" :default="defaultValues">
                                <TextElement name="name" label="Name" :floating="false"
                                    :columns="{ sm: { container: 6 } }" />
                                <SelectElement name="method" label="Method" :items="methods" :native="false"
                                    :columns="{ sm: { container: 6 } }" />
                                <TextElement name="url" label="URL" :floating="false"
                                    :columns="{ sm: { container: 12 } }" />
                                <TextareaElement name="description" label="Description" :rows="2" />
                                <TextareaElement name="headers_json" label="Headers JSON" :rows="5" />
                                <TextareaElement name="request_schema_json" label="Request Schema JSON" :rows="7" />
                                <TextElement name="timeout_seconds" label="Timeout Seconds" input-type="number"
                                    :floating="false" :columns="{ sm: { container: 6 } }" />
                                <ToggleElement name="enabled" text="Tool Enabled" :true-value="true"
                                    :false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />
                                <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    route: String,
});

const emit = defineEmits(["close", "error", "success"]);

const methods = ["GET", "POST", "PUT", "PATCH", "DELETE"];

const defaultValues = computed(() => ({
    ai_receptionist_uuid: null,
    name: null,
    description: null,
    method: "POST",
    url: null,
    headers_json: "{}",
    request_schema_json: "{}",
    timeout_seconds: 10,
    enabled: true,
}));

const parseJson = (value, label) => {
    try {
        return value ? JSON.parse(value) : {};
    } catch (error) {
        throw new Error(`${label} must be valid JSON.`);
    }
};

const submitForm = async (FormData, form$) => {
    const requestData = { ...form$.requestData };
    requestData.headers = parseJson(requestData.headers_json, "Headers JSON");
    requestData.request_schema = parseJson(requestData.request_schema_json, "Request Schema JSON");
    delete requestData.headers_json;
    delete requestData.request_schema_json;

    return await form$.$vueform.services.axios.post(props.route, requestData);
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("close");
};

const handleError = (error) => {
    emit("error", error);
};
</script>
