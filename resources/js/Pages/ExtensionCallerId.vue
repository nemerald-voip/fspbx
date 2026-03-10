<template>
    <div class="min-h-screen bg-gray-50">
        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Caller ID</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Choose which phone number should be used as your outbound caller ID.
                </p>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-4 py-4 sm:px-6">
                    <h2 class="text-sm font-semibold text-gray-900">Available Numbers</h2>
                </div>

                <div v-if="items.length" class="divide-y divide-gray-200">
                    <div
                        v-for="item in items"
                        :key="item.destination_uuid"
                        class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6"
                    >
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-gray-900">
                                {{ item.destination_description }}
                            </div>
                            <div class="mt-1 text-sm text-gray-500">
                                {{ item.destination_number }}
                            </div>
                        </div>

                        <div class="shrink-0">
                            <button
                                type="button"
                                :disabled="savingUuid === item.destination_uuid"
                                @click="toggleCallerId(item)"
                                :class="[
                                    item.isCallerID ? 'bg-emerald-600' : 'bg-gray-200',
                                    savingUuid === item.destination_uuid ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer',
                                    'relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2'
                                ]"
                            >
                                <span
                                    :class="[
                                        item.isCallerID ? 'translate-x-6' : 'translate-x-1',
                                        'inline-block h-4 w-4 transform rounded-full bg-white transition duration-200'
                                    ]"
                                />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="px-6 py-10 text-center">
                    <p class="text-sm text-gray-500">No caller ID numbers are available.</p>
                </div>
            </div>
        </div>
    </div>

    <Notification
        :show="notificationShow"
        :type="notificationType"
        :messages="notificationMessages"
        @update:show="hideNotification"
    />
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import Notification from './components/notifications/Notification.vue'

const props = defineProps({
    extension_uuid: String,
    destinations: Array,
    routes: Object,
})

const items = ref(props.destinations.map(item => ({ ...item })))
const savingUuid = ref(null)

const notificationType = ref(null)
const notificationMessages = ref(null)
const notificationShow = ref(false)

const toggleCallerId = async (item) => {
    const originalState = items.value.map(row => ({ ...row }))
    const newState = !item.isCallerID

    // optimistic UI
    if (newState) {
        items.value = items.value.map(row => ({
            ...row,
            isCallerID: row.destination_uuid === item.destination_uuid,
        }))
    } else {
        items.value = items.value.map(row => ({
            ...row,
            isCallerID: false,
        }))
    }

    savingUuid.value = item.destination_uuid

    try {
        const response = await axios.post(props.routes.update, {
            destination_uuid: item.destination_uuid,
            set: newState,
        })

        if (response.data.error) {
            items.value = originalState
            showNotification('error', { server: [response.data.error.message] })
            return
        }

        showNotification('success', { server: [response.data.success.message] })
    } catch (error) {
        items.value = originalState
        handleErrorResponse(error)
    } finally {
        savingUuid.value = null
    }
}

const handleErrorResponse = (error) => {
    if (error.response) {
        showNotification('error', error.response.data.errors || error.response.data.error || { request: [error.message] })
    } else if (error.request) {
        showNotification('error', { request: [error.message] })
    } else {
        showNotification('error', { request: [error.message] })
    }
}

const hideNotification = () => {
    notificationShow.value = false
    notificationType.value = null
    notificationMessages.value = null
}

const showNotification = (type, messages = null) => {
    notificationType.value = type
    notificationMessages.value = messages
    notificationShow.value = true
}
</script>