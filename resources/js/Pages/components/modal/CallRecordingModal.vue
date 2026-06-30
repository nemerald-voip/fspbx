<template>
    <TransitionRoot as="div" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="div" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

                        <DialogPanel
                            class="relative transform  rounded-lg bg-surface px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-5xl sm:p-6">

                            <div class="absolute right-0 top-0 pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-surface text-subtle hover:text-muted focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>

                            <div v-if="loading" class="w-full h-full">
                                <div class="flex justify-center items-center space-x-3">
                                    <div>
                                        <svg class="animate-spin  h-10 w-10 text-info"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4">
                                            </circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div class="text-lg text-info m-auto">Loading...</div>
                                </div>
                            </div>

                            <div v-if="!loading" class="flex-col space-y-5">
                                <div>
                                    <div class="space-y-1">
                                        <!-- Title -->
                                        <h1 class="text-2xl font-bold text-body">
                                            {{ capitalizeFirstLetter(recordingOptions?.item?.direction) }} Call
                                        </h1>

                                        <!-- When -->
                                        <p class="text-sm text-muted">
                                            On {{ recordingOptions?.item?.start_date }} at {{
                                                recordingOptions?.item?.start_time }}
                                        </p>

                                        <!-- Parties -->
                                        <dl class="text-sm text-body">
                                            <div class="flex gap-2">
                                                <dt class="font-medium text-muted w-12">From:</dt>
                                                <dd class="flex-1">
                                                    <span v-if="recordingOptions?.item?.direction === 'outbound'">
                                                        <!-- extension name if present, else caller name -->
                                                        {{ recordingOptions?.item?.extension?.name_formatted ||
                                                            recordingOptions?.item?.caller_id_name }}
                                                        <span v-if="recordingOptions.item?.caller_id_number_formatted"
                                                            class="text-muted">
                                                            - {{ recordingOptions?.item?.caller_id_number_formatted }}
                                                        </span>
                                                    </span>
                                                    <span v-else>
                                                        {{ recordingOptions?.item?.caller_id_name }}
                                                        <span v-if="recordingOptions?.item?.caller_id_number_formatted"
                                                            class="text-muted">
                                                            - {{ recordingOptions?.item?.caller_id_number_formatted }}
                                                        </span>
                                                    </span>
                                                </dd>
                                            </div>

                                            <div class="flex gap-2">
                                                <dt class="font-medium text-muted w-12">To:</dt>
                                                <dd class="flex-1">
                                                    <span v-if="recordingOptions?.item?.direction === 'outbound'">
                                                        {{ recordingOptions.item?.caller_destination_formatted }}
                                                    </span>
                                                    <span v-else>
                                                        <!-- inbound destination is usually the extension (callee) -->
                                                        {{ recordingOptions?.item?.extension?.name_formatted ||
                                                            recordingOptions?.item?.caller_destination_formatted }}
                                                    </span>
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>


                                <AudioPlayer v-if="!loading" :url="recordingOptions?.audio_url ?? ''"
                                    :download-url="recordingOptions?.download_url ?? ''"
                                    :file-name="recordingOptions?.filename ?? ''" />

                                <!-- State 1: Feature is NOT ENABLED for the account -->
                                <div v-if="!recordingOptions?.isCallTranscriptionServiceEnabled"
                                    class="mt-6 rounded-lg border border-info bg-info-subtle p-6">
                                    <div class="flex items-start gap-4">
                                        <div class="shrink-0">
                                            <svg class="h-6 w-6 text-info" xmlns="http://www.w3.org/2000/svg"
                                                fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="text-base font-semibold text-info">Unlock AI-Powered Insights
                                            </h3>
                                            <p class="mt-1 text-sm text-info">
                                                Enhance your call analysis with automated transcripts and summaries.
                                                This feature is not currently active for your account.
                                            </p>
                                            <p class="mt-3 text-sm">
                                                <span class="font-medium">Want to activate it?</span> Please contact
                                                your account administrator or support.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- State 2: Feature IS ENABLED and user HAS PERMISSION to view it -->
                                <div v-else-if="recordingOptions?.isCallTranscriptionServiceEnabled && recordingOptions?.permissions?.transcription_view"
                                    class="mt-6 rounded-lg border bg-surface-2 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-4">
                                        <!-- Left Side: Title and Description -->
                                        <div class="flex items-start gap-4">
                                            <div
                                                class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-accent bg-accent-subtle">
                                                <SparklesIcon class="h-6 w-6 text-accent-fg" />
                                            </div>
                                            <div>
                                                <h3 class="text-base font-semibold text-heading">AI Voice Transcription
                                                </h3>
                                                <p class="text-sm text-muted">Generate a searchable text version of
                                                    this audio.</p>
                                            </div>
                                        </div>

                                        <!-- Right Side: Action Button and Status Pills -->
                                        <div class="flex items-center gap-4 pl-14 sm:pl-0">
                                            <!-- Transcribe Button -->
                                            <button
                                                v-if="showTranscribeBtn && recordingOptions?.permissions?.transcription_create"
                                                type="button" @click="requestTranscription"
                                                :disabled="isRequestingTranscription"
                                                class="inline-flex items-center justify-center rounded-md bg-accent px-3 py-2 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50">
                                                <svg v-if="isRequestingTranscription" class="mr-2 h-5 w-5 animate-spin"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                                <span>{{ isRequestingTranscription ? 'Requesting...' : 'Transcribe'
                                                }}</span>
                                            </button>

                                            <!-- Status pill shows after request OR if API already returns a status -->
                                            <div v-else-if="displayStatus"
                                                class="inline-flex items-center gap-2 rounded-full px-3 ring-1" :class="{
                                                    'bg-warning-subtle text-warning ring-warning': displayStatus === 'pending' || displayStatus === 'queued',
                                                    'bg-info-subtle text-info ring-info': displayStatus === 'processing',
                                                    'bg-success-subtle text-success ring-success': displayStatus === 'completed',
                                                    'bg-danger-subtle text-danger ring-danger': displayStatus === 'failed'
                                                }">
                                                <span class="h-2 w-2 rounded-full" :class="{
                                                    'bg-warning': displayStatus === 'pending' || displayStatus === 'queued',
                                                    'bg-info': displayStatus === 'processing',
                                                    'bg-success': displayStatus === 'completed',
                                                    'bg-danger': displayStatus === 'failed'
                                                }"></span>
                                                <span class="font-medium capitalize">{{ displayStatus }}</span>
                                            </div>

                                            <!-- Manual refresh (throttled to every 10s) -->


                                            <!-- Regenerate (only when failed) -->

                                            <button
                                                v-if="showRegenerateBtn && recordingOptions?.permissions?.transcription_create"
                                                type="button" @click="regenerateTranscription"
                                                :disabled="isRegenerating"
                                                class="inline-flex items-center text-sm/6 font-medium text-accent-fg hover:text-accent-fg">
                                                <ArrowPathIcon
                                                    :class="['h-4 w-4', isRegenerating ? 'animate-spin' : '']" />
                                                <span class="ml-1">{{ isRegenerating ? 'Regenerating…' : 'Regenerate'
                                                }}</span>

                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="recordingOptions?.permissions?.transcription_view">

                                    <!-- State 1: Initial Placeholder (Before transcription is requested) -->
                                    <div v-if="recordingOptions?.isCallTranscriptionServiceEnabled && !hasTranscript && !transcriptRequested"
                                        class="mt-6 rounded-lg border-2 border-dashed border-strong p-12 text-center">
                                        <ClipboardDocumentListIcon class="mx-auto h-12 w-12 text-subtle" />
                                        <h3 class="mt-2 text-sm font-semibold text-heading">Transcript not yet
                                            generated</h3>
                                        <p v-if="recordingOptions?.permissions?.transcription_create"
                                            class="mt-1 text-sm text-muted">
                                            Click the "Transcribe" button above to generate the transcript.
                                        </p>
                                    </div>

                                    <div v-else-if="!hasTranscript && transcriptRequested"
                                        class="mt-6 flex flex-col items-center justify-center rounded-lg border border-default bg-surface-2 p-12 text-center">

                                        <!-- Spinner -->
                                        <!-- <svg class="h-10 w-10 animate-spin text-accent-fg"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg> -->
                                        <p class="mt-4 text-sm font-semibold text-accent-fg">Transcription in progress.
                                            Click Refresh to check status.</p>

                                        <!-- START: REFRESH BUTTON -->
                                        <button type="button" @click="refreshStatus" :disabled="!canRefresh"
                                            class="mt-6 inline-flex items-center rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50">
                                            <ArrowPathIcon class="-ml-0.5 mr-1.5 h-5 w-5 text-subtle"
                                                :class="{ 'animate-spin': !canRefresh }" />
                                            <span v-if="canRefresh">Refresh Status</span>
                                            <span v-else>Refresh in {{ cooldownSeconds }}s</span>
                                        </button>
                                        <!-- END: REFRESH BUTTON -->

                                    </div>

                                    <!-- State 3: Completed Tabs (Transcript is ready) -->
                                    <div v-else-if="hasTranscript && recordingOptions?.permissions?.transcription_read"
                                        class="mt-6 text-sm">
                                        <TabGroup :selectedIndex="selectedTabIndex" @change="selectedTabIndex = $event">

                                            <!-- Mobile-friendly Select Menu -->
                                            <div class="sm:hidden">
                                                <div class="relative">
                                                    <select v-model="selectedTabIndex" aria-label="Select a tab"
                                                        class="block w-full appearance-none rounded-md border border-strong bg-surface py-2 pl-3 pr-10 text-base text-heading focus:border-accent focus:outline-none focus:ring-focus">
                                                        <option v-for="(tab, index) in TABS" :key="tab.key"
                                                            :value="index">
                                                            {{ tab.label }}
                                                        </option>
                                                    </select>
                                                    <div
                                                        class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                                        <ChevronDownIcon class="h-5 w-5 text-subtle"
                                                            aria-hidden="true" />
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Desktop Tab Pills -->
                                            <div class="hidden sm:block">
                                                <TabList as="nav" class="flex space-x-4 border-b-2 pb-1"
                                                    aria-label="Tabs">
                                                    <Tab v-for="tab in TABS" :key="tab.key" v-slot="{ selected }"
                                                        as="template">
                                                        <button :class="[
                                                            'rounded-md px-3 py-2 text-sm font-medium transition-all duration-200',
                                                            'focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2',
                                                            selected
                                                                ? 'bg-accent-subtle text-accent-fg'
                                                                : 'text-muted hover:bg-surface-3 hover:text-body',
                                                        ]">
                                                            {{ tab.label }}
                                                        </button>
                                                    </Tab>
                                                </TabList>
                                            </div>

                                            <!-- The Tab Panels (the content) -->
                                            <TabPanels class="mt-4 p-4">
                                                <!-- Transcript Panel -->
                                                <TabPanel :key="TABS[0].key"
                                                    class="rounded-lg focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2">
                                                    <!-- Paste your existing transcript rendering logic here -->
                                                    <div class="space-y-6" v-if="Array.isArray(grouped) && grouped.length">
                                                        <div v-for="(g, i) in grouped" :key="i"
                                                            class="flex items-start gap-4">
                                                            <div class="shrink-0 rounded-md px-2 py-0.5 text-sm font-medium"
                                                                :class="speakerClasses(g.speaker).timeChip">
                                                                {{ msToClock(g.start) }}
                                                            </div>
                                                            <div class="flex-1">
                                                                <div class="flex items-center gap-2">
                                                                    <p class="font-semibold"
                                                                        :class="speakerClasses(g.speaker).name">
                                                                        Speaker {{ g.speaker }}
                                                                    </p>
                                                                </div>
                                                                <p class="mt-1 leading-relaxed text-body">
                                                                    {{g.chunks.map(c => c.text).join(' ')}}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="space-y-6" v-else>
                                                        {{ recordingOptions?.transcription?.text }}
                                                    </div>
                                                </TabPanel>


                                                <!-- Summary Panel -->
                                                <TabPanel :key="TABS[1].key"
                                                    class="rounded-lg p-0.5 focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2 sm:p-2">
                                                    <!-- Summary State: Completed -->
                                                    <div v-if="hasSummary" class="space-y-8">
                                                        <!-- Overall Summary -->
                                                        <div class="space-y-3">
                                                            <h3
                                                                class="flex items-center gap-2 text-base font-semibold text-heading">
                                                                <DocumentTextIcon class="h-6 w-6 text-muted" />
                                                                <span>Call Summary</span>
                                                            </h3>
                                                            <p class="text-body leading-relaxed">
                                                                {{ recordingOptions?.transcription?.summary }}
                                                            </p>
                                                        </div>

                                                        <!-- Key Points -->
                                                        <div class="space-y-3">
                                                            <h3
                                                                class="flex items-center gap-2 text-base font-semibold text-heading">
                                                                <LightBulbIcon class="h-6 w-6 text-warning" />
                                                                <span>Key Points</span>
                                                            </h3>
                                                            <ul
                                                                class="list-disc space-y-2 pl-6 text-body marker:text-subtle">
                                                                <li v-for="(point, i) in recordingOptions?.transcription?.key_points"
                                                                    :key="`kp-${i}`">{{ point }}</li>
                                                            </ul>
                                                        </div>

                                                        <!-- Action Items -->
                                                        <div class="space-y-3">
                                                            <h3
                                                                class="flex items-center gap-2 text-base font-semibold text-heading">
                                                                <CheckCircleIcon class="h-6 w-6 text-success" />
                                                                <span>Action Items</span>
                                                            </h3>
                                                            <ul class="space-y-2 text-body">
                                                                <li v-for="(item, i) in recordingOptions?.transcription?.action_items"
                                                                    :key="`ai-${i}`"
                                                                    class="rounded-md border border-default bg-surface-2 p-3">
                                                                    <p class="font-medium text-heading">{{
                                                                        item.description }}</p>
                                                                    <p v-if="item.owner" class="text-xs text-muted">
                                                                        Owner: {{
                                                                            item.owner }}</p>
                                                                </li>
                                                            </ul>
                                                        </div>

                                                        <!-- Decisions Made -->
                                                        <div class="space-y-3">
                                                            <h3
                                                                class="flex items-center gap-2 text-base font-semibold text-heading">
                                                                <ScaleIcon class="h-6 w-6 text-info" />
                                                                <span>Decisions Made</span>
                                                            </h3>
                                                            <ul
                                                                class="list-disc space-y-2 pl-6 text-body marker:text-subtle">
                                                                <li v-for="(decision, i) in recordingOptions?.transcription?.decisions_made"
                                                                    :key="`dm-${i}`">{{ decision }}</li>
                                                            </ul>
                                                        </div>

                                                        <!-- Sentiment -->
                                                        <div class="space-y-3">
                                                            <h3
                                                                class="flex items-center gap-2 text-base font-semibold text-heading">
                                                                <ChatBubbleBottomCenterTextIcon
                                                                    class="h-6 w-6 text-info" />
                                                                <span>Overall Sentiment</span>
                                                            </h3>
                                                            <p
                                                                class="inline-flex items-center rounded-full bg-info-subtle px-3 py-1 text-sm font-medium capitalize text-info">
                                                                {{ recordingOptions?.transcription?.sentiment_overall }}
                                                            </p>
                                                        </div>

                                                    </div>

                                                    <!-- Summary State: In Progress -->
                                                    <div v-else-if="(displaySummaryStatus === 'queued' || displaySummaryStatus === 'processing') || (summaryRequested && displaySummaryStatus !== 'completed' && displaySummaryStatus !== 'failed')"
                                                        class="flex flex-col items-center justify-center rounded-lg border border-default bg-surface-2 p-12 text-center">
                                                        <!-- <svg class="h-10 w-10 animate-spin text-accent-fg"
                                                            xmlns="http://www.w3.org/2000/svg" fill="none"
                                                            viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                                                stroke="currentColor" stroke-width="4"></circle>
                                                            <path class="opacity-75" fill="currentColor"
                                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                            </path>
                                                        </svg> -->
                                                        <p class="mt-4 text-sm font-semibold text-accent-fg">
                                                            Generating AI summary...
                                                        </p>
                                                        <p class="mt-1 text-sm text-muted">
                                                            You can check the progress by clicking Refresh.
                                                        </p>

                                                        <!-- START: REFRESH BUTTON -->
                                                        <button type="button" @click="refreshStatus"
                                                            :disabled="!canRefresh"
                                                            class="mt-6 inline-flex items-center rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-50">
                                                            <ArrowPathIcon
                                                                class="-ml-0.5 mr-1.5 h-5 w-5 text-subtle"
                                                                :class="{ 'animate-spin': !canRefresh }" />
                                                            <span v-if="canRefresh">Refresh Status</span>
                                                            <span v-else>Refresh in {{ cooldownSeconds }}s</span>
                                                        </button>
                                                        <!-- END: REFRESH BUTTON -->
                                                    </div>

                                                    <!-- Summary State: Failed -->
                                                    <div v-else-if="displaySummaryStatus === 'failed' && !summaryRequested"
                                                        class="rounded-lg border-2 border-dashed border-danger bg-danger-subtle p-12 text-center">
                                                        <ExclamationTriangleIcon
                                                            class="mx-auto h-12 w-12 text-danger" />
                                                        <h3 class="mt-2 text-sm font-semibold text-danger">
                                                            Summary Generation Failed
                                                        </h3>
                                                        <p class="mt-1 text-sm text-danger">
                                                            We were unable to generate a summary for this call.
                                                        </p>
                                                        <button type="button" @click="regenerateSummary"
                                                            :disabled="isRegeneratingSummary"
                                                            class="mt-4 inline-flex items-center rounded-md bg-danger-solid px-3 py-2 text-sm font-semibold text-on-accent shadow-sm hover:bg-danger-solid-hover disabled:opacity-50">
                                                            <ArrowPathIcon class="-ml-0.5 mr-1.5 h-5 w-5"
                                                                :class="{ 'animate-spin': isRegeneratingSummary }" />
                                                            {{ isRegeneratingSummary ? 'Retrying...' : 'Retry' }}
                                                        </button>
                                                    </div>

                                                    <!-- Summary State: Not Yet Generated -->
                                                    <div v-else
                                                        class="rounded-lg border-2 border-dashed border-strong p-12 text-center">
                                                        <SparklesIcon class="mx-auto h-12 w-12 text-subtle" />
                                                        <h3 class="mt-2 text-sm font-semibold text-heading">
                                                            AI Summary is available
                                                        </h3>
                                                        <p class="mt-1 text-sm text-muted">
                                                            Summary generation is part of the transcription process.
                                                        </p>
                                                    </div>
                                                </TabPanel>


                                            </TabPanels>
                                        </TabGroup>
                                    </div>
                                    <div v-else-if="recordingOptions?.isCallTranscriptionServiceEnabled && !recordingOptions?.permissions?.transcription_read"
                                        class="mt-6 rounded-lg border-2 border-dashed border-strong p-12 text-center">
                                        <p class="text-sm font-medium text-body">You do not have permission to view
                                            transcript content.</p>
                                    </div>

                                </div>

                            </div>

                            <!-- <Vueform v-if="!loading" ref="form$" :endpoint="submitForm" @success="handleSuccess" @error="handleError"
                                @response="handleResponse" :display-errors="false" :default="{
                                    // user_uuid: options.item.user_uuid,
                                }">
                                
                            </Vueform> -->
                        </DialogPanel>


                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'

import { Dialog, DialogPanel, TransitionChild, TransitionRoot, TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import AudioPlayer from "@generalComponents/AudioPlayer.vue"
import {
    XMarkIcon, SparklesIcon, ClipboardDocumentListIcon, ChevronDownIcon, DocumentTextIcon, LightBulbIcon,
    CheckCircleIcon, ScaleIcon, ExclamationTriangleIcon, ChatBubbleBottomCenterTextIcon
} from "@heroicons/vue/24/outline";
import { ArrowPathIcon } from '@heroicons/vue/24/outline'


const emit = defineEmits(['close', 'confirm', 'success', 'error', 'refresh-data'])

const props = defineProps({
    cdr_uuid: String,
    routes: Object,
    show: Boolean,
});

const loading = ref(false)
const transcriptRequested = ref(false)
const isRequestingTranscription = ref(null)
const isRegenerating = ref(false)
const isRegeneratingSummary = ref(false)
const recordingOptions = ref(null)
const currentStatus = ref(null)
const selectedTabIndex = ref(0)
const status = computed(() =>
    recordingOptions.value?.transcription?.status ?? null
)
const utterances = computed(() => recordingOptions.value?.transcription?.utterances ?? [])
// const hasTranscript = computed(() => status.value === 'completed' && utterances.value.length > 0)
const hasTranscript = computed(() => status.value === 'completed')

// --- STATE MANAGEMENT FOR SUMMARY ---
const summaryRequested = ref(false)
const currentSummaryStatus = ref(null)
const summaryStatus = computed(() => recordingOptions.value?.transcription?.summary_status ?? null)
const hasSummary = computed(() => summaryStatus.value === 'completed' && recordingOptions.value?.transcription?.summary)
const displaySummaryStatus = computed(() => currentSummaryStatus.value ?? summaryStatus.value ?? null)

const showTranscribeBtn = computed(() =>
    !hasTranscript.value && !transcriptRequested.value && !status.value
)

const showRegenerateBtn = computed(() =>
    !hasTranscript.value && !transcriptRequested.value && status.value == "failed"
)

const displayStatus = computed(() =>
    currentStatus.value ?? status.value ?? null
)

const getCallRecordingOptions = () => {
    if (!props.cdr_uuid) return
    axios
        .get(props.routes.call_recording_route, { params: { item_uuid: props.cdr_uuid } })
        .then((response) => {
            recordingOptions.value = response.data
            // console.log(recordingOptions.value)
            if (response.data?.transcription?.status == 'failed') {
                console.log("AI transcription error : " + response.data?.transcription?.error_message)
            }
        })
        .catch((error) => {
            emit('error', error);
            emit('close');
        })
        .finally(() => {
            loading.value = false
        })
}

const requestTranscription = async () => {
    isRequestingTranscription.value = true
    try {
        const { data } = await axios.post(
            recordingOptions.value.routes.transcribe_route,
            {
                uuid: recordingOptions.value?.item?.xml_cdr_uuid ?? null,
                domain_uuid: recordingOptions.value?.item?.domain_uuid ?? null,
                // options: overrides,                      // optional provider overrides
            },
        )
        // policy.value = data
        emit('success', 'success', data.messages)
        transcriptRequested.value = true
        currentStatus.value = 'queued'
        getCallRecordingOptions()
        return data
    } catch (err) {
        console.log(err);
        emit('error', err);
        return []
    } finally {
        isRequestingTranscription.value = false
    }
}

async function regenerateTranscription() {
    if (isRegenerating.value) return
    isRegenerating.value = true
    try {
        const { data } = await axios.post(
            recordingOptions.value.routes.transcribe_route,
            {
                uuid: recordingOptions.value?.item?.xml_cdr_uuid ?? null,
                domain_uuid: recordingOptions.value?.item?.domain_uuid ?? null,
            }
        )
        emit('success', 'success', data.messages)
        // show queued immediately; user can hit Refresh (manual) to check progress
        transcriptRequested.value = true
        currentStatus.value = 'queued'
        getCallRecordingOptions()
    } catch (err) {
        emit('error', err)
    } finally {
        isRegenerating.value = false
    }
}

async function regenerateSummary() {
    if (isRegeneratingSummary.value) return;
    isRegeneratingSummary.value = true;
    summaryRequested.value = true;
    currentSummaryStatus.value = 'queued'; // Provide immediate UI feedback

    try {
        const { data } = await axios.post(
            recordingOptions.value.routes.summarize_route,
            {
                uuid: recordingOptions.value?.transcription?.uuid ?? null,
            }
        );
        emit('success', 'success', data.messages);
        // Do NOT await a refresh here. Let the user do it manually.
    } catch (err) {
        emit('error', err);
        // If the request fails, reset the state so the user can try again
        summaryRequested.value = false;
        currentSummaryStatus.value = null;
    } finally {
        isRegeneratingSummary.value = false;
    }
}


function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function msToClock(ms) {
    const s = Math.max(0, Math.round(ms / 1000))
    const m = Math.floor(s / 60), r = s % 60
    return `${m}:${String(r).padStart(2, '0')}`
}

// ---- 10s cooldown for manual refresh ----
const cooldownSeconds = ref(0)
const canRefresh = computed(() => cooldownSeconds.value === 0)
let cooldownTimer = null

function startCooldown() {
    cooldownSeconds.value = 10
    if (cooldownTimer) clearInterval(cooldownTimer)
    cooldownTimer = setInterval(() => {
        if (cooldownSeconds.value > 0) cooldownSeconds.value -= 1
        if (cooldownSeconds.value <= 0) {
            clearInterval(cooldownTimer)
            cooldownTimer = null
        }
    }, 1000)
}

async function refreshStatus() {
    if (!canRefresh.value) return
    await getCallRecordingOptions()
    currentStatus.value = null
    // IMPORTANT: allow the button to re-appear if we’re still failed
    if (status.value === 'failed') {
        transcriptRequested.value = false
    }
    startCooldown()
}

onUnmounted(() => {
    if (cooldownTimer) clearInterval(cooldownTimer)
    selectedTabIndex.value = 0
})

const SPEAKER_PALETTES = [
    { // A
        timeChip: 'bg-accent-subtle text-accent-fg',
        avatar: 'bg-accent-subtle text-accent-fg',
        name: 'text-accent-fg',
    },
    { // B
        timeChip: 'bg-success-subtle text-success',
        avatar: 'bg-success-subtle text-success',
        name: 'text-success',
    },
    { // C
        timeChip: 'bg-amber-50 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        avatar: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
        name: 'text-amber-700 dark:text-amber-300',
    },
    { // D
        timeChip: 'bg-fuchsia-50 dark:bg-fuchsia-900/40 text-fuchsia-600 dark:text-fuchsia-300',
        avatar: 'bg-fuchsia-100 dark:bg-fuchsia-900/40 text-fuchsia-600 dark:text-fuchsia-300',
        name: 'text-fuchsia-600 dark:text-fuchsia-300',
    },
    { // E
        timeChip: 'bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-300',
        avatar: 'bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-300',
        name: 'text-sky-600 dark:text-sky-300',
    },
    { // F
        timeChip: 'bg-danger-subtle text-danger',
        avatar: 'bg-danger-subtle text-danger',
        name: 'text-danger',
    },
    { // G
        timeChip: 'bg-violet-50 dark:bg-violet-900/40 text-violet-600 dark:text-violet-300',
        avatar: 'bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-300',
        name: 'text-violet-600 dark:text-violet-300',
    },
    { // H
        timeChip: 'bg-lime-50 dark:bg-lime-900/40 text-lime-700 dark:text-lime-300',
        avatar: 'bg-lime-100 dark:bg-lime-900/40 text-lime-600 dark:text-lime-300',
        name: 'text-lime-600 dark:text-lime-300',
    },
    { // I
        timeChip: 'bg-cyan-50 dark:bg-cyan-900/40 text-cyan-600 dark:text-cyan-300',
        avatar: 'bg-cyan-100 dark:bg-cyan-900/40 text-cyan-600 dark:text-cyan-300',
        name: 'text-cyan-600 dark:text-cyan-300',
    },
    { // J
        timeChip: 'bg-orange-50 dark:bg-orange-900/40 text-orange-600 dark:text-orange-300',
        avatar: 'bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-300',
        name: 'text-orange-600 dark:text-orange-300',
    },
]

const DEFAULT_PALETTE = {
    timeChip: 'bg-surface-3 text-body',
    avatar: 'bg-surface-3 text-body',
    name: 'text-body',
}

const TABS = [
    { key: 'transcript', label: 'Transcript' },
    { key: 'summary', label: 'Summary' },
]


function speakerIndex(label) {
    if (!label) return 0
    const s = String(label).trim().toUpperCase()
    const code = s.charCodeAt(0)
    if (code >= 65 && code <= 90) return (code - 65) % SPEAKER_PALETTES.length // A–Z
    // small hash for non-letters
    let h = 0
    for (const ch of s) h = (h * 31 + ch.charCodeAt(0)) >>> 0
    return h % SPEAKER_PALETTES.length
}

function speakerClasses(label) {
    return SPEAKER_PALETTES[speakerIndex(label)] || DEFAULT_PALETTE
}

// Group consecutive lines by speaker (cleaner bubbles)
const grouped = computed(() => {
    const out = []
    let cur = null
    for (const u of utterances.value) {
        if (!cur || cur.speaker !== u.speaker) {
            cur = { speaker: u.speaker, start: u.start, end: u.end, chunks: [u] }
            out.push(cur)
        } else {
            cur.chunks.push(u)
            cur.end = u.end
        }
    }
    return out
})

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            loading.value = true
            transcriptRequested.value = false
            currentStatus.value = null
            getCallRecordingOptions()
        }

    }
)

</script>
<style>
div[data-lastpass-icon-root] {
    display: none !important
}

div[data-lastpass-root] {
    display: none !important
}
</style>