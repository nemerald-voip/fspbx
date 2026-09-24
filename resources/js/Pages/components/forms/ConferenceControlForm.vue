<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="handleParentClose">
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
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-7xl sm:p-6">
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

                            <Vueform @mounted="(form) => form.disableValidation()" @submit="clearServerFormErrors" v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-2 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" :label="$t('Settings')" :elements="[
                                                    'conference_control_uuid',
                                                    'settings_header',
                                                    'control_name',
                                                    'control_enabled',
                                                    'control_description',
                                                    'button_container',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab v-if="mode === 'edit'" name="controls" :label="$t('Controls')" :elements="[
                                                    'controls_header',
                                                    'controls_table',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-10 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <HiddenElement name="conference_control_uuid" :meta="true" />

                                                <StaticElement name="settings_header" tag="h4" :content="$t('Conference Control Settings')"
                                                    :description="$t('Configure the control set name and availability.')" />

                                                <TextElement name="control_name" :label="$t('Name')"
                                                    :placeholder="$t('Conference control name')" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <ToggleElement name="control_enabled" :text="$t('Conference Control Enabled')"
                                                    true-value="true" false-value="false" :labels="{ on: 'On', off: 'Off' }"
                                                    :columns="{ sm: { container: 6 } }" label="&nbsp;" />

                                                <TextareaElement name="control_description" :label="$t('Description')" :rows="2" />

                                                <GroupElement name="button_container" />
                                                <ButtonElement name="settings_submit" :button-label="$t('Save')"
                                                    :submits="true" align="right" />

                                                <StaticElement name="controls_header" tag="h4" :content="$t('Controls')"
                                                    :description="$t('Manage the digits and actions available while a caller is inside the conference.')" />

                                                <StaticElement name="controls_table">
                                                    <div class="space-y-4">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <div class="text-sm text-gray-500">
                                                                {{ $tChoice('{1} :count control|[0,*] :count controls', details.length) }}
                                                            </div>
                                                            <div class="flex flex-wrap justify-end gap-2">
                                                                <button v-if="detailPermissions.detail_create" type="button"
                                                                    @click="openDetailForm()"
                                                                    class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                                                    {{ $t('Add') }}
                                                                </button>
                                                                <button v-if="detailPermissions.detail_update" type="button"
                                                                    :disabled="selectedDetails.length === 0"
                                                                    @click="toggleSelectedDetails"
                                                                    class="rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">
                                                                    {{ $t('Toggle') }}
                                                                </button>
                                                                <button v-if="detailPermissions.detail_destroy" type="button"
                                                                    :disabled="selectedDetails.length === 0"
                                                                    @click="confirmBulkDeleteDetails"
                                                                    class="rounded-md bg-red-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-50">
                                                                    {{ $t('Delete') }}
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="overflow-x-auto rounded-md border border-gray-200 bg-white">
                                                            <table class="min-w-[920px] table-fixed divide-y divide-gray-200">
                                                                <colgroup>
                                                                    <col v-if="hasDetailActions" class="w-12" />
                                                                    <col class="w-24" />
                                                                    <col class="w-56" />
                                                                    <col />
                                                                    <col class="w-28" />
                                                                    <col v-if="hasDetailActions" class="w-32" />
                                                                </colgroup>
                                                                <thead class="bg-gray-50">
                                                                    <tr>
                                                                        <th v-if="hasDetailActions" class="px-4 py-3 text-left">
                                                                            <input type="checkbox"
                                                                                :checked="allDetailsSelected"
                                                                                @change="toggleDetailPageSelection"
                                                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                                                        </th>
                                                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Digits') }}</th>
                                                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Action') }}</th>
                                                                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Data') }}</th>
                                                                        <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900">{{ $t('Enabled') }}</th>
                                                                        <th v-if="hasDetailActions" class="px-4 py-3 text-right text-sm font-semibold text-gray-900"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-gray-200 bg-white">
                                                                    <tr v-for="detail in details" :key="detail.conference_control_detail_uuid">
                                                                        <td v-if="hasDetailActions" class="px-4 py-2">
                                                                            <input v-model="selectedDetails" type="checkbox"
                                                                                :value="detail.conference_control_detail_uuid"
                                                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                                                                        </td>
                                                                        <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-700"
                                                                            :class="{ 'cursor-pointer hover:text-gray-900': detailPermissions.detail_update }"
                                                                            @click="detailPermissions.detail_update && openDetailForm(detail)">
                                                                            {{ detail.control_digits }}
                                                                        </td>
                                                                        <td class="whitespace-normal break-words px-4 py-2 text-sm text-gray-700">{{ detail.control_action }}</td>
                                                                        <td class="whitespace-normal break-words px-4 py-2 text-sm text-gray-500">{{ detail.control_data }}</td>
                                                                        <td class="whitespace-nowrap px-4 py-2 text-center text-sm">
                                                                            <button v-if="detailPermissions.detail_update" type="button"
                                                                                @click="toggleDetail(detail)"
                                                                                class="cursor-pointer">
                                                                                <Badge :text="detail.control_enabled === 'true' ? $t('True') : $t('False')"
                                                                                    v-bind="enabledBadgeProps(detail.control_enabled)" />
                                                                            </button>
                                                                            <Badge v-else :text="detail.control_enabled === 'true' ? $t('True') : $t('False')"
                                                                                v-bind="enabledBadgeProps(detail.control_enabled)" />
                                                                        </td>
                                                                        <td v-if="hasDetailActions" class="whitespace-nowrap px-3 py-1 text-right text-sm">
                                                                            <button v-if="detailPermissions.detail_update" type="button"
                                                                                @click="openDetailForm(detail)"
                                                                                class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                                                                                :title="$t('Edit')">
                                                                                <PencilSquareIcon class="h-5 w-5" />
                                                                            </button>
                                                                            <button v-if="detailPermissions.detail_destroy" type="button"
                                                                                @click="confirmDeleteDetail(detail)"
                                                                                class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-red-600"
                                                                                :title="$t('Delete')">
                                                                                <TrashIcon class="h-5 w-5" />
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                    <tr v-if="details.length === 0">
                                                                        <td :colspan="hasDetailActions ? 6 : 4"
                                                                            class="px-4 py-6 text-center text-sm text-gray-500">
                                                                            {{ $t('No controls found.') }}
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

    <AddEditItemModal :show="showDetailForm" :header="detailFormHeader" :loading="false" @close="closeDetailForm">
        <template #modal-body>
            <Vueform @mounted="(form) => form.disableValidation()" @submit="clearServerFormErrors" ref="detailForm$" :endpoint="submitDetailForm" @success="handleDetailSuccess"
                @error="handleDetailError" @response="handleDetailResponse" :display-errors="false"
                :default="detailDefaultValues">
                <TextElement name="control_digits" :label="$t('Digits')" placeholder="1" :floating="false" />
                <TextElement name="control_action" :label="$t('Action')" placeholder="vol talk dn" :floating="false" />
                <TextElement name="control_data" :label="$t('Data')" :placeholder="$t('Optional data')" :floating="false" />
                <ToggleElement name="control_enabled" :text="$t('Control Enabled')" true-value="true" false-value="false"
                    :labels="{ on: 'On', off: 'Off' }" label="&nbsp;" />
                <ButtonElement name="detail_submit" :button-label="$t('Save')" :submits="true" align="right" />
            </Vueform>
        </template>
    </AddEditItemModal>

    <ConfirmationModal :show="showDetailDeleteConfirmation" @close="showDetailDeleteConfirmation = false"
        @confirm="executeDetailDelete" :header="$t('Are you sure?')" :text="detailDeleteText"
        :confirm-button-label="$t('Delete')" :cancel-button-label="$t('Cancel')" :loading="detailDeleteSubmitting" />
</template>

<script setup>
import { clearServerFormErrors } from '../../../composables/serverFormErrors.js';
import { trans, transChoice } from '@i18n';
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
        default: () => trans('Conference Control'),
    },
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data", "reload-options"]);

const form$ = ref(null);
const detailForm$ = ref(null);
const details = ref([]);
const selectedDetails = ref([]);
const showDetailForm = ref(false);
const editingDetail = ref(null);
const showDetailDeleteConfirmation = ref(false);
const detailDeleteSubmitting = ref(false);
const detailDeleteTarget = ref(null);

const defaultValues = computed(() => ({
    conference_control_uuid: props.options?.item?.conference_control_uuid ?? null,
    control_name: props.options?.item?.control_name ?? null,
    control_enabled: props.options?.item?.control_enabled ?? "true",
    control_description: props.options?.item?.control_description ?? null,
}));

const detailPermissions = computed(() => props.options?.permissions ?? {});
const hasDetailActions = computed(() => (
    detailPermissions.value.detail_update || detailPermissions.value.detail_destroy
));
const allDetailsSelected = computed(() => (
    details.value.length > 0 && selectedDetails.value.length === details.value.length
));
const detailFormHeader = computed(() => (
    editingDetail.value ? trans('Edit Conference Control Detail') : trans('Create Conference Control Detail')
));
const detailDefaultValues = computed(() => ({
    control_digits: editingDetail.value?.control_digits ?? null,
    control_action: editingDetail.value?.control_action ?? null,
    control_data: editingDetail.value?.control_data ?? null,
    control_enabled: editingDetail.value?.control_enabled ?? "true",
}));
const detailDeleteText = computed(() => {
    if (detailDeleteTarget.value === "bulk") {
        return transChoice('{1} Delete :count selected control detail?|[0,*] Delete :count selected control details?', selectedDetails.value.length);
    }

    return trans('Delete this conference control detail?');
});

watch(
    () => props.options?.details,
    (value) => {
        details.value = Array.isArray(value) ? [...value] : [];
        selectedDetails.value = [];
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
    if (showDetailForm.value || showDetailDeleteConfirmation.value) {
        return;
    }

    emit("close");
};

const submitDetailForm = async (FormData, form$) => {
    const requestData = form$.requestData;

    if (editingDetail.value) {
        return await form$.$vueform.services.axios.put(editingDetail.value.update_route, requestData);
    }

    return await form$.$vueform.services.axios.post(props.options.routes.detail_store_route, requestData);
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

const handleDetailResponse = (response, form$) => {
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

const handleDetailSuccess = (response) => {
    emit("success", "success", response.data.messages);
    closeDetailForm();
    emit("reload-options");
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append(trans('Could not submit form'));
};

const handleDetailError = (error, detail, form$) => {
    form$.messageBag.clear();

    if (detail.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append(trans('Could not submit form'));
};

const openDetailForm = (detail = null) => {
    editingDetail.value = detail;
    showDetailForm.value = true;
};

const closeDetailForm = () => {
    showDetailForm.value = false;
    editingDetail.value = null;
};

const toggleDetailPageSelection = () => {
    selectedDetails.value = allDetailsSelected.value
        ? []
        : details.value.map((detail) => detail.conference_control_detail_uuid);
};

const toggleDetail = (detail) => {
    executeBulkToggle([detail.conference_control_detail_uuid]);
};

const toggleSelectedDetails = () => {
    executeBulkToggle(selectedDetails.value);
};

const executeBulkToggle = (items) => {
    form$.value.$vueform.services.axios.post(props.options.routes.detail_bulk_toggle_route, { items })
        .then((response) => {
            emit("success", "success", response.data.messages);
            emit("reload-options");
        })
        .catch((error) => emit("error", error));
};

const confirmDeleteDetail = (detail) => {
    detailDeleteTarget.value = detail;
    showDetailDeleteConfirmation.value = true;
};

const confirmBulkDeleteDetails = () => {
    detailDeleteTarget.value = "bulk";
    showDetailDeleteConfirmation.value = true;
};

const executeDetailDelete = () => {
    detailDeleteSubmitting.value = true;

    const request = detailDeleteTarget.value === "bulk"
        ? form$.value.$vueform.services.axios.post(props.options.routes.detail_bulk_delete_route, { items: selectedDetails.value })
        : form$.value.$vueform.services.axios.delete(detailDeleteTarget.value.destroy_route);

    request
        .then((response) => {
            emit("success", "success", response.data.messages);
            showDetailDeleteConfirmation.value = false;
            detailDeleteTarget.value = null;
            emit("reload-options");
        })
        .catch((error) => emit("error", error))
        .finally(() => {
            detailDeleteSubmitting.value = false;
        });
};

function enabledBadgeProps(value) {
    return value === "true"
        ? { backgroundColor: "bg-green-50", textColor: "text-green-700", ringColor: "ring-green-600/20" }
        : { backgroundColor: "bg-gray-50", textColor: "text-gray-600", ringColor: "ring-gray-500/20" };
}
</script>
