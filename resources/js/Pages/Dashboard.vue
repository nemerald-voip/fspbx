<template>
    <MainLayout>
        <main>
            <div class="mx-auto max-w-8xl px-4 py-10 sm:px-6 lg:px-8">
                <div
                    class="mx-auto grid max-w-2xl grid-cols-1 grid-rows-1 items-start gap-x-8 gap-y-8 lg:mx-0 lg:max-w-none lg:grid-cols-3">
                    <!-- Company summary -->
                    <div class="lg:col-start-3 lg:row-end-1">
                        <div class="rounded-lg bg-gray-50 shadow-sm ring-1 ring-gray-900/5">

                            <dl class="flex flex-wrap">
                                <div class="flex-auto pl-6 py-6 truncate border-b border-gray-900/5">
                                    <dt class="text-sm  leading-6 text-gray-600">Company name</dt>
                                    <div class="mt-1 text-lg font-semibold leading-6 text-gray-900">{{
                                        company_data.company_name }}</div>
                                </div>

                                <!-- <div class="flex-none self-end px-6 pt-4">
                                    <dt class="sr-only">Status</dt>
                                    <dd
                                        class="rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-600 ring-1 ring-inset ring-green-600/20">
                                        Paid</dd>
                                </div> -->

                                <div v-if="Object.keys(data).length === 0" class="w-full">
                                    <div class="animate-pulse flex space-x-4 pt-6 pl-6 w-6/12">
                                        <div class="flex-1 space-y-6 py-1">
                                            <div class="h-2 bg-slate-300 rounded w-10/12"></div>
                                            <div class="grid grid-cols-3 gap-4">
                                                <div class="h-2 bg-slate-300 rounded col-span-2"></div>
                                                <div class="h-2 bg-slate-300 rounded col-span-1"></div>
                                            </div>
                                            <div class="grid grid-cols-3 gap-4 w-9/12">
                                                <div class="h-2 bg-slate-300 rounded col-span-1"></div>
                                                <div class="h-2 bg-slate-300 rounded col-span-2"></div>
                                            </div>

                                        </div>
                                    </div>
                                </div>


                                <div v-if="Object.keys(counts).length !== 0 && counts.extensions && counts.extensions >= 0"
                                    class="mt-6 flex w-full flex-none gap-x-4 px-6">
                                    <dt class="flex-none">
                                        <ContactPhoneIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm leading-6 text-gray-500">
                                        <span class="pr-3">Extensions: {{ counts.extensions }}</span>
                                        <span v-if="counts.local_reg_count && counts.local_reg_count >= 0"
                                            class="pr-3 text-green-600 text-nowrap">
                                            Online: {{ counts.local_reg_count }}
                                        </span>
                                        <span v-if="counts.local_reg_count && counts.local_reg_count >= 0"
                                            class=" text-rose-600 text-nowrap">
                                            Offline: {{ counts.extensions - counts.local_reg_count }}
                                        </span>
                                    </dd>
                                </div>

                                <div v-if="Object.keys(counts).length != 0 && counts.phone_numbers && counts.phone_numbers >= 0"
                                    class="mt-4 flex w-full flex-none gap-x-4 px-6">
                                    <dt class="flex-none">
                                        <DialpadIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm leading-6 text-gray-500">Phone Numbers: {{ counts.phone_numbers
                                    }}
                                    </dd>
                                </div>

                                <div v-if="Object.keys(counts).length != 0 && counts.faxes && counts.faxes >= 0"
                                    class="mt-4 flex w-full flex-none gap-x-4 px-6">
                                    <dt class="flex-none">
                                        <FaxIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm leading-6 text-gray-500">Virtual Faxes: {{ counts.faxes }}</dd>
                                </div>

                                <div class="mt-4 flex w-full flex-none gap-x-4 px-6 pb-8">
                                    <dt class="flex-none">
                                        <ClockIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm font-medium leading-6 text-gray-500">Time Zone: {{
                                        company_data.time_zone }}</dd>
                                </div>
                            </dl>

                            <!-- <div class="mt-6 border-t border-gray-900/5 px-6 py-6">
                                <a href="#" class="text-sm font-semibold leading-6 text-gray-900">Download receipt <span
                                        aria-hidden="true">&rarr;</span></a>
                            </div> -->
                        </div>
                    </div>

                    <!-- Quick Access -->
                    <div
                        class="-mx-4 px-4 py-8 shadow-sm bg-gray-50 ring-1 ring-gray-900/5 sm:mx-0 sm:rounded-lg sm:px-8 sm:pb-14 lg:col-span-2 lg:row-span-2 lg:row-end-2 xl:px-12 xl:pb-16 xl:pt-12">

                        <h2 class="text-base font-semibold leading-6 text-gray-900">Quick Access</h2>
                        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="card in cards" :key="card.slug">
                                <DashboardTile :card="card" :count="counts[card.slug]" />
                            </div>
                        </div>

                    </div>

                    <!-- Global Info -->
                    <div class="lg:hidde" v-show="props.data.superadmin">
                        <div class="rounded-lg bg-gray-50 shadow-sm ring-1 ring-gray-900/5">

                            <dl class="flex flex-wrap">
                                <div class="flex-auto pl-6 py-6 truncate border-b border-gray-900/5">
                                    <div class="mt-1 text-lg font-semibold leading-6 text-gray-900">Global Info</div>
                                    <dt class="text-sm  leading-6 text-gray-600">(Superadmin Only)</dt>

                                </div>

                                <div class="mt-6 mb-4 flex w-full  gap-x-4 px-6">
                                    <div
                                        class="grid  grid-cols-1 md:grid-cols-2 w-full divide-x divide-gray-200 overflow-hidden rounded-lg bg-white shadow">

                                        <div class=" px-4  shadow py-5">
                                            <dt class="truncate text-sm font-medium text-gray-500">Total Domains</dt>
                                            <dd class="mt-2 text-3xl font-semibold tracking-tight text-indigo-600">
                                                {{ data.domain_count }}
                                            </dd>
                                        </div>

                                        <div class="px-4 shadow py-5">
                                            <dt class="truncate text-sm font-medium text-gray-500">Total extensions</dt>
                                            <dd class="mt-1 flex items-baseline justify-between">
                                                <div class="flex items-baseline font-semibold text-gray-500">
                                                    {{ counts.global_reg_count }}
                                                    <span class="ml-2 text-sm font-medium text-gray-500">
                                                        online
                                                    </span>
                                                </div>

                                                <div
                                                    class="bg-sky-100 text-sky-800', 'inline-flex items-baseline rounded-full px-2.5 py-0.5 text-sm font-medium md:mt-2 lg:mt-0'">
                                                    Total {{ data.extension_count }}
                                                </div>

                                            </dd>
                                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2.5 ">
                                                <div class="bg-green-500 h-2.5 rounded-full"
                                                    :style="{ width: (counts.global_reg_count / data.extension_count * 100) + '%' }">
                                                </div>
                                            </div>
                                        </div>


                                        <div class="px-4 shadow py-5">
                                            <dd class="mt-1 flex items-baseline justify-between">
                                                <div class="text-sm font-medium text-gray-500">
                                                    Disk: <span> {{ Math.round(data.diskused * 10) / 10 }}/{{
                                                        Math.round(data.disktotal * 10) / 10 }} GB</span>

                                                </div>

                                                <div :class="[
                                                    data.diskusagecolor === 'bg-success' ? 'bg-green-100 text-green-600' :
                                                        data.diskusagecolor === 'bg-warning' ? 'bg-yellow-100 text-yellow-600' :
                                                            data.diskusagecolor === 'bg-danger' ? 'bg-rose-100 text-rose-600' :
                                                                'bg-sky-100 text-sky-800',
                                                    'inline-flex items-baseline rounded-full px-2.5 py-0.5 text-sm font-medium md:mt-2 lg:mt-0'
                                                ]">
                                                    {{ Math.round(data.diskusage) }}%
                                                </div>

                                            </dd>
                                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2.5 ">
                                                <div :class="data.diskusagecolor === 'bg-success' ? 'bg-green-500' : data.diskusagecolor === 'bg-warning' ? 'bg-yellow-500' : data.diskusagecolor === 'bg-danger' ? 'bg-rose-500' : ''"
                                                    class="h-2.5 rounded-full" :style="{ width: data.diskusage + '%' }">
                                                </div>
                                            </div>

                                            <dd class="mt-1 flex items-baseline justify-between">
                                                <div class="text-sm font-medium text-gray-500">
                                                    Memory: <span> {{ Math.round(data.ramused * 10) / 10 }}/{{
                                                        Math.round(data.ramtotal
                                                            *
                                                            10) / 10 }} GB</span>

                                                </div>

                                                <div :class="[
                                                    data.ramusagecolor === 'bg-success' ? 'bg-green-100 text-green-600' :
                                                        data.ramusagecolor === 'bg-warning' ? 'bg-yellow-100 text-yellow-600' :
                                                            data.ramusagecolor === 'bg-danger' ? 'bg-rose-100 text-rose-600' :
                                                                'bg-sky-100 text-sky-800',
                                                    'inline-flex items-baseline rounded-full px-2.5 py-0.5 text-sm font-medium md:mt-2 lg:mt-0'
                                                ]">
                                                    {{ Math.round(data.ramusage) }}%
                                                </div>

                                            </dd>
                                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2.5 ">
                                                <div :class="data.ramusagecolor === 'bg-success' ? 'bg-green-500' : data.ramusagecolor === 'bg-warning' ? 'bg-yellow-500' : data.ramusagecolor === 'bg-danger' ? 'bg-rose-500' : ''"
                                                    class="h-2.5 rounded-full" :style="{ width: data.ramusage + '%' }">
                                                </div>
                                            </div>


                                        </div>

                                        <div class="px-4 shadow py-5">
                                            <dd class="mt-1 flex items-baseline justify-between">
                                                <div class="text-sm font-medium text-gray-500">
                                                    Hostname
                                                </div>

                                                <div
                                                    class="truncate inline-flex items-baseline py-0.5 text-sm font-medium '">
                                                    <span class="px-2.5 py-0.5 rounded-full bg-slate-200">{{ data.hostname
                                                    }}</span>
                                                </div>
                                            </dd>

                                            <dd class="mt-1 flex items-baseline justify-between ">
                                                <div class="text-sm font-medium text-gray-500">
                                                    Uptime
                                                </div>

                                                <div class="truncate pl-12 py-0.5 text-sm font-medium ">
                                                    <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-600">{{
                                                        data.uptime
                                                    }}</span>
                                                </div>

                                            </dd>

                                            <dd class="mt-1 flex items-baseline justify-between ">
                                                <div class="text-sm font-medium text-gray-500">
                                                    CPU cores
                                                </div>

                                                <div class="truncate pl-12 py-0.5 text-sm font-medium ">
                                                    <span class="px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-600">{{
                                                        data.core_count
                                                    }}</span>
                                                </div>

                                            </dd>


                                            <dd class="mt-1 flex items-baseline justify-between">
                                                <div class="text-sm font-medium text-gray-500">
                                                    Horizon Status
                                                </div>

                                                <div class="truncate pl-12 py-0.5 text-sm font-medium ">
                                                    <span :class="[
                                                                data.horizonStatus === 'running' ? 'bg-green-100 text-green-600' :
                                                                    data.horizonStatus === 'paused' ? 'bg-yellow-100 text-yellow-600' : '',
                                                                'px-2.5 py-0.5 rounded-full'
                                                            ]">
                                                        {{ data.horizonStatus }}
                                                    </span>
                                                </div>

                                            </dd>



                                        </div>

                                    </div>

                                </div>


                            </dl>

                            <!-- <div class="mt-6 border-t border-gray-900/5 px-6 py-6">
                                <a href="#" class="text-sm font-semibold leading-6 text-gray-900">Download receipt <span
                                        aria-hidden="true">&rarr;</span></a>
                            </div> -->
                        </div>
                    </div>

                </div>
            </div>
        </main>

    </MainLayout>

    <TransitionRoot as="template" :show="open" enter="transform transition ease-in-out duration-500 sm:duration-700"
        enter-from="translate-y-full" enter-to="translate-y-0"
        leave="transform transition ease-in-out duration-500 sm:duration-700" leave-from="translate-y-0"
        leave-to="translate-y-full" class="hidden  lg:block">

        <div class="pointer-events-none fixed inset-x-0 bottom-0 px-6 pb-6" @close="open = false">
            <div class="pointer-events-auto mx-auto max-w-6xl rounded-xl bg-white p-6 shadow-lg ring-1 ring-gray-900/10">

                <div class="relative">
                    <div class="absolute right-0 top-0">
                        <button type="button" class="-m-1.5 flex-none p-1.5" @click="open = false">
                            <span class="sr-only">Dismiss</span>
                            <XMarkIcon class="h-5 w-5 text-gray-900" aria-hidden="true" />
                        </button>
                    </div>

                    <div>

                        <h3 class="text-base font-semibold leading-6 text-gray-900">Global Info <span
                                class="font-normal italic text-gray-600 text-sm">(Superadmin only)</span></h3>
                        <dl
                            class="mt-3 grid grid-cols-4 divide-x divide-gray-200 overflow-hidden rounded-lg bg-white shadow">

                            <div class=" px-4  shadow py-5">
                                <dt class="truncate text-sm font-medium text-gray-500">Total Domains</dt>
                                <dd class="mt-2 text-3xl font-semibold tracking-tight text-indigo-600">
                                    {{ data.domain_count }}
                                </dd>
                            </div>

                            <div class="px-4 py-5">
                                <dt class="truncate text-sm font-medium text-gray-500">Total extensions</dt>
                                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                                    <div class="flex items-baseline font-semibold text-gray-500">
                                        {{ counts.global_reg_count }}
                                        <span class="ml-2 text-sm font-medium text-gray-500">
                                            online
                                        </span>
                                    </div>

                                    <div
                                        class="bg-sky-100 text-sky-800', 'inline-flex items-baseline rounded-full px-2.5 py-0.5 text-sm font-medium md:mt-2 lg:mt-0'">
                                        Total {{ data.extension_count }}
                                    </div>

                                </dd>
                                <div class="mt-1 w-full bg-gray-200 rounded-full h-2.5 ">
                                    <div class="bg-green-500 h-2.5 rounded-full"
                                        :style="{ width: (counts.global_reg_count / data.extension_count * 100) + '%' }">
                                    </div>
                                </div>
                            </div>


                            <div class="px-4 ">
                                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                                    <div class="text-sm font-medium text-gray-500">
                                        Disk: <span> {{ Math.round(data.diskused * 10) / 10 }}/{{
                                            Math.round(data.disktotal * 10) / 10 }} GB</span>

                                    </div>

                                    <div :class="[
                                        data.diskusagecolor === 'bg-success' ? 'bg-green-100 text-green-600' :
                                            data.diskusagecolor === 'bg-warning' ? 'bg-yellow-100 text-yellow-600' :
                                                data.diskusagecolor === 'bg-danger' ? 'bg-rose-100 text-rose-600' :
                                                    'bg-sky-100 text-sky-800',
                                        'inline-flex items-baseline rounded-full px-2.5 py-0.5 text-sm font-medium md:mt-2 lg:mt-0'
                                    ]">
                                        {{ Math.round(data.diskusage) }}%
                                    </div>

                                </dd>
                                <div class="mt-1 w-full bg-gray-200 rounded-full h-2.5 ">
                                    <div :class="data.diskusagecolor === 'bg-success' ? 'bg-green-500' : data.diskusagecolor === 'bg-warning' ? 'bg-yellow-500' : data.diskusagecolor === 'bg-danger' ? 'bg-rose-500' : ''"
                                        class="h-2.5 rounded-full" :style="{ width: data.diskusage + '%' }"></div>
                                </div>

                                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                                    <div class="text-sm font-medium text-gray-500">
                                        Memory: <span> {{ Math.round(data.ramused * 10) / 10 }}/{{ Math.round(data.ramtotal
                                            *
                                            10) / 10 }} GB</span>

                                    </div>

                                    <div :class="[
                                        data.ramusagecolor === 'bg-success' ? 'bg-green-100 text-green-600' :
                                            data.ramusagecolor === 'bg-warning' ? 'bg-yellow-100 text-yellow-600' :
                                                data.ramusagecolor === 'bg-danger' ? 'bg-rose-100 text-rose-600' :
                                                    'bg-sky-100 text-sky-800',
                                        'inline-flex items-baseline rounded-full px-2.5 py-0.5 text-sm font-medium md:mt-2 lg:mt-0'
                                    ]">
                                        {{ Math.round(data.ramusage) }}%
                                    </div>

                                </dd>
                                <div class="mt-1 w-full bg-gray-200 rounded-full h-2.5 ">
                                    <div :class="data.ramusagecolor === 'bg-success' ? 'bg-green-500' : data.ramusagecolor === 'bg-warning' ? 'bg-yellow-500' : data.ramusagecolor === 'bg-danger' ? 'bg-rose-500' : ''"
                                        class="h-2.5 rounded-full" :style="{ width: data.ramusage + '%' }"></div>
                                </div>


                            </div>

                            <div class="px-4 pb-2">
                                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                                    <div class="text-sm font-medium text-gray-500">
                                        Hostname
                                    </div>

                                    <div class="truncate inline-flex items-baseline py-0.5 text-sm font-medium '">
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-200">{{ data.hostname }}</span>
                                    </div>
                                </dd>

                                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                                    <div class="text-sm font-medium text-gray-500">
                                        Uptime
                                    </div>

                                    <div class="truncate pl-12 py-0.5 text-sm font-medium ">
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-600">{{ data.uptime
                                        }}</span>
                                    </div>

                                </dd>

                                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                                    <div class="text-sm font-medium text-gray-500">
                                        CPU cores
                                    </div>

                                    <div class="truncate pl-12 py-0.5 text-sm font-medium ">
                                        <span class="px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-600">{{
                                            data.core_count
                                        }}</span>
                                    </div>

                                </dd>


                                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                                    <div class="text-sm font-medium text-gray-500">
                                        Horizon Status
                                    </div>

                                    <div class="truncate pl-12 py-0.5 text-sm font-medium ">
                                        <span :class="[
                                                    data.horizonStatus === 'running' ? 'bg-green-100 text-green-600' :
                                                        data.horizonStatus === 'paused' ? 'bg-yellow-100 text-yellow-600' : '',
                                                    'px-2.5 py-0.5 rounded-full'
                                                ]">
                                            {{ data.horizonStatus }}
                                        </span>
                                    </div>

                                </dd>



                            </div>

                        </dl>
                    </div>


                </div>
            </div>
        </div>

    </TransitionRoot>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from "@inertiajs/vue3";
import MainLayout from '../Layouts/MainLayout.vue'
import DashboardTile from './components/general/DashboardTile.vue'
import ContactPhoneIcon from "./components/icons/ContactPhoneIcon.vue"
import DialpadIcon from "./components/icons/DialpadIcon.vue"
import FaxIcon from "./components/icons/FaxIcon.vue"
import {
    ClockIcon,
} from '@heroicons/vue/20/solid'

import { TransitionRoot } from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    data: {
        type: Object,
        default: () => ({}) // Providing an empty object as default
    },
    company_data: Object,
    cards: Array,
    counts: {
        type: Object,
        default: () => ({}) // Providing an empty object as default
    },

})

const open = ref(false);

const globalInfoShow = ref(props.data.superadmin);


onMounted(() => {
    //request list of entities
    getCounts();
})

const getCounts = () => {
    router.visit("/dashboard", {
        preserveScroll: true,
        preserveState: true,
        data: {
        },
        only: ["counts"],
        onSuccess: (page) => {
            getData();
        }

    });
}


const getData = () => {
    router.visit("/dashboard", {
        preserveScroll: true,
        preserveState: true,
        data: {
        },
        only: ["data"],
        onSuccess: (page) => {
            if (props.data.superadmin) {
                open.value = true;
            }
        }

    });

}


</script>