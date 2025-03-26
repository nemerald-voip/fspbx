<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in localOptions.navigation" :key="item.name" href="#"
                    :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                    @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                    <component :is="iconComponents[item.icon]"
                        :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                        aria-hidden="true" />
                    <span class="truncate">{{ item.name }}</span>
                    <ExclamationCircleIcon v-if="((errors?.extension || errors?.wake_up_time || errors?.status) && item.slug === 'settings')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">

            <div v-if="activeTab === 'remote_wakeup'">

                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Settings</h3>
                            <p class="mt-1 text-sm text-gray-500">Specify the extensions that are permitted to initiate remote wakeup calls. Only these authorized extensions will be allowed to schedule wakeup alerts, ensuring secure and controlled access to the service.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">


                            <div class="col-span-6 sm:col-span-3 space-y-3">
                                <LabelInputRequired target="" label="Allowed List" />
                                    <ComboBox :options="options.extensions" :search="true" multiple
                                    :placeholder="'Select extension(s)'" :selectedItem="options.allowed_list"
                                    @update:model-value="handleUpdateAllowListField" />
                                <!-- <div v-if="errors?.extension" class="mt-2 text-xs text-red-600">
                                    {{ errors.extension[0] }}
                                </div> -->
                            </div>

                        </div>
                    </div>
                </div>
                <div class="bg-gray-100 px-4 py-3 text-right sm:px-6">
                    <button type="submit"
                        class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                </div>


            </div>


        </form>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import LabelInputRequired from "../general/LabelInputRequired.vue";
import { usePage } from "@inertiajs/vue3";
import ComboBox from "../general/ComboBox.vue";
import { Cog6ToothIcon } from '@heroicons/vue/24/outline';
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

// Make a local reactive copy of options to manipulate in this component
const localOptions = reactive({ ...props.options });

const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);

const form = reactive({
    // uuid: props.options.wakeup_call.uuid,
    allowed_list: props.options.allowed_list,
    // extension: props.options.wakeup_call.extension_uuid,
    // recurring: props.options.wakeup_call.recurring,
    // status: props.options.wakeup_call.status,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel', 'domain-selected']);

// Map icon names to their respective components
const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
};

const handleUpdateAllowListField = (newSelection) => {
  // Check if newSelection is an array, and map its items to their value property
  form.allowed_list = Array.isArray(newSelection)
    ? newSelection.map(item => item.value)
    : (newSelection ? newSelection.value : null);
};

const submitForm = () => {
    // console.log(form);
    emits('submit', form); // Emit the event with the form data
}


const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

</script>

<style>

div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>
