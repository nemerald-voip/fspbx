<template>
    <AddEditItemModal :show="true" :header="header" custom-class="sm:max-w-4xl" @close="close">
        <template #modal-body>
            <Vueform ref="form$" :default="item" :endpoint="submitForm" :display-errors="false"
                @mounted="form => form.disableValidation()" @submit="clearServerFormErrors"
                @response="showServerFormErrors" @success="saved" @error="failed">
                <template #empty>
                    <FormElements>
                        <TextElement name="name" :label="$t('Name')" :floating="false" :disabled="readOnly"
                            :description="$t('Use this exact profile name in your dialplan. If you rename it, update the dialplan references too.')"
                            :columns="{ sm: { container: 8 } }" />
                        <ToggleElement name="enabled" :text="$t('Enabled')" :disabled="readOnly"
                            :true-value="true" :false-value="false" :columns="{ sm: { container: 4 } }" />
                        <TextElement name="description" :label="$t('Description')" :floating="false" :disabled="readOnly" />
                        <StaticElement name="rules_heading" tag="h4" :content="$t('Rules')"
                            :description="$t('Lower order runs first. The first matching rule wins. Use $1, $2, etc. for captured groups. A blank replacement removes the matched number.')" />
                        <ListElement name="rules" :initial="0" :add-text="$t('Add rule')"
                            :controls="{ add: !readOnly, remove: !readOnly }" :disabled="readOnly">
                            <template #default="{ index }">
                                <ObjectElement :name="index">
                                    <HiddenElement name="uuid" />
                                    <TextElement name="regex" :label="$t('Regular Expression')" :floating="false"
                                        :disabled="readOnly" :columns="{ sm: { container: 5 } }"
                                        :add-class="'font-mono'" autocomplete="off" />
                                    <TextElement name="replace" :label="$t('Replace')" :floating="false"
                                        :disabled="readOnly" :columns="{ sm: { container: 5 } }"
                                        :add-class="'font-mono'" autocomplete="off" />
                                    <TextElement name="order" :label="$t('Order')" :floating="false"
                                        :disabled="readOnly" inputmode="numeric" :columns="{ sm: { container: 2 } }" />
                                </ObjectElement>
                            </template>
                        </ListElement>
                        <ButtonElement v-if="!readOnly" name="save" :button-label="$t('Save')" :submits="true" align="right" />
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { trans } from '@i18n'
import AddEditItemModal from '../modal/AddEditItemModal.vue'
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js'

const props = defineProps({ item: Object, route: String, readOnly: Boolean })
const emit = defineEmits(['close', 'saved', 'error'])
const form$ = ref(null)
const header = computed(() => props.readOnly ? trans('Number Translation')
    : props.item.uuid ? trans('Edit Number Translation') : trans('Add Number Translation'))
const close = () => { if (!form$.value?.submitting) emit('close') }
const submitForm = async (_, form) => {
    clearServerFormErrors(form)
    const method = props.item.uuid ? 'put' : 'post'
    return await form.$vueform.services.axios[method](props.route, form.requestData)
}
const saved = response => emit('saved', response.data)
const failed = error => emit('error', error)
</script>
