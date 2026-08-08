<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto ">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            :class="['relative transform rounded-lg bg-gray-100 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:p-6', customClass]">
                            <div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-gray-100 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">{{ $t('Close') }}</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>
                            <div v-if="!loading">
                                <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
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
                                                <h1 class="text-2xl font-bold text-gray-900">{{
                                                    directionHeading(item.direction) }}</h1>
                                                <p class="text-sm font-medium text-gray-500">{{ $t('On :date at :time', { date: item.start_date, time: item.start_time }) }}</p>
                                            </div>
                                        </div>
                                        <!-- <div
                                                class="mt-6 flex flex-col-reverse justify-stretch space-y-4 space-y-reverse sm:flex-row-reverse sm:justify-end sm:space-x-3 sm:space-y-0 sm:space-x-reverse md:mt-0 md:flex-row md:space-x-3">
                                                <button type="button"
                                                    class="inline-flex items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Disqualify</button>
                                                <button type="button"
                                                    class="inline-flex items-center justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">Advance
                                                    to offer</button>
                                            </div> -->
                                        <!-- </div> -->

                                        <div
                                            class="mx-auto mt-8 grid max-w-3xl grid-cols-1 gap-6 sm:px-6 lg:max-w-7xl lg:grid-flow-col-dense lg:grid-cols-2">
                                            <div class="space-y-6 lg:col-start-1">
                                                <!-- Description list-->
                                                <section aria-labelledby="applicant-information-title">
                                                    <div class="bg-white shadow sm:rounded-lg">
                                                        <div class="px-4 py-5 sm:px-6">
                                                            <h2 id="applicant-information-title"
                                                                class="text-lg font-medium leading-6 text-gray-900">
                                                                {{ $t('Call Information') }}</h2>
                                                            <div class="mt-1 max-w-2xl text-sm text-gray-500 space-y-1">
                                                                <div class="flex items-start gap-2">
                                                                    <span class="text-gray-500">{{ $t('SIP Call-ID:') }}</span>
                                                                    <span class="text-gray-900 break-all">{{ item.sip_call_id || '-' }}</span>
                                                    <button type="button"
                                                        @click="handleCopyToClipboard(item.sip_call_id)"
                                                        class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                        :title="$t('Copy to clipboard')">
                                                        <!-- Small Copy Icon -->
                                                        <ClipboardDocumentIcon
                                                            class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                                    </button>
                                                                </div>
                                                                <div class="flex items-start gap-2">
                                                                    <span class="text-gray-500">{{ $t('Unique ID:') }}</span>
                                                                    <span class="text-gray-900 break-all">{{ item.xml_cdr_uuid || '-' }}</span>
                                                    <button type="button"
                                                        @click="handleCopyToClipboard(item.xml_cdr_uuid)"
                                                        class="ml-2 p-1 rounded-full text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2"
                                                        :title="$t('Copy to clipboard')">
                                                        <!-- Small Copy Icon -->
                                                        <ClipboardDocumentIcon
                                                            class="h-4 w-4 text-gray-500 hover:text-gray-900  cursor-pointer" />
                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                                                            <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                                                                <div class="sm:col-span-1">
                                                                    <dt class="text-sm font-medium text-gray-500">
                                                                        {{ $t('From') }}</dt>
                                                                    <dd class="mt-1 text-sm text-gray-900">
                                                                        {{ item.caller_id_number_formatted }}
                                                                    </dd>
                                                                </div>
                                                                <div class="sm:col-span-1">
                                                                    <dt class="text-sm font-medium text-gray-500">{{ $t('To') }}</dt>
                                                                    <dd class="mt-1 text-sm text-gray-900">
                                                                        {{ item.caller_destination_formatted }}
                                                                    </dd>
                                                                </div>

                                                                <div class="sm:col-span-2">
                                                                    <dl class="divide-y divide-gray-200">
                                                                        <div
                                                                            class="flex justify-between py-3 text-sm font-medium">
                                                                            <dt class="text-gray-900">{{ $t('Waiting time') }}
                                                                            </dt>
                                                                            <dd class="whitespace-nowrap text-gray-500">
                                                                                {{ item.waitsec_formatted }}
                                                                            </dd>
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between py-3 text-sm font-medium">
                                                                            <dt class="text-gray-900">{{ $t('In-call duration')
                                                                            }}</dt>
                                                                            <dd class="whitespace-nowrap text-gray-500">

                                                                                {{ item.billsec_formatted }}
                                                                            </dd>
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between py-3 text-sm font-medium">
                                                                            <dt class="text-gray-900">{{ $t('Total duration') }}
                                                                            </dt>
                                                                            <dd class="whitespace-nowrap text-gray-500">
                                                                                {{ item.duration_formatted }}</dd>
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between py-3 text-sm font-medium">
                                                                            <dt class="text-gray-900">{{ $t('Status') }}</dt>
                                                                            <dd class="whitespace-nowrap text-gray-500">
                                                                                {{ statusLabel(item.status) }}</dd>
                                                                        </div>
                                                                    </dl>
                                                                </div>
                                                                <!-- <div class="sm:col-span-2">
                                                                    <dt class="text-sm font-medium text-gray-500">Salary
                                                                        expectation</dt>
                                                                    <dd class="mt-1 text-sm text-gray-900">$120,000</dd>
                                                                </div>
                                                                <div class="sm:col-span-1">
                                                                    <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                                                    <dd class="mt-1 text-sm text-gray-900">+1 555-555-5555
                                                                    </dd>
                                                                </div>
                                                                <div class="sm:col-span-2">
                                                                    <dt class="text-sm font-medium text-gray-500">About</dt>
                                                                    <dd class="mt-1 text-sm text-gray-900">Fugiat ipsum
                                                                        ipsum deserunt culpa aute sint do nostrud anim
                                                                        incididunt cillum culpa consequat. Excepteur qui
                                                                        ipsum aliquip consequat sint. Sit id mollit nulla
                                                                        mollit nostrud in ea officia proident. Irure nostrud
                                                                        pariatur mollit ad adipisicing reprehenderit
                                                                        deserunt qui eu.</dd>
                                                                </div>
                                                                <div class="sm:col-span-2">
                                                                    <dt class="text-sm font-medium text-gray-500">
                                                                        Attachments</dt>
                                                                    <dd class="mt-1 text-sm text-gray-900">
                                                                        <ul role="list"
                                                                            class="divide-y divide-gray-200 rounded-md border border-gray-200">
                                                                            <li v-for="attachment in attachments"
                                                                                :key="attachment.name"
                                                                                class="flex items-center justify-between py-3 pl-3 pr-4 text-sm">
                                                                                <div class="flex w-0 flex-1 items-center">
                                                                                    <PaperClipIcon
                                                                                        class="h-5 w-5 flex-shrink-0 text-gray-400"
                                                                                        aria-hidden="true" />
                                                                                    <span
                                                                                        class="ml-2 w-0 flex-1 truncate">{{
                                                                                            attachment.name }}</span>
                                                                                </div>
                                                                                <div class="ml-4 flex-shrink-0">
                                                                                    <a :href="attachment.href"
                                                                                        class="font-medium text-blue-600 hover:text-blue-500">Download</a>
                                                                                </div>
                                                                            </li>
                                                                        </ul>
                                                                    </dd>
                                                                </div> -->
                                                            </dl>
                                                        </div>
                                                        <!-- <div>
                                                            <a href="#"
                                                                class="block bg-gray-50 px-4 py-4 text-center text-sm font-medium text-gray-500 hover:text-gray-700 sm:rounded-b-lg">Read
                                                                full application</a>
                                                        </div> -->
                                                    </div>
                                                </section>

                                            </div>

                                            <section aria-labelledby="timeline-title" class="lg:col-start-2">
                                                <div class="bg-white px-4 py-5 shadow sm:rounded-lg sm:px-6">
                                                    <h2 id="timeline-title" class="text-lg font-medium text-gray-900">
                                                        {{ $t('Timeline') }}</h2>

                                                    <!-- Timeline Feed -->
                                                    <div class="flow-root mt-2">
                                                        <ul role="list" class="mb-8">

                                                            <!-- Separate first element -->
                                                            <li v-if="item.direction == 'inbound'">
                                                                <div class="relative pb-8">
                                                                    <span
                                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200"
                                                                        aria-hidden="true"></span>
                                                                    <div class="relative flex items-start space-x-3">
                                                                        <!-- <template> -->
                                                                        <div class="relative">
                                                                            <div
                                                                                class="flex rounded-full bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-600">
                                                                                <PhoneIncomingIcon class="w-4 h-4 mr-2" />
                                                                                {{ $t('Call from :number', { number: item.caller_id_number_formatted }) }}
                                                                            </div>
                                                                        </div>
                                                                        <!-- </template> -->
                                                                    </div>
                                                                </div>
                                                            </li>

                                                            <li v-if="item.direction == 'local'">
                                                                <div class="relative pb-8">
                                                                    <span
                                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200"
                                                                        aria-hidden="true"></span>
                                                                    <div class="relative flex items-start space-x-3">
                                                                        <div class="relative">
                                                                            <div
                                                                                class="flex rounded-full bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-600">
                                                                                <PhoneLocalIcon class="w-4 h-4 mr-2" />
                                                                                {{ $t('Call to :number', { number: item.caller_destination_formatted }) }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>

                                                            <li v-if="item.direction == 'outbound'">
                                                                <div class="relative pb-8">
                                                                    <span
                                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200"
                                                                        aria-hidden="true"></span>
                                                                    <div class="relative flex items-start space-x-3">
                                                                        <div class="relative">
                                                                            <div
                                                                                class="flex rounded-full bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-600">
                                                                                <PhoneLocalIcon class="w-4 h-4 mr-2" />
                                                                                {{ $t('Call to :number', { number: item.caller_destination_formatted }) }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </li>

                                                            <!-- Loop through call_flow items -->
                                                            <li v-for="(flow, index) in item.call_flow" :key="index">
                                                                <div class="relative pb-8">
                                                                    <span v-if="index !== item.call_flow.length"
                                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200"
                                                                        aria-hidden="true"></span>
                                                                    <div class="relative flex items-start space-x-3">
                                                                        <template
                                                                            v-if="flow.dialplan_app_type === 'outbound_call'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <!-- <DialpadIcon class="w-4 h-4 mr-2" /> -->
                                                                                        <DialpadIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="min-w-0 flex-1">
                                                                                <div>
                                                                                    <div class="text-sm">
                                                                                        <div
                                                                                            class="font-medium text-gray-900">
                                                                                            <span
                                                                                                class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                                {{ flow.time_line }}
                                                                                            </span>
                                                                                            {{ flow.destination_number }}
                                                                                        </div>
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                                <div class="mt-2 text-sm text-gray-700">
                                                                                    <p>{{ flow.comment }}</p>
                                                                                </div>
                                                                            </div>

                                                                        </template>



                                                                        <template
                                                                            v-if="flow.dialplan_app_type === 'inbound_call'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <!-- <DialpadIcon class="w-4 h-4 mr-2" /> -->
                                                                                        <DialpadIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <div class="min-w-0 flex-1">
                                                                                <div>
                                                                                    <div class="text-sm">
                                                                                        <div
                                                                                            class="font-medium text-gray-900">
                                                                                            <span
                                                                                                class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                                {{ flow.time_line }}
                                                                                            </span>
                                                                                            {{ flow.destination_number }}
                                                                                        </div>
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                                <div class="mt-2 text-sm text-gray-700">
                                                                                    <p>{{ flow.comment }}</p>
                                                                                </div>
                                                                            </div>

                                                                        </template>

                                                                        <template v-if="flow.dialplan_app_type === 'extension'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <ContactPhoneIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-1.5">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p v-if="flow.bridged_time != 0" class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ $t('Result: Answered') }}</p>
                                                                                    <p v-if="flow.call_disposition" class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ $t('Result: :disposition', { disposition: flow.call_disposition }) }}</p>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>


                                                                        <template v-if="flow.dialplan_app_type === 'ring_group'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <UserGroupIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-1.5">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>
                                                                        <template
                                                                            v-if="flow.dialplan_app_type === 'auto_receptionist'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <IvrIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app_type === 'voicemail'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <VoicemailIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ $t('Voicemail :name', { name: flow.dialplan_name }) }}
                                                                                    </div>
                                                                                    <p v-if="item.voicemail_message">
                                                                                        {{ $t('The caller left a message') }}
                                                                                    </p>
                                                                                    <p v-else="item.voicemail_message">
                                                                                        {{ $t('The caller did not leave a message') }}
                                                                                    </p>

                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app_type === 'schedule'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <CalendarDaysIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app_type === 'virtual_fax'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <FaxIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app_type === 'contact_center_queue'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <SupportAgent
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ $t('Result: :disposition', { disposition: item.cc_result }) }}</p>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app_type === 'call_flow'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <AlternativeRouteIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>


                                                                        <template v-if="flow.dialplan_app_type === 'park'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <ParkIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app_type === 'call_intercept'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <MergeIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-0">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                       
                                                                                    </div>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ flow.duration_formatted }}</p>
                                                                                </div>
                                                                            </div>
                                                                        </template>

                                                                        <template v-if="flow.dialplan_app_type === 'misc_destination'">
                                                                            <div>
                                                                                <div class="relative px-1">
                                                                                    <div
                                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                                        <ContactPhoneIcon
                                                                                            class="h-5 w-5 text-gray-500"
                                                                                            aria-hidden="true" />
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="min-w-0 flex-1 py-1.5">
                                                                                <div class="text-sm  text-gray-500">
                                                                                    <div class="font-medium text-gray-900">
                                                                                        <span
                                                                                            class="inline-flex items-center rounded-full bg-blue-50 px-1.5 py-0.5 text-sm font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                                                            {{ flow.time_line }}
                                                                                        </span>
                                                                                        {{ flow.dialplan_app }}
                                                                                    </div>
                                                                                    <div
                                                                                        class="font-semibold text-gray-900">
                                                                                        {{ flow.dialplan_name }}
                                                                                        ({{ flow.destination_number }})
                                                                                    </div>
                                                                                    <p v-if="flow.bridged_time != 0" class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ $t('Result: Answered') }}</p>
                                                                                    <p v-if="flow.call_disposition" class="mt-0.5 text-sm text-gray-500">
                                                                                        {{ $t('Result: :disposition', { disposition: flow.call_disposition }) }}</p>
                                                                                    <p class="mt-0.5 text-sm text-gray-500">
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
                                                                                class="flex rounded-full bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-600">
                                                                                <CallEndIcon class="w-4 h-4 mr-2" />
                                                                                {{ $t('End of the call') }}

                                                                            </div>
                                                                        </div>
                                                                        <!-- <div class="relative">
                                                                            <span
                                                                                class="rounded-full bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-600">
                                                                                End of the call
                                                                            </span>

                                                                        </div> -->
                                                                        <div class="min-w-0 flex-1">
                                                                            <div>
                                                                                <!-- <div class="text-sm">
                                                                                    <a
                                                                                        class="font-medium text-gray-900">End of the call</a>
                                                                                </div> -->
                                                                                <p class="mt-0.5 text-sm text-gray-500">
                                                                                <div v-if="item.call_disposition">
                                                                                    {{ item.call_disposition }}
                                                                                </div>



                                                                                </p>
                                                                            </div>
                                                                            <div class="mt-2 text-sm text-gray-700">
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
                                                            class="inline-flex items-center justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">Advance
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
import { trans } from "@i18n";
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
        emit('success', 'success', { message: [trans('Copied to clipboard.')] });
    }).catch((error) => {
        // Handle the error case
        emit('error', { response: { data: { errors: { request: [trans('Failed to copy to clipboard.')] } } } });
    });
}

function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

const directionHeading = (direction) => {
    switch (direction) {
        case 'outbound':
            return trans('Outbound Call');
        case 'inbound':
            return trans('Inbound Call');
        case 'local':
            return trans('Local Call');
        default:
            return capitalizeFirstLetter(direction) + ' Call';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'answered':
            return trans('Answered');
        case 'no_answer':
            return trans('No Answer');
        case 'cancelled':
            return trans('Cancelled');
        case 'voicemail':
            return trans('Voicemail');
        case 'missed call':
            return trans('Missed Call');
        case 'abandoned':
            return trans('Abandoned');
        case 'failed':
            return trans('Failed');
        default:
            return status;
    }
};

</script>
