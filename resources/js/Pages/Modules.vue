<template>
    <MainLayout />

    <div class="m-3 space-y-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-indigo-600">Switch configuration</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900">FreeSWITCH Modules</h1>
                <p class="mt-1 text-sm text-gray-500">Manage module autoload settings and live FreeSWITCH status.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading"
                    @click="refreshData"
                >
                    <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                    Refresh
                </button>
                <a
                    v-if="permissions.create"
                    :href="routes.legacy_add"
                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                >
                    <PlusIcon class="h-4 w-4" />
                    New module
                </a>
            </div>
        </header>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatTile label="Total modules" :value="stats.total" tone="gray" />
            <StatTile label="Autoload enabled" :value="stats.enabled" tone="indigo" />
            <StatTile label="Running" :value="stats.running" tone="green" />
            <StatTile label="Stopped" :value="stats.stopped" tone="rose" />
        </div>

        <div class="flex flex-col gap-4 lg:flex-row">
            <aside class="lg:w-72 lg:shrink-0">
                <div class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200">
                    <div class="relative mb-3">
                        <MagnifyingGlassIcon class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-gray-400" />
                        <input
                            v-model="filterData.search"
                            type="text"
                            placeholder="Search modules..."
                            class="block w-full rounded-md border-0 py-1.5 pl-9 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                        />
                    </div>

                    <div class="mb-3 space-y-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Runtime</label>
                            <select
                                v-model="filterData.runtime"
                                class="mt-1 block w-full rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                            >
                                <option value="all">Any status</option>
                                <option value="running">Running</option>
                                <option value="stopped">Stopped</option>
                                <option value="unknown">Unknown</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500">Autoload</label>
                            <select
                                v-model="filterData.autoload"
                                class="mt-1 block w-full rounded-md border-0 py-1.5 pl-2 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                            >
                                <option value="all">Any status</option>
                                <option value="true">Enabled</option>
                                <option value="false">Disabled</option>
                            </select>
                        </div>
                    </div>

                    <p class="px-1 pb-1 text-xs font-medium uppercase tracking-wider text-gray-400">Categories</p>
                    <nav class="max-h-[60vh] space-y-0.5 overflow-y-auto" aria-label="Module categories">
                        <button type="button" :class="categoryButtonClass('')" @click="selectedCategory = ''">
                            <span class="min-w-0 flex-1 truncate">All</span>
                            <span :class="categoryBadgeClass('')">{{ filteredRows.length }}</span>
                        </button>
                        <button
                            v-for="category in categoriesWithCounts"
                            :key="category.value"
                            type="button"
                            :class="categoryButtonClass(category.value)"
                            @click="selectedCategory = category.value"
                        >
                            <span class="min-w-0 flex-1 truncate">{{ category.label }}</span>
                            <span :class="categoryBadgeClass(category.value)">{{ category.count }}</span>
                        </button>
                        <p v-if="!categoriesWithCounts.length" class="px-3 py-2 text-xs text-gray-400">No matching categories</p>
                    </nav>
                </div>
            </aside>

            <section class="min-w-0 flex-1">
                <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">{{ selectedCategoryLabel }}</h2>
                            <p class="text-xs text-gray-500">{{ displayedRows.length }} module{{ displayedRows.length === 1 ? '' : 's' }} shown</p>
                        </div>
                        <div v-if="hasSelectableActions" class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="displayedRows.length"
                                type="button"
                                class="text-xs text-gray-500 hover:text-gray-900"
                                @click="toggleSelectAllVisible"
                            >
                                {{ allVisibleSelected ? 'Clear selection' : 'Select visible' }}
                            </button>
                            <div v-if="selectedItems.length" class="flex flex-wrap items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700">
                                <span>{{ selectedItems.length }} selected</span>
                                <button v-if="permissions.update" type="button" class="rounded px-1.5 py-0.5 hover:bg-indigo-100" @click="handleBulkActionRequest('start')">Start</button>
                                <button v-if="permissions.update" type="button" class="rounded px-1.5 py-0.5 hover:bg-indigo-100" @click="handleBulkActionRequest('stop')">Stop</button>
                                <button v-if="permissions.update" type="button" class="rounded px-1.5 py-0.5 hover:bg-indigo-100" @click="handleBulkActionRequest('toggle')">Toggle autoload</button>
                                <button v-if="permissions.destroy" type="button" class="rounded px-1.5 py-0.5 text-rose-700 hover:bg-rose-100" @click="handleBulkActionRequest('delete')">Delete</button>
                            </div>
                        </div>
                    </header>

                    <div v-if="loading" class="px-4 py-12">
                        <Loading :show="true" :absolute="false" />
                    </div>

                    <ul v-else-if="displayedRows.length" class="divide-y divide-gray-100">
                        <li
                            v-for="row in displayedRows"
                            :key="row.module_uuid"
                            class="flex flex-col gap-3 px-4 py-3 transition hover:bg-gray-50 sm:flex-row sm:items-start"
                        >
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <input
                                    v-if="hasSelectableActions"
                                    v-model="selectedItems"
                                    type="checkbox"
                                    :value="row.module_uuid"
                                    :aria-label="`Select ${row.module_label}`"
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                />

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <h3 class="text-sm font-semibold text-gray-900">{{ row.module_label }}</h3>
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-indigo-700 ring-1 ring-inset ring-indigo-600/20 hover:bg-indigo-100"
                                            :title="`Filter by ${row.module_category}`"
                                            @click="selectedCategory = row.module_category"
                                        >
                                            {{ row.module_category }}
                                        </button>
                                        <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600">{{ row.module_name }}</code>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ row.module_description || 'No description' }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                                        <span>Order {{ row.module_order ?? 0 }}</span>
                                        <span>Default autoload {{ row.module_default_enabled === 'true' ? 'enabled' : 'disabled' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2 sm:max-w-[26rem] sm:justify-end">
                                <span :class="runtimeStatusClass(row.status)">
                                    <span :class="['mr-1 inline-block h-1.5 w-1.5 rounded-full', runtimeDotClass(row.status)]" />
                                    {{ runtimeLabel(row.status) }}
                                </span>
                                <button
                                    v-if="permissions.update && row.status === 'stopped'"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!row.can_control_runtime"
                                    :title="row.can_control_runtime ? 'Start module' : 'Enable autoload before starting this module'"
                                    @click="confirmAction('start', [row.module_uuid])"
                                >
                                    <PlayIcon class="h-3.5 w-3.5" />
                                    Start
                                </button>
                                <button
                                    v-if="permissions.update && row.status === 'running'"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!row.can_control_runtime"
                                    title="Stop module"
                                    @click="confirmAction('stop', [row.module_uuid])"
                                >
                                    <StopIcon class="h-3.5 w-3.5" />
                                    Stop
                                </button>
                                <button
                                    v-if="permissions.update"
                                    type="button"
                                    :class="autoloadStatusClass(row.module_enabled)"
                                    :title="row.module_enabled === 'true' ? 'Disable module autoload' : 'Enable module autoload'"
                                    @click="confirmAction('toggle', [row.module_uuid])"
                                >
                                    <span :class="['mr-1 inline-block h-1.5 w-1.5 rounded-full', row.module_enabled === 'true' ? 'bg-indigo-500' : 'bg-gray-400']" />
                                    Autoload {{ row.module_enabled === 'true' ? 'enabled' : 'disabled' }}
                                </button>
                                <span v-else :class="autoloadStatusClass(row.module_enabled)">
                                    <span :class="['mr-1 inline-block h-1.5 w-1.5 rounded-full', row.module_enabled === 'true' ? 'bg-indigo-500' : 'bg-gray-400']" />
                                    Autoload {{ row.module_enabled === 'true' ? 'enabled' : 'disabled' }}
                                </span>
                                <a
                                    v-if="permissions.update"
                                    :href="row.edit_url"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50"
                                >
                                    Edit
                                </a>
                                <button
                                    v-if="permissions.destroy"
                                    type="button"
                                    class="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50"
                                    @click="confirmAction('delete', [row.module_uuid])"
                                >
                                    Delete
                                </button>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="px-4 py-12 text-center">
                        <p class="text-sm font-medium text-gray-900">No modules match your filters</p>
                        <p class="mt-1 text-xs text-gray-500">Try clearing search, runtime, or autoload filters.</p>
                        <button
                            type="button"
                            class="mt-3 rounded-md bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                            @click="resetFilters"
                        >
                            Reset filters
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <ConfirmationModal
        :show="confirmation.show"
        :header="confirmation.header"
        :text="confirmation.text"
        :confirm-button-label="confirmation.button"
        cancel-button-label="Cancel"
        :loading="confirmation.loading"
        :color="confirmation.color"
        @close="closeConfirmation"
        @confirm="executeConfirmedAction"
    />

    <Notification :show="notificationShow" :type="notificationType" :messages="notificationMessages" @update:show="notificationShow = $event" />
</template>

<script setup>
import { computed, h, onMounted, ref, watch } from "vue";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import Loading from "./components/general/Loading.vue";
import ConfirmationModal from "./components/modal/ConfirmationModal.vue";
import Notification from "./components/notifications/Notification.vue";
import { ArrowPathIcon, MagnifyingGlassIcon, PlusIcon } from "@heroicons/vue/24/outline";
import { PlayIcon, StopIcon } from "@heroicons/vue/20/solid";

const StatTile = (props) => {
    const toneMap = {
        gray: "text-gray-900",
        indigo: "text-indigo-600",
        green: "text-green-600",
        rose: "text-rose-600",
    };

    return h("div", { class: "rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200" }, [
        h("p", { class: "text-xs text-gray-500" }, props.label),
        h("p", { class: ["mt-0.5 text-xl font-semibold", toneMap[props.tone] || toneMap.gray] }, String(props.value ?? 0)),
    ]);
};
StatTile.props = ["label", "value", "tone"];

const props = defineProps({
    routes: Object,
    permissions: Object,
});

const allRows = ref([]);
const loading = ref(false);
const selectedItems = ref([]);
const selectedCategory = ref("");
const filterData = ref({ search: "", runtime: "all", autoload: "all" });
const notificationShow = ref(false);
const notificationType = ref("success");
const notificationMessages = ref(null);
const confirmation = ref({
    show: false,
    action: null,
    items: [],
    header: "",
    text: "",
    button: "Continue",
    color: "indigo",
    loading: false,
});

const routes = computed(() => props.routes || {});
const permissions = computed(() => props.permissions || {});
const hasSelectableActions = computed(() => permissions.value.update || permissions.value.destroy);

const stats = computed(() => ({
    total: allRows.value.length,
    enabled: allRows.value.filter((row) => row.module_enabled === "true").length,
    running: allRows.value.filter((row) => row.status === "running").length,
    stopped: allRows.value.filter((row) => row.status === "stopped").length,
}));

const filteredRows = computed(() => {
    const search = filterData.value.search.trim().toLowerCase();

    return allRows.value.filter((row) => {
        if (filterData.value.runtime !== "all" && row.status !== filterData.value.runtime) {
            return false;
        }

        if (filterData.value.autoload !== "all" && row.module_enabled !== filterData.value.autoload) {
            return false;
        }

        if (search) {
            const haystack = [row.module_label, row.module_name, row.module_category, row.module_description]
                .filter((value) => value !== null && value !== undefined)
                .join(" ")
                .toLowerCase();

            if (!haystack.includes(search)) {
                return false;
            }
        }

        return true;
    });
});

const categoriesWithCounts = computed(() => {
    const counts = new Map();

    for (const row of filteredRows.value) {
        const category = row.module_category || "Uncategorized";
        counts.set(category, (counts.get(category) || 0) + 1);
    }

    return Array.from(counts.entries())
        .map(([value, count]) => ({ value, label: value, count }))
        .sort((a, b) => a.label.localeCompare(b.label));
});

const displayedRows = computed(() => {
    const rows = selectedCategory.value
        ? filteredRows.value.filter((row) => row.module_category === selectedCategory.value)
        : filteredRows.value;

    return [...rows].sort((a, b) => {
        const categoryComparison = String(a.module_category || "").localeCompare(String(b.module_category || ""));
        if (categoryComparison !== 0) {
            return categoryComparison;
        }

        const orderComparison = Number(a.module_order ?? 0) - Number(b.module_order ?? 0);
        if (orderComparison !== 0) {
            return orderComparison;
        }

        return String(a.module_label || "").localeCompare(String(b.module_label || ""));
    });
});

const selectedCategoryLabel = computed(() => selectedCategory.value || "All modules");

const allVisibleSelected = computed(() => {
    if (!displayedRows.value.length) {
        return false;
    }

    return displayedRows.value.every((row) => selectedItems.value.includes(row.module_uuid));
});

watch(
    [selectedCategory, () => filterData.value.search, () => filterData.value.runtime, () => filterData.value.autoload],
    () => {
        selectedItems.value = [];
    }
);

watch(categoriesWithCounts, (categories) => {
    if (selectedCategory.value && !categories.some((category) => category.value === selectedCategory.value)) {
        selectedCategory.value = "";
    }
});

onMounted(() => fetchData());

function fetchData(force = false) {
    loading.value = true;

    const params = { page: 1, per_page: 5000 };
    if (force) {
        params._ = Date.now();
    }

    axios
        .get(routes.value.data_route, { params })
        .then((response) => {
            allRows.value = response.data?.data || [];
            selectedItems.value = [];
        })
        .catch(handleError)
        .finally(() => {
            loading.value = false;
        });
}

function refreshData() {
    fetchData(true);
}

function resetFilters() {
    filterData.value = { search: "", runtime: "all", autoload: "all" };
    selectedCategory.value = "";
}

function toggleSelectAllVisible() {
    const visibleItems = displayedRows.value.map((row) => row.module_uuid);

    if (allVisibleSelected.value) {
        selectedItems.value = selectedItems.value.filter((uuid) => !visibleItems.includes(uuid));
        return;
    }

    selectedItems.value = Array.from(new Set([...selectedItems.value, ...visibleItems]));
}

function handleBulkActionRequest(action) {
    confirmAction(action, selectedItems.value);
}

function confirmAction(action, items) {
    if (!items.length) {
        showNotification("error", { request: ["No modules selected."] });
        return;
    }

    const count = items.length;
    const copy = {
        start: {
            header: "Start modules?",
            text: `Start ${count} selected module${count === 1 ? "" : "s"} in FreeSWITCH.`,
            button: "Start",
            color: "green",
        },
        stop: {
            header: "Stop modules?",
            text: `Stop ${count} selected module${count === 1 ? "" : "s"} in FreeSWITCH.`,
            button: "Stop",
            color: "red",
        },
        toggle: {
            header: "Toggle autoload?",
            text: `Toggle autoload for ${count} selected module${count === 1 ? "" : "s"}.`,
            button: "Toggle",
            color: "indigo",
        },
        delete: {
            header: "Delete modules?",
            text: `Delete ${count} selected module${count === 1 ? "" : "s"}.`,
            button: "Delete",
            color: "red",
        },
    }[action];

    confirmation.value = {
        show: true,
        action,
        items: [...items],
        loading: false,
        ...copy,
    };
}

function executeConfirmedAction() {
    const actionRoutes = {
        start: routes.value.bulk_start,
        stop: routes.value.bulk_stop,
        toggle: routes.value.bulk_toggle,
        delete: routes.value.bulk_delete,
    };

    confirmation.value.loading = true;

    axios
        .post(actionRoutes[confirmation.value.action], { items: confirmation.value.items })
        .then((response) => {
            showNotification("success", response.data.messages);
            closeConfirmation();
            fetchData(true);
        })
        .catch((error) => {
            handleError(error);
            closeConfirmation();
            fetchData(true);
        })
        .finally(() => {
            confirmation.value.loading = false;
        });
}

function closeConfirmation() {
    confirmation.value.show = false;
}

function categoryButtonClass(category) {
    const active = selectedCategory.value === category;

    return [
        "flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm transition",
        active ? "bg-indigo-50 font-medium text-indigo-700" : "text-gray-600 hover:bg-gray-50 hover:text-gray-900",
    ];
}

function categoryBadgeClass(category) {
    return [
        "ml-auto rounded-full px-2 py-0.5 text-[11px] font-medium",
        selectedCategory.value === category ? "bg-indigo-100 text-indigo-700" : "bg-gray-100 text-gray-500",
    ];
}

function runtimeLabel(status) {
    return {
        running: "Running",
        stopped: "Stopped",
        unknown: "Unknown",
    }[status] || "Unknown";
}

function runtimeStatusClass(status) {
    const tone = {
        running: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
        stopped: "bg-rose-50 text-rose-700 ring-rose-600/20",
        unknown: "bg-gray-50 text-gray-600 ring-gray-500/20",
    }[status] || "bg-gray-50 text-gray-600 ring-gray-500/20";

    return ["inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset", tone];
}

function runtimeDotClass(status) {
    return {
        running: "bg-emerald-500",
        stopped: "bg-rose-500",
        unknown: "bg-gray-400",
    }[status] || "bg-gray-400";
}

function autoloadStatusClass(enabled) {
    const tone = enabled === "true"
        ? "bg-indigo-50 text-indigo-700 ring-indigo-600/20 hover:bg-indigo-100"
        : "bg-gray-50 text-gray-600 ring-gray-500/20 hover:bg-gray-100";

    return ["inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset", tone];
}

function showNotification(type, messages = null) {
    notificationType.value = type;
    notificationMessages.value = messages;
    notificationShow.value = true;
}

function handleError(error) {
    if (error?.response?.status === 419) {
        showNotification("error", { request: ["Session expired. Reload the page."] });
        return;
    }

    if (error?.response?.data) {
        showNotification("error", error.response.data.messages || error.response.data.errors || { request: [error.message] });
        return;
    }

    showNotification("error", { request: [error?.message || "Request failed."] });
}
</script>
