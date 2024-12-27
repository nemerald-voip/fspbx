<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in options.navigation" :key="item.name" href="#"
                    :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                    @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                    <component :is="iconComponents[item.icon]"
                        :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                        aria-hidden="true" />
                    <span class="truncate">{{ item.name }}</span>
                    <ExclamationCircleIcon v-if="((errors?.organization_name || errors?.organization_domain || errors?.region || errors?.package) && item.slug === 'organization') ||
                        (errors?.voicemail_alternate_greet_id && item.slug === 'advanced')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <div class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <form @submit.prevent="submitForm">
                <div v-if="activeTab === 'organization'">
                    <div class="shadow sm:rounded-md">
                        <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-base font-semibold leading-6 text-gray-900">Organization Details</h3>

                                <!-- <Toggle label="Status" v-model="" /> -->

                                <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                            </div>

                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="organization_name" label="Organization Name"
                                        class="truncate" />
                                    <InputField v-model="form.organization_name" type="text" name="organization_name"
                                        id="organization_name" class="mt-2" :error="!!errors?.organization_name" />
                                    <div v-if="errors?.organization_name" class="mt-2 text-xs text-red-600">
                                        {{ errors.organization_name[0] }}
                                    </div>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired target="organization_domain" label="Unique Organization Domain"
                                        class="truncate" />
                                    <InputField v-model="form.organization_domain" type="text" name="organization_domain"
                                        id="organization_domain" class="mt-2" :error="!!errors?.organization_domain" />
                                    <div v-if="errors?.organization_domain" class="mt-2 text-xs text-red-600">
                                        {{ errors.organization_domain[0] }}
                                    </div>
                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired label="Region" class="truncate mb-1" />

                                    <ComboBox :options="options.regions" :search="true" :placeholder="'Select region'"
                                        :error="errors?.region && errors.region.length > 0" :selectedItem="form.region"
                                        @update:model-value="handleUpdateRegionField" />
                                    <div v-if="errors?.region" class="mt-2 text-xs text-red-600">
                                        {{ errors.region[0] }}
                                    </div>
                                    <p class="mt-3 text-sm leading-6 text-gray-600">Choose the region closest to your users
                                        location. You won't be able to change it later.</p>

                                </div>

                                <div class="col-span-6 sm:col-span-3">
                                    <LabelInputRequired label="Package" class="truncate mb-1" />

                                    <ComboBox :options="options.packages" :search="true" :placeholder="'Select package'"
                                        :error="errors?.package && errors.package.length > 0" :selectedItem="form.package"
                                        @update:model-value="handleUpdatePackageField" />
                                    <div v-if="errors?.package" class="mt-2 text-xs text-red-600">
                                        {{ errors.package[0] }}
                                    </div>
                                    <p class="mt-3 text-sm leading-6 text-gray-600">Choose a package to set available features.</p>

                                </div>

                                <div class="divide-y divide-gray-200 col-span-6">

                                    <Toggle label="Secure User Credentials"
                                        description="When enabled, users will receive a one-time link to access their app password instead of plain text."
                                        v-model="form.dont_send_user_credentials" customClass="py-4" />

                                </div>



                            </div>

                        </div>
                        <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">

                            <button type="submit"
                                class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                ref="saveButtonRef" :disabled="isSubmitting">
                                <Spinner :show="isSubmitting" />
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div v-if="activeTab === 'connections'" action="#" method="POST">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                        <!-- <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Keys</h3>
                            <p class="mt-1 text-sm text-gray-500">Ensure calls are routed to the right team every time.
                                Select a routing option below to fit your business needs.</p>
                        </div> -->

                        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                            <div class="sm:col-span-full space-y-3">
                                <!-- <LabelInputOptional :target="'destination_actions'" :label="'Send calls to'" /> -->
                                <RingotelConnections v-model="connections" :routingTypes="options.routing_types"
                                    :optionsUrl="options.routes.get_routing_options" @add-connection="handleAddConnection"
                                    @delete-connection="handleDeleteConnectionRequest"
                                    @edit-connection="handleEditConnection"
                                    :isDeleting="showConnectionDeletingStatus" />
                            </div>

                        </div>

                        <div class="bg-gray-100 px-4 py-3 text-right sm:px-6">

                            <button @click.prevent="handleFinishButtonClick()"
                                class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2 
                                disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-indigo-300 disabled:text-indigo-500"
                                :disabled="connections.length == 0">
                                Finish
                            </button>
                        </div>


                    </div>
                </div>
            </div>
        </div>



    </div>

    <AddEditItemModal :customClass="'sm:max-w-3xl'" :show="showConnectionModal" :header="'Create a Connection'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateRingotelConnectionForm :options="options" :errors="errors"
                :is-submitting="ringotelConnectionFormSubmiting" @submit="handleCreateConnectionRequest"
                @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-3xl'" :show="showEditConnectionModal" :header="'Edit Connection'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdateRingotelConnectionForm :options="options" :errors="errors" :selected-connection="selectedConnection"
                :is-submitting="ringotelConnectionFormSubmiting" @submit="handleUpdateConnectionRequest"
                @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>
</template>


<script setup>
import { reactive, ref, watch } from "vue";
import { usePage } from '@inertiajs/vue3';


import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import InputFieldWithIcon from "@generalComponents/InputFieldWithIcon.vue";
import Popover from "@generalComponents/Popover.vue";
import Textarea from "@generalComponents/Textarea.vue";
import SyncAltIcon from "@icons/SyncAltIcon.vue";
import Toggle from "@generalComponents/Toggle.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "@generalComponents/Spinner.vue";
import { Switch, SwitchDescription, SwitchGroup, SwitchLabel } from '@headlessui/vue'
import { InformationCircleIcon } from "@heroicons/vue/24/outline";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { BuildingOfficeIcon } from '@heroicons/vue/24/outline';
import RingotelConnections from "../general/RingotelConnections.vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import CreateRingotelConnectionForm from "../forms/CreateRingotelConnectionForm.vue";
import UpdateRingotelConnectionForm from "../forms/UpdateRingotelConnectionForm.vue";

const ringotelConnectionFormSubmiting = ref(null);
const loadingModal = ref(false);

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    activeTab: String,
    errors: Object,
});

// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.activeTab || props.options.navigation[0].slug);

// Watch for changes in the activeTab prop and update the local activeTab
watch(
    () => props.activeTab,
    (newValue) => {
        activeTab.value = newValue || props.options.navigation[0].slug;
    }
);

const setActiveTab = (tabSlug) => {
    // activeTab.value = tabSlug;
};

const showConnectionModal = ref(false);
const showConnectionDeletingStatus = ref(false);
const selectedConnection = ref(null);
const showEditConnectionModal = ref(false);

// Map icon names to their respective components
const iconComponents = {
    'SyncAltIcon': SyncAltIcon,
    'BuildingOfficeIcon': BuildingOfficeIcon,
};

const connections = ref([...props.options.connections]);

// Watch for changes in props.options.connections and update the local variable
watch(
    () => props.options.connections,
    (newConnections) => {
        connections.value = [...newConnections];
    }
);


const page = usePage();

const form = reactive({
    organization_name: props.options.model.domain_description,
    organization_domain: props.options.settings.suggested_ringotel_domain,
    region: props.options.settings.organization_region,
    package: props.options.settings.package,
    dont_send_user_credentials: props.options.settings.dont_send_user_credentials === "true",
    domain_uuid: props.options.model.domain_uuid,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'error', 'success', 'clear-errors']);

const submitForm = () => {
    emits('submit', form); // Emit the event with the form data
}

const handleFinishButtonClick = () => {
    emits('cancel'); 
}

const handleUpdateRegionField = (selected) => {
    form.region = selected.value;
}

const handleUpdatePackageField = (selected) => {
    form.package = selected.value;
}

const handleAddConnection = (selected) => {
    emits('clear-errors');
    showConnectionModal.value = true;
}

const handleCreateConnectionRequest = (form) => {
    ringotelConnectionFormSubmiting.value = true;
    emits('clear-errors');

    axios.post(props.options.routes.create_connection, form)
        .then((response) => {
            ringotelConnectionFormSubmiting.value = false;
            emits('success', response.data.messages);

            // Add the new connection to the connections array
            connections.value.push({
                org_id: response.data.org_id,
                conn_id: response.data.conn_id,
                connection_name: response.data.connection_name,
                domain: response.data.domain
            });

            handleModalClose();
            // handleClearSelection();
        }).catch((error) => {
            ringotelConnectionFormSubmiting.value = false;
            // handleClearSelection();
            // handleFormErrorResponse(error);
            emits('error', error); // Emit the event with error
        });

};

const handleUpdateConnectionRequest = (form) => {
    ringotelConnectionFormSubmiting.value = true;
    emits('clear-errors');

    axios.post(props.options.routes.create_connection, form)
        .then((response) => {
            ringotelConnectionFormSubmiting.value = false;
            emits('success', response.data.messages);

            // Add the new connection to the connections array
            connections.value.push({
                org_id: response.data.org_id,
                conn_id: response.data.conn_id,
                connection_name: response.data.connection_name,
                domain: response.data.domain
            });

            handleModalClose();
            // handleClearSelection();
        }).catch((error) => {
            ringotelConnectionFormSubmiting.value = false;
            // handleClearSelection();
            // handleFormErrorResponse(error);
            emits('error', error); // Emit the event with error
        });

};

const handleEditConnection = (connection) => {
    emits('clear-errors');
    // Find the matching connection from props.options.connections
    const matchedConnection = props.options.connections.find(
        (conn) => conn.id === connection.conn_id
    );

    if (matchedConnection) {
        selectedConnection.value = matchedConnection;
        showEditConnectionModal.value = true;
        // console.log(selectedConnection.value);
    } else {
        emits('error', { request: "Matching connection not found" });
    }
}

const handleDeleteConnectionRequest = (connection) => {
    showConnectionDeletingStatus.value = true;
    // emits('clear-errors');

    axios.post(props.options.routes.delete_connection, connection)
        .then((response) => {
            showConnectionDeletingStatus.value = false;
            emits('success', response.data.messages);

            const updatedConnections = connections.value.filter(
                (conn) => conn.conn_id !== connection.conn_id
            );
            connections.value = updatedConnections;
            console.log(connections.value);

        }).catch((error) => {
            showConnectionDeletingStatus.value = false;
            emits('error', error); // Emit the event with error
        });

};

const handleModalClose = () => {
    showConnectionModal.value = false;
    showEditConnectionModal.value = false;
}


</script>
