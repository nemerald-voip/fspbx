<template>
    <AddEditItemModal :show="show" :header="header" :loading="loading" custom-class="sm:max-w-2xl" @close="emit('close')">
        <template #modal-body>
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError" :display-errors="false">
                <template #empty>
                    <FormElements>
                        <HiddenElement v-if="item?.var_uuid" name="var_uuid" :meta="true" />
                        <StaticElement v-if="item?.var_uuid" name="var_uuid_display">
                            <div class="mb-1">
                                <div class="mb-1 text-sm font-medium text-gray-600">Unique ID</div>
                                <div class="flex items-center">
                                    <span class="select-all text-sm font-normal text-gray-900">{{ item.var_uuid }}</span>
                                    <button type="button" class="ml-2 rounded-full p-1 text-gray-400 transition-colors hover:bg-blue-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2" title="Copy to clipboard" @click="handleCopyToClipboard(item.var_uuid)">
                                        <ClipboardDocumentIcon class="h-4 w-4 text-gray-500 hover:text-gray-900" />
                                    </button>
                                </div>
                            </div>
                        </StaticElement>
                        <SelectElement name="var_category" label="Category" :items="categoryOptions" :create="true" allow-absent :native="false" input-type="search" autocomplete="off" :strict="false" :floating="false" :columns="{ sm: { container: 6 } }" />
                        <TextElement name="var_name" label="Name" :floating="false" :columns="{ sm: { container: 6 } }" />
                        <SelectElement name="var_command" label="Command" :items="commandOptions" :native="false" :floating="false" :columns="{ sm: { container: 6 } }" />
                        <TextElement name="var_order" label="Order" input-type="number" :floating="false" :columns="{ sm: { container: 6 } }" />
                        <TextElement name="var_hostname" label="Hostname" :floating="false" />
                        <TextareaElement name="var_value" label="Value" :rows="3" :autogrow="false" :floating="false" />
                        <ToggleElement name="var_enabled" text="Enabled" />
                        <TextareaElement name="var_description" label="Description" :rows="2" :floating="false" />
                        <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { ClipboardDocumentIcon } from '@heroicons/vue/24/outline'
import AddEditItemModal from './AddEditItemModal.vue'

const emit = defineEmits(['close', 'success', 'error'])

const props = defineProps({
    show: Boolean,
    item: {
        type: Object,
        default: () => ({})
    },
    route: String,
    loading: Boolean,
    commands: {
        type: Object,
        default: () => ({})
    },
    categories: {
        type: Array,
        default: () => []
    }
})

const form$ = ref(null)

const header = computed(() => props.item?.var_uuid ? 'Edit Variable' : 'Create Variable')

const commandOptions = computed(() => Object.entries(props.commands || {}).map(([value, label]) => ({ value, label })))

const categoryOptions = computed(() => (props.categories || []).map((category) => ({
    value: category.value,
    label: category.label,
})))

const hydrateForm = async () => {
    if (!props.show || props.loading) return
    await nextTick()
    if (!form$.value) return

    form$.value?.update(props.item || {})
    form$.value?.clean()
}

watch(() => props.show, hydrateForm, { flush: 'post' })
watch(() => props.item, hydrateForm, { deep: true, flush: 'post' })
watch(() => props.loading, hydrateForm, { flush: 'post' })

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', { success: ['Copied to clipboard.'] })
    }).catch(() => {
        emit('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } })
    })
}

const submitForm = async (FormData, form) => {
    const method = props.item?.var_uuid ? 'put' : 'post'
    return await form.$vueform.services.axios[method](props.route, form.requestData)
}

const handleSuccess = (response) => {
    emit('success', response.data.messages)
    emit('close')
}

const handleError = (error) => {
    emit('error', error)
}
</script>
