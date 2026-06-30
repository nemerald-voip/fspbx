<template>
    <div
        class="flex flex-col gap-1 rounded-md border px-2.5 py-2 transition"
        :class="[
            hasError ? 'border-danger bg-danger-subtle' : 'border-default bg-surface hover:border-strong',
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
                    class="block w-full rounded-md border-0 py-1.5 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus disabled:bg-surface-2 disabled:text-muted"
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
                    class="block w-full rounded-md border-0 py-1.5 text-sm text-heading ring-1 ring-inset ring-strong focus:ring-2 focus:ring-inset focus:ring-focus disabled:bg-surface-2 disabled:text-muted"
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
                    class="block w-full rounded-md border-0 bg-transparent py-1.5 text-sm text-body ring-1 ring-inset ring-transparent placeholder:text-subtle hover:ring-strong focus:bg-surface focus:ring-2 focus:ring-inset focus:ring-focus disabled:text-subtle"
                />
            </div>

            <!-- Remove -->
            <div class="col-span-2 flex justify-end sm:col-span-1">
                <button
                    v-if="canRemove"
                    type="button"
                    class="rounded-full p-1.5 text-subtle hover:bg-surface-3 hover:text-danger"
                    title="Remove"
                    @click="emit('remove')"
                >
                    <TrashIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Documentation for known parameters -->
        <p v-if="definition" class="px-1 text-xs text-subtle">{{ definition.description }}</p>
        <p v-if="hasError" class="px-1 text-xs text-danger">{{ error }}</p>
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
