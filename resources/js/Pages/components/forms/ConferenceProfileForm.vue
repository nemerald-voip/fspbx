<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="handleParentClose">
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
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-7xl sm:p-6">
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
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-2 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'conference_profile_uuid',
                                                    'settings_header',
                                                    'profile_name',
                                                    'profile_enabled',
                                                    'profile_description',
                                                    'button_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab v-if="mode === 'edit'" name="parameters" label="Parameters" :elements="[
                                                    'parameters_header',
                                                    'parameters_table',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-10 shadow sm:rounded-md space-y-6 text-body bg-surface-2 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="conference_profile_uuid" :meta="true" />

                                                <StaticElement name="settings_header" tag="h4" content="Conference Profile Settings"
                                                    description="Configure the profile name and availability." />

                                                <TextElement name="profile_name" label="Name"
                                                    placeholder="Conference profile name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="profile_enabled" text="Conference Profile Enabled"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextareaElement name="profile_description" label="Description" :rows="2" />

                                                <GroupElement name="button_container" />
                                                <ButtonElement name="settings_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <StaticElement name="parameters_header" tag="h4" content="Parameters"
                                                    description="Manage the name/value parameters applied by this conference profile." />

                                                <StaticElement name="parameters_table">
                                                    <div class="space-y-4">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <div class="text-sm text-muted">
                                                                {{ params.length }} parameters
                                                            </div>
                                                            <div class="flex flex-wrap justify-end gap-2">
                                                                <button v-if="paramPermissions.param_create" type="button"
                                                                    @click="openParamForm()"
                                                                    class="rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover">
                                                                    Add
                                                                </button>
                                                                <button v-if="paramPermissions.param_update" type="button"
                                                                    :disabled="selectedParams.length === 0"
                                                                    @click="toggleSelectedParams"
                                                                    class="rounded-md bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50">
                                                                    Toggle
                                                                </button>
                                                                <button v-if="paramPermissions.param_destroy" type="button"
                                                                    :disabled="selectedParams.length === 0"
                                                                    @click="confirmBulkDeleteParams"
                                                                    class="rounded-md bg-danger-solid px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-danger-solid-hover disabled:cursor-not-allowed disabled:opacity-50">
                                                                    Delete
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="overflow-x-auto rounded-md border border-default bg-surface">
                                                            <table class="min-w-[920px] table-fixed divide-y divide-default">
                                                                <colgroup>
                                                                    <col v-if="hasParamActions" class="w-12" />
                                                                    <col class="w-24" />
                                                                    <col class="w-56" />
                                                                    <col />
                                                                    <col class="w-28" />
                                                                    <col v-if="hasParamActions" class="w-32" />
                                                                </colgroup>
                                                                <thead class="bg-surface-2">
                                                                    <tr>
                                                                        <th v-if="hasParamActions" class="px-4 py-3 text-left">
                                                                            <input type="checkbox"
                                                                                :checked="allParamsSelected"
                                                                                @change="toggleParamPageSelection"
                                                                                class="h-4 w-4 rounded border-strong text-accent-fg" />
                                                                        </th>
                                                                        <th class="px-4 py-3 text-left text-sm font-semibold text-heading">Name</th>
                                                                        <th class="px-4 py-3 text-left text-sm font-semibold text-heading">Value</th>
                                                                        <th class="px-4 py-3 text-left text-sm font-semibold text-heading">Description</th>
                                                                        <th class="px-4 py-3 text-center text-sm font-semibold text-heading">Enabled</th>
                                                                        <th v-if="hasParamActions" class="px-4 py-3 text-right text-sm font-semibold text-heading"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-default bg-surface">
                                                                    <tr v-for="param in params" :key="param.conference_profile_param_uuid">
                                                                        <td v-if="hasParamActions" class="px-4 py-2">
                                                                            <input v-model="selectedParams" type="checkbox"
                                                                                :value="param.conference_profile_param_uuid"
                                                                                class="h-4 w-4 rounded border-strong text-accent-fg" />
                                                                        </td>
                                                                        <td class="whitespace-nowrap px-4 py-2 text-sm text-body"
                                                                            :class="{ 'cursor-pointer hover:text-heading': paramPermissions.param_update }"
                                                                            @click="paramPermissions.param_update && openParamForm(param)">
                                                                            {{ param.profile_param_name }}
                                                                        </td>
                                                                        <td class="whitespace-normal break-words px-4 py-2 text-sm text-body">{{ param.profile_param_value }}</td>
                                                                        <td class="whitespace-normal break-words px-4 py-2 text-sm text-muted">{{ param.profile_param_description }}</td>
                                                                        <td class="whitespace-nowrap px-4 py-2 text-center text-sm">
                                                                            <button v-if="paramPermissions.param_update" type="button"
                                                                                @click="toggleParam(param)"
                                                                                class="cursor-pointer">
                                                                                <Badge :text="param.profile_param_enabled === 'true' ? 'True' : 'False'"
                                                                                    v-bind="enabledBadgeProps(param.profile_param_enabled)" />
                                                                            </button>
                                                                            <Badge v-else :text="param.profile_param_enabled === 'true' ? 'True' : 'False'"
                                                                                v-bind="enabledBadgeProps(param.profile_param_enabled)" />
                                                                        </td>
                                                                        <td v-if="hasParamActions" class="whitespace-nowrap px-3 py-1 text-right text-sm">
                                                                            <button v-if="paramPermissions.param_update" type="button"
                                                                                @click="openParamForm(param)"
                                                                                class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-body"
                                                                                title="Edit">
                                                                                <PencilSquareIcon class="h-5 w-5" />
                                                                            </button>
                                                                            <button v-if="paramPermissions.param_destroy" type="button"
                                                                                @click="confirmDeleteParam(param)"
                                                                                class="rounded-full p-2 text-subtle transition hover:bg-surface-3 hover:text-danger"
                                                                                title="Delete">
                                                                                <TrashIcon class="h-5 w-5" />
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                    <tr v-if="params.length === 0">
                                                                        <td :colspan="hasParamActions ? 6 : 4"
                                                                            class="px-4 py-6 text-center text-sm text-muted">
                                                                            No parameters found.
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </StaticElement>
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

    <AddEditItemModal :show="showParamForm" :header="paramFormHeader" :loading="false" @close="closeParamForm">
        <template #modal-body>
            <Vueform ref="paramForm$" :endpoint="submitParamForm" @success="handleParamSuccess"
                @error="handleParamError" @response="handleParamResponse" :display-errors="false"
                :default="paramDefaultValues">
                <TextElement name="profile_param_name" label="Name" placeholder="Parameter name" :floating="false" />
                <TextElement name="profile_param_value" label="Value" placeholder="Parameter value" :floating="false" />
                <TextElement name="profile_param_description" label="Description" placeholder="Optional description" :floating="false" />
                <ToggleElement name="profile_param_enabled" text="Parameter Enabled" true-value="true" false-value="false"
                    :labels="{ on: 'On', off: 'Off' }" label="&nbsp;" />
                <ButtonElement name="param_submit" button-label="Save" :submits="true" align="right" />
            </Vueform>
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showParamDeleteConfirmation" @close="showParamDeleteConfirmation = false"
        @confirm="executeParamDelete" :header="'Are you sure?'" :text="paramDeleteText"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" :loading="paramDeleteSubmitting" />
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { PencilSquareIcon, TrashIcon, XMarkIcon } from "@heroicons/vue/24/solid";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import ConfirmationModal from "../modal/ConfirmationModal.vue";
import Badge from "@generalComponents/Badge.vue";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: {
        type: String,
        default: "Conference Profile",
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data", "reload-options"]);

const form$ = ref(null);
const paramForm$ = ref(null);
const params = ref([]);
const selectedParams = ref([]);
const showParamForm = ref(false);
const editingParam = ref(null);
const showParamDeleteConfirmation = ref(false);
const paramDeleteSubmitting = ref(false);
const paramDeleteTarget = ref(null);

const defaultValues = computed(() => ({
    conference_profile_uuid: props.options?.item?.conference_profile_uuid ?? null,
    profile_name: props.options?.item?.profile_name ?? null,
    profile_enabled: props.options?.item?.profile_enabled ?? "true",
    profile_description: props.options?.item?.profile_description ?? null,
}));

const paramPermissions = computed(() => props.options?.permissions ?? {});
const hasParamActions = computed(() => (
    paramPermissions.value.param_update || paramPermissions.value.param_destroy
));
const allParamsSelected = computed(() => (
    params.value.length > 0 && selectedParams.value.length === params.value.length
));
const paramFormHeader = computed(() => (
    editingParam.value ? "Edit Conference Profile Parameter" : "Create Conference Profile Parameter"
));
const paramDefaultValues = computed(() => ({
    profile_param_name: editingParam.value?.profile_param_name ?? null,
    profile_param_value: editingParam.value?.profile_param_value ?? null,
    profile_param_description: editingParam.value?.profile_param_description ?? null,
    profile_param_enabled: editingParam.value?.profile_param_enabled ?? "true",
}));
const paramDeleteText = computed(() => {
    if (paramDeleteTarget.value === "bulk") {
        return `Delete ${selectedParams.value.length} selected profile parameter(s)?`;
    }

    return "Delete this conference profile parameter?";
});

watch(
    () => props.options?.params,
    (value) => {
        params.value = Array.isArray(value) ? [...value] : [];
        selectedParams.value = [];
    },
    { immediate: true },
);

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

const handleParentClose = () => {
    if (showParamForm.value || showParamDeleteConfirmation.value) {
        return;
    }

    emit("close");
};

const submitParamForm = async (FormData, form$) => {
    const requestData = form$.requestData;

    if (editingParam.value) {
        return await form$.$vueform.services.axios.put(editingParam.value.update_route, requestData);
    }

    return await form$.$vueform.services.axios.post(props.options.routes.param_store_route, requestData);
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
    applyValidationErrors(response, form$);
};

const handleParamResponse = (response, form$) => {
    applyValidationErrors(response, form$);
};

const applyValidationErrors = (response, form$) => {
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

const handleParamSuccess = (response) => {
    emit("success", "success", response.data.messages);
    closeParamForm();
    emit("reload-options");
};

const handleError = (error, params, form$) => {
    form$.messageBag.clear();

    if (params.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append("Could not submit form");
};

const handleParamError = (error, param, form$) => {
    form$.messageBag.clear();

    if (param.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append("Could not submit form");
};

const openParamForm = (param = null) => {
    editingParam.value = param;
    showParamForm.value = true;
};

const closeParamForm = () => {
    showParamForm.value = false;
    editingParam.value = null;
};

const toggleParamPageSelection = () => {
    selectedParams.value = allParamsSelected.value
        ? []
        : params.value.map((param) => param.conference_profile_param_uuid);
};

const toggleParam = (param) => {
    executeBulkToggle([param.conference_profile_param_uuid]);
};

const toggleSelectedParams = () => {
    executeBulkToggle(selectedParams.value);
};

const executeBulkToggle = (items) => {
    form$.value.$vueform.services.axios.post(props.options.routes.param_bulk_toggle_route, { items })
        .then((response) => {
            emit("success", "success", response.data.messages);
            emit("reload-options");
        })
        .catch((error) => emit("error", error));
};

const confirmDeleteParam = (param) => {
    paramDeleteTarget.value = param;
    showParamDeleteConfirmation.value = true;
};

const confirmBulkDeleteParams = () => {
    paramDeleteTarget.value = "bulk";
    showParamDeleteConfirmation.value = true;
};

const executeParamDelete = () => {
    paramDeleteSubmitting.value = true;

    const request = paramDeleteTarget.value === "bulk"
        ? form$.value.$vueform.services.axios.post(props.options.routes.param_bulk_delete_route, { items: selectedParams.value })
        : form$.value.$vueform.services.axios.delete(paramDeleteTarget.value.destroy_route);

    request
        .then((response) => {
            emit("success", "success", response.data.messages);
            showParamDeleteConfirmation.value = false;
            paramDeleteTarget.value = null;
            emit("reload-options");
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            paramDeleteSubmitting.value = false;
        });
};

function enabledBadgeProps(value) {
    return value === "true"
        ? { backgroundColor: "bg-success-subtle", textColor: "text-success", ringColor: "ring-success/20" }
        : { backgroundColor: "bg-surface-2", textColor: "text-body", ringColor: "ring-strong/20" };
}
</script>
