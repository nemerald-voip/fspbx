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
                    <Multiselect v-model="selectedExtension" :options="extensionList" :searchable="true"
                        :close-on-select="true" :show-labels="false" placeholder="Select Extension" label="name"
                        track-by="value" class="custom-multiselect" @select="onExtensionChange" />
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

                <deep-chat ref="elementRef"  :history="currentHistory" :connect="connectConfig"
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
        </div>

        <!-- VueForm CREATE ROOM MODAL -->
        <div v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-2xl w-96 p-6 transform transition-all scale-100">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Start New Conversation</h3>

                <!-- VueForm Component -->
                <Vueform :endpoint="false" :schema="createRoomSchema" @submit="handleCreateRoom" />

                <!-- Close Button (Optional, if not included in form actions) -->
                <div class="mt-4 flex justify-center">
                    <button @click="showCreateModal = false"
                        class="text-sm text-gray-400 hover:text-gray-600">Cancel</button>
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
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.css'
import Pusher from 'pusher-js';

// --- Props (from Laravel/Inertia) ---
const props = defineProps({
    routes: { type: Object, required: true },
})

// --- State ---
const data = ref([]);
const activeRoomId = ref(null);
const rooms = ref([]);
const loadingRooms = ref(false);
const currentHistory = ref([]); // Messages for the active room
const showCreateModal = ref(false);
const newRoomName = ref('');
const currentExtensionUuid = ref(null);
const selectedExtension = ref(null); // <--- Holds the full {name, value} object
const notificationType = ref(null);
const notificationMessages = ref(null);
const notificationShow = ref(null);

// Reference to DeepChat element to call methods directly
const elementRef = ref(null);

// DIDs State (Populated when extension changes)
const myDids = ref([]);

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
    Pusher.logToConsole = true; 

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
            const defaultId = data.value.extension_uuid;

            // Find the extension object in the list
            const exists = (data.value.extensions || []).find(e => e.value === defaultId);

            if (exists) {
                // 1. Set the UUID
                currentExtensionUuid.value = defaultId;

                // 2. Set the Multiselect Object
                selectedExtension.value = exists;

                // 3. CRITICAL: Populate DIDs for the "From" dropdown
                if (exists.dids) myDids.value = exists.dids;

            } else if (data.value.extensions?.length > 0) {
                // Fallback: Select the first one available
                const firstExt = data.value.extensions[0];
                currentExtensionUuid.value = firstExt.value;
                selectedExtension.value = firstExt;
                if (firstExt.dids) myDids.value = firstExt.dids;
            }
        }
    } catch (error) {
        handleErrorResponse(error);
    }
}

// When user selects from Dropdown
const onExtensionChange = (selectedOption) => {
    // 1. Update the string UUID
    currentExtensionUuid.value = selectedOption ? selectedOption.value : null;

    // 2. CRITICAL: Update the DIDs list for the "Create Room" form
    if (selectedOption && selectedOption.dids) {
        myDids.value = selectedOption.dids;
    } else {
        myDids.value = [];
    }

    // 3. Clear Chat & Refresh Rooms
    activeRoomId.value = null;
    currentHistory.value = [];
    fetchRooms();
};

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
    leaveChannel(activeRoomId.value);

    if (!window.Echo) return;

    const channelId = roomId.replace(/\+/g, '');

    console.log(`🔌 Joining Reverb channel: room.${channelId}`);

    window.Echo.private(`room.${channelId}`)
        .listen('.message.new', (e) => {
            console.log('✅ LISTENER FIRED:', e);

            // 1. Skip my own messages (Optimistic UI handled them)
            if (e.role === 'user') return;

            // 2. USE SIGNALS INSTEAD OF ELEMENT REF
            if (deepChatSignals) {
                console.log('Injecting via Signals...');
                
                // signals.onResponse() injects the message into the chat UI
                // e contains { text: "...", role: "ai", timestamp: "..." }
                deepChatSignals.onResponse(e); 
            } else {
                console.error('❌ DeepChat Signals not initialized yet');
            }
        })
        .error((error) => {
            console.error('Reverb Subscription Error:', error);
        });
}

function leaveChannel(roomId) {
    if (window.Echo && roomId) {
        // FIX: Strip '+' here too so we leave the correct channel
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
            } catch (e) {
                console.error("Send failed", e);
                // Optional: Notify user of failure
                // signals.onResponse({ error: "Failed to send" });
            }
        };
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

<style>
.multiselect__content-wrapper {
    z-index: 50 !important;
}

/* Optional: Match your Tailwind styles closer */
.custom-multiselect .multiselect__tags {
    min-height: 42px;
    padding-top: 10px;
    border-radius: 0.5rem;
    /* rounded-lg */
    border-color: #d1d5db;
    /* border-gray-300 */
}
</style>