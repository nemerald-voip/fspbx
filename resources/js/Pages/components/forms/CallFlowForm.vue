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
                                    <span class="sr-only">{{ $t('Close') }}</span>
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
                                    <div class="text-lg text-blue-600 m-auto">{{ $t('Loading...') }}</div>
                                </div>
                            </div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
            @response="handleResponse" :display-errors="false" :default="defaultValues">
            <template #empty>
                <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                    <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                        <FormTabs view="vertical">
                            <FormTab name="settings" :label="$t('Settings')" :elements="[
                                'call_flow_uuid',
                                'call_flow_uuid_clean',
                                'settings_header',
                                'call_flow_name',
                                'call_flow_enabled',
                                'call_flow_extension',
                                'call_flow_feature_code',
                                'call_flow_description',
                                'routing_divider',
                                'call_flow_status',
                                'routing_summary',
                                'default_route',
                                'alternate_route',
                                'button_container',
                                'settings_submit',
                            ]" />
                            <FormTab name="advanced" :label="$t('Advanced')" :elements="[
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

                            <StaticElement name="settings_header" tag="h4" :content="$t('Call Flow Settings')"
                                :description="$t('Configure the feature code, current state, and call routing.')" />

                            <StaticElement name="call_flow_uuid_clean"
                                :conditions="[() => props.options?.item?.call_flow_uuid]">
                                <div class="mb-1">
                                    <div class="text-sm font-medium text-gray-600 mb-1">
                                        {{ $t('Unique ID') }}
                                    </div>

                                    <div class="flex items-center group">
                                        <span class="text-sm text-gray-900 select-all font-normal">
                                            {{ props.options?.item?.call_flow_uuid }}
                                        </span>

                                        <button type="button"
                                            @click="handleCopyToClipboard(props.options?.item?.call_flow_uuid)"
                                            class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                            :title="$t('Copy to clipboard')">
                                            <ClipboardDocumentIcon
                                                class="h-4 w-4 text-gray-500 hover:text-gray-900 cursor-pointer" />
                                        </button>
                                    </div>
                                </div>
                            </StaticElement>

                            <TextElement name="call_flow_name" :label="$t('Name')" :placeholder="$t('Enter call flow name')"
                                :floating="false" :columns="{ sm: { container: 6 } }" />

                            <ToggleElement name="call_flow_enabled" :text="$t('Call Flow Enabled')" true-value="true"
                                false-value="false" :labels="{ on: $t('On'), off: $t('Off') }"
                                :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                            <TextElement name="call_flow_extension" :label="$t('Extension')" :placeholder="$t('Enter extension')"
                                :floating="false" :columns="{ sm: { container: 6 } }" @change="handleExtensionChange" />

                            <TextElement name="call_flow_feature_code" :label="$t('Feature Code')"
                                :placeholder="$t('Enter feature code')" :floating="false"
                                :columns="{ sm: { container: 6 } }" />

                            <TextareaElement name="call_flow_description" :label="$t('Description')" :rows="2" />

                            <StaticElement name="routing_divider" tag="hr" />

                            <RadiogroupElement name="call_flow_status" :label="$t('Calls Are Routing To')" view="tabs"
                                :items="routeItems" :columns="{ sm: { wrapper: 6 } }">
                                <template #radio="{ classes, item, value, id, name, isDisabled, el$ }">
                                    <div :class="classes.wrapper">
                                        <input v-model="el$.model" type="radio" :value="value" :class="classes.input"
                                            :name="name" :id="id" :disabled="isDisabled" />
                                        <span class="flex min-w-0 flex-col items-center leading-tight">
                                            <span class="max-w-full truncate font-medium">{{ item.label }}</span>
                                            <span v-if="item.caption" class="mt-0.5 text-xs opacity-80">{{ item.caption }}</span>
                                        </span>
                                    </div>
                                </template>
                            </RadiogroupElement>

                            <StaticElement name="routing_summary">
                                <div class="space-y-2">
                                    <div class="flex items-start gap-3 rounded-md border border-gray-200 bg-white px-4 py-3">
                                        <ArrowRightIcon class="mt-0.5 h-5 w-5 flex-none"
                                            :class="isCallFlowEnabled ? 'text-indigo-600' : 'text-gray-400'"
                                            aria-hidden="true" />
                                        <div class="min-w-0 text-sm">
                                            <div class="text-gray-500">{{ routingSummary.caption }}</div>
                                            <div class="text-gray-900">
                                                <span class="font-semibold">{{ routingSummary.destination }}</span>
                                                <span v-if="routingSummary.details" class="text-gray-500"> · {{ routingSummary.details }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-500">{{ switchHelpText }}</p>
                                </div>
                            </StaticElement>

                            <GroupElement name="default_route" :add-class="{ wrapper: routeCardClass('true') }">
                                <StaticElement name="default_route_header">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div v-if="routeCards.default.hasLabel"
                                                class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                {{ $t('Default Route') }}
                                            </div>
                                            <div class="truncate text-lg font-semibold text-gray-900">
                                                {{ routeCards.default.name }}
                                            </div>
                                            <p class="text-sm text-gray-500">{{ $t('Your normal call path.') }}</p>
                                        </div>
                                        <span v-if="routeCards.default.active"
                                            class="inline-flex flex-none items-center gap-1.5 rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800">
                                            <span class="h-2 w-2 rounded-full bg-indigo-600" aria-hidden="true"></span>
                                            {{ $t('Active now') }}
                                        </span>
                                        <span v-else class="flex-none py-1 text-xs text-gray-500">{{ $t('Not active') }}</span>
                                    </div>
                                </StaticElement>

                                <SelectElement name="call_flow_action" :items="routingTypes" label-prop="name"
                                    :search="true" :native="false" :label="$t('Destination Type')" input-type="search"
                                    autocomplete="off" :placeholder="$t('Choose type')" :floating="false" :strict="false"
                                    :columns="{ sm: { container: 6 } }"
                                    @change="(newValue, oldValue, el$) => handleActionChange(oldValue, el$, 'default_route.call_flow_target')" />

                                <SelectElement name="call_flow_target"
                                    :items="(query, input) => fetchRoutingTargets(query, input, 'default_route.call_flow_action')"
                                    :search="true" label-prop="name" :native="false" :label="$t('Destination')"
                                    input-type="search" allow-absent :object="true" :format-data="formatRoutingTarget"
                                    autocomplete="off" :placeholder="$t('Choose destination')" :floating="false" :strict="false"
                                    :columns="{ sm: { container: 6 } }" :conditions="[
                                        ['default_route.call_flow_action', 'not_empty'],
                                        ['default_route.call_flow_action', 'not_in', destinationTypesWithoutTarget]
                                    ]" />

                                <TextElement name="call_flow_label" :label="$t('Display Label')"
                                    :placeholder="$t('Example: Day Mode')" :floating="false"
                                    :description="$t('Shown on the phone after switching and in the call flow list.')"
                                    :columns="{ sm: { container: 6 } }" />

                                <SelectElement name="call_flow_sound" :items="soundOptions" :groups="true" :search="true"
                                    :native="false" :label="$t('Switch Confirmation Sound')" input-type="search" allow-absent
                                    autocomplete="off" :placeholder="$t('Optional sound')" :floating="false" :strict="false"
                                    :description="$t('Played to whoever dials the feature code to switch to this route. Callers do not hear it.')"
                                    :columns="{ sm: { container: 6 } }" />
                            </GroupElement>

                            <GroupElement name="alternate_route" :add-class="{ wrapper: routeCardClass('false') }">
                                <StaticElement name="alternate_route_header">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div v-if="routeCards.alternate.hasLabel"
                                                class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                {{ $t('Alternate Route') }}
                                            </div>
                                            <div class="truncate text-lg font-semibold text-gray-900">
                                                {{ routeCards.alternate.name }}
                                            </div>
                                            <p class="text-sm text-gray-500">
                                                {{ $t('Used when switched for after hours, holidays, or temporary coverage.') }}
                                            </p>
                                        </div>
                                        <span v-if="routeCards.alternate.active"
                                            class="inline-flex flex-none items-center gap-1.5 rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800">
                                            <span class="h-2 w-2 rounded-full bg-indigo-600" aria-hidden="true"></span>
                                            {{ $t('Active now') }}
                                        </span>
                                        <span v-else class="flex-none py-1 text-xs text-gray-500">{{ $t('Not active') }}</span>
                                    </div>
                                </StaticElement>

                                <SelectElement name="call_flow_alternate_action" :items="routingTypes" label-prop="name"
                                    :search="true" :native="false" :label="$t('Destination Type')" input-type="search"
                                    autocomplete="off" :placeholder="$t('Choose type')" :floating="false" :strict="false"
                                    :columns="{ sm: { container: 6 } }"
                                    @change="(newValue, oldValue, el$) => handleActionChange(oldValue, el$, 'alternate_route.call_flow_alternate_target')" />

                                <SelectElement name="call_flow_alternate_target"
                                    :items="(query, input) => fetchRoutingTargets(query, input, 'alternate_route.call_flow_alternate_action')"
                                    :search="true" label-prop="name" :native="false" :label="$t('Destination')"
                                    input-type="search" allow-absent :object="true" :format-data="formatRoutingTarget"
                                    autocomplete="off" :placeholder="$t('Choose destination')" :floating="false" :strict="false"
                                    :columns="{ sm: { container: 6 } }" :conditions="[
                                        ['alternate_route.call_flow_alternate_action', 'not_empty'],
                                        ['alternate_route.call_flow_alternate_action', 'not_in', destinationTypesWithoutTarget]
                                    ]" />

                                <TextElement name="call_flow_alternate_label" :label="$t('Display Label')"
                                    :placeholder="$t('Example: Night Mode')" :floating="false"
                                    :description="$t('Shown on the phone after switching and in the call flow list.')"
                                    :columns="{ sm: { container: 6 } }" />

                                <SelectElement name="call_flow_alternate_sound" :items="soundOptions" :groups="true"
                                    :search="true" :native="false" :label="$t('Switch Confirmation Sound')" input-type="search"
                                    allow-absent autocomplete="off" :placeholder="$t('Optional sound')" :floating="false"
                                    :strict="false"
                                    :description="$t('Played to whoever dials the feature code to switch to this route. Callers do not hear it.')"
                                    :columns="{ sm: { container: 6 } }" />
                            </GroupElement>

                            <GroupElement name="button_container" />

                            <ButtonElement name="settings_submit" :button-label="$t('Save')" :submits="true" align="right" />

                            <StaticElement name="advanced_header" tag="h4" :content="$t('Advanced Settings')"
                                :description="$t('Configure optional grouping and PIN protection.')" />

                            <SelectElement name="call_flow_group" :items="groupOptions" :search="true" :native="false"
                                :label="$t('Group')" input-type="search" autocomplete="off" :placeholder="$t('Select group')"
                                :floating="false" :strict="true" :columns="{ sm: { container: 6 } }"
                                :info="$t('Optional. When a grouped call flow is switched to the alternate route, the other call flows in that group switch back to the default route.')" />

                            <ButtonElement name="new_group_button" :button-label="$t('New Group')" :secondary="true"
                                :submits="false" align="left" :columns="{ sm: { container: 6 } }" label="&nbsp;"
                                @click="startAddingGroup" />

                            <TextElement name="call_flow_pin_number" :label="$t('PIN Number')" :placeholder="$t('Optional PIN')"
                                :floating="false" :columns="{ sm: { wrapper: 6 } }" />

                            <GroupElement name="advanced_button_container" />

                            <ButtonElement name="advanced_submit" :button-label="$t('Save')" :submits="true" align="right" />
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
import { ArrowRightIcon, ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import { trans } from "@i18n";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: () => trans("Call Flow"),
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
        emit("success", "success", { message: [trans("Copied to clipboard.")] });
    }).catch(() => {
        emit("error", { response: { data: { errors: { request: [trans("Failed to copy to clipboard.")] } } } });
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

// Live form values so the route switch, summary, and cards follow unsaved edits.
const formValues = computed(() => form$.value?.data ?? defaultValues.value);
const currentStatus = computed(() => (formValues.value.call_flow_status === "false" ? "false" : "true"));
const isCallFlowEnabled = computed(() => formValues.value.call_flow_enabled !== "false");

const routeCard = (status, labelKey, fallback) => {
    const label = String(formValues.value[labelKey] ?? "").trim();

    return {
        name: label || fallback,
        hasLabel: label !== "",
        active: currentStatus.value === status,
    };
};

const routeCards = computed(() => ({
    default: routeCard("true", "call_flow_label", trans("Default Route")),
    alternate: routeCard("false", "call_flow_alternate_label", trans("Alternate Route")),
}));

const routeItems = computed(() => [
    {
        value: "true",
        label: routeCards.value.default.name,
        caption: routeCards.value.default.hasLabel ? trans("Default Route") : null,
    },
    {
        value: "false",
        label: routeCards.value.alternate.name,
        caption: routeCards.value.alternate.hasLabel ? trans("Alternate Route") : null,
    },
]);

const routeCardClass = (status) => currentStatus.value === status
    ? "rounded-lg border border-indigo-600 bg-indigo-50/50 p-5 ring-1 ring-indigo-600"
    : "rounded-lg border border-gray-200 bg-white p-5";

const describeRoute = (actionKey, targetKey) => {
    const action = formValues.value[actionKey];
    const typeName = routingTypes.value.find((type) => type.value === action)?.name ?? action;

    if (!action) {
        return { destination: trans("No destination set"), type: null };
    }

    if (destinationTypesWithoutTarget.includes(action)) {
        return { destination: typeName, type: null };
    }

    const target = formValues.value[targetKey];
    const targetName = target && typeof target === "object"
        ? (target.name ?? target.extension ?? null)
        : target;

    return {
        destination: targetName || trans("No destination set"),
        type: targetName ? typeName : null,
    };
};

const routingSummary = computed(() => {
    const isDefault = currentStatus.value === "true";
    const route = isDefault
        ? describeRoute("call_flow_action", "call_flow_target")
        : describeRoute("call_flow_alternate_action", "call_flow_alternate_target");
    const routeName = isDefault ? routeCards.value.default.name : routeCards.value.alternate.name;
    const extension = String(formValues.value.call_flow_extension ?? "").trim();

    let caption;
    if (isCallFlowEnabled.value) {
        caption = extension
            ? trans("Calls to :extension currently go to", { extension })
            : trans("Calls currently go to");
    } else {
        caption = extension
            ? trans("This call flow is off. When it is on, calls to :extension go to", { extension })
            : trans("This call flow is off. When it is on, calls go to");
    }

    return {
        caption,
        destination: route.destination,
        details: [route.type, routeName].filter(Boolean).join(" · "),
    };
});

const switchHelpText = computed(() => {
    const code = String(formValues.value.call_flow_feature_code ?? "").trim();

    if (!code) {
        return trans("Set a feature code to switch routes from a phone.");
    }

    return trans("Dial :code to switch routes. BLF keys for :code light up while :route is active.", {
        code,
        route: routeCards.value.alternate.name,
    });
});

const handleActionChange = (oldValue, el$, targetPath) => {
    const target = el$.form$.el$(targetPath);

    if (!target) {
        return;
    }

    if (oldValue !== null && oldValue !== undefined) {
        target.clear();
    }

    target.updateItems();
};

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
    return { [name]: value?.bridge_uuid ?? value?.extension ?? value ?? null };
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
        groupError.value = trans("Could not find the group save route.");
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
            ?? trans("Could not save the group.");
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

// Route fields live inside GroupElements, so server error keys are matched by element name.
function findElementByName(elements, name) {
    for (const el$ of Object.values(elements ?? {})) {
        if (el$.name === name) {
            return el$;
        }

        const child = findElementByName(el$.children$, name);
        if (child) {
            return child;
        }
    }

    return null;
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => {
        clearErrorsRecursive(el$);
    });

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            const el$ = findElementByName(form$.elements$, elName);

            if (el$) {
                el$.messageBag.append(response.data.errors[elName][0]);
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
