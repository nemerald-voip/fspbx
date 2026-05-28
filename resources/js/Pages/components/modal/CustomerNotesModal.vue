<template>
    <AddEditItemModal :show="show" header="Customer Notes" :loading="false" custom-class="sm:max-w-4xl"
        body-class="max-h-[72vh] overflow-y-auto" @close="handleClose">
        <template #modal-body>
            <div v-if="!isEditing" class="space-y-4">
                <div class="flex justify-end">
                    <button v-if="canEdit" type="button" @click="startEditing"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50">
                        Edit
                    </button>
                </div>

                <div v-for="note in visibleNotes" :key="note.key" :class="['rounded-lg bg-amber-50/70 p-4 ring-1 ring-inset ring-amber-200', note.borderClass]">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide" :class="note.labelClass">
                        <span :class="['h-2 w-2 rounded-full', note.dotClass]"></span>
                        {{ note.label }}
                    </div>
                    <div v-if="note.content" class="customer-notes-content text-sm leading-6 text-gray-800" v-html="note.content"></div>
                    <p v-else class="text-sm italic text-gray-500">No notes yet.</p>
                </div>
            </div>

            <Vueform v-else ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                :display-errors="false" :float-placeholders="false">
                <template #empty>
                    <FormElements>
                        <EditorElement v-if="canEditLevel(1)" name="level_1" label="Level 1 notes"
                            placeholder="Level 1 customer notes." :floating="false" />

                        <EditorElement v-if="canEditLevel(2)" name="level_2" label="Level 2 notes"
                            placeholder="Level 2 customer notes." :floating="false" />

                        <EditorElement v-if="canEditLevel(3)" name="level_3" label="Level 3 notes"
                            placeholder="Level 3 customer notes." :floating="false" />

                        <GroupElement name="actions">
                            <ButtonElement name="cancel" button-label="Cancel" :secondary="true" :submits="false" align="right"
                                @click="cancelEditing" />
                            <ButtonElement name="submit" button-label="Save" :submits="true" align="right" />
                        </GroupElement>
                    </FormElements>
                </template>
            </Vueform>
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import AddEditItemModal from './AddEditItemModal.vue'

const emit = defineEmits(['close', 'success', 'error', 'updated'])

const props = defineProps({
    show: Boolean,
    customerNotes: {
        type: Object,
        default: () => ({ levels: [], notes: {} }),
    },
    canEdit: Boolean,
    route: String,
})

const form$ = ref(null)
const isEditing = ref(false)

const noteLayers = [
    {
        key: 'level_1',
        level: 1,
        label: 'Level 1',
        borderClass: 'border-l-4 border-l-amber-600',
        labelClass: 'text-amber-800',
        dotClass: 'bg-amber-600',
    },
    {
        key: 'level_2',
        level: 2,
        label: 'Level 2',
        borderClass: 'border-l-4 border-l-sky-600',
        labelClass: 'text-sky-800',
        dotClass: 'bg-sky-600',
    },
    {
        key: 'level_3',
        level: 3,
        label: 'Level 3',
        borderClass: 'border-l-4 border-l-rose-600',
        labelClass: 'text-rose-800',
        dotClass: 'bg-rose-600',
    },
]

const canEditLevel = (level) => (props.customerNotes?.levels || []).includes(level)

const visibleNotes = computed(() => {
    const notes = props.customerNotes?.notes || {}

    return noteLayers
        .filter((layer) => canEditLevel(layer.level))
        .map((layer) => ({
            ...layer,
            content: notes[layer.key] || null,
        }))
})

const hydrateForm = async () => {
    if (!props.show || !isEditing.value) return
    await nextTick()
    if (!form$.value) return

    form$.value.update({
        level_1: props.customerNotes?.notes?.level_1 || '',
        level_2: props.customerNotes?.notes?.level_2 || '',
        level_3: props.customerNotes?.notes?.level_3 || '',
    })
    form$.value.clean()
}

const startEditing = async () => {
    if (!props.canEdit) return
    isEditing.value = true
    await hydrateForm()
}

const cancelEditing = () => {
    isEditing.value = false
}

const handleClose = () => {
    isEditing.value = false
    emit('close')
}

const submitForm = async (FormData, form) => {
    return await form.$vueform.services.axios.put(props.route, {
        notes: form.requestData,
    })
}

const handleSuccess = (response) => {
    emit('updated', response.data.customer_notes)
    emit('success', response.data.messages)
    isEditing.value = false
}

const handleError = (error) => {
    emit('error', error)
}
</script>

<style scoped>
.customer-notes-content :deep(p),
.customer-notes-content :deep(div),
.customer-notes-content :deep(ul),
.customer-notes-content :deep(ol),
.customer-notes-content :deep(blockquote),
.customer-notes-content :deep(pre) {
    margin-bottom: 0.75rem;
}

.customer-notes-content :deep(ul),
.customer-notes-content :deep(ol) {
    padding-left: 1.25rem;
}

.customer-notes-content :deep(ul) {
    list-style: disc;
}

.customer-notes-content :deep(ol) {
    list-style: decimal;
}

.customer-notes-content :deep(a) {
    color: #0e7490;
    text-decoration: underline;
}
</style>
