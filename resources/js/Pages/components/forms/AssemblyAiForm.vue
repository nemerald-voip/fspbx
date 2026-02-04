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

                            <!-- 1) General & Language Settings -->
                            <StaticElement name="h_general" tag="h4" content="AssemblyAI General & Language Settings" />

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

                            <SelectElement name="speech_model" :items="[
                                { value: 'best', label: 'Best' },
                                { value: 'slam-1', label: 'Slam-1' },
                                { value: 'universal', label: 'Universal' },
                            ]" :search="true" :native="false" label="Speech Model" input-type="search"
                                autocomplete="off" :columns="{ lg: { wrapper: 5 } }"
                                description="The speech model to use for the transcription. When null, the 'universal' model is used." 
                                  :conditions="[() => !canEdit ]" />
                            <TextElement name="language_code" label="Language Code"
                                description="The language of your audio file. Default: en_us." placeholder="Optional"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="keyterms_prompt" label="Key terms"
                                description="Up to 200 (Universal) or 1000 (Slam-1) domain terms; max 6 words per phrase."
                                placeholder="Optional. List of strings" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="multichannel" text="Enable Multichannel transcription" :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g1" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />

                            <!-- 2) Language Detection -->
                            <StaticElement name="h_lang_detect" tag="h4" content="Language Detection" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="language_detection" text="Enable Automatic language detection"
                                :default="true" :true-value="true" :false-value="false" 
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="language_confidence_threshold" label="Language Confidence Threshold"
                                description="Error if detected language confidence falls below this threshold. Default: 0."
                                placeholder="Optional" :floating="false" :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />
                            <ObjectElement name="language_detection_options" :columns="{ lg: { wrapper: 6 } }"
                                :conditions="[() => !canEdit ]" 
                                :add-classes="{
                                    ElementLayout: {
                                        innerWrapper: 'relative mt-2 rounded-lg border border-gray-200 bg-gray-50/60 p-4 lg:p-5 pl-5',
                                    }
                                }">
                                <StaticElement name="ldo_header" tag="h4"
                                    content="Language Detection Options (Optional)" />

                                <StaticElement name="ldo_stripe" :content="''"
                                    :add-classes="{ StaticElement: { container: 'pointer-events-none absolute left-0 top-0 h-full w-1 rounded-l-lg bg-indigo-500' } }" />

                                <TextElement name="expected_languages" label="Expected Languages"
                                    :columns="{ lg: { wrapper: 6 } }" />
                                <TextElement name="fallback_language" label="Fallback Language"
                                    :columns="{ lg: { wrapper: 6 } }" />

                                <ToggleElement name="code_switching" text="Code Switching" />
                                <TextElement name="code_switching_confidence_threshold"
                                    label="Code Switching Confidence Threshold" :columns="{ lg: { wrapper: 6 } }" />
                            </ObjectElement>

                            <TextElement name="language_codes" label="Language Codes"
                                description="For code-switching. One value must be 'en'." placeholder="Optional"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g2" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />


                            <!-- 5) Speaker Identification -->
                            <StaticElement name="h_speakers" tag="h4" content="Speaker Identification" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="speaker_labels" text="Enable Speaker diarization" :default="true"
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
                                    content="Speaker Diarization Options (Optional)" :conditions="[() => !canEdit ]" />

                                <StaticElement name="sd_stripe" :content="''"
                                    :add-classes="{ StaticElement: { container: 'pointer-events-none absolute left-0 top-0 h-full w-1 rounded-l-lg bg-indigo-500' } }" 
                                    :conditions="[() => !canEdit ]" />

                                <TextElement name="min_speakers_expected" label="Minimum Speakers Expected"
                                    description="Default: 1" :floating="false" placeholder="Optional"
                                    :columns="{ lg: { wrapper: 5 } }" 
                                    :conditions="[() => !canEdit ]" />
                                <TextElement name="max_speakers_expected" label="Maximum Speakers Expected"
                                    description="Default: 10. Setting too high may reduce accuracy."
                                    placeholder="Optional" :floating="false" :columns="{ lg: { wrapper: 5 } }" 
                                    :conditions="[() => !canEdit ]" />
                            </ObjectElement>
                            <TextElement name="speakers_expected" label="Number of Expected Speakers"
                                description="Tell the diarization model how many speakers to identify."
                                placeholder="Optional" :floating="false" :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g5" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />

                            <!-- 8) Formatting & Customization -->
                            <StaticElement name="h_formatting" tag="h4" content="Formatting & Customization" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="format_text" text="Enable Text Formatting" :default="true"
                                :true-value="true" :false-value="false" 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="punctuate" text="Enable Automatic Punctuation" :default="true"
                                :true-value="true" :false-value="false" 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="disfluencies" text="Transcribe filler words (e.g., “umm”)" :conditions="[() => !canEdit ]" />

                            <GroupElement name="container" :conditions="[() => !canEdit ]" />

                            <ListElement name="custom_spelling" :initial="0"
                                :conditions="[() => !canEdit ]" 
                                :add-classes="{ ListElement: { listItem: 'bg-white p-4 mb-4 rounded-lg shadow-md' } }">
                                <template #label="{ el$ }">
                                    <ElementLabel :for="el$._id" class="flex items-center gap-1">
                                        <span class="text-lg font-semibold text-gray-600">Custom Spelling
                                        </span>
                                        <span class="text-sm font-medium text-gray-500"> (Optional)</span>
                                    </ElementLabel>
                                </template>

                                <template #default="{ index }">
                                    <ObjectElement :name="index">
                                        <TextElement name="from" label="From" :columns="{ sm: { container: 6 } }"
                                            description="Word/phrase" />
                                        <TextElement name="to" label="To" :columns="{ sm: { container: 6 } }"
                                            description="Replacement" />
                                    </ObjectElement>
                                </template>
                            </ListElement>

                            <GroupElement name="container2" :conditions="[() => !canEdit ]" />

                            <TextElement name="audio_start_from" label="Audio Start From (ms)"
                                description="Start time in milliseconds." placeholder="Optional" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="audio_end_at" label="Audio End At (ms)"
                                description="End time in milliseconds." placeholder="Optional" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g4" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />

                            <!-- 6) Content Moderation & Safety -->
                            <StaticElement name="h_safety" tag="h4" content="Content Moderation & Safety" 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="content_safety" text="Enable Content Moderation"
                                description="Detect sensitive content and severity." 
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="content_safety_confidence" label="Content Safety Confidence"
                                description="25–100. Default: 50." placeholder="Optional" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="filter_profanity" text="Filter profanity from the transcribed text" 
                                :conditions="[() => !canEdit ]" />

                            <StaticElement name="div_g6" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />

                            <!-- 7) PII Redaction -->
                            <StaticElement name="h_pii" tag="h4" content="PII Redaction" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="redact_pii" text="Redact PII in transcribed text" :conditions="[() => !canEdit ]" />

                            <TagsElement name="redact_pii_policies" :close-on-select="false" :search="true" :items="[
                                { value: 'account_number', label: 'Account Number' },
                                { value: 'banking_information', label: 'Banking Information' },
                                { value: 'blood_type', label: 'Blood Type' },
                                { value: 'credit_card_cvv', label: 'Credit Card CVV' },
                                { value: 'credit_card_expiration', label: 'Credit Card Expiration' },
                                { value: 'credit_card_number', label: 'Credit Card Number' },
                                { value: 'date', label: 'Date' },
                                { value: 'date_interval', label: 'Date Interval' },
                                { value: 'date_of_birth', label: 'Date of Birth' },
                                { value: 'drivers_license', label: 'Drivers License' },
                                { value: 'drug', label: 'Drug' },
                                { value: 'duration', label: 'Duration' },
                                { value: 'email_address', label: 'Email Address' },
                                { value: 'event', label: 'Event' },
                                { value: 'filename', label: 'Filename' },
                                { value: 'gender_sexuality', label: 'Gender / Sexuality' },
                                { value: 'healthcare_number', label: 'Healthcare Number' },
                                { value: 'injury', label: 'Injury' },
                                { value: 'ip_address', label: 'IP Address' },
                                { value: 'language', label: 'Language' },
                                { value: 'location', label: 'Location' },
                                { value: 'marital_status', label: 'Marital Status' },
                                { value: 'medical_condition', label: 'Medical Condition' },
                                { value: 'medical_process', label: 'Medical Process' },
                                { value: 'money_amount', label: 'Money Amount' },
                                { value: 'nationality', label: 'Nationality' },
                                { value: 'number_sequence', label: 'Number Sequence' },
                                { value: 'occupation', label: 'Occupation' },
                                { value: 'organization', label: 'Organization' },
                                { value: 'passport_number', label: 'Passport Number' },
                                { value: 'password', label: 'Password' },
                                { value: 'person_age', label: 'Person Age' },
                                { value: 'person_name', label: 'Person Name' },
                                { value: 'phone_number', label: 'Phone Number' },
                                { value: 'physical_attribute', label: 'Physical Attribute' },
                                { value: 'political_affiliation', label: 'Political Affiliation' },
                                { value: 'religion', label: 'Religion' },
                                { value: 'statistics', label: 'Statistics' },
                                { value: 'time', label: 'Time' },
                                { value: 'url', label: 'URL' },
                                { value: 'us_social_security_number', label: 'US Social Security Number' },
                                { value: 'username', label: 'Username' },
                                { value: 'vehicle_id', label: 'Vehicle ID' },
                                { value: 'zodiac_sign', label: 'Zodiac Sign' },
                            ]" label="PII Redaction Policies" input-type="search" autocomplete="off" :floating="false"
                                description="List of policies to enable." :columns="{ lg: { wrapper: 5 } }" 
                                :conditions="[() => !canEdit ]" />

                            <SelectElement name="redact_pii_sub" :items="[
                                { value: 'entity_name', label: 'Entity Name' },
                                { value: 'hash', label: 'Hash' },
                            ]" :search="true" :native="false" label="Replacement Logic for Detected PII"
                                input-type="search" autocomplete="off" :columns="{ lg: { wrapper: 5 } }"
                                description="Optional" 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="redact_pii_audio" text="Redact PII in audio (beeped out)" :conditions="[() => !canEdit ]" />
                            <SelectElement name="redact_pii_audio_quality" :items="[
                                { value: 'mp3', label: 'MP3' },
                                { value: 'wav', label: 'WAV' },
                            ]" :search="true" :native="false" label="Redacted Audio Quality" input-type="search"
                                autocomplete="off" :columns="{ lg: { wrapper: 5 } }"
                                description="Filetype for generated redacted audio." default="mp3" 
                                :conditions="[() => !canEdit ]" />
                            <ObjectElement name="redact_pii_audio_options" :columns="{ lg: { wrapper: 6 } }"
                                :conditions="[() => !canEdit ]" 
                                :add-classes="{
                                    ElementLayout: {
                                        innerWrapper: 'relative mt-2 rounded-lg border border-gray-200 bg-gray-50/60 p-4 lg:p-5 pl-5',
                                    }
                                }">
                                <StaticElement name="pii_header" tag="h4" content="Redacted Audio Options (Optional)" />

                                <StaticElement name="pii_stripe" :content="''"
                                    :add-classes="{ StaticElement: { container: 'pointer-events-none absolute left-0 top-0 h-full w-1 rounded-l-lg bg-indigo-500' } }" />

                                <ToggleElement name="return_redacted_no_speech_audio"
                                    text="Return redacted audio even when there is no speech"
                                    description="By default, URLs are returned only when speech is detected." />
                            </ObjectElement>

                            <StaticElement name="div_g7" tag="hr" top="2" bottom="2" :conditions="[() => !canEdit ]" />


                            <!-- 3) Content Intelligence & Analysis -->
                            <StaticElement name="h_content_intel" tag="h4" content="Content Intelligence & Analysis" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="auto_chapters" text="Enable Auto Chapters"
                                description="Summarizes audio into chapters for navigation." 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="auto_highlights" text="Enable Key Phrases" :conditions="[() => !canEdit ]" />
                            <ToggleElement name="entity_detection" text="Enable Entity Detection"
                                description="Detect names, orgs, addresses, phone numbers, medical data, SSNs, etc." 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="sentiment_analysis" text="Enable Sentiment Analysis" 
                                :conditions="[() => !canEdit ]" />
                            <ToggleElement name="iab_categories" text="Enable Topic Detection"
                                description="Identifies topics using the IAB Content Taxonomy." 
                                :conditions="[() => !canEdit ]" />
                            <TextElement name="topics" label="Topics" description="Custom topics."
                                placeholder="Optional. List of strings" :floating="false"
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

        <!-- Right rail for help, previews, or saved presets -->
        <div class="basis-1/4 xl:pl-6 mt-8 xl:mt-0">
            <!-- (Optional) You can add contextual help or a live JSON preview here -->
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
      form$.messageBag.append('Couldn\'t submit form')
      break
  }
}
</script>

