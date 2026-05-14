<template>
    <AddEditItemModal :show="show" :header="header" :loading="loading" custom-class="sm:max-w-2xl" @close="emit('close')">
        <template #modal-body>
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError" :display-errors="false">
                <template #empty>
                    <FormElements>
                        <TextElement :name="field('category')" label="Category" :readonly="isDomainInherited" :columns="{ sm: { container: 6 } }" />
                        <TextElement :name="field('subcategory')" label="Subcategory" :readonly="isDomainInherited" :columns="{ sm: { container: 6 } }" />
                        <SelectElement :name="field('name')" label="Type" :items="typeOptions" :native="false" :readonly="isDomainInherited" :columns="{ sm: { container: 6 } }" />
                        <TextElement :name="field('order')" label="Order" input-type="number" :readonly="isDomainInherited" :columns="{ sm: { container: 6 } }" />
                        <TextareaElement :name="field('value')" label="Value" :rows="5" />
                        <ToggleElement :name="field('enabled')" text="Enabled" />
                        <TextareaElement :name="field('description')" label="Description" :rows="2" :readonly="isDomainInherited" />
                        <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
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
    }
})

const form$ = ref(null)

const typeOptions = computed(() => Object.entries(props.types || {}).map(([value, label]) => ({ value, label })))

const header = computed(() => {
    if (props.mode === 'default') {
        return props.item?.default_setting_uuid ? 'Edit Default Setting' : 'Create Default Setting'
    }

    return props.item?.domain_setting_uuid ? 'Edit Domain Override' : 'Create Domain Override'
})

const isDomainInherited = computed(() => props.mode === 'domain' && !props.item?.is_custom)

const prefix = computed(() => props.mode === 'default' ? 'default_setting' : 'domain_setting')

const field = (name) => `${prefix.value}_${name}`

watch(() => props.show, async (show) => {
    if (!show) return
    await nextTick()
    form$.value?.update(props.item || {})
    form$.value?.clean()
})

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
