<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-4 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-heading">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-surface text-subtle hover:text-muted focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full py-10">
                                <div class="flex justify-center items-center space-x-3">
                                    <svg class="animate-spin h-10 w-10 text-info"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    <div class="text-lg text-info m-auto">Loading...</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'conference_room_uuid',
                                                    'settings_header',
                                                    'conference_room_uuid_clean',
                                                    'conference_center_uuid',
                                                    'conference_room_name',
                                                    'moderator_pin',
                                                    'participant_pin',
                                                    'enabled',
                                                    'description',
                                                    'button_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_header',
                                                    'profile',
                                                    'record',
                                                    'max_members',
                                                    'placeholder',
                                                    'start_datetime',
                                                    'stop_datetime',
                                                    'wait_mod',
                                                    'moderator_endconf',
                                                    'announce_name',
                                                    'announce_count',
                                                    'announce_recording',
                                                    'mute',
                                                    'sounds',
                                                    'email_address',
                                                    'placeholder1',
                                                    'account_code',
                                                    'advanced_button_container',
                                                    'advanced_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-body bg-surface-2 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="conference_room_uuid" :meta="true" />

                                                <StaticElement name="settings_header" tag="h4" content="Conference Room Settings"
                                                    description="Configure the room name, access PINs, and availability." />

                                                <StaticElement name="conference_room_uuid_clean"
                                                    :conditions="[() => props.options?.item?.conference_room_uuid]">
                                                    <div class="mb-1">
                                                        <div class="text-sm font-medium text-body mb-1">
                                                            Unique ID
                                                        </div>

                                                        <div class="flex items-center group">
                                                            <span class="text-sm text-heading select-all font-normal">
                                                                {{ props.options?.item?.conference_room_uuid }}
                                                            </span>

                                                            <button type="button"
                                                                @click="handleCopyToClipboard(props.options?.item?.conference_room_uuid)"
                                                                class="ml-2 p-1 rounded-full text-subtle hover:text-info hover:bg-info-subtle transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                                title="Copy to clipboard">
                                                                <ClipboardDocumentIcon
                                                                    class="h-4 w-4 text-muted hover:text-heading cursor-pointer" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </StaticElement>

                                                <SelectElement name="conference_center_uuid" label="Conference Center"
                                                    :items="conferenceCenters" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="conference_room_name" label="Room Name"
                                                    placeholder="Conference room name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="moderator_pin" label="Moderator PIN"
                                                    placeholder="Moderator PIN" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="participant_pin" label="Participant PIN"
                                                    placeholder="Participant PIN" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="enabled" text="Conference Room Enabled"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.enabled]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextareaElement name="description" label="Description" :rows="2" />

                                                <GroupElement name="button_container" />
                                                <ButtonElement name="settings_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <StaticElement name="advanced_header" tag="h4" content="Advanced Settings"
                                                    description="Manage room behavior, scheduling, and optional accounting fields." />

                                                <SelectElement name="profile" label="Profile" :items="profiles"
                                                    :native="false" :search="true" :floating="false"
                                                    :conditions="[() => permissions.profile]"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="record" text="Record Calls" true-value="true"
                                                    false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.record]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextElement name="max_members" input-type="number" label="Max Members"
                                                    :floating="false" :conditions="[() => permissions.max_members]"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="placeholder" />

                                                <DateElement name="start_datetime" label="Start Date/Time"
                                                    :time="true" :seconds="true" :hour24="true"
                                                    value-format="YYYY-MM-DD HH:mm:ss"
                                                    load-format="YYYY-MM-DD HH:mm:ss"
                                                    display-format="YYYY-MM-DD HH:mm:ss"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <DateElement name="stop_datetime" label="Stop Date/Time"
                                                    :time="true" :seconds="true" :hour24="true"
                                                    value-format="YYYY-MM-DD HH:mm:ss"
                                                    load-format="YYYY-MM-DD HH:mm:ss"
                                                    display-format="YYYY-MM-DD HH:mm:ss"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="wait_mod" text="Wait for Moderator"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.wait_mod]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <ToggleElement name="moderator_endconf" text="Moderator Ends Conference"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.moderator_endconf]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <ToggleElement name="announce_name" text="Announce Name"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.announce_name]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <ToggleElement name="announce_count" text="Announce Count"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.announce_count]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <ToggleElement name="announce_recording" text="Announce Recording"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.announce_recording]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <ToggleElement name="mute" text="Mute" true-value="true"
                                                    false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.mute]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <ToggleElement name="sounds" text="Sounds" true-value="true"
                                                    false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :conditions="[() => permissions.sounds]"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextElement name="email_address" label="Email Address"
                                                    :floating="false" :conditions="[() => permissions.email_address]"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="placeholder1" />

                                                <TextElement name="account_code" label="Account Code"
                                                    :floating="false" :conditions="[() => permissions.account_code]"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="advanced_button_container" />
                                                <ButtonElement name="advanced_submit" button-label="Save"
                                                    :submits="true" align="right" />
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
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Conference Room",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);

const permissions = computed(() => props.options?.permissions ?? {});
const conferenceCenters = computed(() => props.options?.conference_centers ?? []);
const profiles = computed(() => props.options?.profiles ?? []);

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit("success", "success", { message: ["Copied to clipboard."] });
    }).catch(() => {
        emit("error", { response: { data: { errors: { request: ["Failed to copy to clipboard."] } } } });
    });
};

const defaultValues = computed(() => ({
    conference_room_uuid: props.options?.item?.conference_room_uuid ?? null,
    conference_center_uuid: props.options?.item?.conference_center_uuid ?? null,
    conference_room_name: props.options?.item?.conference_room_name ?? null,
    moderator_pin: props.options?.item?.moderator_pin ?? null,
    participant_pin: props.options?.item?.participant_pin ?? null,
    profile: props.options?.item?.profile ?? "default",
    record: props.options?.item?.record ?? "false",
    max_members: props.options?.item?.max_members ?? 0,
    start_datetime: props.options?.item?.start_datetime ?? null,
    stop_datetime: props.options?.item?.stop_datetime ?? null,
    wait_mod: props.options?.item?.wait_mod ?? "true",
    moderator_endconf: props.options?.item?.moderator_endconf ?? "false",
    announce_name: props.options?.item?.announce_name ?? "true",
    announce_recording: props.options?.item?.announce_recording ?? "true",
    announce_count: props.options?.item?.announce_count ?? "true",
    mute: props.options?.item?.mute ?? "false",
    sounds: props.options?.item?.sounds ?? "false",
    email_address: props.options?.item?.email_address ?? null,
    account_code: props.options?.item?.account_code ?? null,
    enabled: props.options?.item?.enabled ?? "true",
    description: props.options?.item?.description ?? null,
}));

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    if (props.mode === "create") {
        return await form$.$vueform.services.axios.post(route, requestData);
    }

    return await form$.$vueform.services.axios.put(route, requestData);
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
