<template>
    <MainLayout />

    <div class="m-3">
        <vue-advanced-chat :height="height" :current-user-id="currentUserId" :rooms="JSON.stringify(rooms)"
            :messages="JSON.stringify(messages)" :loading-rooms="loadingRooms" :loading-messages="loadingMessages"
            :rooms-loaded="roomsLoaded" :messages-loaded="messagesLoaded" :room-id="currentRoomId"
            @fetch-rooms="fetchRooms" @fetch-messages="onFetchMessages" @send-message="onSendMessage"
            @open-room="onOpenRoom" />
    </div>


    <!-- <NotificationSimple :show="restartRequestNotificationErrorTrigger" :isSuccess="false" :header="'Warning'"
        :text="'Please select at least one device'" @update:show="restartRequestNotificationErrorTrigger = false" />
    <NotificationSimple :show="restartRequestNotificationSuccessTrigger" :isSuccess="true" :header="'Success'"
        :text="'Restart request has been submitted'" @update:show="restartRequestNotificationSuccessTrigger = false" />


    <ConfirmationModal :show="confirmationRetryTrigger" @close="confirmationRetryTrigger = false"
        @confirm="confirmRetryAction" :header="'Are you sure?'" :text="'Confirm resending selected messages.'"
        :confirm-button-label="'Retry'" cancel-button-label="Cancel" />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" /> -->
</template>

<script setup>
import { computed, onMounted, ref, onBeforeUnmount } from "vue";
import axios from 'axios';
import { register } from 'vue-advanced-chat'

import NotificationSimple from "./components/notifications/Simple.vue";
import AddEditItemModal from "./components/modal/AddEditItemModal.vue";
import DeleteConfirmationModal from "./components/modal/DeleteConfirmationModal.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import MainLayout from "../Layouts/MainLayout.vue";

import Notification from "./components/notifications/Notification.vue";


const loading = ref(false)
const loadingModal = ref(false)
const selectAll = ref(false);
const selectedItems = ref([]);
const selectPageItems = ref(false);
const restartRequestNotificationSuccessTrigger = ref(false);
const restartRequestNotificationErrorTrigger = ref(false);
const createModalTrigger = ref(false);
const editModalTrigger = ref(false);
const bulkUpdateModalTrigger = ref(false);
const confirmationModalTrigger = ref(false);
const confirmationRetryTrigger = ref(false);
const confirmationModalDestroyPath = ref(null);
const createFormSubmiting = ref(null);
const updateFormSubmiting = ref(null);
const confirmDeleteAction = ref(null);
const confirmRetryAction = ref(null);
const bulkUpdateFormSubmiting = ref(null);
const formErrors = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
let tooltipCopyContent = ref('Copy to Clipboard');

const props = defineProps({
  routes: { type: Object, required: true },
  auth: { type: Object, required: true }
})

// UI state
const height = 'calc(100vh - 80px)'
const currentUserId = String(props.auth.currentExtensionUuid)

// vue-advanced-chat expects rooms/messages as arrays but passed as JSON strings
const rooms = ref([])
const messages = ref([])

const currentRoomId = ref(null)

const loadingRooms = ref(false)
const loadingMessages = ref(false)
const roomsLoaded = ref(false)
const messagesLoaded = ref(false)

// Realtime subscription handle
let activeChannelName = null

onMounted(async () => {
    register()
  await fetchRooms()
})

onBeforeUnmount(() => {
  unsubscribeActiveRoom()
})

async function fetchRooms() {
  if (loadingRooms.value) return
  loadingRooms.value = true

  try {
    const { data } = await axios.get(props.routes.roomsIndex)

    // Expecting API shape:
    // data.rooms = [{ roomId, roomName, users, lastMessage, unreadCount, ... }]
    rooms.value = (data.rooms || []).map(normalizeRoom)
    roomsLoaded.value = true
  } finally {
    loadingRooms.value = false
  }
}

async function onOpenRoom({ detail }) {
  // detail: { roomId }
  const roomId = String(detail?.roomId || '')
  if (!roomId) return

  currentRoomId.value = roomId
  await loadMessages(roomId)

  // subscribe to realtime room channel
  subscribeToRoom(roomId)
}

async function onFetchMessages({ detail }) {
  // vue-advanced-chat calls this when it needs messages
  // detail: { roomId, options: { offset, limit } } depending on config
  const roomId = String(detail?.roomId || currentRoomId.value || '')
  if (!roomId) return
  await loadMessages(roomId)
}

async function loadMessages(roomId) {
  loadingMessages.value = true
  try {
    const { data } = await axios.get(props.routes.roomMessages.replace(':roomId', roomId))

    // Expecting API shape:
    // data.messages = [{ message_uuid, source, destination, message, direction, created_at, media, ... }]
    messages.value = (data.messages || []).map(m => normalizeMessage(m, roomId))
    messagesLoaded.value = true
  } finally {
    loadingMessages.value = false
  }
}

async function onSendMessage({ detail }) {
  // detail from vue-advanced-chat typically includes:
  // { roomId, content, files, ... }
  const roomId = String(detail?.roomId || currentRoomId.value || '')
  const content = String(detail?.content || '').trim()

  if (!roomId || !content) return

  // optimistic UI: show immediately
  const tempId = `tmp-${Date.now()}`
  const optimistic = {
    _id: tempId,
    content,
    senderId: currentUserId,
    username: props.auth.currentExtensionName || 'You',
    timestamp: new Date().toISOString(),
    date: new Date().toISOString().slice(0, 10),
    roomId,
    status: 'sending'
  }
  messages.value = [optimistic, ...messages.value]

  try {
    const { data } = await axios.post(props.routes.sendMessage, {
      roomId,
      message: content
    })

    // Expecting API response:
    // data.message = { message_uuid, ... }
    // Replace optimistic message with real one
    if (data?.message) {
      messages.value = messages.value.map(m => {
        if (m._id === tempId) return normalizeMessage(data.message, roomId)
        return m
      })
    } else {
      // keep optimistic, but mark as sent
      messages.value = messages.value.map(m => (m._id === tempId ? { ...m, status: 'sent' } : m))
    }
  } catch (e) {
    // mark failed
    messages.value = messages.value.map(m => (m._id === tempId ? { ...m, status: 'failed' } : m))
    throw e
  }
}

/**
 * Realtime: listen to "sms.room.{roomId}" (example)
 * You can rename this to match your Laravel broadcast channel naming.
 */
function subscribeToRoom(roomId) {
  unsubscribeActiveRoom()

  // If Echo isn't set up, skip gracefully
  if (!window.Echo) return

  activeChannelName = `private-sms.room.${roomId}`

  window.Echo.private(`sms.room.${roomId}`)
    .listen('.sms.message.received', (payload) => {
      // payload.message should be your DB row / API shape
      const msg = payload?.message
      if (!msg) return

      const normalized = normalizeMessage(msg, roomId)

      // Prevent duplicates (if we already have it)
      const exists = messages.value.some(m => String(m._id) === String(normalized._id))
      if (exists) return

      messages.value = [normalized, ...messages.value]
    })
}

function unsubscribeActiveRoom() {
  if (!window.Echo) return
  if (!activeChannelName) return

  // activeChannelName is "private-..." but Echo.leaveChannel uses the base name you passed (without "private-")
  const base = activeChannelName.replace(/^private-/, '')
  try {
    window.Echo.leave(base)
  } catch (_) {}

  activeChannelName = null
}

/**
 * Normalize data to what vue-advanced-chat expects
 */
function normalizeRoom(r) {
  // required keys for rooms:
  // roomId, roomName, users, lastMessage, unreadCount, etc.
  return {
    roomId: String(r.roomId),
    roomName: r.roomName || r.name || 'Conversation',
    avatar: r.avatar || null,
    unreadCount: Number(r.unreadCount || 0),
    lastMessage: r.lastMessage || null,
    users: Array.isArray(r.users) ? r.users : []
  }
}

function normalizeMessage(row, roomId) {
  // DB row you showed:
  // message_uuid, source, destination, message, direction, created_at, media...
  const id = row.message_uuid || row.uuid || row.id || `${Date.now()}`
  const createdAt = row.created_at || row.timestamp || new Date().toISOString()

  const isOutbound = String(row.direction || '').toLowerCase() === 'outbound'

  return {
    _id: String(id),
    content: row.message ?? '',
    senderId: isOutbound ? currentUserId : `external:${row.source || row.destination || 'unknown'}`,
    username: isOutbound ? (props.auth.currentExtensionName || 'You') : (row.source || 'External'),
    timestamp: new Date(createdAt).toISOString(),
    date: new Date(createdAt).toISOString().slice(0, 10),
    roomId: String(roomId),
    // optional status hook
    status: row.status || (isOutbound ? 'sent' : 'received'),
    // optional: attach media support later
    files: row.media ? [{ name: 'media', url: row.media }] : []
  }
}

</script>
