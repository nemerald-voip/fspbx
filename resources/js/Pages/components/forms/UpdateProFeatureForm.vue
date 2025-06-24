<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <template v-for="item in options.navigation" :key="item.name">
                    <a href="#" v-if="item?.slug !== 'downloads' || props.options.item?.license_details?.meta?.valid"
                        :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                        @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                        <component :is="iconComponents[item.icon]"
                            :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                            aria-hidden="true" />
                        <span class="truncate">{{ item.name }}</span>
                        <ExclamationCircleIcon
                            v-if="((errors?.device_address || errors?.device_template) && item.slug === 'settings')"
                            class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                    </a>
                </template>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="sm:px-6 lg:col-span-9 lg:px-0">
            <div v-if="activeTab === 'license'">
                <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                    <div>
                        <h3 class="text-base font-semibold leading-6 text-gray-900">License Status</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">Ensure calls are routed to the right team every time.
                            Select a routing option below to fit your business needs.</p> -->
                    </div>


                    <div v-if="props.options.item.license" class="mt-2 border-t border-gray-100">
                        <dl class="divide-y divide-gray-100">
                            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                <dt class="text-sm font-medium leading-6 text-gray-900">License Key</dt>
                                <dd class="mt-1 flex text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    <span class="flex-grow">{{ props.options.item.license }}</span>
                                    <span class="ml-4 flex-shrink-0">
                                        <button type="button" @click.prevent="handleShowEditLicenseModal"
                                            class="rounded-md bg-gray-100 font-medium text-indigo-600 hover:text-indigo-500">Update</button>
                                    </span>
                                </dd>
                            </div>
                            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                <dt class="text-sm font-medium leading-6 text-gray-900">License Status</dt>
                                <dd class="mt-1 flex text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    <span v-if="props.options.item.license_details?.data?.id"
                                        class="flex-grow text-green-600">License key is valid</span>
                                    <span v-else class="flex-grow text-rose-600">License key is not valid</span>
                                    <!-- <span class="ml-4 flex-shrink-0">
                                        <button type="button"
                                            class="rounded-md g-gray-100 font-medium text-indigo-600 hover:text-indigo-500">Update</button>
                                    </span> -->
                                </dd>
                            </div>
                            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                <dt class="text-sm font-medium leading-6 text-gray-900">License suspended</dt>
                                <dd class="mt-1 flex text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    <span
                                        v-if="props.options.item.license_details?.data && !props.options.item.license_details?.data?.attributes?.suspended"
                                        class="flex-grow text-green-600">No</span>
                                    <!-- <span v-if="props.options.item.license_details?.data?.attributes?.suspended" class="flex-grow text-green-600">No</span> -->
                                    <span
                                        v-if="props.options.item.license_details?.data && props.options.item.license_details?.data?.attributes?.suspended"
                                        class="flex-grow text-rose-600">Yes</span>
                                    <!-- <span class="ml-4 flex-shrink-0">
                                        <button type="button"
                                            class="rounded-md bg-white font-medium text-indigo-600 hover:text-indigo-500">Update</button>
                                    </span> -->
                                </dd>
                            </div>
                            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                <dt class="text-sm font-medium leading-6 text-gray-900">Expiration</dt>
                                <dd class="mt-1 flex text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    <div>
                                        <span v-if="props.options.item.license_details?.data?.attributes?.expiry"
                                            class="flex-grow"> {{
                                                props.options.item.license_details?.data?.attributes?.expiry }}</span>
                                        <span v-if="props.options.item.license_details?.meta?.code == 'EXPIRED'"
                                            class="ml-3 flex-grow text-rose-600"> Expired</span>
                                    </div>

                                    <!-- <span class="ml-4 flex-shrink-0">
                                        <button type="button"
                                            class="rounded-md bg-white font-medium text-indigo-600 hover:text-indigo-500">Update</button>
                                    </span> -->
                                </dd>
                            </div>
                            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                <dt class="text-sm font-medium leading-6 text-gray-900">Activation Status</dt>
                                <dd class="mt-1 flex text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                                    <span v-if="props.options.item.license_details?.meta?.code == 'NO_MACHINE'"
                                        class="flex-grow text-rose-600">The license key has not been activated on this
                                        machine</span>
                                    <span v-if="props.options.item.license_details?.meta?.code == 'VALID'"
                                        class="flex-grow text-green-600">The license key is activated</span>
                                    <span v-else class="flex-grow text-rose-600"></span>

                                    <span v-if="props.options.item.license_details?.meta?.code == 'NO_MACHINE'"
                                        class="ml-4 flex-shrink-0">
                                        <button type="button" @click.prevent="submitForm"
                                            class="rounded-md bg-gray-100font-medium text-indigo-600 hover:text-indigo-500">Activate</button>
                                    </span>
                                    <span v-if="props.options.item.license_details?.meta?.code == 'VALID'"
                                        class="ml-4 flex-shrink-0">
                                        <button type="button" @click.prevent="handleDeactivateRequest"
                                            class="rounded-md bg-gray-100font-medium text-indigo-600 hover:text-indigo-500">Deactivate</button>
                                    </span>
                                </dd>
                            </div>

                        </dl>
                    </div>

                    <div v-if="!props.options.item.license"
                        class="bg-gray-100  px-4 py-6 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                        <div
                            class="col-span-full flex justify-center bg-gray-100 px-4  text-center text-sm font-medium text-indigo-500 hover:text-indigo-700 sm:rounded-b-lg">
                            <button @click.prevent="handleShowEditLicenseModal"
                                class="justify-center flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                ref="saveButtonRef" :disabled="isSubmitting">
                                <PlusIcon class="h-6 w-6 text-black-500 hover:text-black-900 active:h-8 active:w-8 " />
                                <span>
                                    Add license
                                </span>
                            </button>


                        </div>
                    </div>



                </div>
            </div>

            <div v-if="activeTab === 'modules'">
                <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                    <div>
                        <h3 class="text-base font-semibold leading-6 text-gray-900">Modules</h3>
                        <!-- <p class="mt-1 text-sm text-gray-500">Ensure calls are routed to the right team every time.
                            Select a routing option below to fit your business needs.</p> -->
                    </div>


                    <div class="flex flex-col">
                        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                                <div class="overflow-hidden border-t border-gray-200">
                                    <table class="min-w-full divide-y divide-gray-200 mb-4">
                                        <thead class="bg-gray-200">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 sm:px-6">
                                                    Module Name
                                                </th>
                                                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 sm:px-6">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody v-if="props.options.modules && props.options.modules.length"
                                            class="divide-y divide-gray-200 bg-white">
                                            <tr v-for="module in props.options.modules" :key="module.slug">
                                                <td class="px-4 py-4 text-sm font-medium text-gray-900 sm:px-6">
                                                    {{ module.name }}
                                                </td>
                                                <td class="px-4 py-4 text-sm sm:px-6">
                                                    <span :class="module.enabled ? 'text-green-600' : 'text-rose-600'">
                                                        {{ module.enabled ? 'Enabled' : 'Disabled' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tbody v-else>
                                            <tr>
                                                <td colspan="2" class="text-center py-8 text-gray-400">No modules found</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-100 px-4 py-6 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                        <div
                            class="col-span-full flex justify-center bg-gray-100 px-4 text-center text-sm font-medium text-indigo-500 hover:text-indigo-700 sm:rounded-b-lg">

                            <!-- Show Install if none are enabled -->
                            <button v-if="!props.options.modules || !props.options.modules.length"
                                @click.prevent="handleInstall"
                                class="justify-center flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                :disabled="isInstalling || isUninstalling">
                                <span>Install</span>
                                <Spinner class="ml-1" :show="isInstalling" />
                            </button>

                            <!-- Show Uninstall if any are enabled -->
                            <button v-if="props.options.modules && props.options.modules.some(m => m.enabled)"
                                @click.prevent="handleUninstall"
                                class="justify-center flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                                :disabled="isUninstalling || isInstalling">
                                <span>Uninstall</span>
                                <Spinner class="ml-1" :show="isUninstalling" />
                            </button>

                        </div>
                    </div>




                </div>
            </div>


            <div class="bg-gray-100 px-4 py-3 text-right sm:px-6">

                <button @click.prevent="emits('cancel')"
                    class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                    :disabled="isSubmitting">
                    <Spinner :show="isSubmitting" />
                    Close
                </button>

            </div>

        </form>
    </div>

    <AddEditItemModal :show="showEditLicenseModal" :header="'Edit License Details'" @close="handleModalClose">
        <template #modal-body>
            <div class="bg-white px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 ">
                    <div>
                        <LabelInputOptional :target="'license'" :label="'License'" />
                        <div class="mt-2">
                            <InputField v-model="form.license" type="text" name="license" placeholder="Enter license key" />
                        </div>
                    </div>

                    <button @click.prevent="submitForm"
                        class="flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 ">
                        Submit
                    </button>
                </div>

            </div>

        </template>

    </AddEditItemModal>
</template>

<script setup>
import { reactive, ref } from "vue";
import { usePage } from '@inertiajs/vue3';


import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import Spinner from "../general/Spinner.vue";
import { PlusIcon } from "@heroicons/vue/24/solid";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { Cog6ToothIcon, AdjustmentsHorizontalIcon, } from '@heroicons/vue/24/outline';
import { CloudArrowDownIcon } from "@heroicons/vue/24/solid";


const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    isInstalling: Boolean,
    isUninstalling: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    license: props.options.item.license,
    update_route: props.options.routes.update_route,
    deactivate_route: props.options.routes.deactivate_route,
    install_route: props.options.routes.install_route,
    uninstall_route: props.options.routes.uninstall_route,
    _token: page.props.csrf_token,
})

const licenseSubmitted = ref(false);

const emits = defineEmits(['submit', 'cancel', 'deactivate', 'install', 'uninstall']);

const showEditLicenseModal = ref(false);

// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);

const submitForm = () => {
    handleModalClose();
    emits('submit', form); // Emit the event with the form data
}

const handleDeactivateRequest = () => {
    emits('deactivate', form);
}

const handleInstall = () => {
    emits('install', form);
}

const handleUninstall = () => {
    emits('uninstall', form);
}


const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'CloudArrowDownIcon': CloudArrowDownIcon,
};


const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};


const handleShowEditLicenseModal = () => {
    showEditLicenseModal.value = true;
};

const handleModalClose = () => {
    if (form.license) {
        licenseSubmitted.value = true;
    }
    showEditLicenseModal.value = false;
};




</script>