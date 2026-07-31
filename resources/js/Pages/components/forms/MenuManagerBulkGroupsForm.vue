<template>
    <AddEditItemModal
        :show="show"
        :header="$t('Edit Group Membership')"
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
                        <StaticElement name="selection_count">
                            <p class="text-sm text-gray-500">
                                {{ $t('Selected menu items: :count', { count: items.length }) }}
                            </p>
                        </StaticElement>
                        <SelectElement
                            name="operation"
                            :label="$t('Group Change')"
                            :items="operationOptions"
                            :native="false"
                            :search="false"
                            :floating="false"
                        />
                        <TagsElement
                            name="group_uuids"
                            :label="$t('Groups')"
                            :items="groupOptions"
                            :close-on-select="false"
                            :search="true"
                            :placeholder="$t('Select groups')"
                            :floating="false"
                        />
                        <StaticElement name="operation_help">
                            <div class="space-y-1 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600 ring-1 ring-inset ring-gray-200">
                                <p v-if="allowsOperation('add')"><span class="font-semibold text-gray-700">{{ $t('Add') }}:</span> {{ $t('Keep current memberships and add the selected groups.') }}</p>
                                <p v-if="allowsOperation('remove')"><span class="font-semibold text-gray-700">{{ $t('Remove') }}:</span> {{ $t('Remove only the selected groups.') }}</p>
                                <p v-if="allowsOperation('replace')"><span class="font-semibold text-gray-700">{{ $t('Replace') }}:</span> {{ $t('Replace all manageable memberships with the selected groups. Leave Groups empty to clear them.') }}</p>
                            </div>
                        </StaticElement>
                        <GroupElement name="actions" />
                        <ButtonElement
                            name="cancel"
                            :button-label="$t('Cancel')"
                            :secondary="true"
                            :submits="false"
                            :columns="{ container: 6 }"
                            @click="emit('close')"
                        />
                        <ButtonElement
                            name="submit"
                            :button-label="$t('Apply Group Changes')"
                            :submits="true"
                            align="right"
                            :columns="{ container: 6 }"
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
    route: String,
    items: {
        type: Array,
        default: () => [],
    },
    groupOptions: {
        type: Array,
        default: () => [],
    },
    operationOptions: {
        type: Array,
        default: () => [],
    },
})

const form$ = ref(null)
const allowsOperation = (operation) => props.operationOptions.some(option => option.value === operation)

const hydrateForm = async () => {
    if (!props.show) return

    await nextTick()
    form$.value?.reset()
    form$.value?.update({
        operation: props.operationOptions[0]?.value ?? '',
        group_uuids: [],
    })
    form$.value?.clean()
}

watch(() => props.show, hydrateForm, { flush: 'post' })
watch(() => props.items, hydrateForm, { deep: true, flush: 'post' })

const submitForm = async (FormData, form) => form.$vueform.services.axios.post(
    props.route,
    {
        ...form.requestData,
        group_uuids: form.requestData.group_uuids ?? [],
        items: props.items,
    }
)

const handleSuccess = (response) => {
    emit('success', response.data)
    emit('close')
}

const handleError = (error) => emit('error', error)
</script>
