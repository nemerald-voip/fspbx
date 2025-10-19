<template>
    <div class="flex flex-col xl:flex-row">
        <div class="basis-3/4">
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                @response="handleResponse" :display-errors="false">

                <template #empty>
                    <div class="space-y-6 text-gray-600 bg-gray-50 px-4 py-6 sm:p-6">
                        <FormElements>

                            <!-- 1) General & Language Settings -->
                            <StaticElement name="h_general" tag="h4" content="AssemblyAI General & Language Settings" />
                            <SelectElement name="speech_model" :items="[
                                { value: 'best', label: 'Best' },
                                { value: 'slam-1', label: 'Slam-1' },
                                { value: 'universal', label: 'Universal' },
                            ]" :search="true" :native="false" label="Speech Model" input-type="search"
                                autocomplete="off" :columns="{ lg: { wrapper: 5 } }"
                                description="The speech model to use for the transcription. When null, the 'universal' model is used." />
                            <TextElement name="language_code" label="Language Code"
                                description="The language of your audio file. Default: en_us." placeholder="Optional"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }" default="en_us" />
                            <TextElement name="keyterms_prompt" label="Key terms"
                                description="Up to 200 (Universal) or 1000 (Slam-1) domain terms; max 6 words per phrase."
                                placeholder="Optional. List of strings" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" />
                            <ToggleElement name="multichannel" text="Enable Multichannel transcription" />

                            <StaticElement name="div_g1" tag="hr" top="2" bottom="2" />

                            <!-- 2) Language Detection -->
                            <StaticElement name="h_lang_detect" tag="h4" content="Language Detection" />
                            <ToggleElement name="language_detection" text="Enable Automatic language detection"
                                :default="true" :true-value="true" :false-value="false" />
                            <TextElement name="language_confidence_threshold" label="Language Confidence Threshold"
                                description="Error if detected language confidence falls below this threshold. Default: 0."
                                placeholder="Optional" :floating="false" :columns="{ lg: { wrapper: 5 } }" />
                            <ObjectElement name="language_detection_options" :columns="{ lg: { wrapper: 6 } }"
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
                                :floating="false" :columns="{ lg: { wrapper: 5 } }" />

                            <StaticElement name="div_g2" tag="hr" top="2" bottom="2" />


                            <!-- 5) Speaker Identification -->
                            <StaticElement name="h_speakers" tag="h4" content="Speaker Identification" />
                            <ToggleElement name="speaker_labels" text="Enable Speaker diarization" :default="true"
                                :true-value="true" :false-value="false" />
                            <ObjectElement name="speaker_options" :columns="{ lg: { wrapper: 6 } }" :add-classes="{
                                ElementLayout: {
                                    innerWrapper: 'relative mt-2 rounded-lg border border-gray-200 bg-gray-50/60 p-4 lg:p-5 pl-5',
                                }
                            }">

                                <StaticElement name="sd_header" tag="h4"
                                    content="Speaker Diarization Options (Optional)" />

                                <StaticElement name="sd_stripe" :content="''"
                                    :add-classes="{ StaticElement: { container: 'pointer-events-none absolute left-0 top-0 h-full w-1 rounded-l-lg bg-indigo-500' } }" />

                                <TextElement name="min_speakers_expected" label="Minimum Speakers Expected"
                                    description="Default: 1" placeholder="Optional" :columns="{ lg: { wrapper: 5 } }" />
                                <TextElement name="max_speakers_expected" label="Maximum Speakers Expected"
                                    description="Default: 10. Setting too high may reduce accuracy."
                                    placeholder="Optional" :columns="{ lg: { wrapper: 5 } }" />
                            </ObjectElement>
                            <TextElement name="speakers_expected" label="Number of Expected Speakers"
                                description="Tell the diarization model how many speakers to identify."
                                placeholder="Optional" :floating="false" :columns="{ lg: { wrapper: 5 } }" />

                            <StaticElement name="div_g5" tag="hr" top="2" bottom="2" />

                            <!-- 8) Formatting & Customization -->
                            <StaticElement name="h_formatting" tag="h4" content="Formatting & Customization" />
                            <ToggleElement name="format_text" text="Enable Text Formatting" :default="true"
                                :true-value="true" :false-value="false" />
                            <ToggleElement name="punctuate" text="Enable Automatic Punctuation" :default="true"
                                :true-value="true" :false-value="false" />
                            <ToggleElement name="disfluencies" text="Transcribe filler words (e.g., “umm”)" />

                            <GroupElement name="container" />

                            <ListElement name="custom_spelling" :initial="0"
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

                            <GroupElement name="container2" />

                            <TextElement name="audio_start_from" label="Audio Start From (ms)"
                                description="Start time in milliseconds." placeholder="Optional" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" />
                            <TextElement name="audio_end_at" label="Audio End At (ms)"
                                description="End time in milliseconds." placeholder="Optional" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" />

                            <StaticElement name="div_g4" tag="hr" top="2" bottom="2" />

                            <!-- 6) Content Moderation & Safety -->
                            <StaticElement name="h_safety" tag="h4" content="Content Moderation & Safety" />
                            <ToggleElement name="content_safety" text="Enable Content Moderation"
                                description="Detect sensitive content and severity." />
                            <TextElement name="content_safety_confidence" label="Content Safety Confidence"
                                description="25–100. Default: 50." placeholder="Optional" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" />
                            <ToggleElement name="filter_profanity" text="Filter profanity from the transcribed text" />

                            <StaticElement name="div_g6" tag="hr" top="2" bottom="2" />

                            <!-- 7) PII Redaction -->
                            <StaticElement name="h_pii" tag="h4" content="PII Redaction" />
                            <ToggleElement name="redact_pii" text="Redact PII in transcribed text" />
                            <TextElement name="redact_pii_policies" label="PII Redaction Policies"
                                description="List of policies to enable." placeholder="Optional. List of strings"
                                :floating="false" :columns="{ lg: { wrapper: 5 } }" />
                            <SelectElement name="redact_pii_sub" :items="[
                                { value: 'entity_type', label: 'Entity Type' },
                                { value: 'hash', label: 'Hash' },
                            ]" :search="true" :native="false" label="Replacement Logic for Detected PII"
                                input-type="search" autocomplete="off" :columns="{ lg: { wrapper: 5 } }"
                                description="Optional" />
                            <ToggleElement name="redact_pii_audio" text="Redact PII in audio (beeped out)" />
                            <SelectElement name="redact_pii_audio_quality" :items="[
                                { value: 'mp3', label: 'MP3' },
                                { value: 'wav', label: 'WAV' },
                            ]" :search="true" :native="false" label="Redacted Audio Quality" input-type="search"
                                autocomplete="off" :columns="{ lg: { wrapper: 5 } }"
                                description="Filetype for generated redacted audio." default="mp3" />
                            <ObjectElement name="redact_pii_audio_options" :columns="{ lg: { wrapper: 6 } }"
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

                            <StaticElement name="div_g7" tag="hr" top="2" bottom="2" />


                            <!-- 3) Content Intelligence & Analysis -->
                            <StaticElement name="h_content_intel" tag="h4" content="Content Intelligence & Analysis" />
                            <ToggleElement name="auto_chapters" text="Enable Auto Chapters"
                                description="Summarizes audio into chapters for navigation." />
                            <ToggleElement name="auto_highlights" text="Enable Key Phrases" />
                            <ToggleElement name="entity_detection" text="Enable Entity Detection"
                                description="Detect names, orgs, addresses, phone numbers, medical data, SSNs, etc." />
                            <ToggleElement name="sentiment_analysis" text="Enable Sentiment Analysis" />
                            <ToggleElement name="iab_categories" text="Enable Topic Detection"
                                description="Identifies topics using the IAB Content Taxonomy." />
                            <TextElement name="topics" label="Topics" description="Custom topics."
                                placeholder="Optional. List of strings" :floating="false"
                                :columns="{ lg: { wrapper: 5 } }" />

                            <StaticElement name="div_g3" tag="hr" top="2" bottom="2" />

                            <!-- 4) Summarization -->
                            <StaticElement name="h_summarization" tag="h4" content="Summarization" />
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
                                autocomplete="off" :columns="{ lg: { wrapper: 5 } }" />



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
import { ref } from 'vue'

const form$ = ref(null)


const submitForm = async (FormData, form$) => {
    // Using form$.requestData will EXCLUDE conditional elements and it 
    // will submit the form as Content-Type: application/json . 
    const requestData = form$.requestData

    // console.log(requestData);
    return await form$.$vueform.services.axios.post(props.options.routes.store_route, requestData)
};

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

const handleResponse = (response, form$) => {
    // Clear form including nested elements 
    Object.values(form$.elements$).forEach(el$ => {
        clearErrorsRecursive(el$)
    })

    // Display custom errors for elements
    if (response.data.errors) {
        Object.keys(response.data.errors).forEach((elName) => {
            if (form$.el$(elName)) {
                form$.el$(elName).messageBag.append(response.data.errors[elName][0])
            }
        })
    }
}

const handleSuccess = (response, form$) => {
    // console.log(response) // axios response
    // console.log(response.status) // HTTP status code
    // console.log(response.data) // response data

    emit('success', 'success', response.data.messages);
    emit('close');
    emit('refresh-data');
    emit('open-edit-form', response.data.business_hours_uuid);
}

const handleError = (error, details, form$) => {
    form$.messageBag.clear() // clear message bag

    switch (details.type) {
        // Error occured while preparing elements (no submit happened)
        case 'prepare':
            console.log(error) // Error object

            form$.messageBag.append('Could not prepare form')
            break

        // Error occured because response status is outside of 2xx
        case 'submit':
            emit('error', error);
            console.log(error) // AxiosError object
            // console.log(error.response) // axios response
            // console.log(error.response.status) // HTTP status code
            // console.log(error.response.data) // response data

            // console.log(error.response.data.errors)


            break

        // Request cancelled (no response object)
        case 'cancel':
            console.log(error) // Error object

            form$.messageBag.append('Request cancelled')
            break

        // Some other errors happened (no response object)
        case 'other':
            console.log(error) // Error object

            form$.messageBag.append('Couldn\'t submit form')
            break
    }
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