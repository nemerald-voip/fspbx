<template>
    <div class="bg-white px-4 py-6 sm:px-6 lg:px-8">



        <div class="grid grid-cols-1 gap-6">
            <div>
                <LabelInputRequired target="emergency_number" label="Emergency Number" />
                <div class="mt-2">
                    <InputField v-model="form.emergency_number" type="text" name="emergency_number"
                        placeholder="Enter number (e.g. 911, 112)" />
                </div>
                <div v-if="errors?.emergency_number" class="mt-2 text-xs text-red-600">
                    {{ errors.emergency_number[0] }}
                </div>
            </div>

            <div>
                <LabelInputOptional target="members" label="Extensions to Notify" />
                <div class="mt-2 relative">
                    <Multiselect v-model="form.members" :options="options.extensions" :multiple="true"
                        :close-on-select="false" :clear-on-select="false" :preserve-search="true"
                        placeholder="Choose Extensions" label="name" track-by="value" :searchable="true" />

                    <div class="mt-1 text-sm text-gray-500">
                        Selected extensions will be called and notified when an emergency number is dialed.
                    </div>
                </div>

                <div v-if="errors?.members" class="mt-2 text-xs text-red-600">
                    {{ errors.members[0] }}
                </div>
            </div>

            <div>
                <LabelInputOptional target="description" label="Description" />
                <div class="mt-2">
                    <InputField v-model="form.description" type="text" name="description" placeholder="Enter description" />
                </div>
            </div>

            <div class="border-t mt-4">
                <div class="mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                    <button @click.prevent="submitForm"
                        class="inline-flex w-full justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 sm:col-start-2"
                        :disabled="isSubmitting">
                        <Spinner :show="isSubmitting" />
                        Save
                    </button>
                    <button type="button"
                        class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:col-start-1 sm:mt-0"
                        @click="emits('cancel')">Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted } from "vue";
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";



const props = defineProps({
    selectedKey: Object,
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});


const form = reactive({
    emergency_number: null,
    members: [],
    description: null
});


const emits = defineEmits(['submit', 'cancel', 'error', 'clear-errors']);


const submitForm = () => {
    const payload = {
        emergency_number: form.emergency_number,
        description: form.description,
        members: form.members.map(m => ({ extension_uuid: m.value }))
    };

    emits('submit', payload); // Emit the event with the form data
}


</script>

