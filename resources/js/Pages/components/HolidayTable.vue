<template>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Holiday</th>
                                <th class="hidden px-6 py-3 text-left text-sm font-semibold text-gray-900 sm:table-cell">
                                    Date(s)
                                </th>
                                <th class="hidden px-6 py-3 text-left text-sm font-semibold text-gray-900 sm:table-cell">
                                    Route To</th>
                                <th class="relative px-6 py-3 text-left text-sm font-medium text-gray-500">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="!loading && holidays.length" class="divide-y divide-gray-200 bg-white">
                            <tr v-for="holiday in holidays" :key="holiday.uuid">
                                <td class=" px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ holiday.description }}

                                    <div class="px-6 py-2 text-sm text-gray-500 sm:hidden">
                                        {{ holiday.human_date }}
                                    </div>

                                    <div class="px-6 py-2 text-sm text-gray-500 sm:hidden">
                                        {{ holiday.target_label }}
                                    </div>
                                </td>
                                <!-- <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ call.members.length }}
                                </td> -->

                                <td class="hidden px-6 py-2 text-sm text-gray-500 sm:table-cell">
                                    {{ holiday.human_date }}
                                </td>
                                <td class="hidden px-6 py-2 text-sm text-gray-500 sm:table-cell">
                                    {{ holiday.target_label }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-2 text-right text-sm font-medium">
                                    <div class="flex items-center whitespace-nowrap justify-end">
                                        <ejs-tooltip :content="'Edit'" position='TopCenter'
                                            target="#destination_tooltip_target">
                                            <div id="destination_tooltip_target">
                                                <PencilSquareIcon @click="handleEditButtonClick(holiday.uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />

                                            </div>
                                        </ejs-tooltip>

                                        <ejs-tooltip :content="'Delete'" position='TopCenter'
                                            target="#delete_tooltip_target">
                                            <div id="delete_tooltip_target">
                                                <TrashIcon @click="handleSingleItemDeleteRequest(holiday.uuid)"
                                                    class="h-9 w-9 transition duration-500 ease-in-out py-2 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 active:bg-gray-300 active:duration-150 cursor-pointer" />
                                            </div>
                                        </ejs-tooltip>
                                    </div>

                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && holidays.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">
                Adjust your search and try again.
              </p> -->
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="text-center my-5 text-sm text-gray-500">
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-6 py-1">
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                                <div class="h-2 bg-slate-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- 
    <ConfirmationModal :show="showDeleteConfirmationModal" @close="showDeleteConfirmationModal = false"
        @confirm="confirmDeleteAction" :header="'Confirm Deletion'"
        :text="'This action will permanently delete the selected emergency call(s). Are you sure you want to proceed?'"
        :confirm-button-label="'Delete'" cancel-button-label="Cancel" /> -->
</template>

<script setup>
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import { registerLicense } from '@syncfusion/ej2-base';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";



const props = defineProps({
    holidays: Object,
    loading: Boolean,
})

const emits = defineEmits(['edit-item', 'delete-item']);


const handleEditButtonClick = (uuid) => {
    emits('edit-item', uuid)

}

const handleUpdateRequest = (form) => {
    updateFormSubmiting.value = true;
    formErrors.value = null;

    axios.put(itemOptions.value.routes.update_route, form)
        .then((response) => {
            updateFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            handleModalClose();
            loadData();
        })
        .catch((error) => {
            updateFormSubmiting.value = false;
            handleFormErrorResponse(error);
        });
};

const handleSingleItemDeleteRequest = (uuid) => {
    showDeleteConfirmationModal.value = true;
    confirmDeleteAction.value = () => executeBulkDelete([uuid]);
};

const executeBulkDelete = (items = selectedItems.value) => {
    axios.post(props.routes.emergency_calls_bulk_delete, { items })
        .then((response) => {
            handleModalClose();
            showNotification('success', response.data.messages);
            loadData();
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}


const handleModalClose = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    showDeleteConfirmationModal.value = false;
    // bulkUpdateModalTrigger.value = false;
}

const getItemOptions = (itemUuid = null) => {
    loadingModal.value = true;
    formErrors.value = null;

    const payload = itemUuid ? { item_uuid: itemUuid } : {};

    axios.post(props.routes.emergency_calls_item_options, payload)
        .then((response) => {
            loadingModal.value = false;
            itemOptions.value = response.data;
            // console.log(itemOptions.value);
        })
        .catch((error) => {
            handleModalClose();
            handleErrorResponse(error);
        });
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}

const handleClearErrors = () => {
    formErrors.value = null;
}

const handleFormErrorResponse = (error) => {
    if (error.request?.status == 419) {
        showNotification('error', { request: ["Session expired. Reload the page"] });
    } else if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
        formErrors.value = error.response.data.errors;
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }

}

const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');


</script>

<style>
@import "@syncfusion/ej2-base/styles/tailwind.css";
@import "@syncfusion/ej2-vue-popups/styles/tailwind.css";</style>