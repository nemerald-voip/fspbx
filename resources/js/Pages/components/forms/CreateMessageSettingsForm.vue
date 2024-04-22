<template>
    <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
            <div class="sm:col-span-12">
                <LabelInputRequired :target="'destination'" :label="'Phone Number'" />
                <div class="mt-2">
                    <InputField v-model="form.destination" type="text" name="destination"
                        placeholder="Enter Phone Number"
                        :error="errors?.destination && errors.destination.length > 0"/>
                </div>
                <div v-if="errors?.destination" class="mt-2 text-sm text-red-600">
                    {{ errors.destination[0] }}
                </div>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'carrier'" :label="'Message Provider'" />
                <div class="mt-2">
                    <SelectBox :options="options.carrier" :selectedItem="null"
                        :search="true" :placeholder="'Choose carrier'" @update:modal-value="handleUpdateCarrier" />
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'extension'" :label="'Extension'" />
                <div class="mt-2">
                    <SelectBox :options="options.chatplan_detail_data" :selectedItem="null"
                        :search="true" :placeholder="'Choose extension'" @update:modal-value="handleUpdateExtension" />
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-600">Assign the extension to which the messages should be
                    forwarded.</p>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'email'" :label="'Email'" />
                <div class="mt-2">
                    <InputField v-model="form.email" type="text" name="email" placeholder="Optional"
                        :error="errors?.email && errors.email.length > 0"/>
                </div>
                <div v-if="errors?.email" class="mt-2 text-sm text-red-600">
                    {{ errors.email[0] }}
                </div>
                <p class="mt-3 text-sm leading-6 text-gray-600">You can choose to use email instead of the extension if
                    you'd like.</p>
            </div>

            <div class="sm:col-span-12">
                <LabelInputOptional :target="'description'" :label="'Description'" />
                <div class="mt-2">
                    <Textarea v-model="form.description" name="description" rows="2" />
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


import SelectBox from "../general/SelectBox.vue";
import Textarea from "../general/Textarea.vue";
import InputField from "../general/InputField.vue";
import LabelInputOptional from "../forms/LabelInputOptional.vue";
import LabelInputRequired from "../forms/LabelInputRequired.vue";
import Spinner from "../general/Spinner.vue";

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const page = usePage();

const form = reactive({
    destination: null,
    carrier: null,
    chatplan_detail_data: null,
    email: null,
    description: null,
    _token: page.props.csrf_token,
})

const emits = defineEmits(['submit', 'cancel']);

const submitForm = () => {
    emits('submit', form); // Emit the event with the form data
}

const handleUpdateExtension = (newSelectedItem) => {
    form.chatplan_detail_data = newSelectedItem.value
}

const handleUpdateCarrier = (newSelectedItem) => {
    form.carrier = newSelectedItem.value
}

</script>
