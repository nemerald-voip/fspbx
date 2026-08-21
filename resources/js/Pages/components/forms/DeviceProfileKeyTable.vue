<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-if="vendorsInUse.length > 1"
                    v-model="vendorFilter"
                    class="rounded-md border-0 bg-white py-1.5 pl-3 pr-8 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600"
                >
                    <option value="">{{ $t("All vendors") }}</option>
                    <option v-for="vendor in vendorsInUse" :key="vendor.value" :value="vendor.value">
                        {{ vendor.label }} ({{ vendor.count }})
                    </option>
                </select>
                <p class="text-sm text-gray-500">{{ summary }}</p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    v-if="hasExtraColumns"
                    type="button"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    @click="showExtraColumns = !showExtraColumns"
                >
                    <AdjustmentsHorizontalIcon class="h-4 w-4 text-gray-500" aria-hidden="true" />
                    {{ showExtraColumns ? $t("Fewer columns") : $t("More columns") }}
                </button>

                <button
                    v-if="permissions.create"
                    type="button"
                    class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    @click="addRow"
                >
                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                    {{ $t("Add key") }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg ring-1 ring-gray-200">
            <div class="max-h-[26rem] overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 shadow-[inset_0_-1px_0_rgb(229,231,235)]">
                            <th scope="col" class="w-28 min-w-28 px-2 py-2">{{ $t("Vendor") }}</th>
                            <th scope="col" class="w-28 min-w-28 px-2 py-2">{{ $t("Area") }}</th>
                            <th scope="col" class="w-16 min-w-16 px-2 py-2">{{ $t("Key") }}</th>
                            <th scope="col" class="w-36 min-w-36 px-2 py-2">{{ $t("Function") }}</th>
                            <th scope="col" class="w-20 min-w-20 px-2 py-2">{{ $t("Line") }}</th>
                            <th scope="col" class="w-28 min-w-28 px-2 py-2">{{ $t("Value") }}</th>
                            <th scope="col" class="w-28 min-w-28 px-2 py-2">{{ $t("Label") }}</th>
                            <template v-if="showExtraColumns">
                                <th v-if="fieldPermissions.extension" scope="col" class="w-32 min-w-32 px-2 py-2">
                                    {{ $t("Extension") }}
                                </th>
                                <th v-if="fieldPermissions.icon" scope="col" class="w-32 min-w-32 px-2 py-2">
                                    {{ $t("Icon") }}
                                </th>
                            </template>
                            <th scope="col" class="w-9 min-w-9 px-2 py-2">
                                <span class="sr-only">{{ $t("Delete") }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white align-top">
                        <tr
                            v-for="entry in visibleRows"
                            :key="entry.row._row_id"
                            :class="hasRowError(entry.index) ? 'bg-red-50/50' : ''"
                        >
                            <td class="px-2 py-1.5">
                                <select
                                    v-model="entry.row.profile_key_vendor"
                                    :class="controlClass(fieldError(entry.index, 'profile_key_vendor'), true)"
                                    :disabled="!canEditRow(entry.index)"
                                    @change="handleVendorChange(entry.row)"
                                >
                                    <option :value="null">{{ $t("Select") }}</option>
                                    <option v-for="vendor in vendors" :key="vendor.value" :value="vendor.value">
                                        {{ vendor.label }}
                                    </option>
                                </select>
                                <p v-if="fieldError(entry.index, 'profile_key_vendor')" :class="errorClass">
                                    {{ fieldError(entry.index, "profile_key_vendor") }}
                                </p>
                            </td>

                            <td class="px-2 py-1.5">
                                <select
                                    v-model="entry.row.profile_key_category"
                                    :class="controlClass(fieldError(entry.index, 'profile_key_category'), true)"
                                    :disabled="!canEditRow(entry.index)"
                                >
                                    <option
                                        v-for="area in areaOptions(entry.row.profile_key_vendor, entry.row.profile_key_category)"
                                        :key="area.value"
                                        :value="area.value"
                                    >
                                        {{ area.label }}
                                    </option>
                                </select>
                                <p v-if="fieldError(entry.index, 'profile_key_category')" :class="errorClass">
                                    {{ fieldError(entry.index, "profile_key_category") }}
                                </p>
                            </td>

                            <td class="px-2 py-1.5">
                                <input
                                    v-model.number="entry.row.profile_key_id"
                                    type="number"
                                    min="1"
                                    max="255"
                                    :class="controlClass(fieldError(entry.index, 'profile_key_id'))"
                                    :disabled="!canEditRow(entry.index)"
                                >
                                <p v-if="fieldError(entry.index, 'profile_key_id')" :class="errorClass">
                                    {{ fieldError(entry.index, "profile_key_id") }}
                                </p>
                            </td>

                            <td class="px-2 py-1.5">
                                <select
                                    v-model="entry.row.profile_key_type"
                                    :class="controlClass(fieldError(entry.index, 'profile_key_type'), true)"
                                    :disabled="!canEditRow(entry.index) || !entry.row.profile_key_vendor"
                                    @change="handleFunctionChange(entry.row)"
                                >
                                    <option :value="null">
                                        {{ entry.row.profile_key_vendor ? $t("Select") : $t("Choose vendor first") }}
                                    </option>
                                    <option
                                        v-for="option in functionOptions(entry.row.profile_key_vendor, entry.row.profile_key_type)"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <p v-if="fieldError(entry.index, 'profile_key_type')" :class="errorClass">
                                    {{ fieldError(entry.index, "profile_key_type") }}
                                </p>
                            </td>

                            <td class="px-2 py-1.5">
                                <select
                                    v-model="entry.row.profile_key_line"
                                    :class="controlClass(fieldError(entry.index, 'profile_key_line'), true)"
                                    :disabled="!canEditRow(entry.index)"
                                >
                                    <option :value="null">—</option>
                                    <option v-for="line in lineOptions" :key="line" :value="line">{{ line }}</option>
                                </select>
                                <p v-if="fieldError(entry.index, 'profile_key_line')" :class="errorClass">
                                    {{ fieldError(entry.index, "profile_key_line") }}
                                </p>
                            </td>

                            <td class="px-2 py-1.5">
                                <input
                                    v-model="entry.row.profile_key_value"
                                    type="text"
                                    :placeholder="$t('Ext / number')"
                                    :class="controlClass(fieldError(entry.index, 'profile_key_value'))"
                                    :disabled="!canEditRow(entry.index)"
                                >
                                <p v-if="fieldError(entry.index, 'profile_key_value')" :class="errorClass">
                                    {{ fieldError(entry.index, "profile_key_value") }}
                                </p>
                            </td>

                            <td class="px-2 py-1.5">
                                <input
                                    v-model="entry.row.profile_key_label"
                                    type="text"
                                    :placeholder="labelPlaceholder(entry.row)"
                                    :class="controlClass(fieldError(entry.index, 'profile_key_label'))"
                                    :disabled="!canEditRow(entry.index)"
                                    data-1p-ignore
                                    data-lpignore="true"
                                    autocomplete="off"
                                >
                                <p v-if="fieldError(entry.index, 'profile_key_label')" :class="errorClass">
                                    {{ fieldError(entry.index, "profile_key_label") }}
                                </p>
                            </td>

                            <template v-if="showExtraColumns">
                                <td v-if="fieldPermissions.extension" class="px-2 py-1.5">
                                    <input
                                        v-model="entry.row.profile_key_extension"
                                        type="text"
                                        :class="controlClass(fieldError(entry.index, 'profile_key_extension'))"
                                        :disabled="!canEditRow(entry.index)"
                                    >
                                    <p v-if="fieldError(entry.index, 'profile_key_extension')" :class="errorClass">
                                        {{ fieldError(entry.index, "profile_key_extension") }}
                                    </p>
                                </td>

                                <td v-if="fieldPermissions.icon" class="px-2 py-1.5">
                                    <input
                                        v-model="entry.row.profile_key_icon"
                                        type="text"
                                        :class="controlClass(fieldError(entry.index, 'profile_key_icon'))"
                                        :disabled="!canEditRow(entry.index)"
                                    >
                                    <p v-if="fieldError(entry.index, 'profile_key_icon')" :class="errorClass">
                                        {{ fieldError(entry.index, "profile_key_icon") }}
                                    </p>
                                </td>
                            </template>

                            <td class="px-2 py-1.5 text-right">
                                <button
                                    v-if="canDeleteRow(entry.index)"
                                    type="button"
                                    :title="$t('Delete key')"
                                    :aria-label="$t('Delete key')"
                                    class="rounded-full p-1.5 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500"
                                    @click="removeRow(entry.index)"
                                >
                                    <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!visibleRows.length">
                            <td :colspan="columnCount" class="px-4 py-10 text-center">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ rows.length ? $t("No keys match this vendor.") : $t("No keys yet.") }}
                                </p>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ rows.length
                                        ? $t("Choose a different vendor to see the rest of the keys.")
                                        : $t("Add keys to share the same button layout across every device using this profile.") }}
                                </p>
                                <button
                                    v-if="permissions.create && !rows.length"
                                    type="button"
                                    class="mt-3 inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-indigo-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    @click="addRow"
                                >
                                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                                    {{ $t("Add key") }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { trans } from "@i18n";
import { AdjustmentsHorizontalIcon, PlusIcon, TrashIcon } from "@heroicons/vue/24/solid";

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    vendors: {
        type: Array,
        default: () => [],
    },
    vendorFunctions: {
        type: Array,
        default: () => [],
    },
    extensionNames: {
        type: Object,
        default: () => ({}),
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    fieldPermissions: {
        type: Object,
        default: () => ({}),
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const vendorFilter = ref("");
const showExtraColumns = ref(false);

const errorClass = "mt-1 text-xs text-red-600";

const lineOptions = Array.from({ length: 13 }, (_, index) => index);

const baseAreas = [
    { value: "line", label: trans("Line") },
    { value: "memory", label: trans("Memory") },
    { value: "programmable", label: trans("Programmable") },
];

const expansionAreas = [
    { value: "expansion", label: trans("Expansion") },
    ...Array.from({ length: 6 }, (_, index) => ({
        value: `expansion-${index + 1}`,
        label: `${trans("Expansion")} ${index + 1}`,
    })),
];

const polycomAreas = [
    { value: "line", label: trans("Line") },
    { value: "any", label: "Any" },
    { value: "unassigned", label: "Unassigned" },
    { value: "blf", label: "BLF" },
    { value: "efk", label: "EFK" },
    { value: "speeddial", label: "Speed dial" },
    { value: "presense", label: "Presence" },
    { value: "presence", label: `${trans("Presence")} (legacy)` },
    { value: "programmable", label: trans("Programmable") },
];

const hasExtraColumns = computed(() =>
    Boolean(props.fieldPermissions.extension || props.fieldPermissions.icon)
);

const columnCount = computed(() => {
    let count = 8;

    if (showExtraColumns.value) {
        count += props.fieldPermissions.extension ? 1 : 0;
        count += props.fieldPermissions.icon ? 1 : 0;
    }

    return count;
});

const vendorsInUse = computed(() => {
    const counts = new Map();

    props.rows.forEach((row) => {
        const vendor = row.profile_key_vendor;

        if (vendor) {
            counts.set(vendor, (counts.get(vendor) ?? 0) + 1);
        }
    });

    return props.vendors
        .filter((vendor) => counts.has(vendor.value))
        .map((vendor) => ({ ...vendor, count: counts.get(vendor.value) }));
});

const visibleRows = computed(() =>
    props.rows
        .map((row, index) => ({ row, index }))
        .filter((entry) => !vendorFilter.value || entry.row.profile_key_vendor === vendorFilter.value)
);

const summary = computed(() => {
    if (vendorFilter.value) {
        return trans(":shown of :total keys", {
            shown: String(visibleRows.value.length),
            total: String(props.rows.length),
        });
    }

    return props.rows.length === 1 ? trans("1 key") : trans(":count keys", { count: String(props.rows.length) });
});

function controlClass(hasError, isSelect = false) {
    const base = [
        "block w-full rounded-md border-0 bg-white py-1.5 text-sm text-gray-900 ring-1 ring-inset",
        "placeholder:text-gray-400 focus:ring-2 focus:ring-inset",
        "disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500",
        isSelect ? "truncate pl-2 pr-8" : "px-2",
    ].join(" ");

    return hasError
        ? `${base} ring-red-500 focus:ring-red-500`
        : `${base} ring-gray-300 focus:ring-indigo-600`;
}

function labelPlaceholder(row) {
    const value = String(row.profile_key_value ?? "").trim();

    return (value ? props.extensionNames[value] : null) ?? null;
}

function fieldError(index, field) {
    return props.errors?.[index]?.[field] ?? null;
}

function hasRowError(index) {
    return Boolean(props.errors?.[index]);
}

function canEditRow(index) {
    const uuid = props.rows[index]?.device_profile_key_uuid;

    return uuid ? Boolean(props.permissions.update) : Boolean(props.permissions.create);
}

function canDeleteRow(index) {
    const uuid = props.rows[index]?.device_profile_key_uuid;

    return uuid ? Boolean(props.permissions.destroy) : Boolean(props.permissions.create);
}

function areaOptions(vendor, currentValue) {
    const name = String(vendor ?? "").toLowerCase();
    let options;

    if (name === "polycom") {
        options = polycomAreas;
    } else if (name === "grandstream") {
        options = [...baseAreas, { value: "expansion", label: trans("Expansion") }];
    } else if (name === "cisco" || name === "yealink") {
        options = [...baseAreas, ...expansionAreas.slice(1)];
    } else {
        options = [...baseAreas, ...expansionAreas];
    }

    if (currentValue && !options.some((option) => option.value === currentValue)) {
        options = [...options, { value: currentValue, label: currentValue }];
    }

    return options;
}

function functionOptions(vendor, currentValue) {
    const options = props.vendorFunctions
        .filter((item) => item.vendor === vendor)
        .map((item) => ({
            value: item.value,
            label: formatFunctionLabel(item),
        }));

    if (currentValue && !options.some((option) => option.value === currentValue)) {
        options.unshift({ value: currentValue, label: currentValue });
    }

    return options;
}

function formatFunctionLabel(item) {
    const type = String(item.type || item.value || "")
        .replace(/[_-]+/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
    const subtype = String(item.subtype || "").trim();

    return subtype ? `${type} — ${subtype}` : type;
}

function handleVendorChange(row) {
    row.profile_key_type = null;
    row.profile_key_subtype = null;

    const areas = areaOptions(row.profile_key_vendor, null);

    if (!areas.some((area) => area.value === row.profile_key_category)) {
        row.profile_key_category = areas[0]?.value ?? "line";
    }
}

function handleFunctionChange(row) {
    const match = props.vendorFunctions.find(
        (item) => item.vendor === row.profile_key_vendor && String(item.value) === String(row.profile_key_type)
    );

    row.profile_key_subtype = match?.subtype ?? null;
}

function nextKeyNumber(vendor, category) {
    const used = props.rows
        .filter((row) => row.profile_key_vendor === vendor && row.profile_key_category === category)
        .map((row) => Number(row.profile_key_id))
        .filter((value) => Number.isFinite(value));

    return used.length ? Math.max(...used) + 1 : 1;
}

function addRow() {
    const previous = props.rows[props.rows.length - 1];
    const vendor = vendorFilter.value || previous?.profile_key_vendor || null;
    const category = previous?.profile_key_category ?? "line";

    props.rows.push({
        _row_id: `new-${Math.random().toString(36).slice(2)}`,
        device_profile_key_uuid: null,
        profile_key_vendor: vendor,
        profile_key_category: category,
        profile_key_id: nextKeyNumber(vendor, category),
        profile_key_type: null,
        profile_key_subtype: null,
        profile_key_line: 0,
        profile_key_value: null,
        profile_key_label: null,
        profile_key_extension: null,
        profile_key_icon: null,
    });
}

function removeRow(index) {
    props.rows.splice(index, 1);
}

defineExpose({ clearVendorFilter: () => (vendorFilter.value = "") });
</script>
