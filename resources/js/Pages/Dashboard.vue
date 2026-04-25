<template>
    <MainLayout>
        <TopBanner :show="showTopBanner" @close="showTopBanner = false" color="bg-rose-600" :text="topBannerText" />

        <main class="bg-slate-50/60">
            <div class="mx-auto max-w-8xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-cyan-700">Account dashboard</p>
                        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">{{
                            company_data.company_name }}</h1>
                        <p class="mt-2 text-sm text-gray-500">Time Zone: {{ company_data.time_zone }}</p>
                    </div>

                    <a v-if="page.props.auth.can.account_settings_index" type="button" :href="routes.account_settings_page"
                        class="inline-flex w-fit items-center justify-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 hover:ring-gray-400">
                        <CogIcon class="-ml-0.5 size-5 text-gray-400" aria-hidden="true" />
                        Settings
                    </a>
                </div>

                <div
                    class="mx-auto grid max-w-2xl grid-cols-1 grid-rows-1 items-start gap-6 lg:mx-0 lg:max-w-none lg:grid-cols-3 lg:pb-36">
                    <!-- Account summary -->
                    <div class="lg:col-start-3 lg:row-end-1">
                        <div class="rounded-lg bg-white p-6 ring-1 ring-gray-200">

                            <dl class="flex flex-wrap">
                                <div class="flex-auto truncate border-b border-gray-100 pb-5">
                                    <dt class="text-sm font-medium leading-6 text-gray-500">Account name</dt>
                                    <div class="mt-1 text-lg font-semibold leading-6 text-gray-950">{{
                                        company_data.company_name }}</div>
                                </div>

                                <div v-if="Object.keys(counts).length === 0" class="w-full">
                                    <div class="animate-pulse pt-6">
                                        <div class="space-y-5">
                                            <div class="h-3 w-10/12 rounded bg-slate-200"></div>
                                            <div class="grid grid-cols-3 gap-4">
                                                <div class="col-span-2 h-3 rounded bg-slate-200"></div>
                                                <div class="col-span-1 h-3 rounded bg-slate-100"></div>
                                            </div>
                                            <div class="grid grid-cols-3 gap-4 w-9/12">
                                                <div class="col-span-1 h-3 rounded bg-slate-100"></div>
                                                <div class="col-span-2 h-3 rounded bg-slate-200"></div>
                                            </div>

                                        </div>
                                    </div>
                                </div>


                                <div v-if="Object.keys(counts).length !== 0 && counts.extensions !== undefined && counts.extensions >= 0"
                                    class="mt-6 flex w-full flex-none gap-x-4">
                                    <dt class="flex-none">
                                        <ContactPhoneIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="min-w-0 flex-1 text-sm leading-6 text-gray-500">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="font-medium text-gray-700">Extensions</span>
                                            <span class="font-semibold text-gray-950">{{ counts.extensions }}</span>
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span
                                                class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                                Online: {{ onlineExtensions }}
                                            </span>
                                            <span
                                                class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                                                Offline: {{ offlineExtensions }}
                                            </span>
                                        </div>
                                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-full rounded-full bg-emerald-500"
                                                :style="{ width: registrationPercent + '%' }"></div>
                                        </div>
                                    </dd>
                                </div>

                                <div v-if="Object.keys(counts).length != 0 && counts.phone_numbers !== undefined && counts.phone_numbers >= 0"
                                    class="mt-5 flex w-full flex-none gap-x-4">
                                    <dt class="flex-none">
                                        <DialpadIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm leading-6 text-gray-500">Phone Numbers: <span
                                            class="font-semibold text-gray-900">{{ counts.phone_numbers }}</span>
                                    </dd>
                                </div>

                                <div v-if="Object.keys(counts).length != 0 && counts.faxes !== undefined && counts.faxes >= 0"
                                    class="mt-4 flex w-full flex-none gap-x-4">
                                    <dt class="flex-none">
                                        <FaxIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm leading-6 text-gray-500">Virtual Faxes: <span
                                            class="font-semibold text-gray-900">{{ counts.faxes }}</span></dd>
                                </div>

                                <div class="mt-4 flex w-full flex-none gap-x-4">
                                    <dt class="flex-none">
                                        <ClockIcon class="h-6 w-5 text-gray-400" aria-hidden="true" />
                                    </dt>
                                    <dd class="text-sm font-medium leading-6 text-gray-500">Time Zone: {{
                                        company_data.time_zone }}</dd>
                                </div>
                            </dl>

                        </div>
                    </div>

                    <!-- Quick Access -->
                    <div class="lg:col-span-2 lg:row-span-2 lg:row-end-2">

                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold leading-6 text-gray-950">Quick Access</h2>
                            <span class="text-sm text-gray-500">{{ cards.length }} shortcuts</span>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            <div v-for="card in cards" :key="card.slug" class="h-full">
                                <DashboardTile :card="card" :count="counts[card.slug]" />
                            </div>
                        </div>

                    </div>

                    <!-- Global Info -->
                    <div class="lg:hidden" v-show="props.data.superadmin">
                        <div class="rounded-lg bg-gray-50 shadow-sm ring-1 ring-gray-900/5">

                            <dl class="flex flex-wrap">
                                <div class="flex-auto pl-6 py-6 truncate border-b border-gray-900/5">
                                    <div class="mt-1 text-lg font-semibold leading-6 text-gray-900">Global Info</div>
                                    <dt class="text-sm  leading-6 text-gray-600">(Superadmin Only)</dt>
                                    <dt class="text-sm  leading-6 text-gray-600">Version: {{ props.data.version }}</dt>
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
                                class="font-normal italic text-gray-600 text-sm">(Superadmin only)</span>
                            <span v-if="props.data.version" class="text-sm font-normal  text-gray-600"> Version: {{
                                props.data.version }}</span>
                        </h3>
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
import { computed, ref, onMounted } from 'vue'
import { router } from "@inertiajs/vue3";
import { usePage } from '@inertiajs/vue3'
import MainLayout from '../Layouts/MainLayout.vue'
import DashboardTile from './components/general/DashboardTile.vue'
import ContactPhoneIcon from "./components/icons/ContactPhoneIcon.vue"
import DialpadIcon from "./components/icons/DialpadIcon.vue"
import FaxIcon from "./components/icons/FaxIcon.vue"
import {
    ClockIcon,
} from '@heroicons/vue/20/solid'

import { TransitionRoot } from '@headlessui/vue'
import { XMarkIcon, CogIcon } from '@heroicons/vue/24/outline'
import TopBanner from './components/notifications/TopBanner.vue';


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
    routes: Object,

})

const page = usePage()
const open = ref(false);

const showTopBanner = ref(Boolean(props.company_data.billing_suspension));
const topBannerText = ref('Your account has been suspended. Reactivation requires payment for past-due invoice(s).');

const onlineExtensions = computed(() => Number(props.counts.local_reg_count || 0));
const offlineExtensions = computed(() => Math.max((props.counts.extensions || 0) - onlineExtensions.value, 0));
const registrationPercent = computed(() => {
    const totalExtensions = Number(props.counts.extensions || 0);

    if (!totalExtensions) {
        return 0;
    }

    return Math.min(Math.round((onlineExtensions.value / totalExtensions) * 100), 100);
});

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
            // console.log(props.data);
            // console.log(props.data.billing_suspension);
            // if (props.data.billing_suspension) {
            //     showTopBanner.value = true;
            // }

        }

    });

}


</script>
