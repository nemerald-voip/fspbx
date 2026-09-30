<template>
    <AddEditItemModal
        :show="show"
        :header="mode === 'copy' ? $t('Copy Menu') : (mode === 'edit' ? $t('Edit Menu') : $t('Create Menu'))"
        custom-class="sm:max-w-xl"
        @close="emit('close')"
    >
        <template #modal-body>
            <p v-if="mode === 'create'" class="mb-5 text-sm text-gray-500">
                {{ $t('Start with the default menu in the selected language. Missing translations stay in English. You can edit every label afterward.') }}
            </p>
            <p v-else-if="mode === 'copy'" class="mb-5 text-sm text-gray-500">
                {{ $t('Copy all items and group visibility from :name. The language and labels stay the same.', { name: item.menu_name }) }}
            </p>
            <Vueform
                ref="form$"
                :endpoint="submitForm"
                :display-errors="false"
                @mounted="(form) => form.disableValidation()"
                @submit="clearServerFormErrors"
                @response="showServerFormErrors"
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
                            v-if="mode !== 'copy'"
                            name="menu_language"
                            :label="$t('Language')"
                            :items="languageOptions"
                            :native="false"
                            :search="true"
                            input-type="search"
                            autocomplete="off"
                            :strict="true"
                            :floating="false"
                            :description="mode === 'edit' ? $t('Changing the language does not translate existing labels.') : undefined"
                        />
                        <StaticElement v-else name="source_language" :label="$t('Language')">
                            <template #default>{{ sourceLanguage }}</template>
                        </StaticElement>
                        <TextareaElement
                            name="menu_description"
                            :label="$t('Description')"
                            :placeholder="$t('Describe where this menu is used')"
                            :rows="2"
                            :floating="false"
                        />
                        <ButtonElement
                            name="submit"
                            :button-label="mode === 'copy' ? $t('Copy Menu') : (mode === 'create' ? $t('Create Menu') : $t('Save'))"
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
import { computed, nextTick, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { trans } from '@i18n'
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js'
import AddEditItemModal from '../modal/AddEditItemModal.vue'

const emit = defineEmits(['close', 'success', 'error'])

const props = defineProps({
    show: Boolean,
    mode: { type: String, default: 'create' },
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
const page = usePage()
const sourceLanguage = computed(() =>
    props.languageOptions.find(option => option.value === props.item?.menu_language)?.label ?? props.item?.menu_language
)

const hydrateForm = async () => {
    if (!props.show) return

    await nextTick()
    form$.value?.reset()
    if (form$.value) clearServerFormErrors(form$.value)
    form$.value?.update({
        menu_name: props.mode === 'copy' ? trans(':name (copy)', { name: props.item.menu_name }) : (props.item?.menu_name ?? ''),
        menu_language: props.item?.menu_language ?? page.props.locale ?? 'en-us',
        menu_description: props.item?.menu_description ?? '',
    })
    form$.value?.clean()
}

watch(() => props.show, hydrateForm, { flush: 'post' })
watch(() => props.item, hydrateForm, { deep: true, flush: 'post' })

const submitForm = async (FormData, form) => {
    const method = props.mode === 'edit' ? 'put' : 'post'
    return form.$vueform.services.axios[method](props.route, form.requestData)
}

const handleSuccess = (response) => {
    emit('success', response.data)
    emit('close')
}

const handleError = (error) => emit('error', error)
</script>
