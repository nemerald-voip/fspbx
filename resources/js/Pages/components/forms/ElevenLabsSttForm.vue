<template>
    <Skeleton v-if="isFormLoading" />

    <div v-show="!isFormLoading" class="flex flex-col xl:flex-row">
        <div class="basis-3/4">
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                @response="handleResponse" :display-errors="false">

                <template #empty>
                    <div class="space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <HiddenElement name="domain_uuid" :meta="true" />

                            <StaticElement name="h_general" tag="h4" content="ElevenLabs Speech-to-Text Settings" />

                            <StaticElement v-if="isInheriting" name="inherited_notice" tag="div" :add-classes="{
                                StaticElement: { container: 'rounded-md border border-yellow-200 bg-yellow-50 p-3' }
                            }" :columns="{ lg: { container: 5 } }">
                                <template #default>
                                    <div class="flex items-start gap-3" role="status" aria-live="polite">
                                        <ExclamationTriangleIcon class="size-5 text-yellow-500 shrink-0"
                                            aria-hidden="true" />
                                        <div class="text-sm text-yellow-900">
                                            <p class="font-medium">
                                                No custom options set. Your account is using the system defaults.
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </StaticElement>

                            <SelectElement name="model_id" :items="[
                                { value: 'scribe_v2', label: 'Scribe V2 (recommended)' },
                                { value: 'scribe_v1', label: 'Scribe V1' },
                            ]" :search="true" :native="false" label="STT Model"
                                input-type="search" autocomplete="off"
                                :columns="{ lg: { wrapper: 5 } }"
                                description="The speech recognition model to use."
                                default="scribe_v2"
                                :conditions="[() => !canEdit]" />

                            <TextElement name="language_code" label="Language Code"
                                description="ISO-639-1 or ISO-639-3 language code (e.g. en, fr, de). Leave blank for auto-detection."
                                placeholder="Optional"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit]" />

                            <ToggleElement name="diarize" text="Enable Speaker Diarization"
                                description="Identify and label different speakers in the audio."
                                :conditions="[() => !canEdit]" />

                            <TextElement name="num_speakers" label="Number of Speakers"
                                description="Expected number of speakers (1-32). Leave blank for auto-detection."
                                placeholder="Optional"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit]" />

                            <SelectElement name="timestamps_granularity" :items="[
                                { value: 'word', label: 'Word' },
                                { value: 'character', label: 'Character' },
                                { value: 'none', label: 'None' },
                            ]" :search="true" :native="false" label="Timestamps Granularity"
                                input-type="search" autocomplete="off"
                                :columns="{ lg: { wrapper: 5 } }"
                                description="Level of timestamp detail in the transcript."
                                default="word"
                                :conditions="[() => !canEdit]" />

                            <TextElement name="keyterms" label="Key Terms"
                                description="Comma-separated list of domain-specific terms to improve accuracy (up to 1000)."
                                placeholder="Optional"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit]" />

                            <ToggleElement name="tag_audio_events" text="Tag Audio Events"
                                description="Detect non-speech audio events like music, laughter, applause."
                                :conditions="[() => !canEdit]" />

                            <GroupElement name="container" :conditions="[() => !canEdit]" />

                            <StaticElement name="actions_row" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12 !mb-0' },
                                StaticElement: { container: 'mt-4' }
                            }">
                                <template #default>
                                    <div class="flex justify-start gap-3">
                                        <ButtonElement v-if="showOverrideBtn" name="overrideDefaults" :secondary="true"
                                            button-label="Override Defaults" @click="startOverride" />

                                        <ButtonElement v-if="showSaveBtn" name="save" button-label="Save"
                                            :submits="true" />

                                        <ButtonElement v-if="showRevertBtn" name="revertDefaults" :secondary="true"
                                            button-label="Revert to Defaults" @click="revertToDefaults" />

                                        <ButtonElement v-if="showCancelBtn" name="cancelOverride" :secondary="true"
                                            button-label="Cancel" @click="cancelOverride" />
                                    </div>
                                </template>
                            </StaticElement>

                        </FormElements>
                    </div>
                </template>
            </Vueform>
        </div>

        <div class="basis-1/4 xl:pl-6 mt-8 xl:mt-0">
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import Skeleton from "@generalComponents/Skeleton.vue"
import { ExclamationTriangleIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  domain_uuid: String,
  routes: Object,
})

const emit = defineEmits(['error', 'success'])

const form$ = ref(null)
const elevenLabsConfig = ref({})
const isFormLoading = ref(false)
const isOverride = ref(false)

const isInheriting = computed(() =>
  elevenLabsConfig.value?.scope === 'system' && !!elevenLabsConfig.value?.domain_uuid
)

const hasDomainOverride = computed(() =>
  elevenLabsConfig.value?.scope === 'domain' && !!elevenLabsConfig.value?.domain_uuid
)

const showOverrideBtn = computed(() => isInheriting.value && !isOverride.value)

const showSaveBtn = computed(() =>
  !props.domain_uuid || hasDomainOverride.value || isOverride.value
)

const showRevertBtn = computed(() => hasDomainOverride.value)
const showCancelBtn = computed(() => isOverride.value && isInheriting.value)

const canEdit = computed(() => {
  if (!props.domain_uuid) return false
  if (hasDomainOverride.value) return false
  return !isOverride.value
})

function startOverride() {
  isOverride.value = true
}

async function revertToDefaults() {
  if (!props.domain_uuid) return

  await axios.delete(props.routes.elevenlabs_route, {
    data: { domain_uuid: props.domain_uuid }
  })

  isOverride.value = false
  await getElevenLabsConfig()
}

function cancelOverride() {
  isOverride.value = false
  form$.value.update(elevenLabsConfig.value ?? {})
}

onMounted(() => {
  getElevenLabsConfig()
})

const getElevenLabsConfig = async () => {
  isFormLoading.value = true
  try {
    const { data } = await axios.get(
      props.routes.elevenlabs_route,
      { params: { domain_uuid: props.domain_uuid ?? null } }
    )

    elevenLabsConfig.value = data ?? {}
    form$.value.update(elevenLabsConfig.value)

    if (hasDomainOverride.value) isOverride.value = false

    return data
  } catch (err) {
    emit('error', err)
    elevenLabsConfig.value = {}
    return {}
  } finally {
    isFormLoading.value = false
  }
}

const submitForm = async (FormData, form$) => {
  const requestData = form$.requestData
  return await form$.$vueform.services.axios.post(props.routes.elevenlabs_store_route, requestData)
}

function clearErrorsRecursive(el$) {
  el$.messageBag?.clear()
  if (el$.children$) {
    Object.values(el$.children$).forEach(childEl$ => clearErrorsRecursive(childEl$))
  }
}

const handleResponse = (response, form$) => {
  Object.values(form$.elements$).forEach(el$ => clearErrorsRecursive(el$))

  if (response.data.errors) {
    Object.keys(response.data.errors).forEach((elName) => {
      if (form$.el$(elName)) {
        form$.el$(elName).messageBag.append(response.data.errors[elName][0])
      }
    })
  }
}

const handleSuccess = (response, form$) => {
  emit('success', 'success', response.data.messages)
  isOverride.value = false
  getElevenLabsConfig()
}

const handleError = (error, details, form$) => {
  form$.messageBag.clear()

  switch (details.type) {
    case 'prepare':
      console.log(error)
      form$.messageBag.append('Could not prepare form')
      break
    case 'submit':
      emit('error', error)
      console.log(error)
      break
    case 'cancel':
      console.log(error)
      form$.messageBag.append('Request cancelled')
      break
    case 'other':
      console.log(error)
      form$.messageBag.append("Couldn't submit form")
      break
  }
}
</script>
