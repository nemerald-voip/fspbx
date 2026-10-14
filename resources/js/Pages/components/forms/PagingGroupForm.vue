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
                                validate-on="" :default="defaultValues">
                                <template #empty>
                                    <div class="shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                        <FormElements>
                                            <StaticElement name="settings_header" tag="h4" :content="$t('Paging Group')"
                                                :description="$t('Dial the number to page every phone in the group. Phones answer automatically on speaker and can only listen.')" />

                                            <TextElement name="name" :label="$t('Name')" :placeholder="$t('Example: Warehouse')"
                                                :floating="false" :columns="{ sm: { container: 6 } }" />

                                            <TextElement name="extension" :label="$t('Number')" :floating="false"
                                                :placeholder="$t('Example: 7000')" :columns="{ sm: { container: 3 } }" />

                                            <ToggleElement name="enabled" :text="$t('Enabled')" :labels="{ on: $t('On'), off: $t('Off') }"
                                                label="&nbsp;" :columns="{ sm: { container: 3 } }" />

                                            <RadiogroupElement name="mode" :label="$t('Type of page')" view="tabs"
                                                :items="[
                                                    { value: 'live', label: $t('Live') },
                                                    { value: 'recorded', label: $t('Recorded') },
                                                ]"
                                                :info="$t('Recorded: after the beep, say your message and hang up or press #. The phones are paged once you finish, so nearby phones cannot cause feedback.')" />

                                            <TagsElement name="members" :label="$t('Phones to page')"
                                                :items="extensionOptions" :search="true" :close-on-select="false"
                                                :native="false" :floating="false" :placeholder="$t('Choose extensions')" />

                                            <TagsElement name="allowed_callers" :label="$t('Who can page')"
                                                :items="extensionOptions" :search="true" :close-on-select="false"
                                                :native="false" :floating="false" :placeholder="$t('Anyone in this account')"
                                                :info="$t('Leave empty to let any extension in this account page the group.')" />

                                            <RadiogroupElement name="busy_mode" :label="$t('Phones already on a call')" view="tabs"
                                                :items="[
                                                    { value: 'skip', label: $t('Skip them') },
                                                    { value: 'force', label: $t('Page them anyway') },
                                                ]"
                                                :info="$t('When paged anyway, the phone decides how to handle the second call.')" />

                                            <StaticElement name="intro_header" tag="h4" :content="$t('Before the page')"
                                                :description="$t('Let everyone know a page is coming. Both play on the phones first, then the page starts.')"
                                                :add-classes="{ StaticElement: { container: 'border-t border-gray-200 pt-6' } }" />

                                            <ToggleElement name="intro_beep" :text="$t('Double beep')"
                                                :labels="{ on: $t('On'), off: $t('Off') }" :columns="{ sm: { container: 4 } }" />

                                            <SelectElement name="intro_recording" :label="$t('Announcement')" :items="recordingOptions"
                                                :search="true" :native="false" :floating="false" :strict="false"
                                                input-type="search" autocomplete="off" :can-clear="true"
                                                :placeholder="$t('None')" :columns="{ sm: { container: 8 } }"
                                                :info="$t('Choose a recording from Recordings Manager, for example one that says Incoming announcement.')" />

                                            <TextareaElement name="description" :label="$t('Description')" :rows="2" />

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
        default: () => trans("Paging Group"),
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const extensionOptions = computed(() => props.options?.extension_options ?? []);
const recordingOptions = computed(() => props.options?.recording_options ?? []);

const defaultValues = computed(() => {
    const item = props.options?.item ?? {};

    return {
        name: item.name ?? null,
        extension: item.extension ?? null,
        enabled: item.enabled ?? true,
        members: item.members ?? [],
        allowed_callers: item.allowed_callers ?? [],
        busy_mode: item.busy_mode ?? "skip",
        mode: item.mode ?? "live",
        intro_beep: item.intro_beep ?? true,
        intro_recording: item.intro_recording ?? null,
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
            // members.0 errors belong to the members field.
            const name = elName.split(".")[0];
            if (form$.el$(name)) {
                form$.el$(name).messageBag.append(response.data.errors[elName][0]);
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
