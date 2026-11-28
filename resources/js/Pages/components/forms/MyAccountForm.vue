<template>
    <div class="rounded-lg border border-gray-200 bg-white p-5">
        <h3 v-if="title" class="text-base font-semibold text-gray-900">{{ title }}</h3>
        <p v-if="description" class="mt-1 text-sm text-gray-500">{{ description }}</p>
        <Vueform :endpoint="submit" :display-errors="false" :class="title || description ? 'mt-4' : ''"
            @mounted="form => form.disableValidation()" @submit="clearServerFormErrors"
            @response="showServerFormErrors" @success="success" @error="failed">
            <template #empty>
                <FormElements>
                    <slot />
                    <ButtonElement name="submit" :id="submitId" :submits="true" :button-label="button" align="right" />
                </FormElements>
            </template>
        </Vueform>
    </div>
</template>

<script setup>
import { useId } from 'vue'
import { trans } from '@i18n'
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors'

const props = defineProps({ title: String, description: String, save: Function, button: String, message: String })
const emit = defineEmits(['saved', 'success', 'error'])
const submitId = useId()

function submit(_, form) {
    return props.save(form.requestData)
}

function success(response, form) {
    for (const field of ['password', 'current_password', 'password_confirmation']) form.el$(field)?.clear()
    emit('success', { success: [props.message] })
    emit('saved', response)
}

function failed(error) {
    // Field errors are shown next to their inputs; anything else goes to the notification.
    if (error.response?.status === 422) return
    emit('error', { error: [error.response?.data?.message || trans('The request failed. Please try again.')] })
}
</script>
