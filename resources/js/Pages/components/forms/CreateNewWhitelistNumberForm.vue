<template>
    <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputRequired :target="'number'" :label="'Number'" />
                <div class="mt-2">
                    <InputField v-model="form.number" type="text" name="number"
                        placeholder="Enter number"
                        :error="errors?.number && errors.number.length > 0" />
                </div>
                <div v-if="errors?.number" class="mt-2 text-sm text-red-600">
                    {{ errors.number[0] }}
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'description'" :label="'Description'" />
                <div class="">
                    <InputField v-model="form.description" type="text" name="description"
                        placeholder="Enter description"
                        :error="errors?.description && errors.description.length > 0" />
                </div>
                <div v-if="errors?.description" class="mt-2 text-sm text-red-600">
                    {{ errors.description[0] }}
                </div>
            </div>




        </div>

        <div class="border-t mt-4 sm:mt-4 ">
            <div class="mt-4 sm:mt-4 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                <button type="submit"
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
    </form>
</template>

<script setup>
import { reactive } from "vue";
import { usePage } from '@inertiajs/vue3';


import InputField from "../general/InputField.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import Spinner from "../general/Spinner.vue";

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    ip_address: null,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel']);

const submitForm = () => {
    emits('submit', form); // Emit the event with the form data
}


</script>