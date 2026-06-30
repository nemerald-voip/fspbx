<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto ">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            :class="['relative transform rounded-lg bg-surface-3 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:p-6', customClass]">
                            <div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-surface-3 text-subtle hover:text-muted focus:outline-none focus:ring-2 focus:ring-focus focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>
                            <div v-if="!loading">
                                <DialogTitle as="h3" class="text-base font-semibold leading-6 text-heading">
                                    {{ header }}
                                </DialogTitle>
                                <div class="mt-4 pb-4">
                                    <slot name="modal-body"></slot>

                                    <main class="">
                                        <!-- Page header -->
                                        <!-- <div class="mx-auto max-w-3xl px-4 sm:px-6 md:flex md:items-center md:justify-between md:space-x-5 lg:max-w-7xl lg:px-8"> -->
                                        <div class="flex items-center space-x-5">
                                            <div class="flex-shrink-0">
                                                <!-- <div class="relative">
                                                        <img class="h-16 w-16 rounded-full"
                                                            src="https://images.unsplash.com/photo-1463453091185-61582044d556?ixlib=rb-=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=8&w=1024&h=1024&q=80"
                                                            alt="" />
                                                        <span class="absolute inset-0 rounded-full shadow-inner"
                                                            aria-hidden="true" />
                                                    </div> -->
                                            </div>
                                            <div>
                                                <h1 class="text-2xl font-bold text-heading">{{
                                                    capitalizeFirstLetter(item.direction) }} Call</h1>
                                                <p class="text-sm font-medium text-muted">On {{ item.start_date }} at {{
                                                    item.start_time }}</p>
                                            </div>
                                        </div>
                                        <!-- <div
                                                class="mt-6 flex flex-col-reverse justify-stretch space-y-4 space-y-reverse sm:flex-row-reverse sm:justify-end sm:space-x-3 sm:space-y-0 sm:space-x-reverse md:mt-0 md:flex-row md:space-x-3">
                                                <button type="button"
                                                    class="inline-flex items-center justify-center rounded-md bg-surface px-3 py-2 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong hover:bg-surface-2">Disqualify</button>
                                                <button type="button"
                                                    class="inline-flex items-center justify-center rounded-md bg-info px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-info focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-info">Advance
                                                    to offer</button>
                                            </div> -->
                                        <!-- </div> -->

                                        <div
                                            class="mx-auto mt-8 grid max-w-3xl grid-cols-1 gap-6 sm:px-6 lg:max-w-7xl lg:grid-flow-col-dense lg:grid-cols-2">
                                            <div class="space-y-6 lg:col-start-1">
                                                <!-- Description list-->
                                                <section aria-labelledby="applicant-information-title">
                                                    <div class="bg-surface shadow sm:rounded-lg">
                                                        <div class="px-4 py-5 sm:px-6">
                                                            <h2 id="applicant-information-title"
                                                                class="text-lg font-medium leading-6 text-heading">
                                                                Call Information</h2>
                                                            <div class="mt-1 max-w-2xl text-sm text-muted space-y-1">
                                                                <div class="flex items-start gap-2">
                                                                    <span class="text-muted">SIP Call-ID:</span>
                                                                    <span class="text-heading break-all">{{ item.sip_call_id || '-' }}</span>
                                                    <button type="button"
                                                        @click="handleCopyToClipboard(item.sip_call_id)"
                                                        class="ml-2 p-1 rounded-full text-subtle hover:text-info hover:bg-info-subtle transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                        title="Copy to clipboard">
                                                        <!-- Small Copy Icon -->
                                                        <ClipboardDocumentIcon
                                                            class="h-4 w-4 text-muted hover:text-heading  cursor-pointer" />
                                                    </button>
                                                                </div>
                                                                <div class="flex items-start gap-2">
                                                                    <span class="text-muted">Unique ID:</span>
                                                                    <span class="text-heading break-all">{{ item.xml_cdr_uuid || '-' }}</span>
                                                    <button type="button"
                                                        @click="handleCopyToClipboard(item.xml_cdr_uuid)"
                                                        class="ml-2 p-1 rounded-full text-subtle hover:text-info hover:bg-info-subtle transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                        title="Copy to clipboard">
                                                        <!-- Small Copy Icon -->
                                                        <ClipboardDocumentIcon
                                                            class="h-4 w-4 text-muted hover:text-heading  cursor-pointer" />
                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="border-t border-default px-4 py-5 sm:px-6">
                                                            <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                                                                <div class="sm:col-span-1">
                                                                    <dt class="text-sm font-medium text-muted">
                                                                        From</dt>
                                                                    <dd class="mt-1 text-sm text-heading">
                                                                        {{ item.caller_id_number_formatted }}
                                                                    </dd>
                                                                </div>
                                                                <div class="sm:col-span-1">
                                                                    <dt class="text-sm font-medium text-muted">To</dt>
                                                                    <dd class="mt-1 text-sm text-heading">
                                                                        {{ item.caller_destination_formatted }}
                                                                    </dd>
                                                                </div>

                                                                <div class="sm:col-span-2">
                                                                    <dl class="divide-y divide-default">
                                                                        <div
                                                                            class="flex justify-between py-3 text-sm font-medium">
                                                                            <dt class="text-heading">{{ 'Waiting time' }}
                                                                            </dt>
                                                                            <dd class="whitespace-nowrap text-muted">
                                                                                {{ item.waitsec_formatted }}
                                                                            </dd>
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between py-3 text-sm font-medium">
                                                                            <dt class="text-heading">{{ 'In-call duration'
                                                                            }}</dt>
                                                                            <dd class="whitespace-nowrap text-muted">

                                                                                {{ item.billsec_formatted }}
                                                                            </dd>
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between py-3 text-sm font-medium">
                                                                            <dt class="text-heading">{{ 'Total duration' }}
                                                                            </dt>
                                                                            <dd class="whitespace-nowrap text-muted">
                                                                                {{ item.duration_formatted }}</dd>
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between py-3 text-sm font-medium">
                                                                            <dt class="text-heading">{{ 'Status' }}</dt>
                                                                            <dd class="whitespace-nowrap text-muted">
                                                                                {{ item.status }}</dd>
                                                                        </div>
                                                                    </dl>
                                                                </div>
                                                                <!-- <div class="sm:col-span-2">
                                                                    <dt class="text-sm font-medium text-muted">Salary
                                                                        expectation</dt>
                                                                    <dd class="mt-1 text-sm text-heading">$120,000</dd>
                                                                </div>
                                                                <div class="sm:col-span-1">
                                                                    <dt class="text-sm font-medium text-muted">Phone</dt>
                                                                    <dd class="mt-1 text-sm text-heading">+1 555-555-5555
                                                                    </dd>
                                                                </div>
                                                                <div class="sm:col-span-2">
                                                                    <dt class="text-sm font-medium text-muted">About</dt>
                                                                    <dd class="mt-1 text-sm text-heading">Fugiat ipsum
                                                                        ipsum deserunt culpa aute sint do nostrud anim
                                                                        incididunt cillum culpa consequat. Excepteur qui
                                                                        ipsum aliquip consequat sint. Sit id mollit nulla
                                                                        mollit nostrud in ea officia proident. Irure nostrud
                                                                        pariatur mollit ad adipisicing reprehenderit
                                                                        deserunt qui eu.</dd>
                                                                </div>
                                                                <div class="sm:col-span-2">
                                                                    <dt class="text-sm font-medium text-muted">
                                                                        Attachments</dt>
                                                                    <dd class="mt-1 text-sm text-heading">
                                                                        <ul role="list"
                                                                            class="divide-y divide-default rounded-md border border-default">
                                                                            <li v-for="attachment in attachments"
                                                                                :key="attachment.name"
                                                                                class="flex items-center justify-between py-3 pl-3 pr-4 text-sm">
                                                                                <div class="flex w-0 flex-1 items-center">
                                                                                    <PaperClipIcon
                                                                                        class="h-5 w-5 flex-shrink-0 text-subtle"
                                                                                        aria-hidden="true" />
                                                                                    <span
                                                                                        class="ml-2 w-0 flex-1 truncate">{{
                                                                                            attachment.name }}</span>
                                                                                </div>
                                                                                <div class="ml-4 flex-shrink-0">
                                                                                    <a :href="attachment.href"
                                                                                        class="font-medium text-info hover:text-info">Download</a>
                                                                                </div>
                                                                            </li>
                                                                        </ul>
                                                                    </dd>
                                                                </div> -->
                                                            </dl>
                                                        </div>
                                                        <!-- <div>
                                                            <a href="#"
                                                                class="block bg-surface-2 px-4 py-4 text-center text-sm font-medium text-muted hover:text-body sm:rounded-b-lg">Read
                                                                full application</a>
                                                        </div> -->
                                                    </div>
                                                </section>

                                            </div>

                                            <section aria-labelledby="timeline-title" class="lg:col-start-2">
                                                <div class="bg-surface px-4 py-5 shadow sm:rounded-lg sm:px-6">
                                                    <h2 id="timeline-title" class="text-lg font-medium text-heading">
                                                        Timeline</h2>

                                                    <!-- Timeline Feed -->
                                                    <div class="flow-root mt-2">
                                                        <ul role="list" class="mb-8">

                                                            <!-- Separate first element -->
                                                            <li v-if="item.direction == 'inbound'">
                                                                <div class="relative pb-8">
                                                                    <span
                                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-surface-3"
                                                                        aria-hidden="true"></span>
                                                                    <div class="relative flex items-start space-x-3">
                                                                        <!-- <template> -->
                                                                        <div class="relative">
                                                                            <div
                                                                                class="flex rounded-full bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong">
                                                                                <PhoneIncomingIcon class="w-4 h-4 mr-2" />
                                                                                Call from
                                                                                {{ item.caller_id_number_formatted }}
                                                                            </div>
                                                                        </div>
                                                                        <!-- </template> -->
                                                                    </div>
                                                                </div>
                                                            </li>

                                                            <li v-if="item.direction == 'local'">
                                                                <div class="relative pb-8">
                                                                    <span
                                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-surface-3"
                                                                        aria-hidden="true"></span>
                                                                    <div class="relative flex items-start space-x-3">
                                                                        <div class="relative">
                                                                            <div
                                                                                class="flex rounded-full bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong">
                                                                                <PhoneLocalIcon class="w-4 h-4 mr-2" /> Call
                                                                                to
                                                                                {{ item.caller_destination_formatted
                                                                                }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>

                                                            <li v-if="item.direction == 'outbound'">
                                                                <div class="relative pb-8">
                                                                    <span
                                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-surface-3"
                                                                        aria-hidden="true"></span>
                                                                    <div class="relative flex items-start space-x-3">
                                                                        <div class="relative">
                                                                            <div
                                                                                class="flex rounded-full bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong">
                                                                                <PhoneLocalIcon class="w-4 h-4 mr-2" /> Call
                                                                                to
                                                                                {{ item.caller_destination_formatted
                                                                                }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>

                                                            <!-- Loop through call_flow items -->
                                                            <li v-for="(flow, index) in item.call_flow" :key="index">
                                                                <div class="relative pb-8">
                                                                    <span v-if="index !== item.call_flow.length"
                                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-surface-3"
                                                                        aria-hidden="true"></span>
                                                                    <div class="relative flex items-start space-x-3">
                                                                        <template
                                                                            v-if="flow.dialplan_app === 'Outbound Call'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <!-- <DialpadIcon class="w-4 h-4 mr-2" /> -->
                                                                                        <DialpadIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="min-w-0 flex-1">
                                                                                <div>
                                                                                    <div class="text-sm">
                                                                                        <div
                                                                                            class="font-medium text-heading">
                                                                                            <span
                                                                                                class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                                {{ flow.time_line }}
                                                                                            </span>
                                                                                            {{ flow.destination_number }}
                                                                                        </div>
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                                <div class="mt-2 text-sm text-body">
                                                                                    <p>{{ flow.comment }}</p>
                                                                                </div>
                                                                            </div>

                                                                        </template>



                                                                        <template
                                                                            v-if="flow.dialplan_app === 'Inbound Call'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <!-- <DialpadIcon class="w-4 h-4 mr-2" /> -->
                                                                                        <DialpadIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="min-w-0 flex-1">
                                                                                <div>
                                                                                    <div class="text-sm">
                                                                                        <div
                                                                                            class="font-medium text-heading">
                                                                                            <span
                                                                                                class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                                {{ flow.time_line }}
                                                                                            </span>
                                                                                            {{ flow.destination_number }}
                                                                                        </div>
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                                <div class="mt-2 text-sm text-body">
                                                                                    <p>{{ flow.comment }}</p>
                                                                                </div>
                                                                            </div>

                                                                        </template>

                                                                        <template v-if="flow.dialplan_app === 'Extension'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <ContactPhoneIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-1.5">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p v-if="flow.bridged_time != 0" class="mt-0.5 text-sm text-muted">
                                                                                        Result: Answered</p>
                                                                                    <p v-if="flow.call_disposition" class="mt-0.5 text-sm text-muted">
                                                                                        Result: {{ flow.call_disposition }}</p>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>


                                                                        <template v-if="flow.dialplan_app === 'Ring Group'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <UserGroupIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-1.5">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>
                                                                        <template
                                                                            v-if="flow.dialplan_app === 'Auto Receptionist'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <IvrIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app === 'Voicemail'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <VoicemailIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        Voicemail {{ flow.dialplan_name }}
                                                                                    </div>
                                                                                    <p v-if="item.voicemail_message">
                                                                                        The caller left a message
                                                                                    </p>
                                                                                    <p v-else="item.voicemail_message">
                                                                                        The caller did not leave a message
                                                                                    </p>

                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app === 'Schedule'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <CalendarDaysIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app === 'Virtual Fax'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <FaxIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app === 'Contact Center Queue'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <SupportAgent
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        Result: {{ item.cc_result }}</p>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app === 'Call Flow'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <AlternativeRouteIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>


                                                                        <template v-if="flow.dialplan_app === 'Park'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <ParkIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app && flow.dialplan_app.includes('Call Intercept')">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <MergeIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                       
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app === 'Misc. Destination'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-3 ring-8 ring-white">
                                                                                        <ContactPhoneIcon
                                                                                            class="h-5 w-5 text-muted"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-1.5">
                                                                                <div class="text-sm  text-muted">
                                                                                    <div class="font-medium text-heading">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-info-subtle px-1.5 py-0.5 text-sm font-medium text-info ring-1 ring-inset ring-info/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-heading">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p v-if="flow.bridged_time != 0" class="mt-0.5 text-sm text-muted">
                                                                                        Result: Answered</p>
                                                                                    <p v-if="flow.call_disposition" class="mt-0.5 text-sm text-muted">
                                                                                        Result: {{ flow.call_disposition }}</p>
                                                                                    <p class="mt-0.5 text-sm text-muted">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>




                                                                    </div>
                                                                </div>
                                                            </li>

                                                            <!-- Separate last element -->
                                                            <li>
                                                                <div class="relative pb-8">

                                                                    <div class="relative flex items-start space-x-3">
                                                                        <div class="relative">
                                                                            <div
                                                                                class="flex rounded-full bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong">
                                                                                <CallEndIcon class="w-4 h-4 mr-2" />
                                                                                End of the call

                                                                            </div>
                                                                        </div>
                                                                        <!-- <div class="relative">
                                                                            <span
                                                                                class="rounded-full bg-surface px-2.5 py-1.5 text-sm font-semibold text-heading shadow-sm ring-1 ring-inset ring-strong">
                                                                                End of the call
                                                                            </span>

                                                                        </div> -->
                                                                        <div class="min-w-0 flex-1">
                                                                            <div>
                                                                                <!-- <div class="text-sm">
                                                                                    <a
                                                                                        class="font-medium text-heading">End of the call</a>
                                                                                </div> -->
                                                                                <p class="mt-0.5 text-sm text-muted">
                                                                                <div v-if="item.call_disposition">
                                                                                    {{ item.call_disposition }}
                                                                                </div>



                                                                                </p>
                                                                            </div>
                                                                            <div class="mt-2 text-sm text-body">
                                                                                <p></p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <!-- <div class="mt-6 flex flex-col justify-stretch">
                                                        <button type="button"
                                                            class="inline-flex items-center justify-center rounded-md bg-info px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-info focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-info">Advance
                                                            to offer</button>
                                                    </div> -->
                                                </div>
                                            </section>
                                        </div>
                                    </main>




                                </div>
                            </div>
                            <Loading :show="loading" :absolute="false" />
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from "@heroicons/vue/24/solid";
import Loading from "../general/Loading.vue";

import PhoneOutgoingIcon from "../icons/PhoneOutgoingIcon.vue"
import PhoneIncomingIcon from "../icons/PhoneIncomingIcon.vue"
import PhoneLocalIcon from "../icons/PhoneLocalIcon.vue"
import { ClipboardDocumentIcon } from "@heroicons/vue/24/outline";

import {
    UserGroupIcon,
    CalendarDaysIcon
} from "@heroicons/vue/24/solid";

import ContactPhoneIcon from "../icons/ContactPhoneIcon.vue"
import DialpadIcon from "../icons/DialpadIcon.vue"
import AlternativeRouteIcon from "../icons/AlternativeRouteIcon.vue"
import IvrIcon from "../icons/IvrIcon.vue"
import SupportAgent from "../icons/SupportAgent.vue"
import CallEndIcon from "../icons/CallEndIcon.vue"
import VoicemailIcon from "../icons/VoicemailIcon.vue"
import FaxIcon from "../icons/FaxIcon.vue"
import ParkIcon from "../icons/ParkIcon.vue"
import MergeIcon from "../icons/MergeIcon.vue"

const emit = defineEmits(['close', 'success', 'error'])

const props = defineProps({
    item: Object,
    show: Boolean,
    header: String,
    loading: Boolean,
    customClass: {
        type: String,
        default: 'sm:max-w-lg'
    },
});

const handleCopyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        emit('success', 'success', { message: ['Copied to clipboard.'] });
    }).catch((error) => {
        // Handle the error case
        emit('error', { response: { data: { errors: { request: ['Failed to copy to clipboard.'] } } } });
    });
}

function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

</script>
