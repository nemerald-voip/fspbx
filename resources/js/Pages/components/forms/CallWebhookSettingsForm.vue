<template>
    <Skeleton v-if="loading && !loaded" />

    <div v-else-if="loadFailed" class="max-w-3xl rounded-md border border-red-200 bg-red-50 p-4">
        <p class="text-sm font-medium text-red-900">Unable to load call webhook settings.</p>
        <button type="button"
            class="mt-3 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
            @click="loadConfiguration">
            Retry
        </button>
    </div>

    <div v-else-if="loaded" class="max-w-3xl">
        <Vueform ref="form$" :endpoint="submitForm" :display-errors="false" @success="handleSuccess"
            @error="handleError">
            <template #empty>
                <div class="space-y-6 bg-gray-50 px-4 py-6 text-gray-600 sm:p-6">
                    <FormElements>
                        <StaticElement name="header" tag="h4" content="Call Webhooks"
                            description="Send real-time inbound call events to your CRM when an extension or queue agent rings, answers, or finishes a call." />

                        <ToggleElement name="enabled" text="Enabled" :true-value="true" :false-value="false" />

                        <TextElement name="endpoint_url" label="Public HTTPS Endpoint URL"
                            placeholder="https://crm.example.com/webhooks/fs-pbx" :floating="false"
                            description="The endpoint must use HTTPS and resolve only to a public network address."
                            :rules="['required', 'url']" />

                        <CheckboxgroupElement name="events" label="Events" :items="eventOptions"
                            description="Choose which call lifecycle changes FS PBX sends." />

                        <StaticElement v-if="maskedSecret" name="masked_secret" tag="div">
                            <template #default>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Signing Secret</label>
                                    <div class="rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-700">
                                        {{ maskedSecret }}
                                    </div>
                                </div>
                            </template>
                        </StaticElement>

                        <StaticElement v-if="revealedSecret" name="revealed_secret" tag="div">
                            <template #default>
                                <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                                    <p class="text-sm font-medium text-amber-900">Copy this signing secret now. It will not be shown again.</p>
                                    <div class="mt-3 flex gap-2">
                                        <input :value="revealedSecret" readonly
                                            class="min-w-0 flex-1 rounded-md border border-amber-300 bg-white px-3 py-2 font-mono text-sm text-gray-900" />
                                        <button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                            @click="copySecret">Copy</button>
                                    </div>
                                </div>
                            </template>
                        </StaticElement>

                        <ButtonElement v-if="canSave" name="save" button-label="Save" :submits="true" />
                    </FormElements>
                </div>
            </template>
        </Vueform>

        <div v-if="exists" class="mt-6 flex flex-wrap gap-3 border-t border-gray-200 pt-6">
            <button v-if="permissions.call_webhook_test" type="button"
                class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                :disabled="testLoading" @click="sendTest">
                {{ testLoading ? 'Sending…' : 'Test Webhook' }}
            </button>
            <button v-if="permissions.call_webhook_update" type="button"
                class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
                :disabled="rotateLoading" @click="rotateSecret">
                {{ rotateLoading ? 'Rotating…' : 'Rotate Secret' }}
            </button>
            <button v-if="permissions.call_webhook_delete" type="button"
                class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500"
                @click="deleteConfirmationOpen = true">
                Delete Configuration
            </button>
        </div>

        <ConfirmationModal :show="deleteConfirmationOpen" header="Delete call webhook?"
            text="This removes the endpoint and signing secret. Call events will stop immediately."
            confirm-button-label="Delete" cancel-button-label="Cancel" :loading="deleteLoading"
            @close="deleteConfirmationOpen = false" @confirm="deleteConfiguration" />
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import ConfirmationModal from '../modal/ConfirmationModal.vue'
import Skeleton from '@generalComponents/Skeleton.vue'

const props = defineProps({
    trigger: {
        type: Boolean,
        default: false,
    },
    routes: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['success', 'error'])
const form$ = ref(null)
const exists = ref(false)
const maskedSecret = ref(null)
const revealedSecret = ref(null)
const loading = ref(false)
const loaded = ref(false)
const loadFailed = ref(false)
const testLoading = ref(false)
const rotateLoading = ref(false)
const deleteLoading = ref(false)
const deleteConfirmationOpen = ref(false)

const eventOptions = [
    { value: 'call.ringing', label: 'Ringing' },
    { value: 'call.answered', label: 'Answered' },
    { value: 'call.ended', label: 'Ended' },
]

const canSave = computed(() => exists.value
    ? props.permissions.call_webhook_update
    : props.permissions.call_webhook_create)

watch(() => props.trigger, () => loadConfiguration())

const loadConfiguration = async () => {
    if (loading.value) return

    loading.value = true
    loadFailed.value = false

    try {
        const response = await axios.get(props.routes.call_webhook_show)
        const configuration = response.data.configuration

        exists.value = configuration.exists
        maskedSecret.value = configuration.masked_secret
        revealedSecret.value = null
        loaded.value = true

        await nextTick()
        form$.value.update({
            enabled: configuration.enabled,
            endpoint_url: configuration.endpoint_url,
            events: configuration.events,
        })
        form$.value.clean()
    } catch (error) {
        loaded.value = false
        loadFailed.value = true
        emit('error', error)
    } finally {
        loading.value = false
    }
}

const submitForm = async (FormData, form) => {
    return await form.$vueform.services.axios.put(props.routes.call_webhook_save, form.requestData)
}

const handleSuccess = (response, form) => {
    exists.value = true
    maskedSecret.value = response.data.masked_secret
    revealedSecret.value = response.data.secret || null
    form.clean()
    emit('success', response.data.messages)
}

const handleError = (error) => {
    emit('error', error)
}

const sendTest = async () => {
    testLoading.value = true
    try {
        const response = await axios.post(props.routes.call_webhook_test)
        emit('success', response.data.messages)
    } catch (error) {
        emit('error', error)
    } finally {
        testLoading.value = false
    }
}

const rotateSecret = async () => {
    rotateLoading.value = true
    try {
        const response = await axios.post(props.routes.call_webhook_rotate_secret)
        maskedSecret.value = response.data.masked_secret
        revealedSecret.value = response.data.secret
        emit('success', response.data.messages)
    } catch (error) {
        emit('error', error)
    } finally {
        rotateLoading.value = false
    }
}

const deleteConfiguration = async () => {
    deleteLoading.value = true
    try {
        const response = await axios.delete(props.routes.call_webhook_destroy)
        exists.value = false
        maskedSecret.value = null
        revealedSecret.value = null
        form$.value.update({ enabled: true, endpoint_url: '', events: eventOptions.map(item => item.value) })
        form$.value.clean()
        emit('success', response.data.messages)
    } catch (error) {
        emit('error', error)
    } finally {
        deleteLoading.value = false
        deleteConfirmationOpen.value = false
    }
}

const copySecret = async () => {
    try {
        await navigator.clipboard.writeText(revealedSecret.value)
        emit('success', { success: ['Signing secret copied.'] })
    } catch {
        emit('error', new Error('Could not copy the signing secret.'))
    }
}
</script>
