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

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
            @response="handleResponse" :display-errors="false" :default="defaultValues">
            <template #empty>
                <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                    <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                        <FormTabs view="vertical">
                            <FormTab name="settings" label="Settings" :elements="[
                                'call_flow_uuid',
                                'call_flow_uuid_clean',
                                'settings_header',
                                'call_flow_name',
                                'call_flow_extension',
                                'call_flow_feature_code',
                                'call_flow_status',
                                'call_flow_enabled',
                                'settings_container',
                                'settings_container2',
                                'settings_container3',
                                'settings_container4',
                                'settings_container5',
                                'routing_header',
                                'call_flow_label',
                                'call_flow_sound',
                                'call_flow_action',
                                'call_flow_target',
                                'alternate_header',
                                'call_flow_alternate_label',
                                'call_flow_alternate_sound',
                                'call_flow_alternate_action',
                                'call_flow_alternate_target',
                                'call_flow_description',
                                'button_container',
                                'settings_submit',
                            ]" />
                            <FormTab name="advanced" label="Advanced" :elements="[
                                'advanced_header',
                                'call_flow_group',
                                'new_group_button',
                                'call_flow_pin_number',
                                'advanced_button_container',
                                'advanced_submit',
                            ]" />
                        </FormTabs>
                    </div>

                    <div
                        class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>
                            <HiddenElement name="call_flow_uuid" :meta="true" />

                            <StaticElement name="settings_header" tag="h4" content="Call Flow Settings"
                                description="Configure the feature code, current state, and call routing." />

                            <StaticElement name="call_flow_uuid_clean"
                                :conditions="[() => props.options?.item?.call_flow_uuid]">
                                <div class="mb-1">
                                    <div class="text-sm font-medium text-gray-600 mb-1">
                                        Unique ID
                                    </div>

                                    <div class="flex items-center group">
                                        <span class="text-sm text-gray-900 select-all font-normal">
                                            {{ props.options?.item?.call_flow_uuid }}
                                        </span>

                                        <button type="button"
                                            @click="handleCopyToClipboard(props.options?.item?.call_flow_uuid)"
                                            class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                            title="Copy to clipboard">
                                            <ClipboardDocumentIcon
                                                class="h-4 w-4 text-gray-500 hover:text-gray-900 cursor-pointer" />
                                        </button>
                                    </div>
                                </div>
                            </StaticElement>

                            <TextElement name="call_flow_name" label="Name" placeholder="Enter call flow name"
                                :floating="false" :columns="{ sm: { container: 6 } }" />

                            <ToggleElement name="call_flow_enabled" text="Call Flow Enabled" true-value="true"
                                false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                            <TextElement name="call_flow_extension" label="Extension" placeholder="Enter extension"
                                :floating="false" :columns="{ sm: { container: 6 } }" @change="handleExtensionChange" />

                            <TextElement name="call_flow_feature_code" label="Feature Code"
                                placeholder="Enter feature code" :floating="false"
                                :columns="{ sm: { container: 6 } }" />


                            <GroupElement name="settings_container" />

                            <StaticElement name="divider" tag="hr" />
                            <GroupElement name="settings_container2" />

                            <RadiogroupElement name="call_flow_status" label="Calls Are Routing To" view="tabs" :items="[
                                {
                                    value: 'true',
                                    label: 'Default Route',
                                },
                                {
                                    value: 'false',
                                    label: 'Alternate Route',
                                },
                            ]" :columns="{ sm: { wrapper: 6 } }"
                                description="The Default route is your normal call path. The Alternate route is used when the flow is toggled for events like after hours, holidays, or temporary coverage." />

                            <GroupElement name="settings_container3" />

                            <StaticElement name="routing_header" tag="h4" content="Default Route"
                                description="Calls use this path during normal operation." />

                            <TextElement name="call_flow_label" label="Default Route Label"
                                placeholder="Example: Day Mode" :floating="false" :columns="{ sm: { container: 6 } }" />

                            <SelectElement name="call_flow_sound" :items="soundOptions" :groups="true" :search="true"
                                :native="false" label="Default Route Sound" input-type="search" allow-absent
                                autocomplete="off" placeholder="Optional sound" :floating="false" :strict="false"
                                :columns="{ sm: { container: 6 } }" />

                            <SelectElement name="call_flow_action" :items="routingTypes" label-prop="name"
                                :search="true" :native="false" label="Default Destination Type" input-type="search"
                                autocomplete="off" placeholder="Choose type" :floating="false" :strict="false"
                                :columns="{ sm: { container: 6 } }" @change="(newValue, oldValue, el$) => {
                                    const target = el$.form$.el$('call_flow_target');

                                    if (oldValue !== null && oldValue !== undefined) {
                                        target.clear();
                                    }

                                    target.updateItems();
                                }" />

                            <SelectElement name="call_flow_target"
                                :items="(query, input) => fetchRoutingTargets(query, input, 'call_flow_action')"
                                :search="true" label-prop="name" :native="false" label="Default Destination"
                                input-type="search" allow-absent :object="true" :format-data="formatRoutingTarget"
                                autocomplete="off" placeholder="Choose destination" :floating="false" :strict="false"
                                :columns="{ sm: { container: 6 } }" :conditions="[
                                    ['call_flow_action', 'not_empty'],
                                    ['call_flow_action', 'not_in', destinationTypesWithoutTarget]
                                ]" />

                                <GroupElement name="settings_container4" />

                            <StaticElement name="alternate_header" tag="h4" content="Alternate Route"
                                description="Calls use this path when the call flow is switched away from the default route." />

                            <TextElement name="call_flow_alternate_label" label="Alternate Route Label"
                                placeholder="Example: Night Mode" :floating="false"
                                :columns="{ sm: { container: 6 } }" />

                            <SelectElement name="call_flow_alternate_sound" :items="soundOptions" :groups="true"
                                :search="true" :native="false" label="Alternate Route Sound" input-type="search"
                                allow-absent autocomplete="off" placeholder="Optional sound" :floating="false"
                                :strict="false" :columns="{ sm: { container: 6 } }" />

                            <SelectElement name="call_flow_alternate_action" :items="routingTypes" label-prop="name"
                                :search="true" :native="false" label="Alternate Destination Type" input-type="search"
                                autocomplete="off" placeholder="Choose type" :floating="false" :strict="false"
                                :columns="{ sm: { container: 6 } }" @change="(newValue, oldValue, el$) => {
                                    const target = el$.form$.el$('call_flow_alternate_target');

                                    if (oldValue !== null && oldValue !== undefined) {
                                        target.clear();
                                    }

                                    target.updateItems();
                                }" />

                            <SelectElement name="call_flow_alternate_target"
                                :items="(query, input) => fetchRoutingTargets(query, input, 'call_flow_alternate_action')"
                                :search="true" label-prop="name" :native="false" label="Alternate Destination"
                                input-type="search" allow-absent :object="true" :format-data="formatRoutingTarget"
                                autocomplete="off" placeholder="Choose destination" :floating="false" :strict="false"
                                :columns="{ sm: { container: 6 } }" :conditions="[
                                    ['call_flow_alternate_action', 'not_empty'],
                                    ['call_flow_alternate_action', 'not_in', destinationTypesWithoutTarget]
                                ]" />

                                <GroupElement name="settings_container5" />

                            <TextareaElement name="call_flow_description" label="Description" :rows="2" />

                            <GroupElement name="button_container" />

                            <ButtonElement name="settings_submit" button-label="Save" :submits="true" align="right" />

                            <StaticElement name="advanced_header" tag="h4" content="Advanced Settings"
                                description="Configure optional grouping and PIN protection." />

                            <SelectElement name="call_flow_group" :items="groupOptions" :search="true" :native="false"
                                label="Group" input-type="search" autocomplete="off" placeholder="Select group"
                                :floating="false" :strict="true" :columns="{ sm: { container: 6 } }"
                                info="Optional. When a grouped call flow is switched to the alternate route, the other call flows in that group switch back to the default route." />

                            <ButtonElement name="new_group_button" button-label="New Group" :secondary="true"
                                :submits="false" align="left" :columns="{ sm: { container: 6 } }" label="&nbsp;"
                                @click="startAddingGroup" />

                            <TextElement name="call_flow_pin_number" label="PIN Number" placeholder="Optional PIN"
                                :floating="false" :columns="{ sm: { wrapper: 6 } }" />

                            <GroupElement name="advanced_button_container" />

                            <ButtonElement name="advanced_submit" button-label="Save" :submits="true" align="right" />
                        </FormElements>
                    </div>
                </div>
            </template>
                            </Vueform>

                            <CallFlowGroupModal v-if="!loading" :show="showNewGroupModal" :loading="isSavingGroup"
                                :error="groupError" @close="closeNewGroupModal" @confirm="addNewGroup" />
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
import CallFlowGroupModal from "../modal/CallFlowGroupModal.vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { XMarkIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Call Flow",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);

const form$ = ref(null);
const showNewGroupModal = ref(false);
const isSavingGroup = ref(false);
const groupError = ref(null);
const addedGroups = ref([]);

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit("success", "success", { message: ["Copied to clipboard."] });
    }).catch(() => {
        emit("error", { response: { data: { errors: { request: ["Failed to copy to clipboard."] } } } });
    });
};

const defaultValues = computed(() => ({
    call_flow_uuid: props.options?.item?.call_flow_uuid ?? null,
    call_flow_name: props.options?.item?.call_flow_name ?? null,
    call_flow_extension: props.options?.item?.call_flow_extension ?? null,
    call_flow_feature_code: props.options?.item?.call_flow_feature_code ?? null,
    call_flow_group: props.options?.item?.call_flow_group ?? null,
    call_flow_status: props.options?.item?.call_flow_status ?? "true",
    call_flow_enabled: props.options?.item?.call_flow_enabled ?? "true",
    call_flow_pin_number: props.options?.item?.call_flow_pin_number ?? null,
    call_flow_label: props.options?.item?.call_flow_label ?? null,
    call_flow_sound: props.options?.item?.call_flow_sound ?? null,
    call_flow_action: props.options?.item?.call_flow_action ?? null,
    call_flow_target: props.options?.item?.call_flow_target ?? null,
    call_flow_alternate_label: props.options?.item?.call_flow_alternate_label ?? null,
    call_flow_alternate_sound: props.options?.item?.call_flow_alternate_sound ?? null,
    call_flow_alternate_action: props.options?.item?.call_flow_alternate_action ?? null,
    call_flow_alternate_target: props.options?.item?.call_flow_alternate_target ?? null,
    call_flow_description: props.options?.item?.call_flow_description ?? null,
}));

const groupOptions = computed(() => {
    const groups = [
        ...(props.options?.group_options ?? []),
        ...addedGroups.value,
    ].filter(Boolean);

    return [...new Set(groups)]
        .sort((a, b) => String(a).localeCompare(String(b)))
        .map((group) => ({
            value: group,
            label: group,
        }));
});

const routingTypes = computed(() => props.options?.routing_types ?? []);
const soundOptions = computed(() => props.options?.sound_options ?? []);
const destinationTypesWithoutTarget = ["check_voicemail", "company_directory", "hangup"];

const fetchRoutingTargets = async (query, input, actionElementName) => {
    const action = input.$parent.el$.form$.el$(actionElementName);
    const route = props.options?.routes?.get_routing_options;

    if (!route || !action?.value) {
        return [];
    }

    try {
        const response = await action.$vueform.services.axios.post(route, {
            category: action.value,
        });

        return response.data.options;
    } catch (error) {
        emit("error", error);
        return [];
    }
};

const formatRoutingTarget = (name, value) => {
    return { [name]: value?.extension ?? value ?? null };
};

const handleExtensionChange = (newValue, oldValue, el$) => {
    if (props.mode !== "create") {
        return;
    }

    const featureCode = el$.form$.el$("call_flow_feature_code");
    const currentFeatureCode = featureCode?.value;

    if (!currentFeatureCode || currentFeatureCode === `*${oldValue}`) {
        featureCode.update(newValue ? `*${newValue}` : null);
    }
};

const startAddingGroup = () => {
    groupError.value = null;
    showNewGroupModal.value = true;
};

const closeNewGroupModal = () => {
    if (isSavingGroup.value) {
        return;
    }

    groupError.value = null;
    showNewGroupModal.value = false;
};

const addNewGroup = async (groupName) => {
    const group = String(groupName ?? "").trim();
    const route = props.options?.routes?.group_store_route;

    if (!route) {
        groupError.value = "Could not find the group save route.";
        return;
    }

    groupError.value = null;
    isSavingGroup.value = true;

    try {
        const response = await form$.value?.$vueform.services.axios.post(route, { name: group });
        const savedGroup = response?.data?.group?.value ?? group;

        if (response?.data?.group_options?.length) {
            addedGroups.value = response.data.group_options;
        } else if (!groupOptions.value.some((option) => option.value === savedGroup)) {
            addedGroups.value.push(savedGroup);
        }

        form$.value?.el$("call_flow_group")?.update(savedGroup);
        emit("success", "success", response?.data?.messages);
        showNewGroupModal.value = false;
    } catch (error) {
        groupError.value = error?.response?.data?.errors?.name?.[0]
            ?? error?.response?.data?.messages?.error?.[0]
            ?? "Could not save the group.";
    } finally {
        isSavingGroup.value = false;
    }
};


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
