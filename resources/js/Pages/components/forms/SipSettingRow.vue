<template>
    <div
        class="flex flex-col gap-1 rounded-md border px-2.5 py-2 transition"
        :class="[
            hasError ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white hover:border-gray-300',
            setting.sip_profile_setting_enabled === 'false' ? 'opacity-70' : '',
        ]"
    >
        <div class="grid grid-cols-12 items-center gap-2">
            <!-- Name -->
            <div class="col-span-12 sm:col-span-4">
                <SipSettingNameInput
                    v-model="setting.sip_profile_setting_name"
                    :disabled="!canEdit"
                    :error="hasError"
                />
            </div>

            <!-- Value (type-aware) -->
            <div class="col-span-7 sm:col-span-3">
                <select
                    v-if="hasOptions"
                    v-model="setting.sip_profile_setting_value"
                    :disabled="!canEdit"
                    class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 disabled:bg-gray-50 disabled:text-gray-500"
                >
                    <option v-for="opt in valueOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                <input
                    v-else
                    v-model="setting.sip_profile_setting_value"
                    :type="definition?.type === 'number' ? 'number' : 'text'"
                    :disabled="!canEdit"
                    spellcheck="false"
                    placeholder="value"
                    class="block w-full rounded-md border-0 py-1.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 disabled:bg-gray-50 disabled:text-gray-500"
                />
            </div>

            <!-- Enabled -->
            <div class="col-span-3 flex justify-center sm:col-span-1">
                <Toggle :model-value="setting.sip_profile_setting_enabled === 'true'" :disabled="!canEdit" @update:model-value="setEnabled" />
            </div>

            <!-- Note -->
            <div class="col-span-10 sm:col-span-3">
                <input
                    v-model="setting.sip_profile_setting_description"
                    :disabled="!canEdit"
                    placeholder="Add a note"
                    class="block w-full rounded-md border-0 bg-transparent py-1.5 text-sm text-gray-600 ring-1 ring-inset ring-transparent placeholder:text-gray-400 hover:ring-gray-200 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-indigo-600 disabled:text-gray-400"
                />
            </div>

            <!-- Remove -->
            <div class="col-span-2 flex justify-end sm:col-span-1">
                <button
                    v-if="canRemove"
                    type="button"
                    class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-red-600"
                    title="Remove"
                    @click="emit('remove')"
                >
                    <TrashIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Documentation for known parameters -->
        <p v-if="definition" class="px-1 text-xs text-gray-400">{{ definition.description }}</p>
        <p v-if="hasError" class="px-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>

<script setup>
import { computed } from "vue";
import { TrashIcon } from "@heroicons/vue/24/outline";
import Toggle from "@generalComponents/Toggle.vue";
import SipSettingNameInput from "./SipSettingNameInput.vue";
import { getSettingDefinition } from "../../data/sofiaSipProfileSettings";

const props = defineProps({
    setting: { type: Object, required: true },
    canEdit: { type: Boolean, default: true },
    canRemove: { type: Boolean, default: true },
    error: { type: String, default: "" },
});

const emit = defineEmits(["remove"]);

const definition = computed(() => getSettingDefinition(props.setting.sip_profile_setting_name));
const hasError = computed(() => Boolean(props.error));

const valueOptions = computed(() => {
    const def = definition.value;
    if (!def?.options) return [];

    const current = props.setting.sip_profile_setting_value;
    return current && !def.options.includes(current) ? [current, ...def.options] : def.options;
});

const hasOptions = computed(() => valueOptions.value.length > 0);

function setEnabled(on) {
    props.setting.sip_profile_setting_enabled = on ? "true" : "false";
}
</script>
