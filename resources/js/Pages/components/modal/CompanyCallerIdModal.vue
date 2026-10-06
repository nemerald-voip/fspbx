<template>
    <AddEditItemModal :show="show" :header="$t('Company caller ID')" custom-class="sm:max-w-2xl" @close="emit('close')">
        <template #modal-body>
            <div class="space-y-4">
                <p class="text-sm text-gray-600">
                    {{ $t('Changes apply to all extensions in this account that use Main Company Number or Company Emergency Number.') }}
                </p>

                <div v-if="!defaults.enabled"
                    class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ $t('Company caller ID is turned off. Saving turns it on for this account.') }}
                </div>

                <div v-if="errorMessage" role="alert"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ errorMessage }}
                </div>

                <!-- Suggestions only: any number can still be typed. -->
                <datalist id="company-caller-id-numbers">
                    <option v-for="item in phoneNumbers" :key="item.value" :value="item.value">{{ item.label }}</option>
                </datalist>

                <Vueform ref="form$" :endpoint="submit" :default="initialValues" :display-errors="false"
                    @mounted="form => form.disableValidation()" @response="handleResponse" @error="handleError"
                    @success="handleSuccess">
                    <StaticElement name="outbound_title">
                        <h4 class="text-sm font-semibold text-gray-900">{{ $t('Main Company Number') }}</h4>
                    </StaticElement>
                    <TextElement name="outbound_caller_id_number" :label="$t('Number')" input-type="tel"
                        placeholder="+13105550100" autocomplete="off" :attrs="{ list: 'company-caller-id-numbers' }"
                        :floating="false" :columns="{ sm: { container: 6 } }" />
                    <TextElement name="outbound_caller_id_name" :label="$t('Name')" autocomplete="off"
                        :placeholder="$t('Optional')" :floating="false" :columns="{ sm: { container: 6 } }" />

                    <StaticElement name="emergency_title">
                        <h4 class="border-t border-gray-200 pt-4 text-sm font-semibold text-gray-900">{{ $t('Company Emergency Number') }}</h4>
                    </StaticElement>
                    <TextElement name="emergency_caller_id_number" :label="$t('Number')" input-type="tel"
                        placeholder="+13105550100" autocomplete="off" :attrs="{ list: 'company-caller-id-numbers' }"
                        :floating="false" :columns="{ sm: { container: 6 } }" />
                    <TextElement name="emergency_caller_id_name" :label="$t('Name')" autocomplete="off"
                        :placeholder="$t('Optional')" :floating="false" :columns="{ sm: { container: 6 } }" />

                    <GroupElement name="buttons_spacer" />
                    <ButtonElement name="cancel" :button-label="$t('Cancel')" :secondary="true" :disabled="saving"
                        :columns="{ container: 6 }" @click="emit('close')" />
                    <ButtonElement name="submit" :button-label="$t('Save')" :submits="true" align="right"
                        :columns="{ container: 6 }" />
                </Vueform>
            </div>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { trans } from '@i18n'
import AddEditItemModal from './AddEditItemModal.vue'

const props = defineProps({
    show: Boolean,
    defaults: { type: Object, required: true },
    phoneNumbers: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'saved', 'success'])

const form$ = ref(null)
const saving = ref(false)
const errorMessage = ref('')
const initialValues = ref({})
const revision = ref('')

// Snapshot on open so a save is checked against the values the user started from.
watch(() => props.show, open => {
    if (!open) return
    initialValues.value = { ...props.defaults.values }
    revision.value = props.defaults.revision
    errorMessage.value = ''
}, { immediate: true })

const submit = async (_data, form) => {
    errorMessage.value = ''
    Object.values(form.elements$).forEach(element => element.messageBag?.clear())
    saving.value = true
    try {
        return await form.$vueform.services.axios.put(props.defaults.save_route, { ...form.requestData, revision: revision.value })
    } finally {
        saving.value = false
    }
}

const handleResponse = (response, form) => {
    Object.entries(response.data?.errors ?? {}).forEach(([field, messages]) => {
        if (form.el$(field)) form.el$(field).messageBag.append(messages[0])
        else errorMessage.value = messages[0]
    })
}

const handleError = (error) => {
    if (!error.response?.data?.errors) {
        errorMessage.value = trans('Unable to save company caller ID. Please try again.')
    }
}

const handleSuccess = (response) => {
    emit('saved', response.data.company_caller_id)
    emit('success', response.data.messages)
    emit('close')
}
</script>
