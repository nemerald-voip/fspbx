<template>
    <div class="flex h-full min-h-0 flex-col bg-surface">
        <!-- Metadata fields -->
        <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
            @response="handleResponse" :display-errors="false" :default="defaultValues" class="flex-none">
            <template #empty>
                <FormElements>
                    <TextElement name="name" label="Template Name" placeholder="Enter template name" :floating="false"
                        :columns="settingsFieldColumns" :disabled="readOnly" rules="required" />

                    <SelectElement name="base_template_uuid" label="Base Template" :items="baseTemplateItems"
                        :native="false" :search="true" :strict="false" placeholder="Choose Base Template"
                        input-type="search" autocomplete="off" :floating="false"
                        :columns="settingsFieldColumns" :disabled="readOnly" :groups="true"
                        @change="handleBaseTemplateChange" />

                    <SelectElement name="vendor" label="Vendor" :items="vendorItems" :native="false" :search="true"
                        :strict="false" placeholder="Choose Vendor" input-type="search" autocomplete="off"
                        :floating="false" :columns="settingsFieldColumns" :disabled="readOnly" />

                    <ToggleElement name="global" text="Share across accounts" label="&nbsp;"
                        :columns="settingsFieldColumns" :disabled="readOnly" />
                </FormElements>
            </template>
        </Vueform>

        <!-- Editor toolbar -->
        <div class="mt-3 mb-2 flex flex-none items-center gap-2">
            <select v-model="editorLang"
                class="rounded-md border-strong py-1 text-sm shadow-sm focus:border-accent focus:ring-focus">
                <option value="php_laravel_blade">Blade</option>
                <option value="xml">XML</option>
                <option value="yaml">YAML</option>
                <option value="lua">Lua</option>
                <option value="php">PHP</option>
            </select>
            <select v-model="editorTheme"
                class="rounded-md border-strong py-1 text-sm shadow-sm focus:border-accent focus:ring-focus">
                <option value="chrome">Light</option>
                <option value="one_dark">Dark</option>
            </select>
        </div>

        <!-- Editor fills remaining vertical space -->
        <div
            class="editor-wrapper relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-default shadow-sm">
            <div v-if="isLoadingTemplate"
                class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 backdrop-blur-[1px]">
                <Spinner :show="true" />
                <span class="ml-2 text-sm text-body">Loading template…</span>
            </div>
            <AceEditor v-model="contentValue" :lang="editorLang" :theme="editorTheme"
                :options="{ fontSize: 16, tabSize: 4, readOnly: isLoadingTemplate || readOnly }" height="100%"
                class="editor_wrap" />
        </div>

        <!-- Buttons -->
        <div class="mt-4 flex-none border-t pt-4">
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button"
                    class="inline-flex justify-center rounded-md bg-surface px-4 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 sm:min-w-24"
                    @click="emit('cancel')">
                    Cancel
                </button>
                <button v-if="!readOnly" type="button" @click="triggerSubmit" :disabled="isSubmitting"
                    class="inline-flex items-center justify-center rounded-md bg-accent px-4 py-2 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover disabled:opacity-60 sm:min-w-24">
                    <Spinner :show="isSubmitting" />
                    Save
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AceEditor from '@generalComponents/AceEditor.vue'
import Spinner from '../general/Spinner.vue'

const props = defineProps({
    options: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'create' }, // 'create' | 'edit' | 'view'
})

const emit = defineEmits(['cancel', 'close', 'error', 'success', 'refresh-data'])

const form$ = ref(null)
const contentValue = ref('')
const baseVersion = ref(null)
const isLoadingTemplate = ref(false)
const isSubmitting = ref(false)
const editorLang = ref('php_laravel_blade')
const editorTheme = ref('chrome')
const savedUpdateRoute = ref(null)

const settingsFieldColumns = { sm: { container: 6 }, lg: { container: 3 } }

const readOnly = computed(() => props.mode === 'view')

const baseTemplateItems = computed(() => props.options?.default_templates ?? [])
const vendorItems = computed(() => props.options?.vendors ?? [])

const flatBaseTemplateItems = computed(() => baseTemplateItems.value.flatMap((item) => item.items ?? [item]))

const resolveBaseTemplateUuid = (item) => {
    if (!item?.base_template) return null
    const match = flatBaseTemplateItems.value.find(
        (opt) =>
            (opt.template_name === item.base_template || opt.label === item.base_template) &&
            (!item.vendor || opt.vendor === item.vendor),
    )
    return match?.value ?? null
}

const defaultValues = computed(() => {
    const item = props.options?.item ?? {}
    return {
        name: item.name ?? null,
        base_template_uuid: resolveBaseTemplateUuid(item),
        vendor: item.vendor ?? null,
        global: !!item && item.domain_uuid === null && props.mode !== 'create',
    }
})

watch(
    () => props.options?.item,
    (item) => {
        contentValue.value = item?.content ?? ''
        baseVersion.value = item?.base_version ?? null
        savedUpdateRoute.value = null
    },
    { immediate: true },
)

const handleBaseTemplateChange = async (newValue) => {
    if (!newValue) {
        contentValue.value = ''
        baseVersion.value = null
        return
    }

    isLoadingTemplate.value = true
    try {
        const response = await axios.post(props.options.routes.template_content, {
            template_uuid: newValue,
        })
        contentValue.value = response.data?.item?.content ?? ''
        const fetchedVendor = response.data?.item?.vendor ?? null
        if (fetchedVendor && form$.value) {
            form$.value.el$('vendor')?.update(fetchedVendor)
        }
        baseVersion.value = response.data?.item?.version ?? null
    } catch (error) {
        emit('error', error)
    } finally {
        isLoadingTemplate.value = false
    }
}

const triggerSubmit = () => {
    form$.value?.submit()
}

const submitForm = async (FormData, formRef) => {
    isSubmitting.value = true
    const data = { ...formRef.requestData }

    const selected = flatBaseTemplateItems.value.find((opt) => opt.value === data.base_template_uuid)
    data.base_template = selected?.template_name ?? null
    delete data.base_template_uuid

    data.content = contentValue.value
    data.base_version = baseVersion.value
    data.type = 'custom'

    const route = savedUpdateRoute.value ?? (props.mode === 'edit'
        ? props.options?.routes?.update_route
        : props.options?.routes?.store_route)

    if (props.mode === 'edit' || savedUpdateRoute.value) {
        return await formRef.$vueform.services.axios.put(route, data)
    }
    return await formRef.$vueform.services.axios.post(route, data)
}

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear()
    if (el$.children$) {
        Object.values(el$.children$).forEach((child) => clearErrorsRecursive(child))
    }
}

const handleResponse = (response, formRef) => {
    isSubmitting.value = false
    Object.values(formRef.elements$).forEach((el$) => clearErrorsRecursive(el$))

    if (response?.data?.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            const target = elName === 'base_template' ? 'base_template_uuid' : elName
            if (formRef.el$(target)) {
                formRef.el$(target).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response) => {
    isSubmitting.value = false
    savedUpdateRoute.value = response?.data?.routes?.update_route ?? savedUpdateRoute.value
    emit('success', 'success', response?.data?.messages)
    emit('refresh-data')
}

const handleError = (error, details, formRef) => {
    isSubmitting.value = false
    formRef.messageBag.clear()

    if (details?.type === 'submit') {
        emit('error', error)
        return
    }

    formRef.messageBag.append('Could not submit form')
}
</script>
