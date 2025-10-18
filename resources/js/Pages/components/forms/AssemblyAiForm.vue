<template>
    <div class="flex flex-col xl:flex-row">
        <div class="basis-3/4">
            <Vueform ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                @response="handleResponse" :display-errors="false">

                <StaticElement name="assembly_ai_general_settings" tag="h4"
                    content="AssemblyAI General Language Settings" description="" />

                <SelectElement name="speech_model" :items="[
                    {
                        value: 'best',
                        label: 'Best',
                    },
                    {
                        value: 'slam-1',
                        label: 'Slam-1',
                    },
                    {
                        value: 'universal',
                        label: 'Universal',
                    },
                ]" :search="true" :native="false" label="Speech Model" input-type="search" autocomplete="off" :columns="{

                    lg: {
                        wrapper: 5,
                    },
                }"
                    description="The speech model to use for the transcription. When null, the 'universal' model is used." />

                <TextElement name="language_code" label="Language Code"
                    description="The language of your audio file. The default value is ‘en_us’." placeholder="Optional"
                    :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" default="en_us" />

                <TextElement name="keyterms_prompt" label="Key terms"
                    description="Improve accuracy with up to 200 (for Universal) or 1000 (for Slam-1) domain-specific words or phrases (maximum 6 words per phrase)."
                    placeholder="Optional. List of Strings" :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />

                <ToggleElement name="multichannel" text="Enable Multichannel transcription" />

                <StaticElement name="divider" tag="hr" top="2" bottom="2" />

                <StaticElement name="assembly_ai_language_detection" tag="h4" content="Language Detection"
                    description="" />

                <ToggleElement name="language_detection" text="Enable Automatic language detection" default="true" />

                <TextElement name="language_confidence_threshold" label="Language Confidence Threshold"
                    description="The confidence threshold for the automatically detected language. An error will be returned if the language confidence is below this threshold. Defaults to 0."
                    placeholder="Optional" :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />
                    
                <ObjectElement name="language_detection_options" label="Language Detection Options (Optional)">
                    <TextElement name="expected_languages" label="Expected Languages"
                        description="List of languages expected in the audio file. Defaults to [\&#34;all\&#34;] when unspecified."
                        placeholder="Optional" :floating="false" :columns="{
                            lg: {

                            },
                        }" />
                    <TextElement name="fallback_language" label="Fallback Languages"
                        description="If the detected language of the audio file is not in the list of expected languages, the fallback_language is used. Specify [\&#34;auto\&#34;] to let our model choose the fallback language from expected_languages with the highest confidence score."
                        placeholder="Optional" :floating="false" :columns="{
                            lg: {

                            },
                        }" />
                    <ToggleElement name="code_switching" text="Code Switching"
                        description="Whether code switching should be detected." />
                    <TextElement name="code_switching_confidence_threshold" label="Code Switching Confidence Threshold"
                        description="The confidence threshold for code switching detection. If the code switching confidence is below this threshold, the transcript will be processed in the language with the highest language_detection_confidence score."
                        placeholder="Optional" :floating="false" :columns="{
                            lg: {

                            },
                        }" />
                </ObjectElement>





                <TextElement name="audio_end_at" label="Audio End At"
                    description="The point in time, in milliseconds, to stop transcribing in your media file"
                    placeholder="Optional" :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />
                <TextElement name="audio_start_from" label="Audio Start From"
                    description="The point in time, in milliseconds, to begin transcribing in your media file"
                    placeholder="Optional" :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />
                <ToggleElement name="auto_chapters" text="Enable Auto Chapters"
                    description="The Auto Chapters model summarizes audio data over time into chapters. Chapters makes it easy for users to navigate and find specific information." />
                <ToggleElement name="auto_highlights" text="Enable Key Phrases" />
                <ToggleElement name="content_safety" text="Enable Content Moderation" description="The Content Moderation model lets you detect inappropriate content in audio files to ensure that your content is safe for all audiences.
The model pinpoints sensitive discussions in spoken data and their severity." />
                <TextElement name="content_safety_confidence" label="Content Safety Confidence"
                    description="The confidence threshold for the Content Moderation model. Values must be between 25 and 100. Defaults to 50."
                    placeholder="Optional" :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />
                <ListElement name="custom_spelling" label="Custom Spelling"
                    description="Customize how words are spelled and formatted using to and from values" :initial="0">
                    <template #default="{ index }">
                        <ObjectElement :name="index">
                            <TextElement name="from" label="From" :columns="{
                                container: 6,
                            }" description="Word or phrase to replace" />
                            <TextElement name="to" label="To" :columns="{
                                container: 6,
                            }" description="Word to replace with" />
                        </ObjectElement>
                    </template>
                </ListElement>
                <ToggleElement name="disfluencies" text="Transcribe Filler Words, like “umm”, in your media file" />
                <ToggleElement name="entity_detection" text="Enable Entity Detection" description="The Entity Detection model lets you automatically identify and categorize key information in transcribed audio content.

Here are a few examples of what you can detect:

Names of people,
Organizations,
Addresses,
Phone numbers,
Medical data,
Social security numbers" />
                <ToggleElement name="filter_profanity" text="Filter profanity from the transcribed text" />
                <ToggleElement name="format_text" text="Enable Text Formatting" default="true" />
                <ToggleElement name="iab_categories" text="Enable Topic Detection"
                    description="The Topic Detection model lets you identify different topics in the transcript. The model uses the IAB Content Taxonomy, a standardized language for content description which consists of 698 comprehensive topics." />


                <TextElement name="language_codes" label="Language Codes"
                    description="The language codes of your audio file. Used for Code switching One of the values specified must be \&#39;en\&#39;."
                    placeholder="Optional" :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />


                <ToggleElement name="punctuate" text="Enable Automatic Punctuation" default="true" />
                <ToggleElement name="redact_pii"
                    text="Redact PII from the transcribed text using the Redact PII model" />
                <ToggleElement name="redact_pii_audio"
                    text="Generate a copy of the original media file with spoken PII “beeped” out. See PII redaction for more details." />
                <StaticElement name="divider" tag="hr" top="1" bottom="1" />
                <ObjectElement name="redact_pii_audio_options" label="Redact PII Audio options (Optional)">
                    <ToggleElement name="return_redacted_no_speech_audio" text="Return Redacted No Speech Audio"
                        description="By default, audio redaction provides redacted audio URLs only when speech is detected. However, if your use-case specifically requires redacted audio files even for silent audio files without any dialogue, you can opt to receive these URLs by setting this parameter to true." />
                </ObjectElement>
                <SelectElement name="redact_pii_audio_quality" :items="[
                    {
                        value: 'mp3',
                        label: 'MP3',
                    },
                    {
                        value: 'wav',
                        label: 'WAV',
                    },
                ]" :search="true" :native="false" label="Redact PII Audio Quality" input-type="search"
                    autocomplete="off" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }"
                    description="Controls the filetype of the audio created by redact_pii_audio. Currently supports mp3 (default) and wav."
                    default="mp3" />
                <TextElement name="redact_pii_policies" label="PII Redaction Policies"
                    description="The list of PII Redaction policies to enable." placeholder="Optional. List of Strings"
                    :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />
                <SelectElement name="redact_pii_sub" :items="[
                    {
                        value: 'entity_type',
                        label: 'Entity Type',
                    },
                    {
                        value: 'hash',
                        label: 'Hash',
                    },
                ]" :search="true" :native="false" label="Replacement Logic for Detected PII" input-type="search"
                    autocomplete="off" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" description="The replacement logic for detected PII, can be entity_type or hash. (Optional)" />
                <ToggleElement name="sentiment_analysis" text="Enable Sentiment Analysis" />
                <ToggleElement name="speaker_labels" text="Enable Speaker diarization" default="true" />
                <ObjectElement name="speaker_options" label="Specify options for speaker diarization (Optional)">
                    <TextElement name="min_speakers_expected" label="Minimum Speakers Expected"
                        description="The minimum number of speakers expected in the audio file. Defaults to 1"
                        placeholder="Optional" :floating="false" :columns="{
                            lg: {

                            },
                        }" />
                    <TextElement name="max_speakers_expected" label="Maximum Speakers Expected
" description="The maximum number of speakers expected in the audio file. Setting this parameter too high may hurt model accuracy. Defaults to 10"
                        placeholder="Optional" :floating="false" :columns="{
                            lg: {

                            },
                        }" />
                </ObjectElement>
                <TextElement name="language_confidence_threshold_1" label="Number of Expected Speakers"
                    description="Tells the Speaker diarization model how many speakers it should attempt to identify."
                    placeholder="Optional" :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />

                <ToggleElement name="summarization" text="Enable Summarization" />
                <SelectElement name="summary_model" :items="[
                    {
                        value: 'informative',
                        label: 'Informative',
                    },
                    {
                        value: 'conversational',
                        label: 'Conversational',
                    },
                    {
                        value: 'catchy',
                        label: 'Catchy',
                    },
                ]" :search="true" :native="false" label="Summary Model" input-type="search" autocomplete="off"
                    :columns="{
                        lg: {

                        },
                    }" description="The model to summarize the transcript" />
                <SelectElement name="summary_type" :items="[
                    {
                        value: 'bullets',
                        label: 'Bullets',
                    },
                    {
                        value: 'bullets_verbose',
                        label: 'Bullets Verbose',
                    },
                    {
                        value: 'gist',
                        label: 'Gist',
                    },
                    {
                        value: 'headline',
                        label: 'Headline',
                    },
                    {
                        value: 'paragraph',
                        label: 'Paragraph',
                    },
                ]" :search="true" :native="false" label="Summary Type" input-type="search" autocomplete="off" :columns="{
                    lg: {

                    },
                }" description="The type of summary" />
                <TextElement name="topics" label="Topics" description="The list of custom topics"
                    placeholder="Optional. List of strings" :floating="false" :columns="{
                        lg: {
                            wrapper: 5,
                        },
                    }" />
            </Vueform>
        </div>
        <div class="basis-1/4"></div>
    </div>
</template>


<script setup>
import { ref } from 'vue'

const form$ = ref(null)
</script>
