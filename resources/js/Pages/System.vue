<template>
    <MainLayout />

    <div class="m-3 space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-base font-semibold leading-6 text-heading">System Status</h1>
                <p class="mt-1 text-sm text-muted">
                    Server, application, database, and resource information.
                    <span v-if="status.generated_at" class="ml-1">
                        Updated {{ formatDate(status.generated_at) }}
                    </span>
                </p>
            </div>

            <button
                type="button"
                class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-accent px-2.5 py-1.5 text-sm font-semibold text-on-accent shadow-sm hover:bg-accent-hover disabled:opacity-50 sm:mt-0"
                :disabled="loading"
                @click="fetchData"
            >
                <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                Refresh
            </button>
        </div>

        <Loading :show="loading" />

        <div v-if="errorMessage" class="rounded-md bg-danger-subtle p-4 text-sm text-danger">
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

            <section v-if="status.database" class="overflow-hidden rounded-lg bg-surface shadow ring-1 ring-black ring-opacity-5">
                <div class="border-b border-default px-4 py-3">
                    <h2 class="text-sm font-semibold text-heading">Database</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-default">
                        <tbody class="divide-y divide-default">
                            <tr v-for="row in status.database.rows" :key="row.label">
                                <td class="w-56 whitespace-nowrap bg-surface-2 px-4 py-2 text-sm font-medium text-body">
                                    {{ row.label }}
                                </td>
                                <td class="px-4 py-2 text-sm text-body">
                                    <span class="break-all">{{ row.value || '-' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="status.database.databases?.length" class="border-t border-default">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-default">
                            <thead class="bg-surface-2">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                                        Database
                                    </th>
                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-muted">
                                        Size
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-default bg-surface">
                                <tr v-for="database in status.database.databases" :key="database.name">
                                    <td class="px-4 py-2 text-sm text-body">{{ database.name }}</td>
                                    <td class="px-4 py-2 text-sm text-body">{{ database.size }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <InfoPanel v-if="status.memcache" title="Memcache" :rows="status.memcache.rows" />

            <div v-if="isEmpty" class="rounded-lg bg-surface p-8 text-center shadow ring-1 ring-black ring-opacity-5">
                <ServerStackIcon class="mx-auto h-12 w-12 text-subtle" />
                <h3 class="mt-2 text-sm font-semibold text-heading">No system data available</h3>
                <p class="mt-1 text-sm text-muted">Your account does not have access to any system status sections.</p>
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
        return () => h("div", { class: "overflow-hidden rounded-lg bg-surface shadow ring-1 ring-black ring-opacity-5" }, [
            h("div", { class: "border-b border-default px-4 py-3" }, [
                h("h2", { class: "text-sm font-semibold text-heading" }, panelProps.title),
            ]),
            h("div", { class: "overflow-x-auto" }, [
                h("table", { class: "min-w-full divide-y divide-default" }, [
                    h("tbody", { class: "divide-y divide-default" }, panelProps.rows.map((row) => (
                        h("tr", { key: row.label }, [
                            h("td", { class: "w-56 whitespace-nowrap bg-surface-2 px-4 py-2 text-sm font-medium text-body" }, row.label),
                            h("td", { class: "px-4 py-2 text-sm text-body" }, [
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
        return () => h("div", { class: "overflow-hidden rounded-lg bg-surface shadow ring-1 ring-black ring-opacity-5" }, [
            h("div", { class: "border-b border-default px-4 py-3" }, [
                h("h2", { class: "text-sm font-semibold text-heading" }, panelProps.title),
            ]),
            h("pre", { class: "overflow-x-auto whitespace-pre p-4 text-xs leading-5 text-body" }, panelProps.output || "Unavailable"),
        ]);
    },
});

onMounted(fetchData);
</script>
