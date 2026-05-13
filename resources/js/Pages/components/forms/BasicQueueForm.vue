<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="emit('close')">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            class="relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">
                            <DialogTitle as="h3" class="mb-4 pr-8 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <button type="button"
                                class="absolute right-4 top-4 rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="emit('close')">
                                <span class="sr-only">Close</span>
                                <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                            </button>

                            <div v-if="loading" class="py-10 text-center text-sm text-gray-500">Loading...</div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :float-placeholders="false" :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="settings" label="Settings" :elements="[
                                                    'queue_name',
                                                    'queue_extension',
                                                    'queue_strategy',
                                                    'queue_greeting',
                                                    'placeholder',
                                                    'placeholder2',
                                                    'queue_greeting_action_buttons',
                                                    'queue_moh_sound',
                                                    'queue_tier_rules_apply',
                                                    'queue_description',
                                                    'settings_submit',
                                                ]" />
                                                <FormTab name="agents" label="Agents" :elements="[
                                                    'tiers_header',
                                                    'selected_agents',
                                                    'tiers',
                                                    'tiers_submit',
                                                ]" />
                                                <FormTab name="advanced" label="Advanced" :elements="[
                                                    'advanced_header',
                                                    'queue_max_wait_time',
                                                    'queue_max_wait_time_with_no_agent',
                                                    'queue_cid_prefix',
                                                    'timeout_action',
                                                    'timeout_target',
                                                    'placeholder3',
                                                    'advanced_submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div
                                            class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <TextElement name="queue_name" label="Name" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="queue_extension" label="Extension" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <SelectElement name="queue_strategy" label="Strategy" :native="false"
                                                    :items="strategyOptions" :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="placeholder" />

                                                <SelectElement name="queue_greeting" label="Greeting"
                                                    :items="fetchGreetings" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" placeholder="Select greeting"
                                                    :strict="false" allow-absent :columns="{ sm: { container: 6 } }">
                                                    <template #after>
                                                        <span v-if="greetingTranscription" class="text-xs italic">
                                                            "{{ greetingTranscription }}"
                                                        </span>
                                                    </template>
                                                </SelectElement>

                                                <GroupElement name="queue_greeting_action_buttons"
                                                    :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isAudioPlaying" @click="playGreeting"
                                                        :columns="{ container: 2 }" name="play_button" label="&nbsp;"
                                                        :secondary="true" :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="buttonIconClassOverrides">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 rounded-full py-1 text-blue-400 ring-1 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>
                                                    <ButtonElement v-if="isAudioPlaying" @click="pauseGreeting"
                                                        name="pause_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }"
                                                        :remove-classes="buttonIconClassOverrides">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 rounded-full py-1 text-red-400 ring-1 transition duration-500 ease-in-out hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>
                                                    <ButtonElement v-if="!isDownloading" @click="downloadGreeting"
                                                        name="download_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }" :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="buttonIconClassOverrides">
                                                        <CloudArrowDownIcon
                                                            class="h-8 w-8 shrink-0 rounded-full py-1 text-blue-400 ring-1 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>
                                                    <ButtonElement v-if="isDownloading" name="download_spinner_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="buttonIconClassOverrides">
                                                        <Spinner :show="true"
                                                            class="ml-0 mr-0 h-8 w-8 shrink-0 rounded-full py-1 text-blue-400 ring-1 transition duration-500 ease-in-out" />
                                                    </ButtonElement>
                                                    <ButtonElement @click="editGreeting" name="edit_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="buttonIconClassOverrides">
                                                        <PencilSquareIcon
                                                            class="h-8 w-8 shrink-0 rounded-full py-1 text-blue-400 ring-1 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>
                                                    <ButtonElement @click="deleteGreeting" name="delete_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableGreeting]"
                                                        :remove-classes="buttonIconClassOverrides">
                                                        <TrashIcon
                                                            class="h-8 w-8 shrink-0 rounded-full py-1 text-red-400 ring-1 transition duration-500 ease-in-out hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>
                                                    <ButtonElement @click="handleNewGreetingButtonClick"
                                                        name="add_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }"
                                                        :remove-classes="buttonIconClassOverrides">
                                                        <PlusIcon
                                                            class="h-8 w-8 shrink-0 rounded-full py-1 text-blue-400 ring-1 transition duration-500 ease-in-out hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>

                                                <SelectElement name="queue_moh_sound" label="Music on Hold"
                                                    :items="musicOnHoldOptions" :groups="true"
                                                    default="local_stream://default" :search="true" :native="false"
                                                    input-type="search" autocomplete="off" :strict="false" allow-absent
                                                    :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="placeholder2" />

                                                <ToggleElement name="queue_tier_rules_apply" text="Tier Rules"
                                                    true-value="true" false-value="false"
                                                    :labels="{ on: 'On', off: 'Off' }" label="&nbsp;"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextareaElement name="queue_description" label="Description"
                                                    :rows="2" />

                                                <ButtonElement name="settings_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <StaticElement name="tiers_header" tag="h4" content="Queue Agents"
                                                    description="Assign agents to this queue and order them by level and position." />

                                                <TagsElement name="selected_agents" :close-on-select="true"
                                                    :items="availableAgentOptions" :search="true" :groups="true"
                                                    :native="false" label="Add Agent(s)" input-type="search"
                                                    autocomplete="off" placeholder="Search by name or ID"
                                                    :floating="false" :hide-selected="false" :object="true"
                                                    :group-hide-empty="true" :append-new-option="false" :submit="false"
                                                    @select="handleAgentSelect"
                                                    description="Pick from the list to add an agent to this queue." />

                                                <ListElement name="tiers" :initial="0" :sort="true" size="sm"
                                                    :controls="{ add: false, remove: true, sort: true }"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-sm' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <HiddenElement name="call_center_agent_uuid" :meta="true" />
                                                            <HiddenElement name="agent_label" :meta="true" />
                                                            <StaticElement name="agent_label_display" tag="div"
                                                                :columns="{ default: { container: 12 }, sm: { container: 6 } }"
                                                                label="Agent" :content="(el$) => {
                                                                    const label = getAgentLabel(
                                                                        el$.parent.value.call_center_agent_uuid,
                                                                        el$.parent.value.agent_label,
                                                                    );
                                                                    return `<span class='text-base font-semibold'>${label}</span>`;
                                                                }" />
                                                            <SelectElement name="tier_level" :items="tierOptions"
                                                                :search="true" :native="false" label="Level"
                                                                input-type="search" autocomplete="off"
                                                                :columns="{ sm: { container: 3 } }" size="sm" />
                                                            <SelectElement name="tier_position" :items="tierOptions"
                                                                :search="true" :native="false" label="Position"
                                                                input-type="search" autocomplete="off"
                                                                :columns="{ sm: { container: 3 } }" size="sm" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <ButtonElement name="tiers_submit" button-label="Save" :submits="true"
                                                    align="right" />

                                                <StaticElement name="advanced_header" tag="h4"
                                                    content="Advanced Settings" />

                                                <TextElement name="queue_max_wait_time" input-type="number"
                                                    label="Max Wait Time" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="queue_max_wait_time_with_no_agent"
                                                    input-type="number" label="Max Wait No Agent" :floating="false"
                                                    :columns="{ sm: { container: 6 } }" />

                                                <TextElement name="queue_cid_prefix" label="Caller ID Prefix"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <GroupElement name="placeholder3" />

                                                <SelectElement name="timeout_action" :items="routingTypes"
                                                    label-prop="name" :search="true" :native="false"
                                                    label="Timeout Action" input-type="search" autocomplete="off"
                                                    placeholder="Choose Action" :floating="false" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" @change="(newValue, oldValue, el$) => {
                                                        const timeoutTarget = el$.form$.el$('timeout_target');

                                                        if (oldValue !== null && oldValue !== undefined) {
                                                            timeoutTarget.clear();
                                                        }

                                                        timeoutTarget.updateItems();
                                                    }" />

                                                <SelectElement name="timeout_target" :items="async (query, input) => {
                                                    const timeoutAction = input.$parent.el$.form$.el$('timeout_action');

                                                    try {
                                                        const response = await timeoutAction.$vueform.services.axios.post(
                                                            props.options.routes.get_routing_options,
                                                            { category: timeoutAction.value },
                                                        );

                                                        return response.data.options;
                                                    } catch (error) {
                                                        emit('error', error);
                                                        return [];
                                                    }
                                                }" :search="true" label-prop="name" :native="false" label="Target"
                                                    input-type="search" allow-absent :object="true"
                                                    :format-data="formatTarget" autocomplete="off"
                                                    placeholder="Choose Target" :floating="false" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" :conditions="[
                                                        ['timeout_action', 'not_empty'],
                                                        ['timeout_action', 'not_in', ['check_voicemail', 'company_directory', 'hangup']]
                                                    ]" />

                                                <ButtonElement name="advanced_submit" button-label="Save"
                                                    :submits="true" align="right" />

                                                <UpdateGreetingModal :greeting="greetingLabel" :show="showEditModal"
                                                    :loading="isGreetingUpdating" @confirm="handleGreetingUpdate"
                                                    @close="showEditModal = false" />
                                                <NewGreetingForm header="New Greeting Message"
                                                    :show="showNewGreetingModal" :voices="options?.voices"
                                                    :speeds="options?.speeds" :default_voice="options?.default_voice"
                                                    :phone_call_instructions="options?.phone_call_instructions"
                                                    :sample_message="options?.sample_message"
                                                    :routes="getRoutesForGreetingForm"
                                                    @close="showNewGreetingModal = false" @error="emit('error', $event)"
                                                    @success="showNotificationFromChild"
                                                    @saved="handleNewGreetingAdded" />
                                                <ConfirmationModal :show="showGreetingDeleteConfirmationModal"
                                                    @close="showGreetingDeleteConfirmationModal = false"
                                                    @confirm="confirmGreetingDeleteAction" header="Confirm Deletion"
                                                    text="This action will permanently delete this greeting. Are you sure you want to proceed?"
                                                    confirm-button-label="Delete" cancel-button-label="Cancel" />
                                            </FormElements>
                                        </div>
                                    </div>
                                </template>
                            </Vueform>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>

    </TransitionRoot>
</template>

<script setup>
import { computed, ref } from "vue";
import axios from "axios";
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from "@headlessui/vue";
import { XMarkIcon } from "@heroicons/vue/24/solid";
import { PlusIcon, TrashIcon } from "@heroicons/vue/24/outline";
import { CloudArrowDownIcon, PauseCircleIcon, PlayCircleIcon } from "@heroicons/vue/24/solid";
import { PencilSquareIcon } from "@heroicons/vue/20/solid";
import ConfirmationModal from "../modal/ConfirmationModal.vue";
import NewGreetingForm from "./NewGreetingForm.vue";
import Spinner from "../general/Spinner.vue";
import UpdateGreetingModal from "../modal/UpdateGreetingModal.vue";

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: String,
    mode: {
        type: String,
        default: "create",
    },
});

const emit = defineEmits(["close", "error", "success", "refresh-data"]);
const form$ = ref(null);
const availableGreetings = ref(null);
const currentAudio = ref(null);
const currentAudioGreeting = ref(null);
const greetingLabel = ref(null);
const isAudioPlaying = ref(false);
const isDownloading = ref(false);
const isGreetingUpdating = ref(false);
const showEditModal = ref(false);
const showGreetingDeleteConfirmationModal = ref(false);
const showNewGreetingModal = ref(false);

const buttonIconClassOverrides = {
    ButtonElement: {
        button_secondary: ["form-bg-btn-secondary"],
        button: ["form-border-width-btn"],
        button_enabled: ["focus:form-ring"],
        button_md: ["form-p-btn"],
    },
};

const strategyOptions = [
    { value: "ring-all", label: "Ring All" },
    { value: "longest-idle-agent", label: "Longest Idle Agent" },
    { value: "round-robin", label: "Round Robin" },
    { value: "top-down", label: "Top Down" },
    { value: "agent-with-least-talk-time", label: "Least Talk Time" },
    { value: "agent-with-fewest-calls", label: "Fewest Calls" },
    { value: "sequentially-by-agent-order", label: "Sequential Agent Order" },
    { value: "random", label: "Random" },
];

const tierOptions = Array.from({ length: 20 }, (_, i) => {
    const value = String(i + 1);
    return { value, label: value };
});

const defaultValues = computed(() => ({
    queue_name: props.options?.item?.queue_name ?? null,
    queue_extension: props.options?.item?.queue_extension ?? null,
    queue_strategy: props.options?.item?.queue_strategy ?? "ring-all",
    queue_greeting: props.options?.item?.queue_greeting || "disabled",
    queue_moh_sound: props.options?.item?.queue_moh_sound ?? "local_stream://default",
    queue_max_wait_time: props.options?.item?.queue_max_wait_time ?? 0,
    queue_max_wait_time_with_no_agent: props.options?.item?.queue_max_wait_time_with_no_agent ?? 90,
    queue_tier_rules_apply: props.options?.item?.queue_tier_rules_apply ?? "false",
    queue_cid_prefix: props.options?.item?.queue_cid_prefix ?? null,
    timeout_action: props.options?.item?.timeout_action ?? null,
    timeout_target: {
        value: props.options?.item?.timeout_target_uuid ?? null,
        extension: props.options?.item?.timeout_target_extension ?? null,
        name: props.options?.item?.timeout_target_name ?? null,
    },
    queue_description: props.options?.item?.queue_description ?? null,
    tiers: (props.options?.tiers ?? []).map((tier) => ({
        call_center_agent_uuid: tier.call_center_agent_uuid,
        agent_label: tier.agent_label || tier.agent_name || null,
        tier_level: String(tier.tier_level ?? 0),
        tier_position: String(tier.tier_position ?? 0),
    })),
}));

const agentOptions = computed(() => props.options?.agent_options ?? []);
const routingTypes = computed(() => props.options?.routing_types ?? []);
const musicOnHoldOptions = computed(() => props.options?.music_on_hold_options ?? []);
const greetingTranscription = computed(() => {
    const selectedId = form$.value?.data?.queue_greeting ?? null;

    if (!selectedId || !availableGreetings.value) {
        return null;
    }

    const selectedItem = availableGreetings.value.find(
        (item) => String(item.value) === String(selectedId),
    );

    return selectedItem?.description || null;
});

const availableAgentOptions = computed(() => {
    const tiersField = form$.value?.el$("tiers");
    const currentTiers = tiersField?.value || defaultValues.value.tiers || [];
    const selectedAgentUuids = currentTiers.map((tier) => tier.call_center_agent_uuid).filter(Boolean);

    return [
        {
            label: "Agents",
            items: agentOptions.value.filter((agent) => !selectedAgentUuids.includes(agent.value)),
        },
    ];
});

const handleAgentSelect = (option) => {
    const currentTiers = form$.value?.el$("tiers")?.value || [];

    form$.value.update({
        tiers: [
            ...currentTiers,
            {
                call_center_agent_uuid: option.value,
                agent_label: option.label,
                tier_level: "0",
                tier_position: "0",
            },
        ],
    });

    form$.value.el$("selected_agents").update([]);
};

const getAgentLabel = (agentUuid, fallback = null) => {
    const agent = agentOptions.value.find((option) => option.value === agentUuid);

    return agent?.label || fallback || agentUuid || "Agent";
};

const fetchGreetings = async () => {
    const route = props.options?.routes?.greeting_route;

    if (!route) {
        availableGreetings.value = [{ value: "disabled", label: "No greeting" }];
        return availableGreetings.value;
    }

    try {
        const response = await axios.get(route);
        availableGreetings.value = [
            { value: "disabled", label: "No greeting" },
            ...(response.data || []),
        ];
        return availableGreetings.value;
    } catch (error) {
        emit("error", error);
        availableGreetings.value = [{ value: "disabled", label: "No greeting" }];
        return availableGreetings.value;
    }
};

const getSelectedGreetingFileName = () => {
    return form$.value?.data?.queue_greeting ?? null;
};

const hasPlayableGreeting = (form$) => {
    const val = form$?.el$("queue_greeting")?.value ?? null;

    return val !== "disabled" && val !== "0" && val !== "-1" && val !== null && val !== "";
};

const showNotification = (type, messages = null) => {
    emit("success", type, messages);
};

const showNotificationFromChild = (type, messages = null) => {
    if (typeof type === "string") {
        showNotification(type, messages);
        return;
    }

    showNotification("success", type);
};

const handleNewGreetingButtonClick = () => {
    stopGreetingAudio();
    showNewGreetingModal.value = true;
};

const playGreeting = () => {
    const greeting = getSelectedGreetingFileName();

    if (!greeting || !props.options?.routes?.serve_greeting_route) {
        return;
    }

    if (currentAudio.value && currentAudioGreeting.value === greeting) {
        if (currentAudio.value.paused) {
            currentAudio.value.play();
            isAudioPlaying.value = true;
        }
        return;
    }

    stopGreetingAudio();

    const fileUrl = props.options.routes.serve_greeting_route.replace(":file_name", encodeURIComponent(greeting));

    currentAudio.value = new Audio(fileUrl);
    currentAudioGreeting.value = greeting;
    isAudioPlaying.value = true;

    currentAudio.value.play().catch(() => {
        isAudioPlaying.value = false;
        showNotification("error", { request: ["Audio playback failed"] });
    });

    currentAudio.value.addEventListener("ended", () => {
        isAudioPlaying.value = false;
    });

    currentAudio.value.addEventListener("error", () => {
        isAudioPlaying.value = false;
        showNotification("error", { request: ["File not found or failed to load audio"] });
    });
};

const pauseGreeting = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
};

const stopGreetingAudio = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        currentAudio.value.currentTime = 0;
        currentAudio.value = null;
    }

    isAudioPlaying.value = false;
    currentAudioGreeting.value = null;
};

const downloadGreeting = () => {
    isDownloading.value = true;
    const greeting = getSelectedGreetingFileName();

    if (!greeting || !props.options?.routes?.serve_greeting_route) {
        isDownloading.value = false;
        return;
    }

    const downloadUrl = props.options.routes.serve_greeting_route.replace(":file_name", encodeURIComponent(greeting))
        + `?download=true&v=${Date.now()}`;

    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = greeting || "greeting.wav";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    isDownloading.value = false;
};

const editGreeting = () => {
    const selectedId = getSelectedGreetingFileName();

    if (!selectedId || !availableGreetings.value) {
        return;
    }

    const selectedItem = availableGreetings.value.find(
        (item) => String(item.value) === String(selectedId),
    );

    if (selectedItem) {
        greetingLabel.value = selectedItem;
        showEditModal.value = true;
    }
};

const handleGreetingUpdate = async (updatedGreeting) => {
    const newName = updatedGreeting?.label?.trim();

    if (!newName) {
        showNotification("error", { request: ["Greeting name cannot be empty."] });
        return;
    }

    isGreetingUpdating.value = true;

    try {
        const response = await axios.post(props.options.routes.update_greeting_route, {
            file_name: updatedGreeting.value,
            new_name: updatedGreeting.label,
        });

        if (response.data.success) {
            form$.value.el$("queue_greeting").clear();
            await form$.value.el$("queue_greeting").updateItems();
            form$.value.update({ queue_greeting: updatedGreeting.value });
            showNotification("success", response.data.messages);
        }
    } catch (error) {
        emit("error", error);
    } finally {
        isGreetingUpdating.value = false;
        showEditModal.value = false;
    }
};

const deleteGreeting = () => {
    stopGreetingAudio();
    showGreetingDeleteConfirmationModal.value = true;
};

const confirmGreetingDeleteAction = async () => {
    const fileName = getSelectedGreetingFileName();

    if (!fileName) {
        showGreetingDeleteConfirmationModal.value = false;
        return;
    }

    try {
        const response = await axios.post(props.options.routes.delete_greeting_route, { file_name: fileName });

        if (response.data.success) {
            stopGreetingAudio();

            if (availableGreetings.value) {
                availableGreetings.value = availableGreetings.value.filter(
                    (greeting) => String(greeting.value) !== String(fileName),
                );
            }

            form$.value.update({ queue_greeting: "disabled" });
            await form$.value.el$("queue_greeting").updateItems();
            showNotification("success", response.data.messages);
        }
    } catch (error) {
        emit("error", error);
    } finally {
        showGreetingDeleteConfirmationModal.value = false;
    }
};

const handleNewGreetingAdded = async (greetingId) => {
    await form$.value.el$("queue_greeting").updateItems();
    form$.value.update({ queue_greeting: greetingId });
    showNewGreetingModal.value = false;
};

const getRoutesForGreetingForm = computed(() => ({
    ...props.options?.routes,
    text_to_speech_route: props.options?.routes?.text_to_speech_route ?? null,
    upload_greeting_route: props.options?.routes?.upload_greeting_route ?? null,
}));

const formatTarget = (name, value) => {
    return { [name]: value?.extension ?? null };
};

const submitForm = async (FormData, form$) => {
    const requestData = form$.requestData;
    const route = props.mode === "create"
        ? props.options.routes.store_route
        : props.options.routes.update_route;

    return props.mode === "create"
        ? await form$.$vueform.services.axios.post(route, requestData)
        : await form$.$vueform.services.axios.put(route, requestData);
};

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear();
    if (el$.children$) {
        Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$));
    }
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => clearErrorsRecursive(el$));

    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0]);
            }
        });
    }
};

const handleSuccess = (response) => {
    emit("success", "success", response.data.messages);
    emit("refresh-data");
    emit("close");
};

const handleError = (error, details, form$) => {
    form$.messageBag.clear();

    if (details.type === "submit") {
        emit("error", error);
        return;
    }

    form$.messageBag.append("Could not submit form");
};
</script>
