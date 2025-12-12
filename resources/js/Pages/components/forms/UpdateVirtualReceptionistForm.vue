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
                    <ExclamationCircleIcon
                        v-if="((errors?.ivr_menu_name || errors?.ivr_menu_extension || errors?.repeat_prompt || errors?.exit_target_uuid || errors?.ivr_menu_greet_long) && item.slug === 'settings') ||
                            ((errors?.caller_id_prefix || errors?.digit_length || errors?.prompt_timeout || errors?.pin) && item.slug === 'advanced')"
                        class="ml-2 h-5 w-5 text-red-500" aria-hidden="true" />

                </a>
            </nav>
        </aside>

        <form @submit.prevent="submitForm" class="space-y-6 sm:px-6 lg:col-span-9 lg:px-0">
            <div v-if="activeTab === 'settings'">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Settings</h3>

                            <Toggle label="Status" v-model="form.ivr_menu_enabled" />

                            <!-- <p class="mt-1 text-sm text-gray-500"></p> -->
                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6" v-if="localOptions.permissions.is_superadmin">
                                <div class="block text-sm font-medium leading-6 text-gray-900">
                                    Unique ID
                                </div>
                                <div class="mt-1 flex items-center group">
                                    <span class="text-sm text-gray-900 select-all font-normal">
                                        {{ form.ivr_menu_uuid }}
                                    </span>
                                    <button type="button" @click="handleCopyToClipboard(form.ivr_menu_uuid)"
                                        class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                        title="Copy to clipboard">
                                        <!-- Small Copy Icon -->
                                        <ClipboardDocumentIcon
                                            class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                    </button>
                                </div>
                            </div>
                            <div class="col-span-4 sm:col-span-3">
                                <LabelInputRequired target="ivr_menu_name" label="Name" class="truncate" />
                                <InputField v-model="form.ivr_menu_name" type="text" name="ivr_menu_name" id="ivr_menu_name"
                                    class="mt-2" :error="!!errors?.ivr_menu_name" />
                                <div v-if="errors?.ivr_menu_name" class="mt-2 text-xs text-red-600">
                                    {{ errors.ivr_menu_name[0] }}
                                </div>
                            </div>

                            <div class="col-span-3 sm:col-span-2">
                                <LabelInputRequired target="ivr_menu_extension" label="Extension" class="truncate" />
                                <InputField v-model="form.ivr_menu_extension" type="text" name="ivr_menu_extension"
                                    id="ivr_menu_extension" class="mt-2" :error="!!errors?.ivr_menu_extension" />
                                <div v-if="errors?.ivr_menu_extension" class="mt-2 text-xs text-red-600">
                                    {{ errors.ivr_menu_extension[0] }}
                                </div>
                            </div>

                            <div class="col-span-6">
                                <LabelInputOptional target="ivr_menu_description" label="Description" class="truncate" />
                                <div class="mt-2">
                                    <Textarea v-model="form.ivr_menu_description" id="ivr_menu_description"
                                        name="ivr_menu_description" rows="2" :error="!!errors?.ivr_menu_description" />
                                </div>
                                <div v-if="errors?.ivr_menu_description" class="mt-2 text-xs text-red-600">
                                    {{ errors.ivr_menu_description[0] }}
                                </div>
                            </div>


                        </div>

                    </div>
                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">

                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:col-start-2"
                            ref="saveButtonRef" :disabled="isSubmitting">
                            <Spinner :show="isSubmitting" />
                            Save
                        </button>
                    </div>
                </div>

                <div class="mt-6 shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Audio Prompt</h3>
                            <p class="mt-1 text-sm text-gray-500">Personalize the audio callers hear when they reach your
                                virtual receptionist.</p>
                        </div>

                        <div class="grid grid-cols-6 gap-6">

                            <div class="col-span-6 sm:col-span-3 text-sm font-medium leading-6 text-gray-900">
                                <LabelInputOptional label="Select prompt" class="truncate mb-1" />

                                <ComboBox :options="localOptions.greetings" :search="false" :placeholder="'Select prompt'"
                                    :selectedItem="form.ivr_menu_greet_long"
                                    @update:model-value="handleUpdateGreetingField" />

                                <div v-if="errors?.ivr_menu_greet_long" class="mt-2 text-xs text-red-600">
                                    {{ errors.ivr_menu_greet_long[0] }}
                                </div>

                            </div>

                            <div :class="{
                                'pb-7': errors?.ivr_menu_greet_long,
                                'pb-1': !errors?.ivr_menu_greet_long
                            }" class="content-end col-span-2 text-sm font-medium leading-6 text-gray-900">
                                <div class="flex items-center whitespace-nowrap gap-2">
                                    <!-- Play Button -->
                                    <PlayCircleIcon v-if="form.ivr_menu_greet_long && !isAudioPlaying" @click="playGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Pause Button -->
                                    <PauseCircleIcon v-if="form.ivr_menu_greet_long && isAudioPlaying"
                                        @click="pauseGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                    <CloudArrowDownIcon v-if="form.ivr_menu_greet_long && !isDownloading"
                                        @click="downloadGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <Spinner :show="isDownloading"
                                        class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Edit Button -->
                                    <PencilSquareIcon v-if="form.ivr_menu_greet_long" @click="editGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Delete Button -->
                                    <TrashIcon v-if="form.ivr_menu_greet_long" @click="deleteGreeting"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                    <PlusIcon @click="toggleGreetingForm"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                </div>

                            </div>

                        </div>

                        <div v-if="greetingDescription">
                            <p class="mt-1 text-xs text-gray-500 italic" v-html="`&quot;${decodedGreetingDescription}&quot;`"></p>

                        </div>

                    </div>

                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                        <button type="submit"
                            class="inline-flex justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                    </div>
                </div>


                <!-- New Greeting Form -->
                <NewGreetingForm v-if="showGreetingForm" :title="'New Greeting Message'" :voices="localOptions.voices" :default_voice="localOptions.default_voice"
                    :speeds="localOptions.speeds" :phone_call_instructions="localOptions.phone_call_instructions"
                    :sample_message="localOptions.sample_message" :routes="getRoutesForGreetingForm"
                    @greeting-saved="handleGreetingSaved" />



                <div class="mt-6 shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">

                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Failback Options</h3>
                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3 text-sm font-medium leading-6 text-gray-900">
                                <LabelInputRequired label="If caller enters no action or an invalid option"
                                    class="truncate mb-1" />

                                <ComboBox :options="localOptions.promt_repeat_options" :search="false"
                                    :placeholder="'Select a repeat option'" :selectedItem="form.repeat_prompt"
                                    :error="errors?.repeat_prompt && errors.repeat_prompt.length > 0"
                                    @update:model-value="handleUpdateRepeatPropmtField" />

                                <div v-if="errors?.repeat_prompt" class="mt-2 text-xs text-red-600">
                                    {{ errors.repeat_prompt[0] }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3 text-sm font-medium leading-6 text-gray-900">
                                <LabelInputRequired :target="'exit_action'" :label="'Forward calls to'"
                                    class="truncate mb-1" />
                                <div class="">
                                    <ComboBox :options="options.routing_types" :selectedItem="form.exit_action"
                                        :search="true" placeholder="Choose Action"
                                        @update:model-value="(value) => handleUpdateActionField(value)"
                                        :error="errors?.exit_action && errors.exit_action.length > 0" />
                                </div>
                                <div v-if="errors?.exit_action" class="mt-2 text-xs text-red-600">
                                    {{ errors.exit_action[0] }}
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3 text-sm font-medium leading-6 text-gray-900">
                                <LabelInputRequired :target="'exit_target_uuid'" :label="'Target'" class="truncate mb-1" />
                                <div class="relative">
                                    <ComboBox :options="targets" :selectedItem="form.exit_target_uuid" :search="true"
                                        :key="targets" placeholder="Choose Target"
                                        @update:model-value="(value) => handleUpdateTargetField(value)"
                                        :disabled="isTargetDisabled"
                                        :error="errors?.exit_target_uuid && errors.exit_target_uuid.length > 0" />

                                    <!-- Spinner Overlay -->
                                    <div v-if="loading"
                                        class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-50">
                                        <Spinner class="w-10 h-10 text-gray-500" :show="loading" />
                                    </div>
                                </div>
                                <div v-if="errors?.exit_target_uuid" class="mt-2 text-xs text-red-600">
                                    {{ errors.exit_target_uuid[0] }}
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




            <div v-if="activeTab === 'keys'">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-100 px-4 py-6 sm:p-6">

                        <div class="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">
                            <div class="sm:col-span-full space-y-3">
                                <!-- <LabelInputOptional :target="'destination_actions'" :label="'Send calls to'" /> -->
                                <IvrOptions v-model="localOptions.ivr.options" :routingTypes="options.routing_types"
                                    :key="form.options" :optionsUrl="options.routes.get_routing_options"
                                    @add-key="handleAddKey" @delete-key="handleDeleteKeyRequest" @edit-key="handleEditKey"
                                    :isDeleting="showKeyDeletingStatus" />

                            </div>


                        </div>
                    </div>
                </div>

            </div>

            <div v-if="activeTab === 'advanced'" action="#" method="POST">
                <div class="shadow sm:rounded-md">
                    <div class="space-y-6 bg-gray-50 px-4 py-6 sm:p-6">
                        <div>
                            <h3 class="text-base font-semibold leading-6 text-gray-900">Advanced</h3>
                            <p class="mt-1 text-sm text-gray-500">Set advanced settings for this virtual receptionist
                            </p>
                        </div>

                        <div class="grid grid-cols-6 gap-6">

                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputOptional target="caller_id_prefix" label="Caller ID Name Prefix" />

                                <InputField v-model="form.caller_id_prefix" type="text" name="caller_id_prefix"
                                    :error="!!errors?.caller_id_prefix" id="caller_id_prefix" class="mt-2" />

                                <div v-if="errors?.caller_id_prefix" class="mt-2 text-xs text-red-600">
                                    {{ errors.caller_id_prefix[0] }}
                                </div>

                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <LabelInputRequired target="digit_length" label="Digit Length" />

                                <InputField v-model="form.digit_length" type="text" name="digit_length"
                                    :error="!!errors?.digit_length" id="digit_length" class="mt-2" />

                                <div v-if="errors?.digit_length" class="mt-2 text-xs text-red-600">
                                    {{ errors.digit_length[0] }}
                                </div>

                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <div class="flex items-center gap-1">
                                    <LabelInputRequired target="prompt_timeout" label="Prompt Timeout (ms)" />

                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>The time, in milliseconds, to wait for the caller's input after the prompt
                                                finishes playing. If no input is received within this time, the system will
                                                proceed based on the configured timeout action.</div>
                                        </template>
                                    </Popover>
                                </div>

                                <InputField v-model="form.prompt_timeout" type="text" name="prompt_timeout"
                                    :error="!!errors?.prompt_timeout" id="timeout" class="mt-2" />

                                <div v-if="errors?.prompt_timeout" class="mt-2 text-xs text-red-600">
                                    {{ errors.prompt_timeout[0] }}
                                </div>

                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <div class="flex items-center gap-1">
                                    <LabelInputOptional target="pin" label="Pin Number" />

                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>Use a PIN to protect this menu from unauthorized access.</div>
                                        </template>
                                    </Popover>
                                </div>

                                <InputField v-model="form.pin" type="text" name="pin" :error="!!errors?.pin" id="pin"
                                    class="mt-2" />

                                <div v-if="errors?.pin" class="mt-2 text-xs text-red-600">
                                    {{ errors.pin[0] }}
                                </div>

                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <div class="flex items-center gap-1">
                                    <LabelInputOptional target="ring_back_tone" label="Ring Back Tone" />

                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>Specify the sound or tone the caller hears while waiting for the
                                                destination to answer the call.</div>
                                        </template>
                                    </Popover>
                                </div>

                                <ListboxGroup :options="options.ring_back_tones" v-model="form.ring_back_tone"
                                    placeholder="Choose an option" />
                                <div v-if="errors?.ring_back_tone" class="mt-2 text-xs text-red-600">
                                    {{ errors.ring_back_tone[0] }}
                                </div>

                            </div>

                            <div class="content-end col-span-3 pb-1 text-sm font-medium leading-6 text-gray-900">
                                <div class="flex items-center whitespace-nowrap gap-2">
                                    <!-- Play Button -->
                                    <PlayCircleIcon
                                        v-if="form.ring_back_tone && !isRingBackTonePlaying && (form.ring_back_tone.endsWith('.wav') || form.ring_back_tone.endsWith('.mp3'))"
                                        @click="playRingBackTone"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Pause Button -->
                                    <PauseCircleIcon
                                        v-if="form.ring_back_tone && isRingBackTonePlaying && (form.ring_back_tone.endsWith('.wav') || form.ring_back_tone.endsWith('.mp3'))"
                                        @click="pauseRingBackTone"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                </div>
                            </div>

                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-3">
                                <div class="flex items-center gap-1">
                                    <LabelInputOptional target="invalid_input_message" label="Invalid Input Message" />

                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>Specify the audio message played to the caller when they provide an invalid
                                                input or press an unrecognized key.</div>
                                        </template>
                                    </Popover>
                                </div>

                                <ListboxGroup :options="options.sounds" v-model="form.invalid_input_message"
                                    placeholder="Choose an option" />
                                <div v-if="errors?.invalid_input_message" class="mt-2 text-xs text-red-600">
                                    {{ errors.invalid_input_message[0] }}
                                </div>

                            </div>

                            <div class="content-end col-span-3 pb-1 text-sm font-medium leading-6 text-gray-900">
                                <div class="flex items-center whitespace-nowrap gap-2">
                                    <!-- Play Button -->
                                    <PlayCircleIcon v-if="form.invalid_input_message && !isInvalidInputMessageAudioPlaying"
                                        @click="playInvalidInputMessage"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Pause Button -->
                                    <PauseCircleIcon v-if="form.invalid_input_message && isInvalidInputMessageAudioPlaying"
                                        @click="pauseInvalidInputMessage"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <div class="flex items-center gap-1">
                                    <LabelInputOptional target="exit_message" label="Exit Message" />

                                    <Popover>
                                        <template v-slot:popover-button>
                                            <InformationCircleIcon class="h-5 w-5 text-blue-500" />
                                        </template>
                                        <template v-slot:popover-panel>
                                            <div>Specify the audio message played to the caller when the menu is terminated.
                                            </div>
                                        </template>
                                    </Popover>
                                </div>

                                <ListboxGroup :options="options.sounds" v-model="form.exit_message"
                                    placeholder="Choose an option" />
                                <div v-if="errors?.exit_message" class="mt-2 text-xs text-red-600">
                                    {{ errors.exit_message[0] }}
                                </div>

                            </div>

                            <div class="content-end col-span-3 pb-1 text-sm font-medium leading-6 text-gray-900">
                                <div class="flex items-center whitespace-nowrap gap-2">
                                    <!-- Play Button -->
                                    <PlayCircleIcon v-if="form.exit_message && !isExitMessageAudioPlaying"
                                        @click="playExitMessage"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />

                                    <!-- Pause Button -->
                                    <PauseCircleIcon v-if="form.exit_message && isExitMessageAudioPlaying"
                                        @click="pauseExitMessage"
                                        class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />

                                </div>
                            </div>

                        </div>

                        <div class="divide-y divide-gray-200 col-span-6">

                            <Toggle label="Enable Direct Dialing"
                                description="Allows callers to dial extensions directly (e.g., If you know your party's extension, you may dial it now)."
                                v-model="form.direct_dial" customClass="py-4" />

                            <Toggle v-if="localOptions.permissions.manage_voicemail_recording_instructions"
                                label="Play Recording Instructions" description='Play a prompt instructing callers to "Record your message after the tone. Stop
                                        speaking to end the recording."'
                                v-model="form.voicemail_play_recording_instructions" customClass="py-4" />

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

    <UpdateGreetingModal :greeting="selectedGreeting" :show="showEditModal" :loading="isGreetingUpdating"
        @confirm="handleGreetingUpdate" @close="showEditModal = false" />

    <AddEditItemModal :customClass="'sm:max-w-lg'" :show="showAddKeyModal" :header="'Add Virtual Receptionist Key'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <CreateVirtualReceptionistKeyForm :options="options" :errors="errors" :is-submitting="submittingKeyCreate"
                @submit="handleCreateKeyRequest" @error="handleKeyFormError" @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>

    <AddEditItemModal :customClass="'sm:max-w-lg'" :show="showEditKeyModal" :header="'Edit Virtual Receptionist Key'"
        :loading="loadingModal" @close="handleModalClose">
        <template #modal-body>
            <UpdateVirtualReceptionistKeyForm :options="options" :errors="errors" :selected-key="selectedKey"
                :is-submitting="submittingKeyUpdate" @submit="handleUpdateKeyRequest" @error="handleKeyFormError"
                @cancel="handleModalClose" />
        </template>
    </AddEditItemModal>
</template>

<script setup>
import { reactive, ref, watch, computed, onMounted } from "vue";
import { usePage } from '@inertiajs/vue3';

import ComboBox from "../general/ComboBox.vue";
import InputField from "../general/InputField.vue";
import Popover from "@generalComponents/Popover.vue";
import Textarea from "@generalComponents/Textarea.vue";
import Toggle from "@generalComponents/Toggle.vue";
import DeleteConfirmationModal from "../modal/DeleteConfirmationModal.vue";
import UpdateGreetingModal from "../modal/UpdateGreetingModal.vue";
import LabelInputOptional from "../general/LabelInputOptional.vue";
import LabelInputRequired from "../general/LabelInputRequired.vue";
import Spinner from "@generalComponents/Spinner.vue";
import { InformationCircleIcon } from "@heroicons/vue/24/outline";
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid'
import { PlusIcon, TrashIcon, PencilSquareIcon } from '@heroicons/vue/20/solid'
import { PlayCircleIcon, CloudArrowDownIcon, PauseCircleIcon } from '@heroicons/vue/24/solid';
import NewGreetingForm from './NewGreetingForm.vue';
import { Cog6ToothIcon, AdjustmentsHorizontalIcon } from '@heroicons/vue/24/outline';
import DialpadIcon from "@icons/DialpadIcon.vue"
import IvrOptions from "../general/IvrOptions.vue";
import AddEditItemModal from "../modal/AddEditItemModal.vue";
import CreateVirtualReceptionistKeyForm from "../forms/CreateVirtualReceptionistKeyForm.vue";
import UpdateVirtualReceptionistKeyForm from "../forms/UpdateVirtualReceptionistKeyForm.vue";
import ListboxGroup from "../general/ListboxGroup.vue";
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
    options: Object,
    isSubmitting: Boolean,
    errors: Object,
});


// Initialize activeTab with the currently active tab from props
const activeTab = ref(props.options.navigation.find(item => item.slug)?.slug || props.options.navigation[0].slug);
const showGreetingForm = ref(false);
const showEditModal = ref(false);
const showNameForm = ref(false);
const isDownloading = ref(false);
const showDeleteConfirmation = ref(false);
const isGreetingUpdating = ref(false);
const selectedGreeting = ref(null);
const showKeyDeletingStatus = ref(false);
const selectedKey = ref(null);
const showEditKeyModal = ref(false);
const showAddKeyModal = ref(false);
const loadingModal = ref(false);
const submittingKeyUpdate = ref(false);
const submittingKeyCreate = ref(false);

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emits('success', ['Copied to clipboard.']);
    }).catch((error) => {
        // Handle the error case
        emits('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}

const greetingDescription = computed(() => {
    // Find the greeting object in the array whose value matches the selected greeting
    const selected = localOptions.greetings.find(
        (greeting) => greeting.value === form.ivr_menu_greet_long
    );
    return selected ? selected.description : null;
});

const decodedGreetingDescription = computed(() => {
    // Create a temporary DOM element (textarea works well for this)
    const txt = document.createElement("textarea");
    txt.innerHTML = greetingDescription.value; // greetingDescription comes from your computed/watched property
    return txt.value;
});

const setActiveTab = (tabSlug) => {
    activeTab.value = tabSlug;
};

const toggleGreetingForm = () => {
    showGreetingForm.value = !showGreetingForm.value;
    showNameForm.value = false;
};


// Map icon names to their respective components
const iconComponents = {
    'Cog6ToothIcon': Cog6ToothIcon,
    'DialpadIcon': DialpadIcon,
    'AdjustmentsHorizontalIcon': AdjustmentsHorizontalIcon,
};


const page = usePage();

// Make a local reactive copy of options to manipulate in this component
const localOptions = reactive({ ...props.options });

// Watch for changes in props.options and update localOptions accordingly
watch(() => props.options, (newOptions) => {
    Object.assign(localOptions, newOptions);
});

const form = reactive({
    ivr_menu_uuid: props.options.ivr.ivr_menu_uuid,
    ivr_menu_name: props.options.ivr.ivr_menu_name,
    ivr_menu_extension: props.options.ivr.ivr_menu_extension,
    ivr_menu_description: props.options.ivr.ivr_menu_description,
    ivr_menu_greet_long: props.options.ivr.ivr_menu_greet_long,
    ivr_menu_enabled: props.options.ivr.ivr_menu_enabled === "true",
    repeat_prompt: props.options.ivr.ivr_menu_max_timeouts ?? '3',
    prompt_timeout: props.options.ivr.ivr_menu_timeout,
    direct_dial: props.options.ivr.ivr_menu_direct_dial === "true",
    caller_id_prefix: props.options.ivr.ivr_menu_cid_prefix,
    pin: props.options.ivr.ivr_menu_pin_number,
    digit_length: props.options.ivr.ivr_menu_digit_len,
    ring_back_tone: props.options.ivr.ivr_menu_ringback,
    invalid_input_message: props.options.ivr.ivr_menu_invalid_sound,
    exit_message: props.options.ivr.ivr_menu_exit_sound ?? 'silence_stream://100',
    exit_action: props.options.ivr.exit_action,
    exit_target_uuid: props.options.ivr.exit_target_uuid,
    exit_target_extension: props.options.ivr.exit_target_extension,
    _token: page.props.csrf_token,
})

const targets = ref();
const loading = ref(false);
const isTargetDisabled = ref(false);
const disabledTypes = ['check_voicemail', 'company_directory', 'hangup'];

const emits = defineEmits(['submit', 'cancel', 'error', 'success', 'clear-errors', 'refresh-data']);

onMounted(() => {
    if (props.options?.ivr?.exit_action) {
        if (disabledTypes.includes(props.options?.ivr?.exit_action)) {
            isTargetDisabled.value = true;
        } else {
            isTargetDisabled.value = false;
        }
        fetchRoutingTypeOptions(props.options?.ivr?.exit_action);
    }
});

const submitForm = () => {
    // console.log (form);
    emits('submit', form); // Emit the event with the form data
}


const handleUpdateRepeatPropmtField = (newSelectedItem) => {
    form.repeat_prompt = newSelectedItem.value;
}


const handleAddKey = () => {
    emits('clear-errors');
    showAddKeyModal.value = true;

};

const handleEditKey = (option) => {
    emits('clear-errors');
    // Find the matching key from props.options.ivr.options
    const matchedKey = props.options.ivr.options.find(
        (ivr) => ivr.ivr_menu_option_uuid === option.ivr_menu_option_uuid
    );

    if (matchedKey) {
        selectedKey.value = matchedKey;
        showEditKeyModal.value = true;
    } else {
        emits('error', { request: "Matching key not found" });
    }
}

const handleCreateKeyRequest = (form) => {
    submittingKeyCreate.value = true;
    emits('clear-errors');

    axios.post(props.options.routes.create_key_route, form)
        .then((response) => {
            submittingKeyCreate.value = false;
            emits('success','success', response.data.messages);
            emits('refresh-data', props.options.ivr.ivr_menu_uuid);

            handleModalClose();
        }).catch((error) => {
            submittingKeyCreate.value = false;
            emits('error', error); // Emit the event with error
        });

};

const handleUpdateKeyRequest = (form) => {
    submittingKeyUpdate.value = true;
    emits('clear-errors');

    axios.put(props.options.routes.update_key_route, form)
        .then((response) => {
            submittingKeyUpdate.value = false;
            emits('success','success', response.data.messages);
            emits('refresh-data', props.options.ivr.ivr_menu_uuid);

            handleModalClose();
        }).catch((error) => {
            submittingKeyUpdate.value = false;
            emits('error', error); // Emit the event with error
        });

};

const handleDeleteKeyRequest = (key) => {
    showKeyDeletingStatus.value = true;
    // emits('clear-errors');

    axios.post(props.options.routes.delete_key_route, key)
        .then((response) => {
            showKeyDeletingStatus.value = false;
            emits('success','success', response.data.messages);
            emits('refresh-data', props.options.ivr.ivr_menu_uuid);

        }).catch((error) => {
            showKeyDeletingStatus.value = false;
            emits('error', error); // Emit the event with error
        });

};

const handleUpdateActionField = (selected) => {
    form.exit_action = selected.value;
    if (disabledTypes.includes(selected.value)) {
        isTargetDisabled.value = true;
    } else {
        isTargetDisabled.value = false;
    }
    form.exit_target_extension = null;
    form.exit_target_uuid = null;
    fetchRoutingTypeOptions(selected.value); // Fetch options when action field updates
}

const handleUpdateTargetField = (selected) => {
    form.exit_target_uuid = selected.value;
    form.exit_target_extension = selected.extension;
}

function fetchRoutingTypeOptions(newValue) {
    loading.value = true; // Show spinner
    axios.post(props.options.routes.get_routing_options, { 'category': newValue })
        .then((response) => {
            targets.value = response.data.options; // Assign the returned options to `targets`
        }).catch((error) => {
            emits('error', error);
        })
        .finally(() => {
            loading.value = false; // Hide spinner after fetch completes
        });
}

const handleUpdateGreetingField = (greeting) => {
    form.ivr_menu_greet_long = greeting.value;
    currentAudio.value = false;
}

// Handler for the greeting-saved event
const handleGreetingSaved = ({ greeting_id, greeting_name, description }) => {
    // Add the new greeting to the localOptions.greetings array
    localOptions.greetings.push({ value: String(greeting_id), name: greeting_name, description: description });

    // Sort the greetings array by greeting_id
    localOptions.greetings.sort((a, b) => Number(a.value) - Number(b.value));

    // Update the selected greeting
    form.ivr_menu_greet_long = String(greeting_id);

    currentAudio.value = null;

    // Apply greeting
    axios.post(props.options.routes.apply_greeting_route,
        {
            ivr: props.options.ivr.ivr_menu_uuid,
            file_name: greeting_id,
            greeting_name: greeting_name,
        }
    )
        .then((response) => {
            // console.log(response.data);
            if (response.data.success) {
                // Notify the parent component or show a local success message
                emits('success', response.data.messages); // Or handle locally
            }
        }).catch((error) => {
            emits('error', error);
        });
};


const currentAudio = ref(null);
const isAudioPlaying = ref(false);

const playGreeting = () => {
    // Check if there's already an audio object and it is paused
    if (currentAudio.value && currentAudio.value.paused) {
        currentAudio.value.play();
        isAudioPlaying.value = true;
        return;
    }

    axios.post(props.options.routes.greeting_route, { file_name: form.ivr_menu_greet_long })
        .then((response) => {
            // Stop the currently playing audio (if any)
            if (currentAudio.value) {
                currentAudio.value.pause();
                currentAudio.value.currentTime = 0; // Reset the playback position
            }
            if (response.data.success) {
                isAudioPlaying.value = true;

                currentAudio.value = new Audio(response.data.file_url);
                currentAudio.value.play().catch((error) => {
                    isAudioPlaying.value = false;
                    emits('error', { message: 'Audio playback failed', });
                });

                // Add an event listener for when the audio ends
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

    axios.post(props.options.routes.greeting_route, { file_name: form.ivr_menu_greet_long })
        .then((response) => {
            if (response.data.success) {
                // Create a URL with the download parameter set to true
                const downloadUrl = `${response.data.file_url}?download=true`;

                // Create an invisible link element
                const link = document.createElement('a');
                link.href = downloadUrl;

                // Use the filename or a default name
                const fileName = response.data.file_name;
                link.download = fileName;

                // Append the link to the body
                document.body.appendChild(link);

                // Trigger the download by programmatically clicking the link
                link.click();

                // Remove the link after the download starts
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


const deleteGreeting = () => {
    // Show the confirmation modal
    showDeleteConfirmation.value = true;
};

const confirmDeleteAction = () => {
    axios
        .post(props.options.routes.delete_greeting_route, { file_name: form.ivr_menu_greet_long })
        .then((response) => {
            if (response.data.success) {
                // Remove the deleted greeting from the localOptions.greetings array
                localOptions.greetings = localOptions.greetings.filter(
                    (greeting) => greeting.value !== String(form.ivr_menu_greet_long)
                );

                // Reset the selected greeting ID
                form.ivr_menu_greet_long = null; // Or set it to another default if needed

                // Notify the parent component or show a local success message
                emits('success', response.data.messages);
            }
        })
        .catch((error) => {
            emits('error', error); // Emit an error event if needed
        })
        .finally(() => {
            showDeleteConfirmation.value = false; // Close the confirmation modal
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

const editGreeting = () => {
    selectedGreeting.value = localOptions.greetings.find(
        (greeting) => greeting.value === form.ivr_menu_greet_long
    );

    if (selectedGreeting.value) {
        showEditModal.value = true;
    }
};


const handleGreetingUpdate = (updatedGreeting) => {
    isGreetingUpdating.value = true;

    const index = localOptions.greetings.findIndex(g => g.value === updatedGreeting.value);
    if (index !== -1) {
        localOptions.greetings[index] = updatedGreeting;

        // Reassign the selected value to force the ComboBox to refresh
        if (form.ivr_menu_greet_long === updatedGreeting.value) {
            form.ivr_menu_greet_long = '';
            setTimeout(() => {
                form.ivr_menu_greet_long = updatedGreeting.value;
            }, 0);
        }
    }

    axios
        .post(props.options.routes.update_greeting_route,
            {
                file_name: updatedGreeting.value,
                new_name: updatedGreeting.name
            })
        .then((response) => {
            if (response.data.success) {
                // Notify the parent component or show a local success message
                emits('success', response.data.messages); // Or handle locally
            }
        })
        .catch((error) => {
            emits('error', error); // Emit an error event if needed
        })
        .finally(() => {
            isGreetingUpdating.value = false;
        });

};

const currentInvalidInputMessageAudio = ref(null);
const isInvalidInputMessageAudioPlaying = ref(false);

// Watch for changes in the invalid_input_message field
watch(
    () => form.invalid_input_message,
    (newValue, oldValue) => {
        // Reset the audio object if the message changes
        if (newValue !== oldValue) {
            if (currentInvalidInputMessageAudio.value) {
                currentInvalidInputMessageAudio.value.pause();
                currentInvalidInputMessageAudio.value = null; // Clear the previous audio object
            }
            isInvalidInputMessageAudioPlaying.value = false; // Reset playing state
        }
    }
);

const playInvalidInputMessage = () => {
    currentExitMessageAudio.value = null;
    // Check if there's already an audio object and it is paused
    if (currentInvalidInputMessageAudio.value && currentInvalidInputMessageAudio.value.paused) {
        currentInvalidInputMessageAudio.value.play();
        isInvalidInputMessageAudioPlaying.value = true;
        return;
    }
    axios.post(props.options.routes.ivr_message_route, { file_name: form.invalid_input_message })
        .then((response) => {
            // Stop the currently playing audio (if any)
            if (currentInvalidInputMessageAudio.value) {
                currentInvalidInputMessageAudio.value.pause();
                currentInvalidInputMessageAudio.value.currentTime = 0; // Reset the playback position
            }
            if (response.data.success) {
                isInvalidInputMessageAudioPlaying.value = true;

                currentInvalidInputMessageAudio.value = new Audio(response.data.file_url);
                currentInvalidInputMessageAudio.value.play().catch((error) => {
                    isInvalidInputMessageAudioPlaying.value = false;
                    emits('error', { message: 'Audio playback failed', });
                });

                // Add an event listener for when the audio ends
                currentInvalidInputMessageAudio.value.addEventListener("ended", () => {
                    isInvalidInputMessageAudioPlaying.value = false;
                });
            }

        }).catch((error) => {
            emits('error', error);
        });
};

const pauseInvalidInputMessage = () => {
    if (currentInvalidInputMessageAudio.value) {
        currentInvalidInputMessageAudio.value.pause();
        isInvalidInputMessageAudioPlaying.value = false;
    }
};


const currentExitMessageAudio = ref(null);
const isExitMessageAudioPlaying = ref(false);

// Watch for changes in the invalid_input_message field
watch(
    () => form.exit_message,
    (newValue, oldValue) => {
        // Reset the audio object if the message changes
        if (newValue !== oldValue) {
            if (currentExitMessageAudio.value) {
                currentExitMessageAudio.value.pause();
                currentExitMessageAudio.value = null; // Clear the previous audio object
            }
            isExitMessageAudioPlaying.value = false; // Reset playing state
        }
    }
);

const playExitMessage = () => {
    currentInvalidInputMessageAudio.value = null;
    // Check if there's already an audio object and it is paused
    if (currentExitMessageAudio.value && currentExitMessageAudio.value.paused) {
        currentExitMessageAudio.value.play();
        isExitMessageAudioPlaying.value = true;
        return;
    }
    axios.post(props.options.routes.ivr_message_route, { file_name: form.exit_message })
        .then((response) => {
            // Stop the currently playing audio (if any)
            if (currentExitMessageAudio.value) {
                currentExitMessageAudio.value.pause();
                currentExitMessageAudio.value.currentTime = 0; // Reset the playback position
            }
            if (response.data.success) {
                isExitMessageAudioPlaying.value = true;

                currentExitMessageAudio.value = new Audio(response.data.file_url);
                currentExitMessageAudio.value.play().catch((error) => {
                    isExitMessageAudioPlaying.value = false;
                    emits('error', { message: 'Audio playback failed', });
                });

                // Add an event listener for when the audio ends
                currentExitMessageAudio.value.addEventListener("ended", () => {
                    isExitMessageAudioPlaying.value = false;
                });
            }

        }).catch((error) => {
            emits('error', error);
        });
};


const currentRingBackToneAudio = ref(null);
const isRingBackTonePlaying = ref(false);

// Watch for changes in the invalid_input_message field
watch(
    () => form.ring_back_tone,
    (newValue, oldValue) => {
        // Reset the audio object if the message changes
        if (newValue !== oldValue) {
            if (currentRingBackToneAudio.value) {
                currentRingBackToneAudio.value.pause();
                currentRingBackToneAudio.value = null; // Clear the previous audio object
            }
            isRingBackTonePlaying.value = false; // Reset playing state
        }
    }
);

const playRingBackTone = () => {
    currentInvalidInputMessageAudio.value = null;
    // Check if there's already an audio object and it is paused
    if (currentRingBackToneAudio.value && currentRingBackToneAudio.value.paused) {
        currentRingBackToneAudio.value.play();
        isRingBackTonePlaying.value = true;
        return;
    }
    const filePath = form.ring_back_tone; // The full path
    const fileName = filePath.substring(filePath.lastIndexOf('/') + 1); // Extract the file name

    axios.post(props.options.routes.greeting_route, { file_name: fileName })
        .then((response) => {
            // Stop the currently playing audio (if any)
            if (currentRingBackToneAudio.value) {
                currentRingBackToneAudio.value.pause();
                currentRingBackToneAudio.value.currentTime = 0; // Reset the playback position
            }
            if (response.data.success) {
                isRingBackTonePlaying.value = true;

                currentRingBackToneAudio.value = new Audio(response.data.file_url);
                currentRingBackToneAudio.value.play().catch((error) => {
                    isRingBackTonePlaying.value = false;
                    emits('error', { message: 'Audio playback failed', });
                });

                // Add an event listener for when the audio ends
                currentRingBackToneAudio.value.addEventListener("ended", () => {
                    isRingBackTonePlaying.value = false;
                });
            }

        }).catch((error) => {
            emits('error', error);
        });
};

const pauseRingBackTone = () => {
    if (currentRingBackToneAudio.value) {
        currentRingBackToneAudio.value.pause();
        isRingBackTonePlaying.value = false;
    }
};


const handleModalClose = () => {
    showEditKeyModal.value = false;
    showAddKeyModal.value = false
}

const handleKeyFormError = (error) => {
    emits('error', error);
}



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