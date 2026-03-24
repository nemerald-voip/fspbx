<template>

    <div class="flex flex-col h-screen overflow-hidden">

        <MainLayout />

        <!-- Main Layout: Full Screen Flex Container -->
        <div class="flex-1 min-h-0 flex w-full mx-auto m-4 border rounded-xl overflow-hidden shadow-xl bg-white">

            <!-- LEFT COLUMN: Sidebar -->
            <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
                <!-- Header -->
                <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">Messages</h2>
                    <!-- Optional: Loading Indicator -->
                    <div class="flex items-center space-x-2">

                        <span v-if="loadingRooms"
                            class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></span>

                        <button @click="showCreateModal = true"
                            class="p-2 bg-blue-50 text-blue-600 rounded-full hover:bg-blue-100 transition-colors"
                            title="New Chat">

                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">

                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />

                            </svg>

                        </button>

                    </div>

                </div>

                <div v-if="props.permissions.messages_view_as" class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">
                        Viewing as:
                    </label>
                    <Vueform ref="extensionForm$" :endpoint="false" :schema="extensionSelectSchema"
                        :float-placeholders="false" />
                </div>

                <!-- Room List -->
                <div class="flex-1 overflow-y-auto">
                    <div v-for="room in rooms" :key="room.id" @click="selectRoom(room.id)"
                        class="group relative flex items-center p-3 cursor-pointer transition-all duration-200 border-l-4 hover:bg-gray-50"
                        :class="[
                            activeRoomId === room.id
                                ? 'bg-blue-50 border-blue-600'
                                : 'border-transparent'
                        ]">

                        <!-- 1. Avatar -->
                        <div class="relative flex-shrink-0 mr-3">
                            <div
                                class="h-10 w-10 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center text-gray-600 font-bold text-xs shadow-sm group-hover:shadow-md transition-shadow">

                                {{ room.name.slice(0, 2) }}
                            </div>
                        </div>

                        <!-- 2. Middle Column: Name, Via, Message -->
                        <div class="flex-1 min-w-0 overflow-hidden mr-2">

                            <!-- Name -->
                            <h3 class="text-sm truncate mb-0.5"
                                :class="room.unread > 0 ? 'font-bold text-gray-900' : 'font-semibold text-gray-700'">
                                {{ room.name }}
                            </h3>

                            <!-- Via Badge + Message -->
                            <div class="flex items-center">
                                <!-- Via Badge -->
                                <span v-if="room.my_number"
                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-700 mr-2 flex-shrink-0 border border-blue-200">
                                    {{ room.my_number }}
                                </span>

                                <!-- Last Message -->
                                <p class="text-xs truncate"
                                    :class="room.unread > 0 ? 'font-semibold text-gray-800' : 'text-gray-500'">
                                    {{ room.lastMessage }}
                                </p>
                            </div>
                        </div>

                        <!-- 3. Right Column: Time & Unread Badge -->
                        <div class="flex flex-col items-end space-y-1">

                            <!-- Time -->
                            <span class="text-[10px] font-medium whitespace-nowrap"
                                :class="room.unread > 0 ? 'text-blue-600' : 'text-gray-400'">
                                {{ formatDate(room.timestamp) }}
                            </span>

                            <!-- Red Unread Badge -->
                            <span v-if="room.unread > 0"
                                class="flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-[10px] font-bold text-white bg-red-500 rounded-full shadow-sm animate-pulse">
                                {{ room.unread }}
                            </span>

                        </div>

                    </div>
                </div>
            </aside>

            <!-- Chat Area -->
            <main class="flex-1 relative flex flex-col bg-gray-100 min-w-0">

                <!-- Chat Toolbar -->
                <div
                    class="h-16 border-b border-gray-200 bg-white flex justify-between items-center px-6 shadow-sm z-10">
                    <div>
                        <h3 class="font-bold text-gray-800 text-lg">{{ currentRoomName }}</h3>
                        <span class="text-xs text-gray-500 font-mono" v-if="activeRoomId">
                            {{ activeRoomId.split('_')[1] }}
                        </span>
                    </div>

                    <!-- Toggle Contact Panel Button -->
                    <button v-if="activeRoomId" @click="toggleContactPanel"
                        class="p-2 rounded-full hover:bg-gray-100 text-gray-500 transition-colors focus:outline-none"
                        :class="{ 'bg-blue-100 text-blue-600 ring-2 ring-blue-200': showContactPanel }"
                        title="Contact Details">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                </div>

                <deep-chat ref="elementRef" :history="currentHistory" :connect="connectConfig"
                    :introMessage="introMessage"
                    style="width: 100%; height: 100%; border: none; background-color: #f3f4f6;" :messageStyles="{
                        default: {
                            shared: {
                                bubble: { maxWidth: '80%', padding: '10px 15px', borderRadius: '12px' }
                            },
                            user: {
                                bubble: { backgroundColor: '#3b82f6', color: 'white' }
                            },
                            ai: {
                                bubble: { backgroundColor: '#ffffff', color: '#1f2937' }
                            }
                        }
                    }" :textInput="{
                        placeholder: { text: 'Type a message...' },
                        styles: {
                            container: { backgroundColor: 'white', borderTop: '1px solid #e5e7eb', maxHeight: '100px', },
                            text: { color: '#374151' }
                        }
                    }">
                </deep-chat>
            </main>

            <!-- COL 3: CONTACT INFO PANEL -->
            <aside v-if="showContactPanel && activeRoomId"
                class="w-96 bg-white border-l border-gray-200 flex flex-col overflow-hidden transition-all duration-300 ease-in-out z-15 shadow-xl">

                <!-- Panel Header -->
                <div
                    class="flex-shrink-0 h-16 px-6 border-b border-gray-100 flex justify-between items-center bg-white">
                    <h2 class="text-lg font-bold text-gray-800">
                        {{ isEditingContact ? 'Edit Contact' : 'Contact Details' }}
                    </h2>
                    <div class="flex items-center space-x-3">
                        <!-- Edit/Cancel Button -->
                        <button @click="toggleContactEditForm" class="text-sm font-medium transition-colors"
                            :class="isEditingContact ? 'text-red-500 hover:text-red-700' : 'text-blue-600 hover:text-blue-800'">
                            {{ isEditingContact ? 'Cancel' : 'Edit' }}
                        </button>

                        <!-- Close Panel -->
                        <button @click="showContactPanel = false" class="text-gray-400 hover:text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- SCROLLABLE CONTENT AREA -->
                <div class="flex-1 overflow-y-auto p-6">

                    <!-- VIEW MODE -->
                    <div v-if="!isEditingContact" class="space-y-6">

                        <!-- Identity Header -->
                        <div class="text-center">
                            <div
                                class="w-20 h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center text-2xl font-bold text-gray-500 mb-3 border border-gray-200">
                                {{ contactInitials }}
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">{{ contactFullName }}</h3>

                            <!-- Job Title / Org / Dept -->
                            <div v-if="contactData?.title" class="text-sm font-semibold text-gray-700 mt-1">
                                {{ contactData?.title }}
                            </div>

                            <!-- FIXED: Organization Name Extraction -->
                            <div v-if="contactData?.organization" class="text-sm text-gray-500 font-medium">
                                {{ contactData.organization.name || contactData.organization }}
                            </div>

                            <div v-if="contactData?.department" class="text-xs text-gray-400 mt-0.5">
                                {{ contactData?.department }}
                            </div>
                        </div>

                        <!-- Contact Info Section -->
                        <div class="border-t border-gray-100 pt-4 space-y-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Contact Details
                            </h4>

                            <!-- Primary Phone -->
                            <div v-if="contactData?.phone_number" class="flex items-start">
                                <div class="mt-0.5 w-5 text-gray-400 flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ contactData?.phone_number }}</p>
                                    <p class="text-xs text-gray-500">Primary Phone</p>
                                </div>
                            </div>

                            <!-- Mobile -->
                            <div v-if="contactData?.mobile_number" class="flex items-start">
                                <div class="mt-0.5 w-5 text-gray-400 flex-shrink-0">
                                    <!-- Mobile device icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zm3 14a1 1 0 100-2 1 1 0 000 2z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ contactData?.mobile_number }}</p>
                                    <p class="text-xs text-gray-500">Mobile</p>
                                </div>
                            </div>

                            <!-- Fax -->
                            <div v-if="contactData?.fax_number" class="flex items-start">
                                <div class="mt-0.5 w-5 text-gray-400 flex-shrink-0">
                                    <!-- Printer/Fax icon -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ contactData?.fax_number }}</p>
                                    <p class="text-xs text-gray-500">Fax</p>
                                </div>
                            </div>

                            <!-- Email -->
                            <div v-if="contactData?.email" class="flex items-start">
                                <div class="mt-0.5 w-5 text-gray-400 flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path
                                            d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                    </svg>
                                </div>
                                <div class="ml-3 break-all">
                                    <a :href="`mailto:${contactData?.email}`"
                                        class="text-sm font-medium text-blue-600 hover:underline">
                                        {{ contactData?.email }}
                                    </a>
                                    <p class="text-xs text-gray-500">Email</p>
                                </div>
                            </div>

                            <!-- Website -->
                            <div v-if="contactData?.website" class="flex items-start">
                                <div class="mt-0.5 w-5 text-gray-400 flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.002 6.002 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.56-.5-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.002 6.002 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.56.5.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.497-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.002 6.002 0 01-5.322 4.882zM4.083 11a6.002 6.002 0 005.322 4.882c-.454-1.147-.748-2.572-.837-4.118H4.083z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3 break-all">
                                    <a :href="contactData?.website" target="_blank"
                                        class="text-sm font-medium text-blue-600 hover:underline">
                                        {{ contactData?.website }}
                                    </a>
                                    <p class="text-xs text-gray-500">Website</p>
                                </div>
                            </div>
                        </div>

                        <!-- Address Section (UPDATED) -->
                        <div v-if="formattedAddress" class="border-t border-gray-100 pt-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Location</h4>
                            <div class="flex items-start">
                                <div class="mt-0.5 w-5 text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <p class="ml-3 text-sm text-gray-700 whitespace-pre-line">{{ formattedAddress }}</p>
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div v-if="contactData?.notes" class="border-t border-gray-100 pt-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Notes</h4>
                            <div
                                class="bg-yellow-50 p-3 rounded text-sm text-gray-700 whitespace-pre-line border border-yellow-100 shadow-sm">
                                {{ contactData?.notes }}
                            </div>
                        </div>

                    </div>

                    <!-- EDIT MODE (Form) -->
                    <div v-else class="flex flex-col h-full">
                        <Vueform ref="contactForm$" :float-placeholders="false" :schema="contactFormSchema"
                            :endpoint="submitContactForm" @response="handleContactResponse"
                            @success="handleContactSuccess" @error="handleContactError" :display-errors="false" />

                        <!-- Delete Button below the form -->
                        <div v-if="contactData?.contact_uuid" class="mt-6 pt-4 pb-6 border-t border-gray-100">
                            <button @click="showDeleteContactModal = true"
                                class="w-full text-center text-sm font-medium text-red-500 hover:text-red-700 py-2">
                                Delete Contact
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- VueForm CREATE ROOM MODAL -->
        <div v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-2xl w-96 p-6 transform transition-all scale-100">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Start New Conversation</h3>

                <!-- VueForm Component -->
                <Vueform :endpoint="false" :float-placeholders="false" :schema="createRoomSchema"
                    @submit="handleCreateRoom" />

                <!-- Close Button (Optional, if not included in form actions) -->
                <div class="mt-4 flex justify-center">
                    <button @click="showCreateModal = false"
                        class="text-sm text-gray-400 hover:text-gray-600">Cancel</button>
                </div>
            </div>
        </div>

        <!-- VueForm CREATE ORG MODAL -->
        <div v-if="showOrgModal"
            class="fixed inset-0 z-20 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-2xl w-96 p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Add Organization</h3>

                <Vueform :endpoint="false" :float-placeholders="false" :schema="createOrgSchema"
                    @submit="handleCreateOrg" />

                <div class="mt-4 flex justify-center">
                    <button @click="showOrgModal = false"
                        class="text-sm text-gray-400 hover:text-gray-600">Cancel</button>
                </div>
            </div>
        </div>

    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

    <!-- DELETE CONTACT CONFIRMATION MODAL -->
    <ConfirmationModal :show="showDeleteContactModal" @close="showDeleteContactModal = false"
        @confirm="handleDeleteContact" header="Delete Contact?"
        :text="`Are you sure you want to delete ${contactFullName}? This action cannot be undone, but your chat history will remain.`"
        confirm-button-label="Delete" cancel-button-label="Cancel" />

</template>

<script setup>
import { computed, onMounted, ref, onBeforeUnmount, nextTick } from "vue";
import axios from 'axios';
import 'deep-chat'; // Registers the web component
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
// import Pusher from 'pusher-js';

// --- Props (from Laravel/Inertia) ---
const props = defineProps({
    routes: { type: Object, required: true },
    permissions: { type: Object, default: () => ({}) }
})

// --- State ---
const data = ref([]);
const activeRoomId = ref(null);
const rooms = ref([]);
const loadingRooms = ref(false);
const currentHistory = ref([]); // Messages for the active room
const showCreateModal = ref(false);
const currentExtensionUuid = ref(null);
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);
let globalEchoChannel = null;
const showContactPanel = ref(true); // Toggle for right sidebar
const contactData = ref(null);
const isEditingContact = ref(false);
const showOrgModal = ref(false);
const contactForm$ = ref(null);
const showDeleteContactModal = ref(false);
const localOrgs = ref([]);
const extensionForm$ = ref(null);

// DIDs State (Populated when extension changes)
const myDids = ref([]);
const locallySentMessages = ref([]);

// Global Variable to store DeepChat signals ---
let deepChatSignals = null;

// --- Computed ---
const currentRoomName = computed(() => {
    return rooms.value.find(r => r.id === activeRoomId.value)?.name || 'Chat';
});

const introMessage = computed(() => {
    return activeRoomId.value
        ? { text: `Conversation with ${currentRoomName.value}` }
        : { text: 'Select a conversation to start chatting.' };
});

const extensionList = computed(() => data.value.extensions || []);

// --- Lifecycle ---
onMounted(async () => {

    // Enable debug logs to see Subscription Success/Failure
    // Pusher.logToConsole = true; 

    // 1. Fetch Extensions first
    await getData();

    // 2. Fetch Rooms (only after we have the Extension ID)
    await fetchRooms();
});

// --- Actions ---

const getData = async () => {
    try {
        const response = await axios.get(props.routes.data_route);
        data.value = response.data;

        // 1. POPULATE THE DROPDOWN ITEMS
        extensionSelectSchema.value.extension.items = extensionList.value.map(ext => ({
            value: ext.value,
            label: ext.name
        }));

        // 2. AUTO-SELECT LOGIC
        if (!currentExtensionUuid.value) {
            let defaultId = data.value.extension_uuid;
            let exists = extensionList.value.find(e => e.value === defaultId);

            // If the default doesn't exist, fallback to the first one
            if (!exists && extensionList.value.length > 0) {
                defaultId = extensionList.value[0].value;
                exists = extensionList.value[0];
            }

            if (exists) {
                currentExtensionUuid.value = defaultId;
                if (exists.dids) myDids.value = exists.dids;

                // 3. FORCE VUEFORM TO SHOW THE SELECTED VALUE
                // We wrap this in nextTick so Vueform has time to register the new items
                nextTick(() => {
                    if (extensionForm$.value) {
                        extensionForm$.value.update({ extension: defaultId });
                    }
                });

                joinExtensionChannel(defaultId);
            }
        }
    } catch (error) {
        handleErrorResponse(error);
    }
}

// When user selects from Dropdown
const onExtensionChange = (uuid) => {
    // 1. Update the string UUID
    currentExtensionUuid.value = uuid || null;

    // 2. Find the full extension object from the list
    const selectedOption = extensionList.value.find(e => e.value === uuid);

    // 3. Update the DIDs list for the "Create Room" form
    if (selectedOption && selectedOption.dids) {
        myDids.value = selectedOption.dids;
    } else {
        myDids.value = [];
    }

    // Switch Global Channel
    joinExtensionChannel(currentExtensionUuid.value);

    // Clear Chat & Refresh Rooms
    activeRoomId.value = null;
    currentHistory.value = [];
    showContactPanel.value = false;
    fetchRooms();
};

// --- Actions ---

async function fetchRooms() {
    loadingRooms.value = true;
    try {
        const { data } = await axios.get(props.routes.roomsIndex, {
            params: {
                extension_uuid: currentExtensionUuid.value
            }
        });

        rooms.value = (data.rooms || []).map(r => ({
            id: String(r.id),
            name: r.name,
            avatar: r.avatar,
            unread: Number(r.unread || 0),
            lastMessage: r.lastMessage || 'No messages yet',

            // CAPTURE NEW FIELDS
            my_number: r.my_number, // The local DID
            timestamp: r.timestamp  // ISO String
        }));

        if (rooms.value.length > 0) {
            selectRoom(rooms.value[0].id);
        } else {
            activeRoomId.value = null;
        }
    } catch (e) {
        console.error("Error fetching rooms:", e);
    } finally {
        loadingRooms.value = false;
    }
}

// --- Helper: Format Date ---
function formatDate(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    const now = new Date();

    // Check if it's today
    const isToday = date.getDate() === now.getDate() &&
        date.getMonth() === now.getMonth() &&
        date.getFullYear() === now.getFullYear();

    if (isToday) {
        // Show Time: "3:15 PM"
        return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    } else {
        // Show Date: "Feb 24"
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
}

// --- Helper: Update Sidebar List ---
const updateSidebar = (roomId, newMessageText, timestamp = null) => {
    // 1. Find the room in the list
    const index = rooms.value.findIndex(r => r.id === roomId);

    if (index !== -1) {
        const room = rooms.value[index];

        // 2. Update Data
        room.lastMessage = newMessageText;
        room.timestamp = timestamp || new Date().toISOString();

        // 3. Move to Top
        // Remove it from current position
        rooms.value.splice(index, 1);
        // Add it to the start
        rooms.value.unshift(room);
    }
};

// --- REVERB WEBSOCKET LOGIC ---
function joinChannel(roomId) {
    leaveChannel(activeRoomId.value);
    if (!window.Echo) return;

    const channelId = roomId.replace(/\+/g, '');
    console.log(`🔌 Joining Reverb channel: room.${channelId}`);

    window.Echo.private(`room.${channelId}`)
        .listen('.message.new', (e) => {
            console.log('✅ LISTENER FIRED:', e);

            const rawText = e.text || e.message || '';
            let role = e.role;
            if (!role) {
                const dir = String(e.direction || '').toLowerCase();
                role = ['out', 'outbound', 'outgoing'].includes(dir) ? 'user' : 'ai';
            }

            // --- SMART DEDUPLICATION ---
            if (role === 'user') {
                // Did we JUST send this message from this specific Vue window?
                const localIndex = locallySentMessages.value.indexOf(rawText);

                if (localIndex !== -1) {
                    // YES: DeepChat already drew the blue bubble when we clicked Send.
                    // Remove it from our tracker and abort so it doesn't duplicate.
                    locallySentMessages.value.splice(localIndex, 1);

                    // (Still update the sidebar timestamp with the real server time)
                    updateSidebar(roomId, rawText || '📷 Image', e.timestamp);
                    return;
                }
            }

            // --- INJECT THE MESSAGE ---
            // If we made it here, it's either from the Customer (ai) 
            // OR it's an Outbound message from your Cell Phone (user).
            if (deepChatSignals) {
                console.log('Injecting via Signals...');
                // Run it through our normalizer to get the Image/Timestamp HTML
                const formattedMessage = normalizeMessageForDeepChat(e);
                deepChatSignals.onResponse(formattedMessage);
            } else {
                console.error('❌ DeepChat Signals not initialized yet');
            }

            // Update Sidebar List
            updateSidebar(roomId, rawText || '📷 Image', e.timestamp);
        })
        .error((error) => {
            console.error('Reverb Subscription Error:', error);
        });
}

const joinExtensionChannel = (extUuid) => {
    // 1. Cleanup old listener
    if (globalEchoChannel) {
        window.Echo.leave(`extension.${globalEchoChannel}`);
    }

    if (!extUuid || !window.Echo) return;

    console.log(`📡 Listening to Global Extension Channel: ${extUuid}`);
    globalEchoChannel = extUuid;

    // 2. Subscribe
    window.Echo.private(`extension.${extUuid}`)
        .listen('.conversation.updated', (e) => {
            console.log('🔔 Global Update:', e);
            handleGlobalUpdate(e);
        });
};

function leaveChannel(roomId) {
    if (window.Echo && roomId) {
        const channelId = roomId.replace(/\+/g, '');
        window.Echo.leave(`room.${channelId}`);
    }
}


// --- Handler: Select Room ---
async function selectRoom(id) {
    if (activeRoomId.value === id) return;

    // Leave old
    if (activeRoomId.value) leaveChannel(activeRoomId.value);

    activeRoomId.value = id;

    // Clear Unread Badge Immediately ---
    const room = rooms.value.find(r => r.id === id);
    if (room) {
        room.unread = 0;
    }

    //Backend Update: Mark messages as read in DB
    try {
        await axios.post(props.routes.markRead, { roomId: id });
    } catch (e) {
        console.error("Failed to mark as read", e);
    }

    // Load history via API (Rest)
    await fetchMessages(id);

    // Listen for new messages (Reverb)
    joinChannel(id);

    // Always switch back to "View Mode" (clean profile) when changing users
    isEditingContact.value = false;

    // If the panel is open, load the new user's data immediately
    if (showContactPanel.value) {
        loadContactData();
    }
}

// --- API: Fetch Messages ---
async function fetchMessages(roomId) {
    // Clear history temporarily while loading
    currentHistory.value = [];

    if (!roomId) return;

    const url = props.routes.roomMessages.replace(':roomId', roomId);

    try {
        const { data } = await axios.get(url, { params: { 'page[size]': 50 } });
        const rawMessages = data.messages || [];

        // DeepChat expects: { text: '...', role: 'user' | 'ai' }
        // We need to reverse because API usually sends Newest -> Oldest, 
        // but Chat UI needs Oldest -> Newest (top to bottom)
        currentHistory.value = rawMessages.map(m => normalizeMessageForDeepChat(m)).reverse();

    } catch (e) {
        console.error("Error fetching messages:", e);
        currentHistory.value = [{ text: "Error loading history.", role: "ai" }];
    }
}

// --- DeepChat Configuration ---
const connectConfig = {
    websocket: true, // Enable async mode
    handler: (body, signals) => {
        // CAPTURE SIGNALS HERE
        // This allows us to use 'signals' anywhere in our code (like inside Echo)
        deepChatSignals = signals;

        signals.onOpen(); // Mark connection as open immediately

        // Handle User Sending Message
        signals.newUserMessage.listener = async (msgBody) => {
            const text = msgBody.messages[0].text;
            const currentId = activeRoomId.value;

            if (!currentId) return;

            // ADD TO OUR TRACKER to prevent WebSocket duplication
            locallySentMessages.value.push(text);

            // Parse ID (source_dest)
            const parts = currentId.split('_');
            if (parts.length !== 2) return;

            try {
                // Fire and Forget (Optimistic UI)
                await axios.post(props.routes.sendMessage, {
                    source: parts[0],
                    destination: parts[1],
                    message: text,
                    extension_uuid: currentExtensionUuid.value
                });

                // Update Sidebar immediately
                updateSidebar(currentId, text, new Date().toISOString());
            } catch (e) {
                console.error("Send failed", e);
                // Optional: Notify user of failure
                // signals.onResponse({ error: "Failed to send" });
            }
        };
    }
};

// --- Helper: Normalize Data ---
function formatMessageTimestamp(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    const now = new Date();

    const isToday = date.getDate() === now.getDate() &&
        date.getMonth() === now.getMonth() &&
        date.getFullYear() === now.getFullYear();

    const time = date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

    if (isToday) {
        return `Today, ${time}`;
    }

    // Check if it's from a previous year
    if (date.getFullYear() !== now.getFullYear()) {
        const fullDate = date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
        return `${fullDate}, ${time}`;
    }

    // Default for earlier this year (e.g., "Feb 24, 3:15 PM")
    const shortDate = date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    return `${shortDate}, ${time}`;
}

// --- Helper: Normalize Data ---
function normalizeMessageForDeepChat(row) {
    // 1. Calculate Role
    let role = row.role;
    if (!role) {
        const dir = String(row.direction || '').toLowerCase();
        const isOutbound = ['out', 'outbound', 'outgoing'].includes(dir);
        role = isOutbound ? 'user' : 'ai';
    }

    // 2. Format Timestamp using our new Smart Formatter
    const rawTime = row.timestamp || row.created_at;
    const timeString = formatMessageTimestamp(rawTime);

    // 3. Handle Images
    let filesArray = undefined;

    if (row.media && Array.isArray(row.media) && row.media.length > 0) {
        // Map over the media items and extract the access_path
        filesArray = row.media.map(mediaItem => {
            return {
                src: mediaItem.access_path, // This maps to your MessageMediaController route
                type: 'image',
                name: mediaItem.original_name || 'image.png'
            };
        });
    }

    // 4. Handle Text Content
    const content = row.text || row.message || '';

    // If there is an image but NO text, return just the image with the timestamp under it
    if (filesArray && !content) {
        return {
            role: role,
            files: filesArray,
            html: timeString ? `<div style="font-size: 10px; opacity: 0.7; text-align: right; margin-top: 4px; white-space: nowrap;">${timeString}</div>` : ''
        };
    }

    // If there is Text, construct an HTML bubble with the text and the smart date
    return {
        role: role,
        files: filesArray,
        html: `
            <div style="display: flex; flex-direction: column;">
                <div style="white-space: pre-wrap; line-height: 1.4;">${content}</div>
                ${timeString ? `<div style="font-size: 10px; opacity: 0.7; text-align: right; margin-top: 6px; white-space: nowrap;">${timeString}</div>` : ''}
            </div>
        `
    };
}

// --- Action: Handle Form Submit ---
const handleCreateRoom = (form$) => {
    const data = form$.requestData;

    const source = data.source;
    let dest = data.destination.replace(/\D/g, ''); // Strip non-digits

    // 1. Construct Composite ID
    const newCompositeId = `${source}_${dest}`;

    // 2. Optimistic UI Update
    const newRoom = {
        id: newCompositeId,
        name: dest,
        my_number: source,
        avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(dest)}&background=random`,
        unread: 0,
        lastMessage: 'Draft'
    };

    rooms.value.unshift(newRoom);
    selectRoom(newRoom.id);

    // 3. Reset & Close
    showCreateModal.value = false;
}

const handleGlobalUpdate = (e) => {
    // 1. Check if the message text is empty but media exists
    let displayText = e.lastMessage || '';
    if (displayText.trim() === '' && e.media && e.media.length > 0) {
        displayText = '📷 Image';
    }

    // 2. Find Room
    const index = rooms.value.findIndex(r => r.id === e.roomId);

    let room;

    if (index !== -1) {
        // UPDATE EXISTING ROOM
        room = rooms.value[index];
        room.lastMessage = displayText; // <-- Use our new variable
        room.timestamp = e.timestamp;

        rooms.value.splice(index, 1);
    } else {
        // CREATE NEW ROOM 
        room = {
            id: e.roomId,
            name: e.name,
            my_number: e.my_number,
            avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(e.name)}&background=random`,
            unread: 0,
            lastMessage: displayText, // <-- Use our new variable
            timestamp: e.timestamp
        };
    }

    // 3. Handle Unread Count
    if (activeRoomId.value !== e.roomId && e.direction === 'in') {
        room.unread = (room.unread || 0) + 1;
    }

    rooms.value.unshift(room);
};

// --- VueForm Schema ---
// We use a computed property so the 'items' (options) update automatically 
// when 'myDids' changes.
const createRoomSchema = computed(() => {
    return {
        source: {
            type: 'select',
            label: 'From',
            items: myDids.value.map(did => ({
                value: did.number,
                label: [did.number, did.label].filter(Boolean).join(' - ')
            })),
            rules: ['required'],
            default: myDids.value.length > 0 ? myDids.value[0].number : null,
            search: true,
            native: false, // Use custom select UI
        },
        destination: {
            type: 'text',
            inputType: 'tel',
            label: 'To (Customer)',
            placeholder: '+15550000000',
            floating: false
        },
        submit: {
            type: 'button',
            submits: true,
            buttonLabel: 'Start Chat',
            align: 'center',
            buttonClass: 'bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded mt-2',

            disabled: myDids.value.length === 0,
        }

    }
});



// --- VueForm Schema ---
const contactFormSchema = ref({
    // Row 1: Names

    first_name: { type: 'text', label: 'First Name', columns: 6 },
    last_name: { type: 'text', label: 'Last Name', columns: 6 },

    // Row 2: Title & Department
    container_job: {
        type: 'group',
        schema: {
            title: { type: 'text', label: 'Job Title', columns: 6 },
            department: { type: 'text', label: 'Department', columns: 6 },
        }
    },

    //  Organization (Split into Select + Add Button)
    container_org_row: {
        type: 'group',
        schema: {
            organization_uuid: {
                type: 'select',
                label: 'Organization',
                search: true,
                native: false,
                inputType: 'search',
                autocomplete: 'off',
                placeholder: 'Search or Select...',
                columns: 10, // Take up most space

                // Fetch from Backend
                items: async (query) => {
                    try {
                        // 1. Fetch from server
                        const res = await axios.get(props.routes.organizationsIndex, { params: { query } });
                        const fetchedOrgs = res.data || [];

                        // 2. Combine server results with our locally known Orgs
                        const combined = [...localOrgs.value, ...fetchedOrgs];

                        // 3. Deduplicate so we don't show the same Org twice
                        return Array.from(new Map(combined.map(item => [item.value, item])).values());
                    } catch (e) {
                        return localOrgs.value;
                    }
                }
            },
            add_org_btn: {
                type: 'button',
                buttonLabel: '+',
                label: '&nbsp;', // Empty label to align with input
                columns: 2,
                buttonClass: 'w-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-2 rounded border border-gray-300',
                onClick: () => { showOrgModal.value = true; }
            }
        }
    },

    divider1: { type: 'static', content: '<div class="h-4"></div>' }, // Spacer

    // Row 4: Contact Methods
    website: { type: 'text', label: 'Website', columns: 12 },
    email: { type: 'text', inputType: 'email', label: 'Email', columns: 12 },
    phone_number: { type: 'text', label: 'Primary Phone', columns: 12, },

    mobile_number: { type: 'text', label: 'Mobile', columns: 12 },
    fax_number: { type: 'text', label: 'Fax', columns: 12 },


    divider2: { type: 'static', content: '<hr class="my-4 border-gray-100">' },

    // Row 5: Address (Granular)
    address_label: { type: 'static', content: '<label class="text-xs font-bold text-gray-500 uppercase">Address</label>' },
    address_street: { type: 'text', placeholder: 'Street Address', columns: 12 },
    container_addr: {
        type: 'group',
        schema: {
            address_city: { type: 'text', placeholder: 'City', columns: 5 },
            address_state: { type: 'text', placeholder: 'State', columns: 3 },
            address_zip: { type: 'text', placeholder: 'Zip', columns: 4 },
        }
    },

    // Row 6: Notes
    notes: { type: 'textarea', label: 'Notes', rows: 3 },

    // Actions
    divider3: { type: 'static', content: '<div class="h-4"></div>' },
    save: {
        type: 'button',
        buttonLabel: 'Save Changes',
        submits: true,
        buttonClass: 'w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded shadow-sm',
    }
});

const createOrgSchema = ref({
    // Basic Info
    name: { type: 'text', label: 'Organization Name', rules: ['required'] },

    // Internet
    email: { type: 'text', inputType: 'email', label: 'Email' },
    website: { type: 'text', label: 'Website' },

    // Address (Grouped for layout)
    address_label: { type: 'static', content: '<label class="text-xs font-bold text-gray-500 uppercase mt-2 block">Address</label>' },
    address_street: { type: 'text', placeholder: 'Street' },

    container_addr: {
        type: 'group',
        schema: {
            address_city: { type: 'text', placeholder: 'City', columns: 5 },
            address_state: { type: 'text', placeholder: 'State', columns: 3 },
            address_zip: { type: 'text', placeholder: 'Zip', columns: 4 },
        }
    },

    // Notes
    notes: { type: 'textarea', label: 'Notes', rows: 2 },

    // Submit
    submit: {
        type: 'button',
        submits: true,
        buttonLabel: 'Create Organization',
        buttonClass: 'w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded mt-2',
    }
});

// Add this next to your other schemas
const extensionSelectSchema = ref({
    extension: {
        type: 'select',
        search: true,
        native: false,
        placeholder: 'Select Extension',
        items: [], // Starts empty, we will fill it manually after API loads
        onChange: (newValue) => {
            // Prevent accidental fires during initialization
            if (newValue && newValue !== currentExtensionUuid.value) {
                onExtensionChange(newValue);
            }
        }
    }
});

// --- Computed Helper for View Mode Address ---
const formattedAddress = computed(() => {
    const s = contactData.value?.address_street;
    const c = contactData.value?.address_city;
    const st = contactData.value?.address_state;
    const z = contactData.value?.address_zip;

    if (!s && !c && !st && !z) return null;

    let line2 = [c, st, z].filter(Boolean).join(', ');
    return [s, line2].filter(Boolean).join('\n');
});

// --- Actions ---

const toggleContactPanel = async () => {
    showContactPanel.value = !showContactPanel.value;
    isEditingContact.value = false; // Reset to View Mode
    if (showContactPanel.value && activeRoomId.value) {
        await loadContactData();
    }
};


const loadContactData = async () => {
    // 1. Safety check: Do we have a room selected?
    if (!activeRoomId.value) return;

    // 2. Extract customer number from the ID "MyDID_CustomerDID"
    const parts = activeRoomId.value.split('_');
    if (parts.length < 2) return;

    const customerNumber = parts[1];

    try {
        contactData.value = null
        // 3. Construct the URL using the route passed from Laravel
        // We replace the placeholder ':phoneNumber' with the actual number
        const url = props.routes.contactShow.replace(':phoneNumber', encodeURIComponent(customerNumber));

        const { data } = await axios.get(url);

        // Prepare the data object
        // If contact exists, use it. If not, create a shell with just the phone number.
        const incomingData = data.contact ? data.contact : { phone_number: customerNumber };

        // If the contact belongs to an organization, we grab its UUID and Name
        // and push it into localOrgs so the Vueform Select dropdown shows the Name instead of the UUID.
        // if (incomingData.organization && incomingData.organization.organization_uuid) {
        //     localOrgs.value = [{
        //         value: incomingData.organization.organization_uuid,
        //         label: incomingData.organization.name
        //     }];
        // } else {
        //     localOrgs.value = null
        // }

        // 2. Update View Mode (The read-only display)
        contactData.value = incomingData;

        // 3. Update Form (The edit mode inputs)
        // We use optional chaining (?.) because the form might not be rendered if we are in View Mode
        // .update() ensures all fields (including hidden/selects) are populated correctly
        contactForm$.value?.update(incomingData);
    } catch (e) {
        console.error("Error loading contact", e);
    }
};

const toggleContactEditForm = async () => {
    isEditingContact.value = !isEditingContact.value

    if (isEditingContact.value) {
        await nextTick();
        contactForm$.value.update(contactData.value);
    }


}

const submitContactForm = async (FormData, form$) => {
    const requestData = form$.requestData
    return await form$.$vueform.services.axios.post(props.routes.contactStore, requestData)
}

// Computed helper for the View Mode Avatar
const contactInitials = computed(() => {
    const first = contactData.value?.first_name ?? '';
    const last = contactData.value?.last_name ?? '';
    if (first || last) return (first.slice(0, 1) + last.slice(0, 1)).toUpperCase();
    return '#';
});

// Computed helper for Full Name
const contactFullName = computed(() => {
    const first = contactData.value?.first_name ?? '';
    const last = contactData.value?.last_name ?? '';
    return `${first} ${last}`.trim() || 'Unknown Contact';
});

// --- Action: Delete Contact ---
const handleDeleteContact = async () => {
    // Ensure we actually have a saved contact to delete
    if (!contactData.value?.contact_uuid) return;

    try {
        const url = props.routes.contactDestroy.replace(':contact', contactData.value.contact_uuid);
        await axios.delete(url);

        showNotification('success', { request: ['Contact deleted successfully'] });

        showDeleteContactModal.value = false;
        isEditingContact.value = false;

        // Reload data (This will fetch null, and reset the panel to just show the raw phone number)
        await loadContactData();

        // Refresh the sidebar so the name reverts back to the phone number
        await fetchRooms();

    } catch (e) {
        handleErrorResponse(e);
    }
};

const handleCreateOrg = async (form$) => {
    const data = form$.requestData;

    try {
        // 1. Create on Backend
        const response = await axios.post(props.routes.organizationsStore, data);
        const newOrg = response.data; // { value: 'uuid', label: 'Name' }

        // 2. Close Modal
        showOrgModal.value = false;

        // 3. Inject into Contact Form safely
        if (contactForm$.value) {
            // Add to our local list so it's guaranteed to be in the dropdown
            localOrgs.value.push(newOrg);

            // Get the specific field element
            console.log(contactForm$.value.elements$['container_org_row'].children$['organization_uuid'])
            const orgSelect = contactForm$.value.elements$['container_org_row'].children$['organization_uuid'];


            // Force the element to re-run the `items` async function
            await orgSelect.updateItems();

            // Set the value (the label will now appear correctly!)
            orgSelect.update(newOrg.value);
        }

        showNotification('success', { request: ['Organization created'] });

    } catch (e) {
        handleErrorResponse(e);
    }
};

const handleContactResponse = (response, contactForm$) => {
    Object.values(contactForm$.elements$).forEach(el$ => clearErrorsRecursive(el$))
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (contactForm$.el$(elName)) {
                contactForm$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleContactSuccess = (response, contactForm$) => {
    // emit('success', 'success', response.data.messages)

    showNotification('success', response.data.messages);

    // Refresh rooms to update the name in the sidebar if it changed
    fetchRooms();

    loadContactData(); // Reload data to reflect changes

    isEditingContact.value = false; // Switch back to View Mode
}

const handleContactError = (error, details, contactForm$) => {
    contactForm$.messageBag.clear()

    switch (details.type) {
        case 'prepare':
            console.log(error)
            contactForm$.messageBag.append('Could not prepare form')
            break
        case 'submit':
            handleErrorResponse(error)
            console.log(error)
            break
        case 'cancel':
            console.log(error)
            contactForm$.messageBag.append('Request cancelled')
            break
        case 'other':
            console.log(error)
            contactForm$.messageBag.append('Couldn\'t submit form')
            break
    }
}

function clearErrorsRecursive(el$) {
    // clear this element’s errors
    el$.messageBag?.clear()

    // if it has child elements, recurse into each
    if (el$.children$) {
        Object.values(el$.children$).forEach(childEl$ => {
            clearErrorsRecursive(childEl$)
        })
    }
}


// Cleanup
onBeforeUnmount(() => {
    if (activeRoomId.value) leaveChannel(activeRoomId.value);
    if (globalEchoChannel) window.Echo.leave(`extension.${globalEchoChannel}`);

});

const handleErrorResponse = (error) => {
    if (error.response) {
        // The request was made and the server responded with a status code
        // that falls out of the range of 2xx
        // console.log(error.response.data);
        showNotification('error', error.response.data.errors || { request: [error.message] });
    } else if (error.request) {
        // The request was made but no response was received
        // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
        // http.ClientRequest in node.js
        showNotification('error', { request: [error.request] });
        console.log(error.request);
    } else {
        // Something happened in setting up the request that triggered an Error
        showNotification('error', { request: [error.message] });
        console.log(error.message);
    }
}

const hideNotification = () => {
    notificationShow.value = false;
    notificationType.value = null;
    notificationMessages.value = null;
}

const showNotification = (type, messages = null) => {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}

</script>

<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>