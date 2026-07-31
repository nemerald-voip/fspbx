<template>
    <AddEditItemModal
        :show="show"
        :header="item?.menu_item_uuid ? $t('Edit Menu Item') : $t('Create Menu Item')"
        custom-class="sm:max-w-2xl"
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
                            name="menu_item_title"
                            :label="$t('Title')"
                            :placeholder="$t('Enter the navigation label')"
                            :floating="false"
                        />
                        <TextElement
                            name="menu_item_link"
                            :label="$t('Link')"
                            placeholder="/example-page"
                            :floating="false"
                        />
                        <SelectElement
                            name="menu_item_parent_uuid"
                            :label="$t('Parent Item')"
                            :items="parentOptions"
                            :native="false"
                            :search="true"
                            input-type="search"
                            autocomplete="off"
                            :strict="false"
                            allow-absent
                            :floating="false"
                            :columns="{ sm: { container: 8 } }"
                        />
                        <TextElement
                            name="menu_item_order"
                            :label="$t('Order')"
                            input-type="number"
                            :floating="false"
                            :columns="{ sm: { container: 4 } }"
                        />
                        <TagsElement
                            v-if="canManageGroups"
                            name="group_uuids"
                            :label="$t('Visible To Groups')"
                            :items="groupOptions"
                            :close-on-select="false"
                            :search="true"
                            :floating="false"
                        />
                        <TextElement
                            name="menu_item_icon"
                            :label="$t('Icon Class')"
                            :placeholder="$t('Optional, for example fa-cog')"
                            :floating="false"
                        />
                        <TextareaElement
                            name="menu_item_description"
                            :label="$t('Description')"
                            :placeholder="$t('Optional administrator note')"
                            :rows="2"
                            :floating="false"
                        />
                        <StaticElement name="order_help">
                            <p class="-mt-2 text-xs text-gray-500">
                                {{ $t('Order applies to top-level items. Child items are shown alphabetically.') }}
                            </p>
                        </StaticElement>
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
    parentOptions: {
        type: Array,
        default: () => [],
    },
    groupOptions: {
        type: Array,
        default: () => [],
    },
    initialParentUuid: {
        type: String,
        default: '',
    },
    canManageGroups: Boolean,
})

const form$ = ref(null)

const hydrateForm = async () => {
    if (!props.show) return

    await nextTick()
    form$.value?.reset()
    form$.value?.update({
        menu_item_title: props.item?.menu_item_title ?? '',
        menu_item_link: props.item?.menu_item_link ?? '',
        menu_item_parent_uuid: props.item?.menu_item_parent_uuid ?? props.initialParentUuid ?? '',
        menu_item_order: props.item?.menu_item_order ?? '',
        group_uuids: props.item?.group_uuids ?? [],
        menu_item_icon: props.item?.menu_item_icon ?? '',
        menu_item_description: props.item?.menu_item_description ?? '',
    })
    form$.value?.clean()
}

watch(() => props.show, hydrateForm, { flush: 'post' })
watch(() => props.item, hydrateForm, { deep: true, flush: 'post' })

const submitForm = async (FormData, form) => {
    const method = props.item?.menu_item_uuid ? 'put' : 'post'
    return form.$vueform.services.axios[method](props.route, form.requestData)
}

const handleSuccess = (response) => {
    emit('success', response.data)
    emit('close')
}

const handleError = (error) => emit('error', error)
</script>
