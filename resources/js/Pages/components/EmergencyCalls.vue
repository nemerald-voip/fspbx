<template>
    <div class="space-y-6 px-4 py-6 sm:p-6">
        <div class="flex justify-between items-center">
            <h3 class="text-base font-semibold leading-6 text-gray-900">Emergency Calls</h3>
            <button type="button" @click.prevent="handleCreateButtonClick()"
                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Create
            </button>
        </div>
    </div>

    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                <div class="overflow-hidden border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Number</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Description</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Members</th>
                                <th class="relative px-6 py-3 text-left text-sm font-medium text-gray-500">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody v-if="!loading && emergencyCalls.length" class="divide-y divide-gray-200 bg-white">
                            <tr v-for="call in emergencyCalls" :key="call.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ call.emergency_number }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ call.description || '-' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ call.members.length }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <a href="#" class="text-orange-600 hover:text-orange-900">View</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="!loading && emergencyCalls.length === 0" class="text-center my-5">
                        <MagnifyingGlassIcon class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No results found</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">
                Adjust your search and try again.
              </p> -->
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="text-center my-5 text-sm text-gray-500">
                        Loading emergency calls...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showCreateModal" :header="'Create New Emergency Call'" :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateEmergencyCallForm :options="itemOptions" :errors="formErrors" 
                :is-submitting="createFormSubmiting" @submit="handleCreateRequest" @cancel="handleModalClose"  @error="handleFormErrorResponse"
                @success="showNotification('success', $event)" @clear-errors="handleClearErrors"/>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AddEditItemModal from "./modal/AddEditItemModal.vue";
import CreateEmergencyCallForm from "./forms/CreateEmergencyCallForm.vue";
import TableField from "./general/TableField.vue";
import { MagnifyingGlassIcon, TrashIcon, PencilSquareIcon } from "@heroicons/vue/24/solid";
import BulkActionButton from "./general/BulkActionButton.vue";
import { registerLicense } from '@syncfusion/ej2-base';
import { TooltipComponent as EjsTooltip } from "@syncfusion/ej2-vue-popups";
import Badge from "@generalComponents/Badge.vue";


const props = defineProps({
    routes: Object,
})

const emergencyCalls = ref([]);
const loading = ref(false);
const error = ref(null);
const showCreateModal = ref(false);
const loadingModal = ref(false)
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
const itemOptions = ref({})
const createFormSubmiting = ref(null);
const updateFormSubmiting = ref(null);


onMounted(async () => {
    loading.value = true;
    try {
        const response = await axios.get(props.routes.emergency_calls);
        emergencyCalls.value = response.data;
    } catch (err) {
        error.value = err.response?.data?.message || err.message;
    } finally {
        loading.value = false;
    }
});

const handleCreateButtonClick = () => {
    showCreateModal.value = true
    formErrors.value = null;
    loadingModal.value = true
    getItemOptions();
}

const handleCreateRequest = (form) => {
    createFormSubmiting.value = true;
    formErrors.value = null;
    
    axios.post(props.routes.store, form)
        .then((response) => {
            createFormSubmiting.value = false;
            showNotification('success', response.data.messages);
            handleModalClose();

        }).catch((error) => {
            createFormSubmiting.value = false;

            handleFormErrorResponse(error);
        });

};

const handleModalClose = () => {
    showCreateModal.value = false;
    // showEditModal.value = false;
    // confirmationModalTrigger.value = false;
    // bulkUpdateModalTrigger.value = false;
}

const getItemOptions = (itemUuid = null) => {
    loadingModal.value = true;
    formErrors.value = null;

    const payload = itemUuid ? { item_uuid: itemUuid } : {};

    axios.post(props.routes.item_options, payload)
        .then((response) => {
            loadingModal.value = false;
            itemOptions.value = response.data;
            console.log(itemOptions.value);
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

</script>