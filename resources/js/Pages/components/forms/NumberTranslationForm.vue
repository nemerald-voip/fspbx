<template>
    <AddEditItemModal :show="true" :header="header" custom-class="sm:max-w-4xl" @close="close">
        <template #modal-body>
            <Vueform ref="form$" :default="item" :endpoint="submitForm" :display-errors="false"
                @mounted="form => form.disableValidation()" @submit="clearServerFormErrors"
                @response="showServerFormErrors" @success="saved" @error="failed">
                <template #empty>
                    <div class="space-y-6 bg-gray-50 px-4 py-6 text-gray-600 shadow sm:rounded-md sm:p-6">
                        <FormElements>
                            <TextElement name="name" :label="$t('Name')" :floating="false" :disabled="readOnly"
                                :description="$t('Use this exact profile name in your dialplan. If you rename it, update the dialplan references too.')"
                                autocomplete="off" :columns="{ sm: { container: 8 } }" />
                            <ToggleElement name="enabled" :text="$t('Enabled')" label="&nbsp;" :disabled="readOnly"
                                :true-value="true" :false-value="false" :columns="{ sm: { container: 4 } }" />
                            <TextElement name="description" :label="$t('Description')" :floating="false"
                                :disabled="readOnly" autocomplete="off" />

                            <StaticElement name="rules_heading" tag="h4" :content="$t('Rules')"
                                :description="$t('Rules run from top to bottom; drag a rule to reorder it. The first matching rule wins. Use $1, $2, etc. for captured groups. A blank replacement removes the matched number.')" />
                            <ListElement name="rules" :initial="0" :add-text="$t('Add rule')" :sort="!readOnly"
                                :controls="{ add: !readOnly, remove: !readOnly, sort: !readOnly }" :disabled="readOnly"
                                :add-classes="{ ListElement: { listItem: 'bg-white p-3 mb-2 rounded-md border border-gray-200' } }">
                                <template #default="{ index }">
                                    <ObjectElement :name="index">
                                        <!-- meta: keeps the uuid in the payload without taking a grid column -->
                                        <HiddenElement name="uuid" :meta="true" />
                                        <TextElement name="regex" :label="$t('Regular Expression')" :floating="false"
                                            :disabled="readOnly" placeholder="^0(\d+)$" autocomplete="off"
                                            :add-classes="{ TextElement: { input: 'font-mono' } }"
                                            :columns="{ default: { container: 12 }, sm: { container: 6 } }" />
                                        <TextElement name="replace" :label="$t('Replace')" :floating="false"
                                            :disabled="readOnly" placeholder="44$1" autocomplete="off"
                                            :add-classes="{ TextElement: { input: 'font-mono' } }"
                                            :columns="{ default: { container: 12 }, sm: { container: 6 } }" />
                                    </ObjectElement>
                                </template>
                            </ListElement>

                            <ButtonElement v-if="!readOnly" name="save" :button-label="$t('Save')" :submits="true" align="right" />
                        </FormElements>
                    </div>
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
