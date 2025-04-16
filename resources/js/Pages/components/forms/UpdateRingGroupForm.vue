<template>
    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
        <aside class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
            <nav class="space-y-1">
                <a v-for="item in localOptions.navigation" :key="item.name" href="#"
                    :class="[activeTab === item.slug ? 'bg-gray-200 text-indigo-700 hover:bg-gray-100 hover:text-indigo-700' : 'text-gray-900 hover:bg-gray-200 hover:text-gray-900', 'group flex items-center rounded-md px-3 py-2 text-sm font-medium']"
                    @click.prevent="setActiveTab(item.slug)" :aria-current="item.current ? 'page' : undefined">
                    <component :is="iconComponents[item.icon]"
                        :class="[item.current ? 'text-indigo-500 group-hover:text-indigo-500' : 'text-gray-400 group-hover:text-gray-500', '-ml-1 mr-3 h-6 w-6 flex-shrink-0']"
                        aria-hidden="true" />
                    <span class="truncate">{{ item.name }}</span>
                    <ExclamationCircleIcon v-if="((errors?.voicemail_id || errors?.voicemail_password) && item.slug === 'settings') ||
                        (errors?.voicemail_alternate_greet_id && item.slug === 'advanced')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <div v-if="activeTab === 'settings'">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">

                        <Vueform ref="form$">
                            <StaticElement name="h4" tag="h4" content="Settings"
                                description="Provide basic information about the ring group" />
                            <TextElement name="name" label="Name" :columns="{
                                sm: {
                                    container: 6,
                                },
                                lg: {
                                    container: 6,
                                },
                            }" placeholder="Enter Ring Group Name" :floating="false" />
                            <TextElement name="extension" :columns="{
                                sm: {
                                    container: 6,
                                },
                                lg: {
                                    container: 6,
                                },
                            }" label="Extension" placeholder="Enter Extension" :floating="false" />


                            <SelectElement name="greeting" :search="true" :native="false" label="Greeting"
                                :items="localOptions.greetings" input-type="search" autocomplete="off"
                                placeholder="Select Greeting" :floating="false" :object="true"
                                info="Enable this option so that callers hear a recorded greeting before they are connected to a group member."
                                :strict="false" :columns="{
                                    sm: {
                                        container: 6,
                                    },
                                    lg: {
                                        container: 6,
                                    },
                                }" >
                                <template #after>
                                    <span v-if="greetingTranscription" class="text-xs italic">
                                        "{{greetingTranscription}}"
                                    </span>
                                    
                                    
                                </template>
                                </SelectElement>

                            <ButtonElement v-if="!isAudioPlaying" @click="playGreeting" name="play_button" label="&nbsp;"
                                :secondary="true" :columns="{
                                    sm: {
                                        container: 1,
                                    },
                                    lg: {
                                        container: 1,
                                    },
                                    default: {
                                        container: 2,
                                    },
                                }" :conditions="[['greeting', '!=', null], ['greeting', '!=', '']]"
                                :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                <PlayCircleIcon
                                    class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                            </ButtonElement>


                            <ButtonElement v-if="isAudioPlaying" @click="pauseGreeting" name="pause_button" label="&nbsp;"
                                :secondary="true" :columns="{
                                    container: 1,
                                }"
                                :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                <PauseCircleIcon
                                    class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                            </ButtonElement>

                            <ButtonElement v-if="!isDownloading" @click="downloadGreeting" name="download_button"
                                label="&nbsp;" :secondary="true" :columns="{
                                    sm: {
                                        container: 1,
                                    },
                                    lg: {
                                        container: 1,
                                    },
                                    default: {
                                        container: 2,
                                    },
                                }" :conditions="[['greeting', '!=', null], ['greeting', '!=', '']]"
                                :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                <CloudArrowDownIcon
                                    class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                            </ButtonElement>

                            <ButtonElement v-if="isDownloading" name="download_spinner_button" label="&nbsp;"
                                :secondary="true" :columns="{
                                    sm: {
                                        container: 1,
                                    },
                                    lg: {
                                        container: 1,
                                    },
                                    default: {
                                        container: 2,
                                    },
                                }"
                                :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                <Spinner :show="true"
                                    class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                            </ButtonElement>

                            <ButtonElement @click="editGreeting" name="edit_button" label="&nbsp;" :secondary="true"
                                :columns="{
                                    sm: {
                                        container: 1,
                                    },
                                    lg: {
                                        container: 1,
                                    },
                                    default: {
                                        container: 2,
                                    },
                                }" :conditions="[['greeting', '!=', null], ['greeting', '!=', '']]"
                                :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                <PencilSquareIcon
                                    class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                            </ButtonElement>

                            <ButtonElement @click="deleteGreeting" name="delete_button" label="&nbsp;" :secondary="true"
                                :columns="{
                                    sm: {
                                        container: 1,
                                    },
                                    lg: {
                                        container: 1,
                                    },
                                    default: {
                                        container: 2,
                                    },
                                }" :conditions="[['greeting', '!=', null], ['greeting', '!=', '']]"
                                :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                <TrashIcon
                                    class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                            </ButtonElement>

                            <ButtonElement @click="handleNewGreetingButtonClick" name="add_button" label="&nbsp;"
                                :secondary="true" :columns="{
                                    container: 1,
                                }" :conditions="[['greeting', '==', null]]"
                                :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                <PlusIcon
                                    class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                            </ButtonElement>

                            <!-- <StaticElement name="p" :content="(el$) => {
                                            return el$.parent.value.greeting;
                                        }" :conditions="[
                                [
                                    'greeting',
                                    'not_empty',
                                ],
                            ]" /> -->


                            <SelectElement name="call_distribution" :search="true" :native="false" label="Call Distribution"
                                :items="localOptions.call_distributions" input-type="search" autocomplete="off"
                                placeholder="Select Call Distribution" :floating="false" info="Advanced (default): This option rings all phones at once, but each phone has its own thread. This is especially useful when there are multiple registrations for the same extension.
Sequential Ring: This option rings one phone at a time in a specific order.
Simultaneous Ring: This option rings all phones at once.
Random Ring: This option rings one phone at a time in a random order.
Rollover: This option rings each phone one at a time, but it skips busy phones." :strict="false" :columns="{
    sm: {
        container: 6,
    },
    lg: {
        container: 6,
    },
}" />



                            <GroupElement name="container_3" />
                            <StaticElement name="divider" tag="hr" />
                            <GroupElement name="container_4" />

                            <StaticElement name="h3_1" tag="h4" content="Members"
                                description="Add and remove users of this ring group" />

                            <TagsElement name="selectedMembers" :close-on-select="false" :items="availableMembers"
                                :create="true" :search="true" :groups="true" :native="false" label="Add Member(s)"
                                input-type="search" autocomplete="off" placeholder="Search by name or extension"
                                :floating="false" :hide-selected="false" :object="true" :group-hide-empty="true"
                                :append-new-option="false"
                                description="Choose from the list of available options or enter an external number manually." />

                            <ButtonElement @click="addSelectedMembers" name="secondaryButton_1"
                                button-label="Add Selected Members" :secondary="true" align="center" :full="false" />

                            <GroupElement name="container_1" />
                            <GroupElement name="container_2" />

                            <ListElement name="members" :sort="true" :controls="{ add: false, }"
                                :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                <template #default="{ index }">
                                    <ObjectElement :name="index">
                                        <HiddenElement name="uuid" :meta="true" />
                                        <HiddenElement name="destination" :meta="true" />
                                        <StaticElement name="p_1" tag="p" :content="(el$) => {
                                            // Retrieve the extension value (stored in a hidden field or member object)
                                            const num = el$.parent.value.destination;
                                            return getMemberLabel(num);
                                        }" :columns="{ default: { container: 8, }, sm: { container: 4, }, }"
                                            label="Member" :attrs="{ class: 'text-base font-semibold' }" />

                                        <SelectElement name="delay" :items="delayOptions" :search="true" :native="false"
                                            label="Delay" input-type="search" allow-absent autocomplete="off" :columns="{
                                                default: {
                                                    container: 6,
                                                },
                                                sm: {
                                                    container: 4,
                                                },
                                            }" size="sm"
                                            info="How many seconds to wait before starting to ring this member."
                                            placeholder="Select option" :floating="false" />
                                        <SelectElement name="timeout" :items="timeoutOptions" :search="true" :native="false"
                                            label="Ring for" input-type="search" allow-absent autocomplete="off" :columns="{
                                                default: {
                                                    container: 6,
                                                },
                                                sm: {
                                                    container: 4,
                                                },
                                            }" size="sm"
                                            info="How many seconds to keep ringing this member before giving up."
                                            placeholder="Select option" :floating="false" />
                                        <GroupElement name="container" :columns="{
                                            default: {
                                                container: 12,
                                            },
                                            sm: {
                                                container: 4,
                                            },
                                        }" />
                                        <ToggleElement name="prompt" :columns="{
                                            default: {
                                                container: 6,
                                            },
                                            sm: {
                                                container: 4,
                                            },
                                        }" align="left" label="Confirm Answer" size="sm"
                                            info="Enable answer confirmation to prevent voicemails and automated systems from answering a call." />
                                        <ToggleElement name="enabled" :columns="{
                                            default: {
                                                container: 5,
                                            },
                                            sm: {
                                                container: 4,
                                            },
                                        }" size="sm" label="Active" />
                                        <!-- <StaticElement name="divider_1" tag="hr" /> -->
                                    </ObjectElement>
                                </template>
                            </ListElement>
                        </Vueform>
                    </div>
                </div>

            </div>



            <div v-if="activeTab === 'advanced'" action="#" method="POST">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Advanced</h3>
                            <p class="mt-1 text-sm text-gray-500">Set advanced settings for this voicemail
                            </p>
                        </div>

                        <div class="divide-y divide-gray-200 col-span-6">

                            <Toggle label="Play Voicemail Tutorial"
                                description="Provide user with a guided tutorial when accessing voicemail for the first time."
                                v-model="form.voicemail_tutorial" customClass="py-4" />

                            <Toggle v-if="localOptions.permissions.manage_voicemail_recording_instructions"
                                label="Play Recording Instructions" description='Play a prompt instructing callers to "Record your message after the tone. Stop
                                        speaking to end the recording."'
                                v-model="form.voicemail_play_recording_instructions" customClass="py-4" />

                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-3 sm:col-span-2">
                                <div class="flex items-center gap-1">
                                    <LabelInputOptional target="voicemail_alternate_greet_id" label="Announce Voicemail
                                        Extension as" />

                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>The parameter allows you to override the voicemail extension number
                                                spoken
                                                by the system in the voicemail greeting. This controls system greetings
                                                that
                                                read back an extension number, not user recorded greetings.</div>
                                        </template>
                                    </Popover>
                                </div>

                                <InputField v-model="form.voicemail_alternate_greet_id" type="text"
                                    name="voicemail_alternate_greet_id" :error="!!errors?.voicemail_alternate_greet_id"
                                    id="voicemail_alternate_greet_id" class="mt-2" />

                                <div v-if="errors?.voicemail_alternate_greet_id" class="mt-2 text-xs text-red-600">
                                    {{ errors.voicemail_alternate_greet_id[0] }}
                                </div>

                            </div>

                        </div>


                    </div>
                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <DeleteConfirmationModal :show="showDeleteConfirmation" @close="showDeleteConfirmation = false"
        @confirm="confirmDeleteAction" />

    <UpdateGreetingModal :greeting="greetingLabel" :show="showEditModal" :loading="isGreetingUpdating"
        @confirm="handleGreetingUpdate" @close="showEditModal = false" />

    <AddEditItemModal :customClass="'sm:max-w-xl'" :show="showNewGreetingModal" :header="''" :loading="loadingModal"
        @close="handleModalClose">
        <template #modal-body>
            <NewGreetingForm :title="'New Greeting Message'" :voices="localOptions.voices" :speeds="localOptions.speeds"
                :phone_call_instructions="localOptions.phone_call_instructions"
                :sample_message="localOptions.sample_message" :routes="getRoutesForGreetingForm"
                @greeting-saved="handleGreetingSaved" />
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { onMounted, reactive, ref, watch, computed, nextTick } from "vue";
import { usePage } from '@inertiajs/vue3';

import InputField from "../general/InputField.vue";
import InputFieldWithIcon from "@generalComponents/InputFieldWithIcon.vue";
import Popover from "@generalComponents/Popover.vue";
import Toggle from "@generalComponents/Toggle.vue";
import DeleteConfirmationModal from "../modal/DeleteConfirmationModal.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import Spinner from "@generalComponents/Spinner.vue";
import { InformationCircleIcon } from "@heroicons/vue/24/outline";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { PlusIcon, TrashIcon, PencilSquareIcon } from '@heroicons/vue/20/solid'
import { PlayCircleIcon, CloudArrowDownIcon, PauseCircleIcon } from '@heroicons/vue/24/solid';
import UpdateGreetingModal from "../modal/UpdateGreetingModal.vue";
import NewGreetingForm from './NewGreetingForm.vue';
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import { Cog6ToothIcon, MusicalNoteIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import { registerLicense } from '@syncfusion/ej2-base';

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});

const form$ = ref(null)
// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);
const showEditModal = ref(false);
const selectedGreetingMethod = ref('text-to-speech');
const isDownloading = ref(false);
const confirmationModalTrigger = ref(false);
const loadingModal = ref(false);
const isGreetingUpdating = ref(false);
const showNewGreetingModal = ref(false);
const showDeleteConfirmation = ref(false);
const greetingLabel = ref(null);

const greetingTranscription = computed(() => {
  // Check that the ref is assigned and has a `value` property
  return form$?.value?.data?.greeting?.description || null
})

const allMemberOptions = props.options.member_options.flatMap(group => group.groupOptions);


// Prepare an array of member objects based on ring_group.destinations:
const memberItems = props.options.ring_group.destinations.map(dest => {
    const match = allMemberOptions.find(opt => opt.destination === dest.destination_number);

    return {
        uuid: dest.ring_group_destination_uuid,
        destination: dest.destination_number,                 // The member's extension/number
        delay: dest.destination_delay,                   // Delay (must match a Select option value)
        timeout: dest.destination_timeout,               // Timeout (must match a Select option value)
        prompt: !!dest.destination_prompt,               // Convert to boolean for Toggle
        enabled: !!dest.destination_enabled,              // Convert to boolean for Toggle
        type: match?.type || null,

    }
})

onMounted(() => {
    form$.value.update({ // updates form data
        name: props.options.ring_group.ring_group_name ?? null,
        extension: props.options.ring_group.ring_group_extension ?? null,
        greeting: props.options.ring_group.ring_group_greeting
            ? props.options.greetings.find(g => g.value === props.options.ring_group.ring_group_greeting)?.value || null
            : null,
        call_distribution: props.options.ring_group.call_distributions
            ? props.options.call_distributions.find(rp => rp.value === props.options.ring_group.ring_group_strategy)?.value || 'enterprise'
            : 'enterprise',

        members: memberItems
    })

    form$.value.clean()
    // console.log(form$.value.data);
})


const delayOptions = Array.from({ length: 21 }, (_, i) => {
    const seconds = i * 5; // 0, 5, 10, ..., 100
    const rings = Math.round(seconds / 5); // 1 ring = ~5 seconds
    return {
        value: String(seconds),
        label: `${rings} ${rings === 1 ? 'Ring' : 'Rings'} (${seconds}s)`
    };
});

const timeoutOptions = Array.from({ length: 21 }, (_, i) => {
    const seconds = i * 5; // 0, 5, 10, ..., 100
    const rings = Math.round(seconds / 5);
    return {
        value: String(seconds),
        label: `${rings} ${rings === 1 ? 'Ring' : 'Rings'} (${seconds}s)`
    };
});


const availableMembers = computed(() => {
    const membersField = form$.value?.el$('members');
    const currentMembers = membersField?.value || [];

    const selectedDestinations = currentMembers.map(m => m.destination);

    return props.options.member_options.map(group => ({
        label: group.groupLabel,
        items: group.groupOptions.filter(opt =>
            !selectedDestinations.includes(opt.destination)
        ),
    }));
});


const addSelectedMembers = () => {
    // console.log(form$.value.el$('selectedMembers').value);
    const selectedItems = form$.value.el$('selectedMembers').value.map(item => {
        return {
            uuid: item.destination ? item.value : null,              // if a destination exists, use the item.value as uuid; otherwise, uuid is null
            destination: item.destination ? item.destination : item.label,  // if item.destination exists, use it; otherwise, use the label
            type: item.type ? item.type : "other",                     // if type exists, use it; else default to "other"
            delay: "0",
            timeout: "30",
            prompt: false,
            enabled: true
        }
    });

    const currentMembers = form$.value.el$('members').value

    form$.value.update({
        members: [...currentMembers, ...selectedItems]
    })

    form$.value.el$('selectedMembers').update([]); // clear selection
};


function getMemberLabel(destination) {
    // console.log(destination);
    // Find the member option based on the extension number.
    const member = allMemberOptions.find(opt => opt.destination === destination);
    // If found, return the full label; otherwise, return the extension.
    return member ? member.label : destination;
};


const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

const showPassword = ref(false);

const handleNewGreetingButtonClick = () => {
    showNewGreetingModal.value = true;
};


const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
    const passwordInput = document.getElementById("voicemail_password");
    if (showPassword.value) {
        passwordInput.style.webkitTextSecurity = "none"; // Show text
    } else {
        passwordInput.style.webkitTextSecurity = "disc"; // Mask text
    }
};

// Map icon names to their respective components
const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'MusicalNoteIcon': MusicalNoteIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
};


const page = usePage();

// Make a local reactive copy of options to manipulate in this component
const localOptions = reactive({ ...props.options });

// Watch for changes in props.options and update localOptions accordingly
watch(() => props.options, (newOptions) => {
    Object.assign(localOptions, newOptions);
});

// const form = reactive({
//     name: props.options.ring_group.ring_group_name ?? null,
//     extension: props.options.ring_group.ring_group_extension ?? null,
//     greeting: props.options.ring_group.ring_group_greeting
//         ? props.options.greetings.find(g => g.value === props.options.ring_group.ring_group_greeting)
//         : null,

//     ring_pattern: props.options.ring_group.ring_pattern
//         ? props.options.ring_patterns.find(rp => rp.value === props.options.ring_group.ring_pattern)
//         : { value: 'enterprise', name: 'Advanced' },
//     // voicemail_id: props.options.voicemail.voicemail_id,
//     // voicemail_password: props.options.voicemail.voicemail_password,
//     // voicemail_mail_to: props.options.voicemail.voicemail_mail_to,
//     // voicemail_description: props.options.voicemail.voicemail_description,
//     // voicemail_transcription_enabled: props.options.voicemail.voicemail_transcription_enabled === "true",
//     // voicemail_email_attachment: props.options.voicemail.voicemail_file === "attach",
//     // voicemail_delete: props.options.voicemail.voicemail_local_after_email === "false",
//     // voicemail_tutorial: props.options.voicemail.voicemail_tutorial === "true",
//     // voicemail_play_recording_instructions: props.options.voicemail.voicemail_recording_instructions === "true",
//     // voicemail_copies: props.options.voicemail_copies,
//     // voicemail_alternate_greet_id: props.options.voicemail.voicemail_alternate_greet_id,
//     // voicemail_enabled: props.options.voicemail.voicemail_enabled === "true",
//     // update_route: props.options.routes.update_route,
//     // greeting_id: props.options.voicemail.greeting_id,
//     _token: page.props.csrf_token,
// })

const greetingDescription = computed(() => {
    if (!form.greeting) return null; // Handle case where no greeting is selected

    const selected = localOptions.greetings.find(
        (greeting) => greeting.value === form.greeting.value
    );
    return selected ? selected.description : null;
});

const decodedGreetingDescription = computed(() => {
    // Create a temporary DOM element (textarea works well for this)
    const txt = document.createElement("textarea");
    txt.innerHTML = greetingDescription.value; // greetingDescription comes from your computed/watched property
    return txt.value;
});

const emits = defineEmits(['submit', 'cancel', 'error', 'success']);

const submitForm = () => {
    const payload = {
        ...form,
        greeting: form.greeting ? form.greeting.value : null, // extract value
        ring_pattern: form.ring_pattern ? form.ring_pattern.value : null, // extract value
    };

    emits('submit', payload); // Emit the event with the form data
}

// Handler for the greeting-saved event
const handleGreetingSaved = ({ greeting_id, greeting_name, description }) => {
    // Add the new greeting to the localOptions.greetings array
    localOptions.greetings.push({ value: String(greeting_id), name: greeting_name, description: description });

    // Sort the greetings array by greeting_id
    localOptions.greetings.sort((a, b) => Number(a.value) - Number(b.value));

    // Update the selected greeting ID
    form.greeting = {
        value: String(greeting_id),
        name: greeting_name,
        description: description
    };

    currentAudio.value = null;

    showNewGreetingModal.value = false;

    emits('success', 'success', { message: ['New greeting has been successfully added.'] });
};


const currentAudio = ref(null);
const isAudioPlaying = ref(false);
const currentAudioGreeting = ref(null);

const playGreeting = () => {
    const greeting = form$.value.data.greeting.value;

    if (!greeting) return; // No greeting selected

    // If there's already an audio playing for the SAME greeting
    if (currentAudio.value && currentAudio.value.src && currentAudioGreeting.value === greeting) {
        if (currentAudio.value.paused) {
            currentAudio.value.play();
            isAudioPlaying.value = true;
        }
        return; // Same greeting, don't reload
    }

    // Otherwise, stop the old audio
    if (currentAudio.value) {
        currentAudio.value.pause();
        currentAudio.value.currentTime = 0;
        currentAudio.value = null;
    }
    isAudioPlaying.value = false;

    axios.post(props.options.routes.greeting_route, { file_name: greeting })
        .then((response) => {
            if (currentAudio.value) {
                currentAudio.value.pause();
                currentAudio.value.currentTime = 0;
            }
            if (response.data.success) {
                isAudioPlaying.value = true;

                currentAudio.value = new Audio(response.data.file_url);
                currentAudioGreeting.value = greeting;
                currentAudio.value.play().catch(() => {
                    isAudioPlaying.value = false;
                    emits('error', { message: 'Audio playback failed' });
                });

                currentAudio.value.addEventListener("ended", () => {
                    isAudioPlaying.value = false;
                });
            }
        }).catch((error) => {
            emits('error', error);
        });
};



const downloadGreeting = () => {
    isDownloading.value = true; // Start the spinner

    const greeting = form$.value.data.greeting.value;

    if (!greeting) {
        isDownloading.value = false;
        return; // No greeting selected, stop
    }

    axios.post(props.options.routes.greeting_route, { file_name: greeting })
        .then((response) => {
            if (response.data.success) {
                // Create a URL with the download parameter set to true
                const downloadUrl = `${response.data.file_url}?download=true`;

                // Create an invisible link element
                const link = document.createElement('a');
                link.href = downloadUrl;

                // Use the filename or a default name
                const fileName = response.data.file_name;
                link.download = fileName || 'greeting.wav';

                // Append the link to the body
                document.body.appendChild(link);

                // Trigger the download
                link.click();

                // Remove the link
                document.body.removeChild(link);
            }
        })
        .catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            isDownloading.value = false; // Stop the spinner after download completes
        });
};



const pauseGreeting = () => {
    if (currentAudio.value) {
        currentAudio.value.pause();
        isAudioPlaying.value = false;
    }
};

const editGreeting = () => {
    if (form$.value.data.greeting) {
        greetingLabel.value = form$.value.data.greeting;
        showEditModal.value = true;
    }
};


const deleteGreeting = () => {
    // Show the confirmation modal
    showDeleteConfirmation.value = true;
};

const confirmDeleteAction = () => {
    axios
        .post(props.options.routes.delete_greeting_route, { file_name: form$.value.data.greeting.value })
        .then((response) => {
            if (response.data.success) {
                // Remove the deleted greeting from the localOptions.greetings array
                localOptions.greetings = localOptions.greetings.filter(
                    (greeting) => greeting.value !== String(form$.value.el$('greeting').value)
                );

                // Reset the selected greeting ID
                form$.value.el$('greeting').update(localOptions.greetings);

                // Notify the parent component or show a local success message
                emits('success', 'success', response.data.messages);
            }
        })
        .catch((error) => {
            emits('error', error); // Emit an error event if needed
        })
        .finally(() => {
            showDeleteConfirmation.value = false; // Close the confirmation modal
        });
};

const handleGreetingUpdate = (updatedGreeting) => {
    isGreetingUpdating.value = true;

    const index = localOptions.greetings.findIndex(g => g.value === updatedGreeting.value);
    if (index !== -1) {
        // Update the local greetings array
        localOptions.greetings[index] = updatedGreeting;

        form$.value.el$('greeting').update(localOptions.greetings);

        form$.value.el$('greeting').clear()
    }

    axios
        .post(props.options.routes.update_greeting_route,
            {
                file_name: updatedGreeting.value,
                new_name: updatedGreeting.label
            })
        .then((response) => {
            if (response.data.success) {
                // Notify the parent component or show a local success message
                emits('success', 'success', response.data.messages); // Or handle locally
            }
        })
        .catch((error) => {
            emits('error', error); // Emit an error event if needed
        })
        .finally(() => {
            isGreetingUpdating.value = false;
        });

};


// Computed property or method to dynamically set routes based on the form type
const getRoutesForGreetingForm = computed(() => {
    // Return routes specifically for the greeting form
    return {
        ...localOptions.routes,
        text_to_speech_route: localOptions.routes.text_to_speech_route,
        upload_greeting_route: localOptions.routes.upload_greeting_route
    };
});


const handleModalClose = () => {
    showNewGreetingModal.value = false;
}

registerLicense('Ngo9BigBOggjHTQxAR8/V1NAaF5cWWdCf1FpRmJGdld5fUVHYVZUTXxaS00DNHVRdkdnWX5eeHVSQ2hYUkB3WEI=');

</script>

<style scoped>
/* This will mask the text input to behave like a password field */
.password-field {
    -webkit-text-security: disc;
    /* For Chrome and Safari */
    -moz-text-security: disc;
    /* For Firefox */
}
</style>