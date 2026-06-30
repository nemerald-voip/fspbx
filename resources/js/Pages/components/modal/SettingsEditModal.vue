<template>
    <AddEditItemModal :show="show" :header="header" :loading="loading" custom-class="sm:max-w-2xl" @close="emit('close')">
        <template #modal-body>
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError" :display-errors="false">
                <template #empty>
                    <FormElements>
                        <HiddenElement v-if="recordUuid" :name="recordUuidField" :meta="true" />
                        <StaticElement v-if="recordUuid" name="setting_uuid_clean">
                            <div class="mb-1">
                                <div class="text-sm font-medium text-body mb-1">
                                    Unique ID
                                </div>

                                <div class="flex items-center group">
                                    <span class="text-sm text-heading select-all font-normal">
                                        {{ recordUuid }}
                                    </span>

                                    <button type="button"
                                        @click="handleCopyToClipboard(recordUuid)"
                                        class="ml-2 p-1 rounded-full text-subtle hover:text-info hover:bg-info-subtle transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                        title="Copy to clipboard">
                                        <ClipboardDocumentIcon
                                            class="h-4 w-4 text-muted hover:text-heading cursor-pointer" />
                                    </button>
                                </div>
                            </div>
                        </StaticElement>
                        <SelectElement :name="field('category')" label="Category" :items="categoryOptions" :create="true" allow-absent :native="false" input-type="search" autocomplete="off" :strict="false" :floating="false" :readonly="isDomainInherited" :columns="{ sm: { container: 6 } }" />
                        <TextElement :name="field('subcategory')" label="Setting Name" :floating="false" :readonly="isDomainInherited" :columns="{ sm: { container: 6 } }" />
                        <SelectElement :name="field('name')" label="Type" :items="typeOptions" :native="false" :floating="false" :readonly="isDomainInherited" :columns="{ sm: { container: 6 } }" />
                        <TextElement :name="field('order')" label="Order" input-type="number" :floating="false" :readonly="isDomainInherited" :columns="{ sm: { container: 6 } }" />
                        <TextareaElement :name="field('value')" label="Value" :rows="2" :autogrow="false" :floating="false" />
                        <ToggleElement :name="field('enabled')" text="Enabled" />
                        <TextareaElement :name="field('description')" label="Description" :rows="2" :floating="false" :readonly="isDomainInherited" />
                        <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";
import AddEditItemModal from './AddEditItemModal.vue'

const emit = defineEmits(['close', 'success', 'error'])

const props = defineProps({
    show: Boolean,
    item: {
        type: Object,
        default: () => ({})
    },
    mode: {
        type: String,
        default: 'domain'
    },
    route: String,
    loading: Boolean,
    types: {
        type: Object,
        default: () => ({})
    },
    categories: {
        type: Array,
        default: () => []
    }
})

const form$ = ref(null)

const typeOptions = computed(() => Object.entries(props.types || {}).map(([value, label]) => ({ value, label })))

const categoryOptions = computed(() => (props.categories || []).map((category) => ({
    value: category.value,
    label: category.label,
})))

const header = computed(() => {
    if (props.mode === 'default') {
        return props.item?.default_setting_uuid ? 'Edit Default Setting' : 'Create Default Setting'
    }

    return props.item?.domain_setting_uuid ? 'Edit Domain Override' : 'Create Domain Override'
})

const isDomainInherited = computed(() => props.mode === 'domain' && !props.item?.is_custom)

const prefix = computed(() => props.mode === 'default' ? 'default_setting' : 'domain_setting')

const recordUuid = computed(() => props.item?.[`${prefix.value}_uuid`] || props.item?.default_setting_uuid || '')

const recordUuidField = computed(() => props.item?.[`${prefix.value}_uuid`] ? `${prefix.value}_uuid` : 'default_setting_uuid')

const field = (name) => `${prefix.value}_${name}`

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
    const method = props.item?.[`${prefix.value}_uuid`] ? 'put' : 'post'
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
