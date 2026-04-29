<template>
    <MainLayout />

    <div class="m-3 space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-base font-semibold leading-6 text-gray-900">System Status</h1>
                <p class="mt-1 text-sm text-gray-500">
                    Server, application, database, and resource information.
                    <span v-if="status.generated_at" class="ml-1">
                        Updated {{ formatDate(status.generated_at) }}
                    </span>
                </p>
            </div>

            <button
                type="button"
                class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 sm:mt-0"
                :disabled="loading"
                @click="fetchData"
            >
                <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                Refresh
            </button>
        </div>

        <Loading :show="loading" />

        <div v-if="errorMessage" class="rounded-md bg-red-50 p-4 text-sm text-red-700">
            {{ errorMessage }}
        </div>

        <div v-if="!loading" class="space-y-6">
            <section v-if="status.info" class="grid gap-6 xl:grid-cols-2">
                <InfoPanel title="System Information" :rows="status.info.rows" />
                <InfoPanel title="Operating System" :rows="status.info.os" />
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <OutputPanel v-if="status.memory" title="Memory" :output="status.memory.output" />
                <OutputPanel v-if="status.disk" title="Drive Space" :output="status.disk.output" />
            </section>

            <section v-if="status.cpu">
                <OutputPanel title="CPU" :output="status.cpu.output" />
            </section>

            <section v-if="status.database" class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5">
                <div class="border-b border-gray-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-gray-900">Database</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in status.database.rows" :key="row.label">
                                <td class="w-56 whitespace-nowrap bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700">
                                    {{ row.label }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <span class="break-all">{{ row.value || '-' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="status.database.databases?.length" class="border-t border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        Database
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        Size
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="database in status.database.databases" :key="database.name">
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ database.name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ database.size }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <InfoPanel v-if="status.memcache" title="Memcache" :rows="status.memcache.rows" />

            <div v-if="isEmpty" class="rounded-lg bg-white p-8 text-center shadow ring-1 ring-black ring-opacity-5">
                <ServerStackIcon class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No system data available</h3>
                <p class="mt-1 text-sm text-gray-500">Your account does not have access to any system status sections.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import Loading from "./components/general/Loading.vue";
import { ArrowPathIcon, ServerStackIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const loading = ref(false);
const errorMessage = ref(null);
const status = ref({
    generated_at: null,
    info: null,
    memory: null,
    cpu: null,
    disk: null,
    database: null,
    memcache: null,
});

const isEmpty = computed(() => {
    return !status.value.info
        && !status.value.memory
        && !status.value.cpu
        && !status.value.disk
        && !status.value.database
        && !status.value.memcache;
});

const fetchData = async () => {
    loading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get(props.routes.data_route);
        status.value = response.data;
    } catch (error) {
        errorMessage.value = error?.response?.data?.messages?.error?.[0]
            ?? "Unable to load system status.";
    } finally {
        loading.value = false;
    }
};

const formatDate = (value) => {
    if (!value) {
        return "";
    }

    return new Date(value).toLocaleString();
};

const InfoPanel = defineComponent({
    name: "InfoPanel",
    props: {
        title: String,
        rows: {
            type: Array,
            default: () => [],
        },
    },
    setup(panelProps) {
        return () => h("div", { class: "overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5" }, [
            h("div", { class: "border-b border-gray-200 px-4 py-3" }, [
                h("h2", { class: "text-sm font-semibold text-gray-900" }, panelProps.title),
            ]),
            h("div", { class: "overflow-x-auto" }, [
                h("table", { class: "min-w-full divide-y divide-gray-200" }, [
                    h("tbody", { class: "divide-y divide-gray-100" }, panelProps.rows.map((row) => (
                        h("tr", { key: row.label }, [
                            h("td", { class: "w-56 whitespace-nowrap bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700" }, row.label),
                            h("td", { class: "px-4 py-2 text-sm text-gray-600" }, [
                                h("span", { class: "break-all whitespace-pre-wrap" }, row.value || "-"),
                            ]),
                        ])
                    ))),
                ]),
            ]),
        ]);
    },
});

const OutputPanel = defineComponent({
    name: "OutputPanel",
    props: {
        title: String,
        output: String,
    },
    setup(panelProps) {
        return () => h("div", { class: "overflow-hidden rounded-lg bg-white shadow ring-1 ring-black ring-opacity-5" }, [
            h("div", { class: "border-b border-gray-200 px-4 py-3" }, [
                h("h2", { class: "text-sm font-semibold text-gray-900" }, panelProps.title),
            ]),
            h("pre", { class: "overflow-x-auto whitespace-pre p-4 text-xs leading-5 text-gray-700" }, panelProps.output || "Unavailable"),
        ]);
    },
});

onMounted(fetchData);
</script>
