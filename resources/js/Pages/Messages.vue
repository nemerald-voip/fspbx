<template>

    <div class="flex flex-col h-screen overflow-hidden">

        <MainLayout />

        <!-- Main Layout: Full Screen Flex Container -->
        <div class="flex-1 min-h-0 flex w-full mx-auto m-4 border rounded-xl overflow-hidden shadow-xl bg-white">

            <!-- LEFT COLUMN: Sidebar -->
            <aside class="w-80 bg-white border-r border-gray-200 flex flex-col">
                <!-- Header -->
                <div class="p-5 border-b border-gray-100 flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">Messages</h2>
                    <!-- Optional: Loading Indicator -->
                    <span v-if="loadingRooms" class="text-xs text-gray-400">Loading...</span>

                    <!-- New Room Button -->
                    <button @click="showCreateModal = true"
                        class="p-2 bg-blue-100 text-blue-600 rounded-full hover:bg-blue-200 transition-colors"
                        title="New Chat">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>

                </div>

                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">
                        Viewing as:
                    </label>
                    <select v-model="currentExtensionUuid" @change="onExtensionChange"
                        class="w-full text-sm pl-3 pr-8 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition shadow-sm bg-white"
                        :disabled="loadingRooms">
                        <option :value="null" disabled>Select Extension...</option>
                        <option v-for="ext in extensionList" :key="ext.value" :value="ext.value">
                            {{ ext.name }}
                        </option>
                    </select>
                </div>

                <!-- Room List -->
                <div class="flex-1 overflow-y-auto">
                    <div v-for="room in rooms" :key="room.id" @click="selectRoom(room.id)"
                        class="flex items-center p-4 cursor-pointer transition-colors duration-200 hover:bg-gray-50 border-l-4"
                        :class="[
                            activeRoomId === room.id
                                ? 'bg-blue-50 border-blue-500'
                                : 'border-transparent'
                        ]">
                        <!-- Avatar -->
                        <img :src="room.avatar || 'https://cdn-icons-png.flaticon.com/512/149/149071.png'" alt="avatar"
                            class="w-10 h-10 rounded-full object-cover mr-4 shadow-sm" />

                        <!-- Room Info -->
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900 truncate">
                                {{ room.name }}
                            </div>
                            <div class="text-sm text-gray-500 truncate">
                                {{ room.lastMessage || 'Click to open chat...' }}
                            </div>
                        </div>

                        <!-- Unread Badge -->
                        <div v-if="room.unread > 0"
                            class="ml-2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                            {{ room.unread }}
                        </div>
                    </div>
                </div>
            </aside>

            <!-- RIGHT COLUMN: Chat Area -->
            <main class="flex-1 relative flex flex-col bg-gray-100">
                <!-- 
               :key="activeRoomId" ensures the component re-renders when switching rooms 
               to load the new history cleanly.
            -->
                <deep-chat :key="activeRoomId" :history="currentHistory" :connect="connectConfig"
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
                            container: { backgroundColor: 'white', borderTop: '1px solid #e5e7eb', padding: '15px' },
                            text: { color: '#374151' }
                        }
                    }">
                </deep-chat>
            </main>
        </div>

        <!-- CREATE ROOM MODAL -->
        <div v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-2xl w-96 p-6 transform transition-all scale-100">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Create New Room</h3>

                <input v-model="newRoomName" @keyup.enter="createRoom"
                    placeholder="Enter room name (e.g., 'Project Alpha')"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none mb-4"
                    autofocus />

                <div class="flex justify-end space-x-3">
                    <button @click="showCreateModal = false"
                        class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="createRoom"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md transition-colors"
                        :disabled="!newRoomName.trim()">
                        Create
                    </button>
                </div>
            </div>
        </div>

    </div>

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages"
        @update:show="hideNotification" />

</template>

<script setup>
import { computed, onMounted, ref, onBeforeUnmount } from "vue";
import axios from 'axios';
import 'deep-chat'; // Registers the web component
import MainLayout from "../Layouts/MainLayout.vue";
import Notification from "./components/notifications/Notification.vue";


// --- Props (from Laravel/Inertia) ---
const props = defineProps({
    routes: { type: Object, required: true },
    auth: { type: Object, required: true }
})

// --- State ---
const data = ref([]);
const activeRoomId = ref(null);
const rooms = ref([]);
const loadingRooms = ref(false);
const currentHistory = ref([]); // Messages for the active room
const showCreateModal = ref(false);
const newRoomName = ref('');
const currentExtensionUuid = ref(null)
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

// Reference to DeepChat element to call methods directly
const elementRef = ref(null);

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
        
        // AUTO-SELECT LOGIC:
        // If currentExtensionUuid is empty, try to set it to the logged-in user's extension
        if (!currentExtensionUuid.value) {
            // Check where the extension_uuid lives in your auth prop structure.
            // Usually: props.auth.user.extension_uuid OR props.auth.extension_uuid
            const myExtensionId = props.auth.user?.extension_uuid || props.auth.extension_uuid;
            
            // Validate that this ID actually exists in the list we just fetched
            const exists = (data.value.extensions || []).find(e => e.value === myExtensionId);
            
            if (exists) {
                currentExtensionUuid.value = myExtensionId;
            } else if (data.value.extensions?.length > 0) {
                // Fallback: Select the first one if my extension isn't in the list
                currentExtensionUuid.value = data.value.extensions[0].value;
            }
        }
    } catch (error) {
        handleErrorResponse(error);
    }
}

const onExtensionChange = () => {
    // Clear current chat view when switching users
    activeRoomId.value = null;
    currentHistory.value = [];
    
    // Refresh list
    fetchRooms();
}

async function fetchRooms() {
    loadingRooms.value = true;
    try {
        const { data } = await axios.get(props.routes.roomsIndex, {
            params: {
                // CRITICAL: Send the selected extension to the backend
                extension_uuid: currentExtensionUuid.value 
            }
        });

        rooms.value = (data.rooms || []).map(r => ({
            id: String(r.id),
            name: r.name,
            avatar: r.avatar,
            unread: Number(r.unread || 0),
            lastMessage: r.lastMessage || 'No messages yet'
        }));

        // If list is not empty, select first
        if (rooms.value.length > 0) {
            selectRoom(rooms.value[0].id);
        } else {
            // Clear view if no rooms
            activeRoomId.value = null;
        }
    } catch (e) {
        console.error("Error fetching rooms:", e);
    } finally {
        loadingRooms.value = false;
    }
}

// --- REVERB WEBSOCKET LOGIC ---

function joinChannel(roomId) {
    // 1. Safety check: Leave old channel
    leaveChannel(activeRoomId.value);

    if (!window.Echo) {
        console.error("Laravel Echo is not configured.");
        return;
    }

    console.log(`🔌 Joining Reverb channel: room.${roomId}`);

    // 2. Listen to Private Channel
    window.Echo.private(`room.${roomId}`)
        .listen('.message.new', (e) => {
            console.log('📩 Reverb Message:', e);

            // e contains exactly what we returned in broadcastWith()
            // { text: "Hello", role: "ai", timestamp: "..." }

            if (elementRef.value) {
                // 3. Inject directly into DeepChat
                elementRef.value.pushNewMessage(e);
            }
        })
        .error((error) => {
            console.error('Reverb Error:', error);
        });
}

function leaveChannel(roomId) {
    if (window.Echo && roomId) {
        window.Echo.leave(`room.${roomId}`);
    }
}

// --- Handler: Select Room ---
async function selectRoom(id) {
    if (activeRoomId.value === id) return;

    // Leave old
    if (activeRoomId.value) leaveChannel(activeRoomId.value);

    activeRoomId.value = id;

    // Load history via API (Rest)
    await fetchMessages(id);

    // Listen for new messages (Reverb)
    joinChannel(id);
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

async function createRoom() {
    if (!newRoomName.value.trim()) return;

    const tempId = `new-${Date.now()}`;
    const name = newRoomName.value;

    try {
        // 1. OPTIONAL: Call your backend to create the room strictly
        // const { data } = await axios.post(props.routes.createRoom, { name });
        // const finalId = data.room.id;

        // 2. FOR NOW: Optimistic Local Update
        const newRoom = {
            id: tempId, // Replace with finalId if using API
            name: name,
            avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random`,
            unread: 0,
            lastMessage: 'Room created'
        };

        // Add to top of list
        rooms.value.unshift(newRoom);

        // Select it immediately
        selectRoom(newRoom.id);

        // Reset Modal
        newRoomName.value = '';
        showCreateModal.value = false;

    } catch (e) {
        console.error("Failed to create room", e);
        alert("Error creating room");
    }
}

// --- DeepChat Configuration ---
const connectConfig = {
    handler: async (body, signals) => {
        // 'body' contains the message the user just typed: { messages: [{ text: 'Hello' }] }
        const userMessageText = body.messages[0].text;
        const currentId = activeRoomId.value;

        if (!currentId) {
            signals.onResponse({ error: 'No room selected' });
            return;
        }

        try {
            // Send to Backend
            const { data } = await axios.post(props.routes.sendMessage, {
                roomId: currentId,
                message: userMessageText
            });

            // If backend returns a formatted message object, great. 
            // If not, DeepChat has already displayed the user's message optimistically.
            // We just need to tell DeepChat the transfer is done.
            signals.onResponse({});

            // NOTE: If your backend replies immediately with an auto-response, 
            // you would pass it here: signals.onResponse({ text: data.reply });

        } catch (e) {
            console.error("Send failed", e);
            signals.onResponse({ error: 'Failed to send message' });
        }
    }
};

// --- Helper: Normalize Data ---
function normalizeMessageForDeepChat(row) {
    // 1. Check if backend sent 'text' (New Controller) OR 'message' (Raw DB)
    const content = row.text || row.message || '';

    // 2. Check if backend sent 'role' directly
    let role = row.role;

    // 3. Fallback: Calculate role if missing (Old DB rows)
    if (!role) {
        const dir = String(row.direction || '').toLowerCase();
        const isOutbound = ['out', 'outbound', 'outgoing'].includes(dir);
        role = isOutbound ? 'user' : 'ai';
    }

    return {
        text: content,
        role: role,
        // Optional: formatting
        // timestamp: row.timestamp 
    };
}

// Cleanup
onBeforeUnmount(() => {
    if (activeRoomId.value) leaveChannel(activeRoomId.value);
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