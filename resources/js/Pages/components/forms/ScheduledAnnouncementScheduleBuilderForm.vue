<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10" @close="handleClose">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel class="relative w-full max-w-6xl transform overflow-visible rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:p-6">
                            <DialogTitle as="h3" class="mb-5 pr-10 text-base font-semibold leading-6 text-gray-900">
                                {{ header }}
                            </DialogTitle>

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="handleClose">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full py-10 text-center text-sm text-gray-500">Loading...</div>

                            <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess"
                                @error="handleError" @response="handleResponse" :display-errors="false"
                                :default="defaultValues">
                                <template #empty>
                                    <div class="lg:grid lg:grid-cols-12 lg:gap-x-5">
                                        <div class="px-2 py-6 sm:px-6 lg:col-span-3 lg:px-0 lg:py-0">
                                            <FormTabs view="vertical">
                                                <FormTab name="schedule" label="Schedule" :elements="[
                                                    'schedule_header',
                                                    'enabled',
                                                    'name',
                                                    'timezone',
                                                    'description',
                                                    'recording_filename',
                                                    'recording_action_buttons',
                                                    'extensions_header',
                                                    'selectedExtensions',
                                                    'busy_extension_behavior',
                                                    'activation_header',
                                                    'starts_on',
                                                    'ends_on',
                                                    'events_header',
                                                    'events',
                                                    'submit',
                                                ]" />
                                                <FormTab name="exclusions" label="Exclusions" :elements="[
                                                    'exclusions_header',
                                                    'exceptions',
                                                    'submit',
                                                ]" />
                                            </FormTabs>
                                        </div>

                                        <div class="sm:px-6 lg:col-span-9 shadow sm:rounded-md space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                                            <FormElements>
                                                <StaticElement name="schedule_header" tag="h4" content="Schedule"
                                                    description="Give this schedule a name, pick the time zone for its announcement times, and choose the recording to play." />

                                                <ToggleElement name="enabled" text="Enabled"
                                                    :labels="{ on: 'On', off: 'Off' }"
                                                    description="Master switch for this schedule. When off, nothing plays — no events run on any day." />

                                                <TextElement name="name" label="Name" :floating="false"
                                                    :rules="['required']" :columns="{ sm: { container: 6 } }" />
                                                <SelectElement name="timezone" label="Time Zone"
                                                    :groups="true" :items="timezoneOptions" :search="true"
                                                    :native="false" input-type="search" autocomplete="off"
                                                    placeholder="Choose time zone" :floating="false" :strict="false"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <TextElement name="description" label="Description" :floating="false" />
                                                <SelectElement name="recording_filename"
                                                    label="Recording or Sound" :items="fetchRecordings" :native="false"
                                                    :search="true" :floating="false" :rules="['required']"
                                                    description="Every announcement event in this schedule plays this recording or sound. Use the plus button to add a new file."
                                                    @change="handleRecordingChange"
                                                    :columns="{ sm: { container: 6 } }" />
                                                <GroupElement name="recording_action_buttons" :columns="{ container: 6 }">
                                                    <ButtonElement v-if="!isRecordingPlaying" @click="playRecording"
                                                        name="play_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableRecording]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlayCircleIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isRecordingPlaying" @click="pauseRecording"
                                                        name="pause_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PauseCircleIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="!isRecordingDownloading" @click="downloadRecording"
                                                        name="download_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableRecording]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <CloudArrowDownIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement v-if="isRecordingDownloading" name="download_spinner_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <Spinner :show="true"
                                                            class="h-8 w-8 ml-0 mr-0 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400" />
                                                    </ButtonElement>

                                                    <ButtonElement @click="editRecording" name="edit_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableRecording]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PencilSquareIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement @click="deleteRecording" name="delete_button"
                                                        label="&nbsp;" :secondary="true" :columns="{ container: 2 }"
                                                        :conditions="[hasPlayableRecording]"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <TrashIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-red-400 hover:bg-red-200 hover:text-red-600 active:bg-red-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>

                                                    <ButtonElement @click="showNewRecordingModal = true"
                                                        name="add_button" label="&nbsp;" :secondary="true"
                                                        :columns="{ container: 2 }"
                                                        :remove-classes="{ ButtonElement: { button_secondary: ['form-bg-btn-secondary'], button: ['form-border-width-btn'], button_enabled: ['focus:form-ring'], button_md: ['form-p-btn'] } }">
                                                        <PlusIcon
                                                            class="h-8 w-8 shrink-0 transition duration-500 ease-in-out py-1 rounded-full ring-1 text-blue-400 hover:bg-blue-200 hover:text-blue-600 active:bg-blue-300 active:duration-150 cursor-pointer" />
                                                    </ButtonElement>
                                                </GroupElement>
                                                <StaticElement name="extensions_header" tag="h4" content="Extensions"
                                                    description="Choose which extensions play this schedule. Search to add an extension; remove the tag to take it out of the schedule."
                                                    :add-classes="{ StaticElement: { container: 'border-t border-gray-200 pt-6' } }" />
                                                <TagsElement name="selectedExtensions" :close-on-select="false"
                                                    :items="extensionOptions" :search="true" :native="false"
                                                    label="Add Extension" input-type="search" autocomplete="off"
                                                    placeholder="Search by name or extension" :floating="false"
                                                    :rules="['required']" :columns="{ container: 12 }" />
                                                <RadiogroupElement name="busy_extension_behavior"
                                                    label="Busy Extensions" view="tabs"
                                                    :items="busyExtensionBehaviorOptions"
                                                    description="Skip leaves busy extensions alone. Force sends the announcement even if the phone is already on a call."
                                                    :columns="{ container: 12 }" />
                                                <StaticElement name="activation_header" tag="h4" content="When it runs"
                                                    description="Optionally limit this schedule to a date range. Leave both blank to run indefinitely."
                                                    :add-classes="{ StaticElement: { container: 'border-t border-gray-200 pt-6' } }" />
                                                <DateElement name="starts_on" label="Starts" :time="false"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />
                                                <DateElement name="ends_on" label="Ends" :time="false"
                                                    :floating="false" :columns="{ sm: { container: 6 } }" />

                                                <StaticElement name="events_header" tag="h4" content="Events"
                                                    description="Each row is one announcement time. Choose the time of day and the weekdays it should play."
                                                    :add-classes="{ StaticElement: { container: 'border-t border-gray-200 pt-6' } }" />
                                                <ListElement name="events" :sort="true" :initial="1" label="Events"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-3 sm:p-4 mb-4 rounded-lg shadow-md' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <DateElement name="time_of_day" label="Time"
                                                                :time="true" :date="false" :hour24="false"
                                                                :floating="false" :rules="['required']" size="sm"
                                                                :columns="{ sm: { container: 4 } }" />
                                                            <CheckboxgroupElement name="weekdays" view="tabs"
                                                                label="Days" :items="weekdayOptions" size="sm"
                                                                :rules="['required']"
                                                                :columns="{ sm: { container: 8 } }" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <StaticElement name="exclusions_header" tag="h4" content="Exclusions"
                                                    description="Use exclusions for holidays and other special dates. On these dates, this schedule will not play." />
                                                <ListElement name="exceptions" :sort="true" label="Exclusions"
                                                    :add-classes="{ ListElement: { listItem: 'bg-white p-3 sm:p-4 mb-4 rounded-lg shadow-md' } }">
                                                    <template #default="{ index }">
                                                        <ObjectElement :name="index">
                                                            <DateElement name="exception_date" label="Date"
                                                                :time="false" :floating="false"
                                                                :rules="['required']" size="sm"
                                                                :columns="{ sm: { container: 6 } }" />
                                                            <TextElement name="comment" label="Comment"
                                                                :floating="false" size="sm"
                                                                :columns="{ sm: { container: 6 } }" />
                                                        </ObjectElement>
                                                    </template>
                                                </ListElement>

                                                <ButtonElement name="submit" button-label="Save Schedule"
                                                    :submits="true" align="right" />
                                            </FormElements>
                                        </div>
                                    </div>
                                </template>
                            </Vueform>

                            <NewGreetingForm :header="'New Announcement Recording'" :show="showNewRecordingModal"
                                @close="showNewRecordingModal = false" :voices="options?.voices"
                                :speeds="options?.speeds" :default_voice="options?.default_voice"
                                :phone_call_instructions="options?.phone_call_instructions"
                                :sample_message="options?.sample_message" :routes="recordingRoutes"
                                @error="emit('error', $event)" @success="emit('success', 'success', $event)"
                                @saved="handleNewRecordingAdded" />

                            <UpdateGreetingModal :greeting="recordingLabel" :show="showEditModal"
                                :loading="isRecordingUpdating" @confirm="handleRecordingUpdate"
                                @close="showEditModal = false" />

                            <ConfirmationModal :show="showRecordingDeleteConfirmationModal"
                                @close="showRecordingDeleteConfirmationModal = false"
                                @confirm="confirmRecordingDeleteAction" :header="'Confirm Deletion'"
                                :text="'This action will permanently delete this recording. Are you sure you want to proceed?'"
                                :confirm-button-label="'Delete'" cancel-button-label="Cancel" />
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { CloudArrowDownIcon, PauseCircleIcon, PlayCircleIcon, XMarkIcon } from '@heroicons/vue/24/solid'
import { PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid'
import Spinner from '@generalComponents/Spinner.vue'
import ConfirmationModal from '../modal/ConfirmationModal.vue'
import UpdateGreetingModal from '../modal/UpdateGreetingModal.vue'
import NewGreetingForm from './NewGreetingForm.vue'

const props = defineProps({
    show: Boolean,
    options: Object,
    loading: Boolean,
    header: { type: String, default: 'Schedule' },
    mode: { type: String, default: 'create' },
})

const emit = defineEmits(['close', 'error', 'success', 'refresh-data'])
const form$ = ref(null)
const recordingOptions = ref(null)
const showNewRecordingModal = ref(false)
const showEditModal = ref(false)
const showRecordingDeleteConfirmationModal = ref(false)
const currentAudio = ref(null)
const currentAudioRecording = ref(null)
const isRecordingDownloading = ref(false)
const isRecordingPlaying = ref(false)
const isRecordingUpdating = ref(false)
const recordingLabel = ref(null)
const selectedRecording = ref(undefined)

const weekdayOptions = [
    { value: 1, label: 'M' },
    { value: 2, label: 'T' },
    { value: 3, label: 'W' },
    { value: 4, label: 'T' },
    { value: 5, label: 'F' },
    { value: 6, label: 'S' },
    { value: 7, label: 'S' },
]

const busyExtensionBehaviorOptions = [
    { value: 'skip', label: 'Skip' },
    { value: 'force', label: 'Force' },
]

const timezoneOptions = computed(() => props.options?.timezones ?? [])

const extensionOptions = computed(() => (props.options?.extensions ?? []).map((item) => ({
    value: item.extension_uuid,
    extension: item.extension,
    label: item.effective_caller_id_name
        ? `${item.extension} - ${item.effective_caller_id_name}`
        : item.extension,
})))

const defaultValues = computed(() => ({
    name: props.options?.item?.name ?? null,
    description: props.options?.item?.description ?? null,
    timezone: props.options?.item?.timezone ?? props.options?.timezone ?? 'UTC',
    recording_filename: props.options?.item?.recording_filename ?? null,
    busy_extension_behavior: props.options?.item?.busy_extension_behavior ?? 'skip',
    selectedExtensions: props.options?.item?.extension_uuids ?? [],
    starts_on: normalizeDate(props.options?.item?.starts_on),
    ends_on: normalizeDate(props.options?.item?.ends_on),
    enabled: props.options?.item?.enabled ?? true,
    events: normalizedEvents.value,
    exceptions: (props.options?.item?.exceptions ?? []).map((exception) => ({
        exception_date: normalizeDate(exception.exception_date),
        comment: exception.comment,
    })),
}))

const blankEvent = () => ({
    time_of_day: null,
    weekdays: [],
})

const normalizedEvents = computed(() => {
    const events = (props.options?.item?.events ?? []).map((event) => ({
        time_of_day: normalizeTime(event.time_of_day),
        weekdays: event.weekdays ?? [],
    }))

    return events.length ? events : [blankEvent()]
})

const recordingRoutes = computed(() => ({
    ...props.options?.routes,
    text_to_speech_route: props.options?.routes?.text_to_speech_route ?? null,
    upload_greeting_route: props.options?.routes?.upload_greeting_route ?? null,
}))

const fetchRecordings = async () => {
    if (!props.options?.routes?.greeting_route) {
        recordingOptions.value = props.options?.recordings ?? []
        return recordingOptions.value
    }

    try {
        const response = await axios.get(props.options.routes.greeting_route)
        recordingOptions.value = response.data.filter((item) => item.value !== '0')
    } catch (error) {
        emit('error', error)
        recordingOptions.value = props.options?.recordings ?? []
    }

    return recordingOptions.value
}

const recordingValue = (value) => {
    if (value && typeof value === 'object') {
        return value.value ?? value.recording_filename ?? value.file_name ?? null
    }

    return value ?? null
}

const handleRecordingChange = (value, oldValue, el$) => {
    selectedRecording.value = recordingValue(value ?? el$?.value) ?? ''
}

const getScheduleRecording = () => {
    const elementValue = recordingValue(form$.value?.el$('recording_filename')?.value)
    if (elementValue) {
        return elementValue
    }

    if (selectedRecording.value !== undefined) {
        return recordingValue(selectedRecording.value)
    }

    return recordingValue(form$.value?.data?.recording_filename)
        ?? recordingValue(props.options?.item?.recording_filename)
}

const getSelectedRecordingItem = () => {
    const recording = getScheduleRecording()
    if (!recording) return null

    return [
        ...(recordingOptions.value ?? []),
        ...(props.options?.recordings ?? []),
    ].find((item) => String(item.value) === String(recording)) ?? null
}

const hasPlayableRecording = () => {
    const val = getScheduleRecording()

    return val !== '0' && val !== '-1' && val !== null && val !== '' && val !== undefined
}

const recordingUrl = (recording, download = false) => {
    if (!recording || !props.options?.routes?.serve_greeting_route) {
        return null
    }

    const url = props.options.routes.serve_greeting_route.replace(':file_name', encodeURIComponent(recording))

    return download ? `${url}?download=true&v=${Date.now()}` : url
}

const stopRecordingAudio = () => {
    if (currentAudio.value) {
        currentAudio.value.pause()
        currentAudio.value.currentTime = 0
        currentAudio.value = null
    }

    isRecordingPlaying.value = false
    currentAudioRecording.value = null
}

watch(
    () => [props.show, props.options?.item?.recording_filename],
    ([show, recording]) => {
        if (show) {
            selectedRecording.value = recording ?? undefined
        } else {
            stopRecordingAudio()
        }
    },
    { immediate: true }
)

const playRecording = () => {
    const recording = getScheduleRecording()
    const fileUrl = recordingUrl(recording)
    if (!fileUrl) return

    if (currentAudio.value && currentAudioRecording.value === recording) {
        if (currentAudio.value.paused) {
            currentAudio.value.play()
            isRecordingPlaying.value = true
        }
        return
    }

    stopRecordingAudio()

    currentAudio.value = new Audio(fileUrl)
    currentAudioRecording.value = recording
    isRecordingPlaying.value = true

    currentAudio.value.play().catch(() => {
        stopRecordingAudio()
        emit('error', { message: 'Audio playback failed' })
    })

    currentAudio.value.addEventListener('ended', stopRecordingAudio)
    currentAudio.value.addEventListener('error', () => {
        stopRecordingAudio()
        emit('error', { message: 'File not found or failed to load audio' })
    })
}

const pauseRecording = () => {
    if (currentAudio.value) {
        currentAudio.value.pause()
        isRecordingPlaying.value = false
    }
}

const downloadRecording = () => {
    const recording = getScheduleRecording()
    const downloadUrl = recordingUrl(recording, true)
    if (!downloadUrl) return

    isRecordingDownloading.value = true
    const link = document.createElement('a')
    link.href = downloadUrl
    link.download = recording || 'announcement.wav'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    isRecordingDownloading.value = false
}

const editRecording = () => {
    const selectedItem = getSelectedRecordingItem()
    if (!selectedItem) return

    recordingLabel.value = selectedItem
    showEditModal.value = true
}

const handleRecordingUpdate = async (updatedRecording) => {
    const newName = updatedRecording?.label?.trim()

    if (!newName) {
        emit('error', {
            response: {
                data: {
                    errors: {
                        request: ['Recording name cannot be empty.'],
                    },
                },
            },
        })
        return
    }

    if (!props.options?.routes?.update_greeting_route) return

    isRecordingUpdating.value = true

    try {
        const response = await axios.post(props.options.routes.update_greeting_route, {
            file_name: updatedRecording.value,
            new_name: updatedRecording.label,
        })

        if (response.data.success) {
            form$.value?.el$('recording_filename')?.clear()
            await form$.value?.el$('recording_filename')?.updateItems()
            selectedRecording.value = updatedRecording.value
            form$.value?.update({ recording_filename: updatedRecording.value })
            emit('success', 'success', response.data.messages)
            emit('refresh-data')
        }
    } catch (error) {
        emit('error', error)
    } finally {
        isRecordingUpdating.value = false
        showEditModal.value = false
    }
}

const deleteRecording = () => {
    stopRecordingAudio()
    showRecordingDeleteConfirmationModal.value = true
}

const confirmRecordingDeleteAction = async () => {
    const fileName = getScheduleRecording()

    if (!fileName) {
        showRecordingDeleteConfirmationModal.value = false
        return
    }

    if (!props.options?.routes?.delete_greeting_route) return

    try {
        const response = await axios.post(props.options.routes.delete_greeting_route, { file_name: fileName })

        if (response.data.success) {
            stopRecordingAudio()

            if (recordingOptions.value) {
                recordingOptions.value = recordingOptions.value.filter(
                    (recording) => String(recording.value) !== String(fileName)
                )
            }

            form$.value?.update({ recording_filename: null })
            selectedRecording.value = ''
            await form$.value?.el$('recording_filename')?.updateItems()
            emit('success', 'success', response.data.messages)
            emit('refresh-data')
        }
    } catch (error) {
        emit('error', error)
    } finally {
        showRecordingDeleteConfirmationModal.value = false
    }
}

const submitForm = async (FormData, form$) => {
    const requestData = {
        ...form$.requestData,
        extension_uuids: (form$.el$('selectedExtensions')?.value ?? form$.requestData.selectedExtensions ?? [])
            .filter(Boolean),
        starts_on: form$.requestData.starts_on || null,
        ends_on: form$.requestData.ends_on || null,
        events: (form$.requestData.events ?? []).filter((event) => {
            return event?.time_of_day
                || (event?.weekdays ?? []).length
        }),
        exceptions: (form$.requestData.exceptions ?? []).filter((exception) => {
            return exception?.exception_date || exception?.comment
        }),
    }
    delete requestData.selectedExtensions

    return props.mode === 'create'
        ? await form$.$vueform.services.axios.post(props.options.routes.store_route, requestData)
        : await form$.$vueform.services.axios.put(props.options.routes.update_route, requestData)
}

function normalizeTime(value) {
    if (!value) return null

    const match = String(value).match(/^(\d{1,2}):(\d{2})/)
    if (!match) return String(value)

    const hour = Number(match[1])
    const minute = match[2]
    const suffix = hour >= 12 ? 'PM' : 'AM'
    const displayHour = ((hour + 11) % 12) + 1

    return `${String(displayHour).padStart(2, '0')}:${minute} ${suffix}`
}

function normalizeDate(value) {
    return value ? String(value).slice(0, 10) : null
}

function clearErrorsRecursive(el$) {
    el$.messageBag?.clear()
    if (el$.children$) Object.values(el$.children$).forEach((childEl$) => clearErrorsRecursive(childEl$))
}

const handleResponse = (response, form$) => {
    Object.values(form$.elements$).forEach((el$) => clearErrorsRecursive(el$))
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            form$.el$(elName)?.messageBag.append(response.data.errors[elName][0])
        })
    }
}

const handleSuccess = (response) => {
    emit('success', 'success', response.data.messages)
    emit('refresh-data')
    handleClose()
}

const handleError = (error, details, form$) => {
    form$?.messageBag?.clear()
    if (details?.type === 'submit') emit('error', error)
    else form$?.messageBag?.append('Could not submit form')
}

const handleClose = () => {
    stopRecordingAudio()
    emit('close')
}

onBeforeUnmount(() => {
    stopRecordingAudio()
})

const handleNewRecordingAdded = async (recording) => {
    showNewRecordingModal.value = false
    recordingOptions.value = null
    await fetchRecordings()
    if (recording) {
        form$.value?.el$('recording_filename')?.updateItems()
        selectedRecording.value = recording
        form$.value?.update({ recording_filename: recording })
    }
    emit('refresh-data')
}
</script>
