<template>
    <AddEditItemModal
        :show="show"
        :header="item?.menu_uuid ? $t('Edit Menu') : $t('Create Menu')"
        custom-class="sm:max-w-xl"
        @close="emit('close')"
    >
        <template #modal-body>
            <Vueform
                ref="form$"
                :endpoint="submitForm"
                :display-errors="false"
                @success="handleSuccess"
                @error="handleError"
            >
                <template #empty>
                    <FormElements>
                        <TextElement
                            name="menu_name"
                            :label="$t('Name')"
                            :placeholder="$t('Enter a menu name')"
                            :floating="false"
                        />
                        <SelectElement
                            name="menu_language"
                            :label="$t('Language')"
                            :items="languageOptions"
                            :native="false"
                            :search="true"
                            input-type="search"
                            autocomplete="off"
                            :strict="true"
                            :floating="false"
                        />
                        <TextareaElement
                            name="menu_description"
                            :label="$t('Description')"
                            :placeholder="$t('Describe where this menu is used')"
                            :rows="2"
                            :floating="false"
                        />
                        <ButtonElement
                            name="submit"
                            :button-label="$t('Save')"
                            :submits="true"
                            align="right"
                        />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import AddEditItemModal from '../modal/AddEditItemModal.vue'

const emit = defineEmits(['close', 'success', 'error'])

const props = defineProps({
    show: Boolean,
    item: {
        type: Object,
        default: () => ({}),
    },
    route: String,
    languageOptions: {
        type: Array,
        default: () => [],
    },
})

const form$ = ref(null)

const hydrateForm = async () => {
    if (!props.show) return

    await nextTick()
    form$.value?.reset()
    form$.value?.update({
        menu_name: props.item?.menu_name ?? '',
        menu_language: props.item?.menu_language ?? 'en-us',
        menu_description: props.item?.menu_description ?? '',
    })
    form$.value?.clean()
}

watch(() => props.show, hydrateForm, { flush: 'post' })
watch(() => props.item, hydrateForm, { deep: true, flush: 'post' })

const submitForm = async (FormData, form) => {
    const method = props.item?.menu_uuid ? 'put' : 'post'
    return form.$vueform.services.axios[method](props.route, form.requestData)
}

const handleSuccess = (response) => {
    emit('success', response.data)
    emit('close')
}

const handleError = (error) => emit('error', error)
</script>
