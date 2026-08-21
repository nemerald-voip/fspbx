<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500">{{ summary }}</p>

            <button
                v-if="permissions.create"
                type="button"
                class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="addRow"
            >
                <PlusIcon class="h-4 w-4" aria-hidden="true" />
                {{ $t("Add setting") }}
            </button>
        </div>

        <div class="overflow-hidden rounded-lg ring-1 ring-gray-200">
            <div class="max-h-[26rem] overflow-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 shadow-[inset_0_-1px_0_rgb(229,231,235)]">
                            <th scope="col" class="w-48 min-w-48 px-2 py-2">{{ $t("Setting name") }}</th>
                            <th scope="col" class="w-40 min-w-40 px-2 py-2">{{ $t("Value") }}</th>
                            <th scope="col" class="w-48 min-w-48 px-2 py-2">{{ $t("Description") }}</th>
                            <th scope="col" class="w-24 min-w-24 px-2 py-2">{{ $t("Enabled") }}</th>
                            <th scope="col" class="w-10 min-w-10 px-2 py-2">
                                <span class="sr-only">{{ $t("Delete") }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white align-top">
                        <tr v-for="(row, index) in rows" :key="row._row_id" :class="errors?.[index] ? 'bg-red-50/50' : ''">
                            <td class="px-2 py-1.5">
                                <input
                                    v-model="row.profile_setting_name"
                                    type="text"
                                    :placeholder="$t('Setting name')"
                                    :class="controlClass(fieldError(index, 'profile_setting_name'))"
                                    :disabled="!canEditRow(index)"
                                >
                                <p v-if="fieldError(index, 'profile_setting_name')" class="mt-1 text-xs text-red-600">
                                    {{ fieldError(index, "profile_setting_name") }}
                                </p>
                            </td>

                            <td class="px-2 py-1.5">
                                <input
                                    v-model="row.profile_setting_value"
                                    type="text"
                                    :placeholder="$t('Setting value')"
                                    :class="controlClass(fieldError(index, 'profile_setting_value'))"
                                    :disabled="!canEditRow(index)"
                                >
                                <p v-if="fieldError(index, 'profile_setting_value')" class="mt-1 text-xs text-red-600">
                                    {{ fieldError(index, "profile_setting_value") }}
                                </p>
                            </td>

                            <td class="px-2 py-1.5">
                                <input
                                    v-model="row.profile_setting_description"
                                    type="text"
                                    :placeholder="$t('Optional note')"
                                    :class="controlClass(fieldError(index, 'profile_setting_description'))"
                                    :disabled="!canEditRow(index)"
                                >
                                <p v-if="fieldError(index, 'profile_setting_description')" class="mt-1 text-xs text-red-600">
                                    {{ fieldError(index, "profile_setting_description") }}
                                </p>
                            </td>

                            <td class="px-2 py-2.5">
                                <Toggle
                                    :model-value="row.profile_setting_enabled === 'true'"
                                    :disabled="!canEditRow(index)"
                                    @update:model-value="(value) => (row.profile_setting_enabled = value ? 'true' : 'false')"
                                />
                            </td>

                            <td class="px-2 py-1.5 text-right">
                                <button
                                    v-if="canDeleteRow(index)"
                                    type="button"
                                    :title="$t('Delete setting')"
                                    :aria-label="$t('Delete setting')"
                                    class="rounded-full p-1.5 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus:ring-2 focus:ring-rose-500"
                                    @click="removeRow(index)"
                                >
                                    <TrashIcon class="h-4 w-4" aria-hidden="true" />
                                </button>
                            </td>
                        </tr>

                        <tr v-if="!rows.length">
                            <td colspan="5" class="px-4 py-10 text-center">
                                <p class="text-sm font-medium text-gray-900">{{ $t("No settings yet.") }}</p>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $t("Settings are written into the provisioning file of every device using this profile.") }}
                                </p>
                                <button
                                    v-if="permissions.create"
                                    type="button"
                                    class="mt-3 inline-flex items-center gap-x-1.5 rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-indigo-600 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                    @click="addRow"
                                >
                                    <PlusIcon class="h-4 w-4" aria-hidden="true" />
                                    {{ $t("Add setting") }}
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
import { computed } from "vue";
import { trans } from "@i18n";
import { PlusIcon, TrashIcon } from "@heroicons/vue/24/solid";
import Toggle from "@generalComponents/Toggle.vue";

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const summary = computed(() =>
    props.rows.length === 1
        ? trans("1 setting")
        : trans(":count settings", { count: String(props.rows.length) })
);

function controlClass(hasError) {
    const base = "block w-full rounded-md border-0 bg-white px-2 py-1.5 text-sm text-gray-900 ring-1 ring-inset placeholder:text-gray-400 focus:ring-2 focus:ring-inset disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500";

    return hasError
        ? `${base} ring-red-500 focus:ring-red-500`
        : `${base} ring-gray-300 focus:ring-indigo-600`;
}

function fieldError(index, field) {
    return props.errors?.[index]?.[field] ?? null;
}

function canEditRow(index) {
    const uuid = props.rows[index]?.device_profile_setting_uuid;

    return uuid ? Boolean(props.permissions.update) : Boolean(props.permissions.create);
}

function canDeleteRow(index) {
    const uuid = props.rows[index]?.device_profile_setting_uuid;

    return uuid ? Boolean(props.permissions.destroy) : Boolean(props.permissions.create);
}

function addRow() {
    props.rows.push({
        _row_id: `new-${Math.random().toString(36).slice(2)}`,
        device_profile_setting_uuid: null,
        profile_setting_name: null,
        profile_setting_value: null,
        profile_setting_description: null,
        profile_setting_enabled: "true",
    });
}

function removeRow(index) {
    props.rows.splice(index, 1);
}
</script>
