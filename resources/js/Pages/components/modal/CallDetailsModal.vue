<template>
    <TransitionRoot as="template" :show="show">
        <Dialog as="div" class="relative z-10">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
            </TransitionChild>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center">
                    <TransitionChild as="template" enter="ease-out duration-300"
                        enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to="opacity-100 translate-y-0 sm:scale-100" leave="ease-in duration-200"
                        leave-from="opacity-100 translate-y-0 sm:scale-100"
                        leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                        <DialogPanel
                            :class="['relative transform rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:p-6', customClass]">
                            <div class="absolute right-0 top-0 hidden pr-4 pt-4 sm:block">
                                <button type="button"
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="emit('close')">
                                    <span class="sr-only">Close</span>
                                    <XMarkIcon class="h-6 w-6" aria-hidden="true" />
                                </button>
                            </div>
                            <div v-if="!loading">
                                <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                                    {{ header }}
                                </DialogTitle>
                                <div class="mt-4 pb-4">
                                    <slot name="modal-body"></slot>




                                    <div class="flow-root">
                                        <ul role="list" class="mb-8">
                                            <!-- Loop through call_flow items -->
                                            <li v-for="(flow, index) in item.call_flow" :key="index">
                                                <div class="relative pb-8">
                                                    <span v-if="index !== item.call_flow.length"
                                                        class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200"
                                                        aria-hidden="true"></span>
                                                    <div class="relative flex items-start space-x-3">
                                                        <template v-if="flow.dialplan_app === 'Outbound Call'">
                                                            <div class="relative">
                                                                <img class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-400 ring-8 ring-white"
                                                                    :src="'https://avataaars.io/?avatarStyle=Circle&topType=LongHairStraight&accessoriesType=Blank&hairColor=BrownDark&facialHairType=Blank&clotheType=BlazerShirt&eyeType=Default&eyebrowType=Default&mouthType=Default&skinColor=Light'" />
                                                                <span
                                                                    class="absolute -bottom-0.5 -right-1 rounded-tl bg-white px-0.5 py-px">
                                                                    <ChatBubbleLeftEllipsisIcon
                                                                        class="h-5 w-5 text-gray-400" aria-hidden="true" />
                                                                </span>
                                                            </div>
                                                            <div class="min-w-0 flex-1">
                                                                <div>
                                                                    <div class="text-sm">
                                                                        <a class="font-medium text-gray-900">{{
                                                                            flow.destination_number }}</a>
                                                                    </div>
                                                                    <p class="mt-0.5 text-sm text-gray-500"> {{
                                                                        flow.time_line }}</p>
                                                                </div>
                                                                <div class="mt-2 text-sm text-gray-700">
                                                                    <p>{{ flow.comment }}</p>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        
                                                        <template v-if="flow.dialplan_app === 'Inbound Call'">
                                                            <div class="relative">
                                                                <img class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-400 ring-8 ring-white"
                                                                    :src="'https://avataaars.io/?avatarStyle=Circle&topType=LongHairStraight&accessoriesType=Blank&hairColor=BrownDark&facialHairType=Blank&clotheType=BlazerShirt&eyeType=Default&eyebrowType=Default&mouthType=Default&skinColor=Light'" />

                                                                <span
                                                                    class="absolute -bottom-0.5 -right-1 rounded-tl bg-white px-0.5 py-px">
                                                                    <ChatBubbleLeftEllipsisIcon
                                                                        class="h-5 w-5 text-gray-400" aria-hidden="true" />
                                                                </span>
                                                            </div>
                                                            <div class="min-w-0 flex-1">
                                                                <div>
                                                                    <div class="text-sm">
                                                                        <a class="font-medium text-gray-900">{{
                                                                            flow.destination_number }}</a>
                                                                    </div>
                                                                    <p class="mt-0.5 text-sm text-gray-500">{{
                                                                        flow.time_line }}</p>
                                                                </div>
                                                                <div class="mt-2 text-sm text-gray-700">
                                                                    <p>{{ flow.comment }}</p>
                                                                </div>
                                                            </div>
                                                        </template>

                                                        <template v-if="flow.dialplan_app === 'Extension'">
                                                            <div>
                                                                <div class="relative px-1">
                                                                    <div
                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                        <UserCircleIcon class="h-5 w-5 text-gray-500"
                                                                            aria-hidden="true" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="min-w-0 flex-1 py-1.5">
                                                                <div class="text-sm text-gray-500">
                                                                    <a class="font-medium text-gray-900">{{
                                                                        flow.dialplan_app }} {{ flow.destination_number
    }}</a>

                                                                    <p class="mt-0.5 text-sm text-gray-500">{{
                                                                        flow.time_line }}</p>
                                                                </div>
                                                            </div>
                                                        </template>

                                                        <template v-if="flow.dialplan_app === 'Ring Group'">
                                                            <div>
                                                                <div class="relative px-1">
                                                                    <div
                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                        <UserCircleIcon class="h-5 w-5 text-gray-500"
                                                                            aria-hidden="true" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="min-w-0 flex-1 py-1.5">
                                                                <div class="text-sm text-gray-500">
                                                                    <a class="font-medium text-gray-900">{{
                                                                        flow.dialplan_app }} {{ flow.destination_number
    }}</a>

                                                                    <p class="mt-0.5 text-sm text-gray-500">{{
                                                                        flow.time_line }}</p>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <template v-if="flow.dialplan_app === 'Auto Receptionist'">
                                                            <div>
                                                                <div class="relative px-1">
                                                                    <div
                                                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 ring-8 ring-white">
                                                                        <TagIcon class="h-5 w-5 text-gray-500"
                                                                            aria-hidden="true" />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="min-w-0 flex-1 py-0">
                                                                <div class="text-sm leading-8 text-gray-500">
                                                                    <a class="font-medium text-gray-900">{{
                                                                        flow.dialplan_app }} {{ flow.destination_number }}</a>
                                                                    <p class="mt-0.5 text-sm text-gray-500">{{
                                                                        flow.time_line }}</p>

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
                                                            <img class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-400 ring-8 ring-white"
                                                                :src="'https://avataaars.io/?avatarStyle=Circle&topType=LongHairStraight&accessoriesType=Blank&hairColor=BrownDark&facialHairType=Blank&clotheType=BlazerShirt&eyeType=Default&eyebrowType=Default&mouthType=Default&skinColor=Light'" />
                                                            <span
                                                                class="absolute -bottom-0.5 -right-1 rounded-tl bg-white px-0.5 py-px">
                                                                <ChatBubbleLeftEllipsisIcon class="h-5 w-5 text-gray-400"
                                                                    aria-hidden="true" />
                                                            </span>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <div>
                                                                <div class="text-sm">
                                                                    <a class="font-medium text-gray-900">End</a>
                                                                </div>
                                                                <p class="mt-0.5 text-sm text-gray-500"></p>
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

const emit = defineEmits(['close'])

const props = defineProps({
    item: Object,
    show: Boolean,
    header: String,
    loading: Boolean,
    customClass: {
        type: String,
        default: 'sm:max-w-lg'
    },
    ManagementTabs: Array,
    selectedTab: Number,
    isCurrentTab: Function,
    selectTab: Function,
});
</script>
