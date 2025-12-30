<template>
    <div class="bg-white px-4 py-6 sm:px-6 lg:px-8">


        <div class="grid grid-cols-1 gap-6 ">

            <Toggle label="Status" v-model="form.status" />

            <div>
                <LabelInputRequired :target="'key'" :label="'Key'" />
                <div class="mt-2">
                    <InputField v-model="form.key" type="text" name="key"
                        placeholder="Enter one or more digits (e.g., 1, 12, 123)"
                        autocomplete="off" />
                </div>
                <div v-if="errors?.key" class="mt-2 text-xs text-red-600">
                    {{ errors.key[0] }}
                </div>
            </div>

            <div>
                <LabelInputRequired :target="'action'" :label="'Action'" />
                <div class="mt-2">
                    <ComboBox :options="options.routing_types" :selectedItem="form.action" :search="true"
                        placeholder="Choose Action" @update:model-value="(value) => handleUpdateActionField(value)"
                        autocomplete="off"
                        :error="errors?.action && errors.action.length > 0" />
                </div>
                <div v-if="errors?.action" class="mt-2 text-xs text-red-600">
                    {{ errors.action[0] }}
                </div>
            </div>

            <div>
                <LabelInputRequired :target="'target'" :label="'Target'" />
                <div class="mt-2 relative">
                    <ComboBox :options="targets" :selectedItem="form.target" :search="true" :key="targets"
                        placeholder="Choose Target" @update:model-value="(value) => handleUpdateTargetField(value)"
                        autocomplete="off"
                        :disabled="isTargetDisabled" :error="errors?.target && errors.target.length > 0"/>

                    <!-- Spinner Overlay -->
                    <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-50">
                        <Spinner class="w-10 h-10 text-gray-500" :show="loading" />
                    </div>
                </div>
                <div v-if="errors?.target" class="mt-2 text-xs text-red-600">
                    {{ errors.target[0] }}
                </div>
            </div>

            <div>
                <LabelInputOptional :target="'description'" :label="'Description'" />
                <div class="mt-2">
                    <InputField v-model="form.description" type="text" name="description" placeholder="Enter description" />
                </div>
            </div>

            <div class="border-t mt-4 sm:mt-4 ">
                <div class="mt-4 sm:mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                    <button @click.prevent="submitForm"
                        class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                        ref="saveButtonRef" :disabled="isSubmitting">
                        <Spinner :show="isSubmitting" />
                        Save
                    </button>
                    <button type="button"
                        class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                        @click="emits('cancel')" ref="cancelButtonRef">Cancel
                    </button>
                </div>
            </div>

        </div>

    </div>
</template>

<script setup>
import { reactive, ref, onMounted } from "vue";
import { usePage } from '@inertiajs/vue3';


import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";
import { Cog6ToothIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import Toggle from "@generalComponents/Toggle.vue";



const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();
const targets = ref();
const loading = ref(false);
const isTargetDisabled = ref(false);
const disabledTypes = ['check_voicemail', 'company_directory', 'hangup'];

const form = reactive({
    menu_uuid: props.options?.ivr?.ivr_menu_uuid ?? null,
    domain_uuid: props.options?.ivr?.domain_uuid ?? null,
    status: true,
    key: null,
    action: null,
    target: null,
    extension: null,
    description: null,
    _token: page.props.csrf_token,
});


const emits = defineEmits(['submit', 'cancel', 'error','clear-errors']);


const submitForm = () => {
    // console.log(form);
    emits('submit', form); // Emit the event with the form data
}

const handleUpdateActionField = (selected) => {
    form.action = selected.value;
    if (disabledTypes.includes(selected.value)) {
        isTargetDisabled.value = true;
    } else {
        isTargetDisabled.value = false;
    }
    fetchRoutingTypeOptions(selected.value); // Fetch options when action field updates
}

const handleUpdateTargetField = (selected) => {
    form.target = selected.value;
    form.extension = selected.extension;
}

function fetchRoutingTypeOptions(newValue) {
    loading.value = true; // Show spinner
    axios.post(props.options.routes.get_routing_options, { 'category': newValue })
        .then((response) => {
            targets.value = response.data.options; // Assign the returned options to `targets`
        }).catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            loading.value = false; // Hide spinner after fetch completes
        });
}


</script>