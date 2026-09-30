<template>
    <Skeleton v-if="isFormLoading" />

    <div v-show="!isFormLoading" class="flex flex-col xl:flex-row">
        <div class="basis-3/4">
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                @response="handleResponse" :display-errors="false" @mounted="(form) => form.disableValidation()" @submit="clearServerFormErrors">

                <template #empty>
                    <div class="space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <HiddenElement name="domain_uuid" :meta="true" />

                            <!-- 1) General & Language Settings -->
                            <StaticElement name="h_general" tag="h4" :content="$t('AssemblyAI General & Language Settings')" />

                            <StaticElement v-if="isInheriting" name="inherited_notice" tag="div" :add-classes="{
                                StaticElement: { container: 'rounded-md border border-yellow-200 bg-yellow-50 p-3' }
                            }" :columns="{ lg: { container: 5 } }">
                                <template #default>
                                    <div class="flex items-start gap-3" role="status" aria-live="polite">
                                        <ExclamationTriangleIcon class="size-5 text-yellow-500 shrink-0"
                                            aria-hidden="true" />
                                        <div class="text-sm text-yellow-900">
                                            <p class="font-medium">
                                                {{ $t('No custom options set. Your account is using the system defaults.') }}
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </StaticElement>

                        <TagsElement
                            name="speech_models"
                            :close-on-select="false"
                            :search="true"
                            :items="[
                                { value: 'universal-3-pro', label: 'Universal 3 Pro' },
                                { value: 'universal-2', label: 'Universal 2' },
                            ]"
                            :label="$t('Speech Models')"
                            input-type="search"
                            autocomplete="off"
                            :floating="false"
                            :columns="{ lg: { wrapper: 5 } }"
                            :description="$t('The Speech Models parameter lets you specify which model to use for transcription. You can provide multiple models in priority order, and AssemblyAI system will automatically route to the best available model based on your request.')"
                            :conditions="[() => !canEdit ]"
                        />
                            <TextElement name="language_code" :label="$t('Language Code')"
                                :description="$t('The language of your audio file. Default: en_us.')" :placeholder="$t('Optional')"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="keyterms_prompt" :label="$t('Key terms')"
                                :description="$t('Up to 200 (Universal) or 1000 (Slam-1) domain terms; max 6 words per phrase.')"
                                :placeholder="$t('Optional. List of strings')" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="multichannel" :text="$t('Enable Multichannel transcription')" :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g1" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />

                            <!-- 2) Language Detection -->
                            <StaticElement name="h_lang_detect" tag="h4" :content="$t('Language Detection')" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="language_detection" :text="$t('Enable Automatic language detection')"
                                :default="true" :true-value="true" :false-value="false"
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="language_confidence_threshold" :label="$t('Language Confidence Threshold')"
                                :description="$t('Error if detected language confidence falls below this threshold. Default: 0.')"
                                :placeholder="$t('Optional')" :floating="false" :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />
                            <ObjectElement name="language_detection_options" :columns="{ lg: { wrapper: 6 } }"
                                :conditions="[() => !canEdit ]"
                                :add-classes="{
                                    ElementLayout: {
                                        innerWrapper: 'relative mt-2 rounded-lg border border-gray-200 bg-gray-50/60 p-4 lg:p-5 pl-5',
                                    }
                                }">
                                <StaticElement name="ldo_header" tag="h4"
                                    :content="$t('Language Detection Options (Optional)')" />

                                <StaticElement name="ldo_stripe" :content="''"
                                    :add-classes="{ StaticElement: { container: 'pointer-events-none absolute left-0 top-0 h-full w-1 rounded-l-lg bg-indigo-500' } }" />

                                <TextElement name="expected_languages" :label="$t('Expected Languages')"
                                    :columns="{ lg: { wrapper: 6 } }" />
                                <TextElement name="fallback_language" :label="$t('Fallback Language')"
                                    :columns="{ lg: { wrapper: 6 } }" />

                                <ToggleElement name="code_switching" :text="$t('Code Switching')" />
                                <TextElement name="code_switching_confidence_threshold"
                                    :label="$t('Code Switching Confidence Threshold')" :columns="{ lg: { wrapper: 6 } }" />
                            </ObjectElement>

                            <TextElement name="language_codes" :label="$t('Language Codes')"
                                :description="$t('For code-switching. One value must be \'en\'.')" :placeholder="$t('Optional')"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g2" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />


                            <!-- 5) Speaker Identification -->
                            <StaticElement name="h_speakers" tag="h4" :content="$t('Speaker Identification')" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="speaker_labels" :text="$t('Enable Speaker diarization')" :default="true"
                                :true-value="true" :false-value="false"
                                :conditions="[() => !canEdit ]" />
                            <ObjectElement name="speaker_options" :columns="{ lg: { wrapper: 6 } }"
                                :conditions="[() => !canEdit ]"
                                :add-classes="{
                                ElementLayout: {
                                    innerWrapper: 'relative mt-2 rounded-lg border border-gray-200 bg-gray-50/60 p-4 lg:p-5 pl-5',
                                }
                            }">

                                <StaticElement name="sd_header" tag="h4"
                                    :content="$t('Speaker Diarization Options (Optional)')" :conditions="[() => !canEdit ]" />

                                <StaticElement name="sd_stripe" :content="''"
                                    :add-classes="{ StaticElement: { container: 'pointer-events-none absolute left-0 top-0 h-full w-1 rounded-l-lg bg-indigo-500' } }"
                                    :conditions="[() => !canEdit ]" />

                                <TextElement name="min_speakers_expected" :label="$t('Minimum Speakers Expected')"
                                    :description="$t('Default: 1')" :floating="false" :placeholder="$t('Optional')"
                                    :columns="{ lg: { wrapper: 5 } }"
                                    :conditions="[() => !canEdit ]" />
                                <TextElement name="max_speakers_expected" :label="$t('Maximum Speakers Expected')"
                                    :description="$t('Default: 10. Setting too high may reduce accuracy.')"
                                    :placeholder="$t('Optional')" :floating="false" :columns="{ lg: { wrapper: 5 } }"
                                    :conditions="[() => !canEdit ]" />
                            </ObjectElement>
                            <TextElement name="speakers_expected" :label="$t('Number of Expected Speakers')"
                                :description="$t('Tell the diarization model how many speakers to identify.')"
                                :placeholder="$t('Optional')" :floating="false" :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g5" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />

                            <!-- 8) Formatting & Customization -->
                            <StaticElement name="h_formatting" tag="h4" :content="$t('Formatting & Customization')" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="format_text" :text="$t('Enable Text Formatting')" :default="true"
                                :true-value="true" :false-value="false"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="punctuate" :text="$t('Enable Automatic Punctuation')" :default="true"
                                :true-value="true" :false-value="false"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="disfluencies" :text="$t('Transcribe filler words (e.g., “umm”)')" :conditions="[() => !canEdit ]" />

                            <GroupElement name="container" :conditions="[() => !canEdit ]" />

                            <ListElement name="custom_spelling" :initial="0"
                                :conditions="[() => !canEdit ]"
                                :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                <template #label="{ el$ }">
                                    <ElementLabel :for="el$._id" class="flex items-center gap-1">
                                        <span class="text-lg font-semibold text-gray-600">{{ $t('Custom Spelling') }}
                                        </span>
                                        <span class="text-sm font-medium text-gray-500"> {{ $t('(Optional)') }}</span>
                                    </ElementLabel>
                                </template>

                                <template #default="{ index }">
                                    <ObjectElement :name="index">
                                        <TextElement name="from" :label="$t('From')" :columns="{ sm: { container: 6 } }"
                                            :description="$t('Word/phrase')" />
                                        <TextElement name="to" :label="$t('To')" :columns="{ sm: { container: 6 } }"
                                            :description="$t('Replacement')" />
                                    </ObjectElement>
                                </template>
                            </ListElement>

                            <GroupElement name="container2" :conditions="[() => !canEdit ]" />

                            <TextElement name="audio_start_from" :label="$t('Audio Start From (ms)')"
                                :description="$t('Start time in milliseconds.')" :placeholder="$t('Optional')" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="audio_end_at" :label="$t('Audio End At (ms)')"
                                :description="$t('End time in milliseconds.')" :placeholder="$t('Optional')" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g4" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />

                            <!-- 6) Content Moderation & Safety -->
                            <StaticElement name="h_safety" tag="h4" :content="$t('Content Moderation & Safety')"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="content_safety" :text="$t('Enable Content Moderation')"
                                :description="$t('Detect sensitive content and severity.')"
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="content_safety_confidence" :label="$t('Content Safety Confidence')"
                                :description="$t('25–100. Default: 50.')" :placeholder="$t('Optional')" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="filter_profanity" :text="$t('Filter profanity from the transcribed text')"
                                :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g6" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />

                            <!-- 7) PII Redaction -->
                            <StaticElement name="h_pii" tag="h4" :content="$t('PII Redaction')" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="redact_pii" :text="$t('Redact PII in transcribed text')" :conditions="[() => !canEdit ]" />

                            <TagsElement name="redact_pii_policies" :close-on-select="false" :search="true" :items="[
                                { value: 'account_number', label: $t('Account Number') },
                                { value: 'banking_information', label: $t('Banking Information') },
                                { value: 'blood_type', label: $t('Blood Type') },
                                { value: 'credit_card_cvv', label: $t('Credit Card CVV') },
                                { value: 'credit_card_expiration', label: $t('Credit Card Expiration') },
                                { value: 'credit_card_number', label: $t('Credit Card Number') },
                                { value: 'date', label: $t('Date') },
                                { value: 'date_interval', label: $t('Date Interval') },
                                { value: 'date_of_birth', label: $t('Date of Birth') },
                                { value: 'drivers_license', label: $t('Drivers License') },
                                { value: 'drug', label: $t('Drug') },
                                { value: 'duration', label: $t('Duration') },
                                { value: 'email_address', label: $t('Email Address') },
                                { value: 'event', label: $t('Event') },
                                { value: 'filename', label: $t('Filename') },
                                { value: 'gender_sexuality', label: $t('Gender / Sexuality') },
                                { value: 'healthcare_number', label: $t('Healthcare Number') },
                                { value: 'injury', label: $t('Injury') },
                                { value: 'ip_address', label: $t('IP Address') },
                                { value: 'language', label: $t('Language') },
                                { value: 'location', label: $t('Location') },
                                { value: 'marital_status', label: $t('Marital Status') },
                                { value: 'medical_condition', label: $t('Medical Condition') },
                                { value: 'medical_process', label: $t('Medical Process') },
                                { value: 'money_amount', label: $t('Money Amount') },
                                { value: 'nationality', label: $t('Nationality') },
                                { value: 'number_sequence', label: $t('Number Sequence') },
                                { value: 'occupation', label: $t('Occupation') },
                                { value: 'organization', label: $t('Organization') },
                                { value: 'passport_number', label: $t('Passport Number') },
                                { value: 'password', label: $t('Password') },
                                { value: 'person_age', label: $t('Person Age') },
                                { value: 'person_name', label: $t('Person Name') },
                                { value: 'phone_number', label: $t('Phone Number') },
                                { value: 'physical_attribute', label: $t('Physical Attribute') },
                                { value: 'political_affiliation', label: $t('Political Affiliation') },
                                { value: 'religion', label: $t('Religion') },
                                { value: 'statistics', label: $t('Statistics') },
                                { value: 'time', label: $t('Time') },
                                { value: 'url', label: 'URL' },
                                { value: 'us_social_security_number', label: $t('US Social Security Number') },
                                { value: 'username', label: $t('Username') },
                                { value: 'vehicle_id', label: $t('Vehicle ID') },
                                { value: 'zodiac_sign', label: $t('Zodiac Sign') },
                            ]" :label="$t('PII Redaction Policies')" input-type="search" autocomplete="off" :floating="false"
                                :description="$t('List of policies to enable.')" :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />

                            <SelectElement name="redact_pii_sub" :items="[
                                { value: 'entity_name', label: $t('Entity Name') },
                                { value: 'hash', label: $t('Hash') },
                            ]" :search="true" :native="false" :label="$t('Replacement Logic for Detected PII')"
                                input-type="search" autocomplete="off" :columns="{ lg: { wrapper: 5 } }"
                                :description="$t('Optional')"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="redact_pii_audio" :text="$t('Redact PII in audio (beeped out)')" :conditions="[() => !canEdit ]" />
                            <SelectElement name="redact_pii_audio_quality" :items="[
                                { value: 'mp3', label: 'MP3' },
                                { value: 'wav', label: 'WAV' },
                            ]" :search="true" :native="false" :label="$t('Redacted Audio Quality')" input-type="search"
                                autocomplete="off" :columns="{ lg: { wrapper: 5 } }"
                                :description="$t('Filetype for generated redacted audio.')" default="mp3"
                                :conditions="[() => !canEdit ]" />
                            <ObjectElement name="redact_pii_audio_options" :columns="{ lg: { wrapper: 6 } }"
                                :conditions="[() => !canEdit ]"
                                :add-classes="{
                                    ElementLayout: {
                                        innerWrapper: 'relative mt-2 rounded-lg border border-gray-200 bg-gray-50/60 p-4 lg:p-5 pl-5',
                                    }
                                }">
                                <StaticElement name="pii_header" tag="h4" :content="$t('Redacted Audio Options (Optional)')" />

                                <StaticElement name="pii_stripe" :content="''"
                                    :add-classes="{ StaticElement: { container: 'pointer-events-none absolute left-0 top-0 h-full w-1 rounded-l-lg bg-indigo-500' } }" />

                                <ToggleElement name="return_redacted_no_speech_audio"
                                    :text="$t('Return redacted audio even when there is no speech')"
                                    :description="$t('By default, URLs are returned only when speech is detected.')" />
                            </ObjectElement>

                            <StaticElement name="div_g7" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />


                            <!-- 3) Content Intelligence & Analysis -->
                            <StaticElement name="h_content_intel" tag="h4" :content="$t('Content Intelligence & Analysis')" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="auto_chapters" :text="$t('Enable Auto Chapters')"
                                :description="$t('Summarizes audio into chapters for navigation.')"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="auto_highlights" :text="$t('Enable Key Phrases')" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="entity_detection" :text="$t('Enable Entity Detection')"
                                :description="$t('Detect names, orgs, addresses, phone numbers, medical data, SSNs, etc.')"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="sentiment_analysis" :text="$t('Enable Sentiment Analysis')"
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="iab_categories" :text="$t('Enable Topic Detection')"
                                :description="$t('Identifies topics using the IAB Content Taxonomy.')"
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="topics" :label="$t('Topics')" :description="$t('Custom topics.')"
                                :placeholder="$t('Optional. List of strings')" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }"
                                :conditions="[() => !canEdit ]" />

                            <!-- <StaticElement name="div_g3" tag="hr" top="2" bottom="2" /> -->

                            <!-- 4) Summarization -->
                            <!-- <StaticElement name="h_summarization" tag="h4" content="Summarization" />
                            <ToggleElement name="summarization" text="Enable Summarization" />
                            <SelectElement name="summary_model" :items="[
                                { value: 'informative', label: 'Informative' },
                                { value: 'conversational', label: 'Conversational' },
                                { value: 'catchy', label: 'Catchy' },
                            ]" :search="true" :native="false" label="Summary Model" input-type="search"
                                autocomplete="off" :columns="{ lg: { wrapper: 5 } }" />
                            <SelectElement name="summary_type" :items="[
                                { value: 'bullets', label: 'Bullets' },
                                { value: 'bullets_verbose', label: 'Bullets Verbose' },
                                { value: 'gist', label: 'Gist' },
                                { value: 'headline', label: 'Headline' },
                                { value: 'paragraph', label: 'Paragraph' },
                            ]" :search="true" :native="false" label="Summary Type" input-type="search"
                                autocomplete="off" :columns="{ lg: { wrapper: 5 } }" /> -->


                            <GroupElement name="container" :conditions="[() => !canEdit ]" />

                            <!-- <ButtonElement name="save" button-label="Save" :submits="true" /> -->

                            <StaticElement name="actions_row" tag="div" :add-classes="{
                                ElementLayout: { outerWrapper: 'col-span-12 !mb-0' },
                                StaticElement: { container: 'mt-4' }
                            }">
                                <template #default>
                                    <div class="flex justify-start gap-3">
                                        <ButtonElement v-if="showOverrideBtn" name="overrideDefaults" :secondary="true"
                                            :button-label="$t('Override Defaults')" @click="startOverride" />

                                        <ButtonElement v-if="showSaveBtn" name="save" :button-label="$t('Save')"
                                            :submits="true" />

                                        <ButtonElement v-if="showRevertBtn" name="revertDefaults" :secondary="true"
                                            :button-label="$t('Revert to Defaults')" @click="revertToDefaults" />

                                        <ButtonElement v-if="showCancelBtn" name="cancelOverride" :secondary="true"
                                            :button-label="$t('Cancel')" @click="cancelOverride" />
                                    </div>
                                </template>
                            </StaticElement>



                        </FormElements>
                    </div>
                </template>
            </Vueform>
        </div>

        <!-- Right rail for help, previews, or saved presets -->
        <div class="basis-1/4 xl:pl-6 mt-8 xl:mt-0">
            <!-- (Optional) You can add contextual help or a live JSON preview here -->
        </div>
    </div>
</template>



<script setup>
import { clearServerFormErrors } from '../../../composables/serverFormErrors.js';
import { trans } from '@i18n';
import { ref, onMounted, computed } from 'vue'
import Skeleton from "@generalComponents/Skeleton.vue"
import { ExclamationTriangleIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  domain_uuid: String,
  routes: Object,
})

const emit = defineEmits(['error', 'success'])

const form$ = ref(null)
const assemblyAiConfig = ref({})
const isFormLoading = ref(false)
const isOverride = ref(false)


// inheriting means: you are on a domain page and API says effective scope is system
const isInheriting = computed(() =>
  assemblyAiConfig.value?.scope === 'system' && !!assemblyAiConfig.value?.domain_uuid
)

// “there is a saved domain override row”
const hasDomainOverride = computed(() =>
  assemblyAiConfig.value?.scope === 'domain' && !!assemblyAiConfig.value?.domain_uuid
)

// buttons logic
const showOverrideBtn = computed(() => isInheriting.value && !isOverride.value)

const showSaveBtn = computed(() =>
  // system page OR (editing domain w/ override) OR (started override)
  !props.domain_uuid || hasDomainOverride.value || isOverride.value
)

const showRevertBtn = computed(() => hasDomainOverride.value)
const showCancelBtn = computed(() => isOverride.value && isInheriting.value)

const canEdit = computed(() => {
  // System page: editable
  if (!props.domain_uuid) return false
  // Domain with saved override: editable
  if (hasDomainOverride.value) return false
  // Domain inheriting: disable until they click Override
  return !isOverride.value
})

function startOverride() {
  isOverride.value = true
}

async function revertToDefaults() {
  if (!props.domain_uuid) return

  // Assumes GET+DELETE share the same route like your other component did.
  // If you have a dedicated delete route, swap it in here.
  await axios.delete(props.routes.assemblyai_route, {
    data: { domain_uuid: props.domain_uuid }
  })

  isOverride.value = false
  await getAssemblyAiConfig()
}

function cancelOverride() {
  isOverride.value = false
  // reset the form to current effective values (still inheriting)
  form$.value.update(assemblyAiConfig.value ?? {})
}


onMounted(() => {
  getAssemblyAiConfig()
})

const getAssemblyAiConfig = async () => {
  isFormLoading.value = true
  try {
    const { data } = await axios.get(
      props.routes.assemblyai_route,
      { params: { domain_uuid: props.domain_uuid ?? null } }
    )

    assemblyAiConfig.value = data ?? {}
    form$.value.update(assemblyAiConfig.value)

    // If config says "domain override exists", user shouldn’t be in override-pending state.
    if (hasDomainOverride.value) isOverride.value = false

    return data
  } catch (err) {
    emit('error', err)
    assemblyAiConfig.value = {}
    return {}
  } finally {
    isFormLoading.value = false
  }
}

const submitForm = async (FormData, form$) => {
  const requestData = form$.requestData
  return await form$.$vueform.services.axios.post(props.routes.assemblyai_store_route, requestData)
}

// -------------------------
// Your existing error handling
// -------------------------
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

  // after save, we now have a domain override row
  isOverride.value = false
  getAssemblyAiConfig()
}

const handleError = (error, details, form$) => {
  form$.messageBag.clear()

  switch (details.type) {
    case 'prepare':
      console.log(error)
      form$.messageBag.append(trans('Could not prepare form'))
      break
    case 'submit':
      emit('error', error)
      console.log(error)
      break
    case 'cancel':
      console.log(error)
      form$.messageBag.append(trans('Request cancelled'))
      break
    case 'other':
      console.log(error)
      form$.messageBag.append(trans('Couldn\'t submit form'))
      break
  }
}
</script>
