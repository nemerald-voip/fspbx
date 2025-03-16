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
                    <ExclamationCircleIcon
                        v-if="((errors?.extension || errors?.wake_up_time || errors?.status) && item.slug === 'settings')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">

            <div v-if="activeTab === 'general'">

                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">General</h3>
                            <p class="mt-1 text-sm text-gray-500">Update contact details.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                            <div class="col-span-3 sm:col-span-3">
                                <LabelInputRequired target="contact_organization" label="Contact Name" class="truncate" />
                                <InputField v-model="form.contact_organization" type="text" name="contact_organization"
                                    id="contact_organization" class="mt-2" :error="!!errors?.contact_organization" />
                                <div v-if="errors?.contact_organization" class="mt-2 text-xs text-red-600">
                                    {{ errors.contact_organization[0] }}
                                </div>
                            </div>

                            <div class="col-span-3 sm:col-span-3">
                                <LabelInputRequired target="destination_number" label="Destination Number"
                                    class="truncate" />
                                <InputField v-model="form.destination_number" type="text" name="destination_number"
                                    id="destination_number" class="mt-2" :error="!!errors?.destination_number" />
                                <div v-if="errors?.destination_number" class="mt-2 text-xs text-red-600">
                                    {{ errors.destination_number[0] }}
                                </div>
                            </div>

                            <div class="col-span-3 sm:col-span-3">
                                <LabelInputOptional target="phone_speed_dial" label="Speed Dial Code" class="truncate" />
                                <InputField v-model="form.phone_speed_dial" type="text" name="phone_speed_dial"
                                    id="phone_speed_dial" class="mt-2" :error="!!errors?.phone_speed_dial" />
                                <div v-if="errors?.phone_speed_dial" class="mt-2 text-xs text-red-600">
                                    {{ errors.phone_speed_dial[0] }}
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="bg-gray-100 px-4 py-3 text-right sm:px-6">
                    <button type="submit"
                        class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                </div>

            </div>

            <div v-if="activeTab === 'advanced'">

                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Advanced Settings</h3>
                            <!-- <p class="mt-1 text-sm text-gray-500">Update contact details.</p> -->
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                            <div class="col-span-6 sm:col-span-3 space-y-3">
                                <div class="flex items-center gap-1">
                                    <LabelInputOptional target="" label="Assigned Users" />

                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>This parameter enables the automatic provisioning of speed dial entries on phones that are assigned to the same users</div>
                                        </template>
                                    </Popover>
                                </div>

                                <ComboBox :options="options.users" :selectedItem="options.contact_users" :search="true"
                                    multiple placeholder="Choose Assigned User(s)" @update:model-value="handleUserUpdate"
                                    :error="errors?.user_uuid && errors.user_uuid.length > 0" />
                                <div v-if="errors?.user_uuid" class="mt-2 text-xs text-red-600">
                                    {{ errors.user_uuid[0] }}
                                </div>
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
import LabelInputOptional from "../general/LabelInputOptional.vue";
import { usePage } from "@inertiajs/vue3";
import ComboBox from "../general/ComboBox.vue";
import { Cog6ToothIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import InputField from "../general/InputField.vue";
import Popover from "@generalComponents/Popover.vue";
import { InformationCircleIcon } from "@heroicons/vue/24/outline";



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
    // contact_uuid: props.options.contact.contact_uuid,
    contact_organization: props.options.contact.contact_organization,
    destination_number: props.options.contact?.primary_phone?.phone_number ?? null,
    phone_speed_dial: props.options.contact?.primary_phone?.phone_speed_dial ?? null,
    contact_users: props.options.contact_users ?? null,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel']);

// Map icon names to their respective components
const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
};

const handleUserUpdate = (newSelectedItem) => {
    form.contact_users = newSelectedItem ? newSelectedItem : null;
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
@import '@vuepic/vue-datepicker/dist/main.css';

div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>
